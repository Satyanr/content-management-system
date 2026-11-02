<?php

return [
    'heartbeat_interval_seconds' => 60,
    'heartbeat_freshness_seconds' => (int) env('DEVICE_HEARTBEAT_FRESHNESS_SECONDS', 180),
    'max_payload_bytes' => 8192,
    'pairing_minutes' => 10,
    'max_pairing_attempts' => 5,
];
