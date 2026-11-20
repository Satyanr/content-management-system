<?php

return [
    'trusted_proxies' => env('DEMO_TRUST_PROXY'),
    'trust_host' => (bool) env('DEMO_TRUST_HOST', false),
];
