<?php

return [
    'api_key' => env('SENDGRID_API_KEY'),
    'eu_data_residency' => (bool) env('SENDGRID_EU_DATA_RESIDENCY', false),
    'timeout' => (int) env('SENDGRID_TIMEOUT', 10),
];
