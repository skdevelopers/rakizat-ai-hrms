<?php

return [
    'sites' => ['PK_OFFICE_1', 'SA_HQ_1'],

    'heartbeat_online_minutes'  => 5,
    'attendance_online_minutes' => 10,

    'no_punch_amber_minutes' => 15,
    'no_punch_red_minutes'   => 60,

    'sync_freshness' => [
        'fresh' => 5,
        'ok'    => 15,
        'stale' => 60,
    ],

    // ✅ critical: reduces a DB load for multiple viewers
    'status_cache_seconds' => 10,
];
