<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttendanceDashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var array<int,string> $allowed */
        $allowed = (array) config('attendance.sites', []);
        $siteId  = (string) $request->get('site_id', $allowed[0] ?? 'PK_OFFICE_1');

        $logs = DB::table('attendance_logs')
            ->where('site_id', $siteId)
            ->orderByDesc('timestamp')
            ->limit(20)
            ->get();

        return view('attendance.dashboard', [
            'siteId' => $siteId,
            'logs'   => $logs,
        ]);
    }

    public function devices(): JsonResponse
    {
        $rows = DB::table('devices')
            ->select(['site_id', 'device_sn'])
            ->orderBy('site_id')
            ->orderBy('device_sn')
            ->get();

        return response()->json([
            'status' => 'ok',
            'data'   => $rows,
        ]);
    }

    /**
     * Sync button without FastAPI:
     * - just triggers a fast refresh on UI side
     * - future: you can store a "sync_requested_at" in cache if you want clients to react
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var array<int,string> $allowed */
        $allowed = (array) config('attendance.sites', []);

        $site = (string) $request->input('site_id', '');
        if ($site === '' || (!empty($allowed) && !in_array($site, $allowed, true))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid site_id',
            ], 422);
        }

        // Optional: mark a flag (doesn't do anything unless you code clients to read it)
        Cache::put("attendance:sync:{$site}", CarbonImmutable::now()->toIso8601String(), 60);

        return response()->json([
            'status'  => 'ok',
            'message' => "Refresh requested for {$site}",
        ]);
    }

    /**
     * ✅ NO N+1:
     * One query per site (devices + last attendance + today count) using subqueries.
     * Optional cache (10s) to protect DB from many dashboards.
     */
    public function status(string $site): JsonResponse
    {
        /** @var array<int,string> $allowed */
        $allowed = (array) config('attendance.sites', []);
        if (!empty($allowed) && !in_array($site, $allowed, true)) {
            return response()->json([
                'status' => 'ok',
                'data'   => ['site_id' => $site, 'total_devices' => 0, 'online_devices' => 0, 'devices' => []],
            ], 422);
        }

        $cacheSeconds = (int) config('attendance.status_cache_seconds', 10);
        $key = "attendance:status:v3:{$site}";

        $payload = Cache::remember($key, $cacheSeconds, function () use ($site): array {
            $now   = CarbonImmutable::now();
            $today = $now->startOfDay();

            $cfg = (array) config('attendance', []);
            $attendanceOnlineMinutes = (int) ($cfg['attendance_online_minutes'] ?? 10);

            $noPunchAmberMinutes = (int) ($cfg['no_punch_amber_minutes'] ?? 15);
            $noPunchRedMinutes   = (int) ($cfg['no_punch_red_minutes'] ?? 60);

            $syncCfg = (array) ($cfg['sync_freshness'] ?? ['fresh' => 5, 'ok' => 15, 'stale' => 60]);
            $freshM = (int) ($syncCfg['fresh'] ?? 5);
            $okM    = (int) ($syncCfg['ok'] ?? 15);
            $staleM = (int) ($syncCfg['stale'] ?? 60);

            // last attendance per device
            $lastSub = DB::table('attendance_logs')
                ->selectRaw('site_id, device_sn, MAX(timestamp) AS last_ts')
                ->where('site_id', $site)
                ->groupBy('site_id', 'device_sn');

            // today count per device
            $todaySub = DB::table('attendance_logs')
                ->selectRaw('site_id, device_sn, COUNT(*) AS today_count')
                ->where('site_id', $site)
                ->where('timestamp', '>=', $today->toDateTimeString())
                ->groupBy('site_id', 'device_sn');

            $rows = DB::table('devices as d')
                ->where('d.site_id', $site)
                ->leftJoinSub($lastSub, 'la', function ($j) {
                    $j->on('la.site_id', '=', 'd.site_id')
                        ->on('la.device_sn', '=', 'd.device_sn');
                })
                ->leftJoinSub($todaySub, 'tc', function ($j) {
                    $j->on('tc.site_id', '=', 'd.site_id')
                        ->on('tc.device_sn', '=', 'd.device_sn');
                })
                ->orderBy('d.device_sn')
                ->get([
                    'd.site_id',
                    'd.device_sn',
                    DB::raw('la.last_ts as last_attendance_ts'),
                    DB::raw('COALESCE(tc.today_count, 0) as records_today'),
                ]);

            $devices = [];
            $onlineCount = 0;

            foreach ($rows as $r) {
                $lastTs = $r->last_attendance_ts ? CarbonImmutable::parse((string) $r->last_attendance_ts) : null;

                $online = false;
                $offlineMinutes = null;

                if ($lastTs) {
                    $offlineMinutes = $lastTs->diffInMinutes($now);
                    $online = $lastTs->gte($now->subMinutes($attendanceOnlineMinutes));
                }

                if ($online) $onlineCount++;

                // No-punch severity
                $noPunchSeverity = null;
                if ($online && (int) $r->records_today === 0 && $lastTs) {
                    $noPunchSeverity = $lastTs->gte($now->subMinutes($noPunchAmberMinutes)) ? 'amber' : 'red';
                    if ($noPunchSeverity === 'red' && $lastTs->gte($now->subMinutes($noPunchRedMinutes))) {
                        // still red; kept for clarity
                    }
                }

                // Sync freshness (based on last attendance)
                $syncFreshness = 'never';
                $lastHuman = null;

                if ($lastTs) {
                    $lastHuman = $lastTs->diffForHumans();

                    if ($lastTs->gte($now->subMinutes($freshM))) {
                        $syncFreshness = 'fresh';
                    } elseif ($lastTs->gte($now->subMinutes($okM))) {
                        $syncFreshness = 'ok';
                    } elseif ($lastTs->gte($now->subMinutes($staleM))) {
                        $syncFreshness = 'stale';
                    } else {
                        $syncFreshness = 'dead';
                    }
                }

                $devices[] = [
                    'site_id' => (string) $r->site_id,
                    'device_sn' => (string) $r->device_sn,

                    'online' => $online,
                    'last_seen' => $lastTs?->toIso8601String(),
                    'last_seen_human' => $lastHuman,
                    'offline_minutes' => $offlineMinutes,

                    'records_today' => (int) $r->records_today,
                    'no_punch_severity' => $noPunchSeverity,

                    'last_attendance_ts' => $lastTs?->toIso8601String(),
                    'last_attendance_human' => $lastHuman,
                    'sync_freshness' => $syncFreshness,
                ];
            }

            return [
                'site_id'        => $site,
                'total_devices'  => count($devices),
                'online_devices' => $onlineCount,
                'devices'        => $devices,
            ];
        });

        return response()->json([
            'status' => 'ok',
            'data'   => $payload,
        ]);
    }

    public function deviceLogs(string $site, string $deviceSn): JsonResponse
    {
        /** @var array<int,string> $allowed */
        $allowed = (array) config('attendance.sites', []);
        if (!empty($allowed) && !in_array($site, $allowed, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid site_id',
            ], 422);
        }

        $logs = DB::table('attendance_logs')
            ->where('site_id', $site)
            ->where('device_sn', $deviceSn)
            ->orderByDesc('timestamp')
            ->limit(200)
            ->get(['user_id', 'timestamp', 'status', 'punch']);

        return response()->json([
            'status' => 'ok',
            'data'   => $logs,
        ]);
    }

    /**
     * SSE stream (optional):
     * - NO withHeaders() (that caused your crash)
     * - session_write_close() to prevent UI freeze
     * - DB query log disabled (long-running safety)
     *
     * WARNING: SSE under PHP-FPM consumes one worker per connection.
     * Keep it optional; use caching to reduce DB load.
     */
    public function stream(Request $request): StreamedResponse
    {
        $interval = (int) $request->query('interval', 5);
        if ($interval < 2) $interval = 2;
        if ($interval > 30) $interval = 30;

        /** @var array<int,string> $allowed */
        $allowed = (array) config('attendance.sites', []);

        $sites = $request->query('sites');
        $siteList = [];

        if (is_string($sites) && $sites !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $sites))));
            foreach ($parts as $s) {
                if (empty($allowed) || in_array($s, $allowed, true)) $siteList[] = $s;
            }
        }

        if (empty($siteList)) {
            $siteList = $allowed;
        }

        // Release PHP session lock for this long request
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        // Safety knobs
        @set_time_limit(0);
        @ignore_user_abort(true);
        DB::disableQueryLog();

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no', // Nginx
        ];

        return response()->stream(function () use ($siteList, $interval) {
            // hello
            echo "event: hello\n";
            echo 'data: {"ok":true}' . "\n\n";
            @ob_flush(); @flush();

            while (true) {
                if (connection_aborted()) break;

                $sitesPayload = [];
                foreach ($siteList as $site) {
                    // reuse cached status() work by hitting Cache key indirectly
                    $sitesPayload[$site] = $this->status($site)->getData(true)['data'] ?? null;
                }

                echo "event: status\n";
                echo 'data: ' . json_encode(['sites' => $sitesPayload], JSON_UNESCAPED_SLASHES) . "\n\n";
                @ob_flush(); @flush();

                sleep($interval);
            }
        }, 200, $headers);
    }
}
