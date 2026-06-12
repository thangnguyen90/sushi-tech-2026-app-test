<?php

return [
    'enabled' => (bool) env('DB_QUERY_LOG_ENABLED', false),
    'only_api' => (bool) env('DB_QUERY_LOG_ONLY_API', true),
    'channel' => env('DB_QUERY_LOG_CHANNEL', 'api-query'),
    'level' => env('DB_QUERY_LOG_LEVEL', 'debug'),
];
