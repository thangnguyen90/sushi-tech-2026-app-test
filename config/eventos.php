<?php

return [
    'public' => [
        'open_api' => env('EVENTOS_PUBLIC_OPEN_API', 'https://public-api.eventos.work'),
        'key' => env('EVENTOS_PUBLIC_KEY', 'public_default_key'),
    ],
    'private' => [
        'base_url' => env('EVENTOS_PRIVATE_BASE_URL'), //'https://api.eventos.example/private'
        'username' => env('EVENTOS_PRIVATE_USERNAME'), //'admin_default'
        'password' => env('EVENTOS_PRIVATE_PASSWORD'), //'password_default'
    ],
    'portal' => env('EVENTOS_PORTAL'),
    'event' => env('EVENTOS_EVENT'),
    'booth' => env('EVENTOS_BOOTH_EXHIBITOR_ID'), //default_booth_id
    'event_id' => env('EVENT_ID'),
    'live_chat_data_source_id' => env('LIVE_CHAT_DATA_SOURCE_ID'),
    'module' => env('EVENTOS_MODULE', null), //default_module
    'client' => env('EVENTOS_CLIENT_ID'), //default_client_id
    'web_api' => [
        'base_url' => env('EVENTOS_PRIVATE_BASE_URL', 'https://api.eventos.example/web_api'),
    ],
    'trigger_command_key' => env('PRIVATE_TOKEN_USER_AGENT', '')
];
