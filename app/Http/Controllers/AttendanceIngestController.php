<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AttendanceIngestController extends Controller
{
    /**
     * Device ingest endpoint.
     *
     * Rules:
     * - Accept JSON by decoded body.
     * - Verify device using site_id + device_sn + device_secret.
     * - Bulk insert with insertOrIgnore for idempotency.
     * - Return accepted local_id list so client can mark sent rows.
     *
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (empty($payload)) {
            $raw = (string) $request->getContent();
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (empty($payload)) {
            return response()->json([
                'status' => 'error',
                'message' => 'JSON body required',
            ], 415);
        }

        try {
            $data = validator($payload, [
                'site_id' => ['required', 'string', 'max:64'],
                'device_sn' => ['required', 'string', 'max:64'],
                'device_secret' => ['required', 'string', 'max:128'],

                'records' => ['required', 'array', 'min:1', 'max:1000'],
                'records.*.local_id' => ['required', 'integer', 'min:1'],
                'records.*.device_sn' => ['required', 'string', 'max:64'],
                'records.*.uid' => ['required', 'integer', 'min:0'],
                'records.*.user_id' => ['required', 'integer', 'min:1'],
                'records.*.timestamp' => ['required', 'date'],
                'records.*.punch' => ['required', 'integer', 'min:0', 'max:255'],
                'records.*.status' => ['required', 'integer', 'min:0', 'max:255'],
            ])->validate();
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid payload',
                'errors' => $e->errors(),
            ], 422);
        }

        $device = DB::table('devices')
            ->where('site_id', $data['site_id'])
            ->where('device_sn', $data['device_sn'])
            ->first(['device_sn', 'device_secret']);

        if (!$device || !hash_equals((string) $device->device_secret, (string) $data['device_secret'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized device',
            ], 401);
        }

        $now = now();
        $rows = [];
        $accepted = [];

        foreach ($data['records'] as $r) {
            $rows[] = [
                'site_id' => $data['site_id'],
                'device_sn' => (string) ($r['device_sn'] ?? $data['device_sn']),
                'uid' => (int) $r['uid'],
                'user_id' => (string) $r['user_id'],
                'timestamp' => $r['timestamp'],
                'punch' => (int) $r['punch'],
                'status' => (int) $r['status'],
                'created_at' => $now,
            ];

            $accepted[] = [
                'local_id' => (int) $r['local_id'],
            ];
        }

        DB::beginTransaction();

        try {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('attendance_logs')->insertOrIgnore($chunk);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Insert failed',
            ], 500);
        }

        return response()->json([
            'status' => 'ok',
            'accepted' => $accepted,
            'accepted_count' => count($accepted),
            'note' => 'Idempotent insert completed',
        ]);
    }
}
