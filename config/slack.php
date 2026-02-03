<?php

return [
    'is_send_slack' => (bool) env('SLACK_IS_SEND', false),
    'webhook_url'   => env('SLACK_WEBHOOK_URL', null),
    'channel'       => env('SLACK_CHANNEL', null),
];
