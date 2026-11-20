<?php

return [
    'horizon_hours' => (int) env('PLAYER_HORIZON_HOURS', 72),
    'poll_interval_seconds' => 60,
    // Set only after verifying the published installer against the documented SHA-256.
    'windows_download_url' => env('PLAYER_WINDOWS_DOWNLOAD_URL'),
];
