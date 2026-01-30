<?php

namespace App\Services\Slack;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SlackWebhookAdmin
{
    private bool $isEnabled;

    private ?string $webhookUrl;

    private ?string $channel;

    /**
     * SlackWebhookAdmin constructor.
     * Loads configuration once.
     */
    public function __construct()
    {
        $this->isEnabled = config('slack.is_send_slack', false);
        $this->webhookUrl = config('slack.webhook_url');
        $this->channel = config('slack.channel');
    }

    /**
     * Builds the payload and sends a message to a Slack channel.
     *
     * @param  string  $header  The main title of the message.
     * @param  string  $message  The detailed message content.
     * @param  string  $file  The file path where the event occurred.
     * @param  string|int|null  $line  The line number.
     */
    public function send(string $header, string $message = '', string $file = '', string|int|null $line = null): void
    {
        if (! $this->isEnabled || empty($this->webhookUrl)) {
            return;
        }

        try {
            $payload = $this->_buildPayload($header, $message, $file, $line);

            Http::post($this->webhookUrl, $payload);

        } catch (Throwable $e) {
            // If Slack notification fails, log the error to the primary log driver
            // to avoid an infinite loop of error notifications.
            Log::error('Failed to send Slack notification: '.$e->getMessage());
        }
    }

    /**
     * Builds the Slack message payload using Block Kit format.
     */
    private function _buildPayload(string $header, string $message, string $file, string|int|null $line): array
    {
        $blocks = [];

        // Header block
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $header,
                'emoji' => true,
            ],
        ];

        // Message block
        if (! empty($message)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '```'.$message.'```',
                ],
            ];
        }

        // File and Line block
        if (! empty($file) && ! empty($line)) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => ":file_folder: `{$file}::{$line}`",
                    ],
                ],
            ];
        }

        return [
            'channel' => $this->channel,
            'text' => $header, // Fallback text for notifications
            'blocks' => $blocks,
        ];
    }
}
