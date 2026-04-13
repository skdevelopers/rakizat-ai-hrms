<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class VerifyAttendanceSync extends Command
{
    protected $signature = 'attendance:verify-sync';
    protected $description = 'Verify FastAPI → Laravel attendance sync per device';

    public function handle(): int
    {
        $devices = DB::table('devices')->get();

        foreach ($devices as $d) {
            $last = DB::table('attendance_logs')
                ->where('site_id', $d->site_id)
                ->where('device_sn', $d->device_sn)
                ->max('timestamp');

            DB::table('devices')
                ->where('id', $d->id)
                ->update(['last_attendance_at' => $last]);

            $this->line(
                "{$d->site_id} / {$d->device_sn} → " .
                ($last ? $last : 'NO ATTENDANCE')
            );
        }

        return self::SUCCESS;
    }
}
