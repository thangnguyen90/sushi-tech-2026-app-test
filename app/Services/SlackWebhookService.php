<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SlackWebhookService
{
    private const int HEADER_MAX_LEN = 150;           // Slack header plain_text limit is 150
    private const int SECTION_TEXT_MAX_LEN = 2900;    // Keep under Slack mrkdwn section limits
    private const int HTTP_TIMEOUT_SECONDS = 3;
    private const int HTTP_RETRY_TIMES = 2;
    private const int HTTP_RETRY_SLEEP_MS = 200;

    private readonly bool $isEnabled;
    private readonly ?string $webhookUrl;
    private readonly ?string $channel;

    private readonly string $appName;
    private readonly string $environment;
    private readonly ?string $appUrl;

    public function __construct()
    {
        $this->isEnabled = (bool) config('slack.is_send_slack', false);
        $this->webhookUrl = $this->nullIfEmpty(config('slack.webhook_url'));
        $this->channel = $this->nullIfEmpty(config('slack.channel'));

        $this->appName = (string) config('app.name', 'app');
        $this->environment = (string) config('app.env', 'production');
        $this->appUrl = $this->nullIfEmpty(config('app.url'));
    }

    /**
     * Sends a message to Slack via Incoming Webhook.
     *
     * @param string $header Main title of the message.
     * @param string $message Detailed message content.
     * @param string $file File path where the event occurred.
     * @param string|int|null $line Line number.
     */
    public function send(string $header, string $message = '', string $file = '', string|int|null $line = null): void
    {
        if (! $this->isEnabled || $this->webhookUrl === null) {
            return;
        }

        try {
            $payload = $this->buildPayload($header, $message, $file, $line);

            $response = $this->httpClient()->post($this->webhookUrl, $payload);
            $this->logIfFailed($response, $header);
        } catch (Throwable $e) {
            // Do not rethrow. Prevent infinite loops where logging triggers Slack again.
            Log::error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }
    }

    /**
     * Convenience helper for exceptions.
     */
    public function sendException(Throwable $e, string $header = 'Unhandled exception', string $file = '', string|int|null $line = null): void
    {
        $message = $e->getMessage();
        $file = $file !== '' ? $file : $e->getFile();
        $line = $line ?? $e->getLine();

        $this->send($header, $message, $file, $line);
    }

    /**
     * Builds the Slack message payload using Block Kit.
     */
    private function buildPayload(string $header, string $message, string $file, string|int|null $line): array
    {
        $safeHeader = $this->truncate($this->normalize($header), self::HEADER_MAX_LEN);

        $blocks = [];

        // Header block
        $blocks[] = [
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => $safeHeader,
                'emoji' => true,
            ],
        ];

        // Meta context (app/env/url/time)
        $blocks[] = $this->buildMetaContextBlock();

        // Message blocks (split if too long)
        $messageBlocks = $this->buildMessageBlocks($message);
        foreach ($messageBlocks as $b) {
            $blocks[] = $b;
        }

        // File/line context
        if ($this->hasFileLine($file, $line)) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => ':file_folder: `' . $this->normalizePath($file) . '::' . (string) $line . '`,  `host=' . $this->hostname() . '`',
                    ],
                ],
            ];
        }

        $payload = [
            'text' => $safeHeader, // Fallback text
            'blocks' => $blocks,
        ];

        // Incoming Webhook may ignore channel depending on Slack settings; keep it optional.
        if ($this->channel !== null) {
            $payload['channel'] = $this->channel;
        }

        return $payload;
    }

    private function buildMetaContextBlock(): array
    {
        $nowIso = now()->toIso8601String();

        $parts = [
            '<!channel> ',
            '*[' . $this->escapeInlineCode($this->environment) . ']*',
            '*time* `' . $this->escapeInlineCode($nowIso) . '`',
        ];

        if ($this->appUrl !== null) {
            $parts[] = '*url* `' . $this->escapeInlineCode($this->appUrl) . '`';
        }

        return [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => implode('  •  ', $parts),
                ],
            ],
        ];
    }

    /**
     * Build message blocks and keep within Slack limits.
     */
    private function buildMessageBlocks(string $message): array
    {
        $message = $this->normalize($message);
        if ($message === '') {
            return [];
        }

        $chunks = $this->chunkByLength($message, self::SECTION_TEXT_MAX_LEN);

        $blocks = [];
        foreach ($chunks as $idx => $chunk) {
            $prefix = count($chunks) > 1 ? "Part " . ($idx + 1) . "/" . count($chunks) . "\n" : '';
            $text = $prefix . $chunk;

            // Wrap in code block, but avoid breaking triple backticks by replacing them.
            $text = str_replace('```', "`\u{200B}`\u{200B}`", $text);

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "```\n" . $text . "\n```",
                ],
            ];
        }

        return $blocks;
    }

    private function httpClient(): PendingRequest
    {
        return Http::timeout(self::HTTP_TIMEOUT_SECONDS)
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_SLEEP_MS)
            ->asJson();
    }

    private function logIfFailed(Response $response, string $header): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('Slack webhook returned non-2xx', [
            'status' => $response->status(),
            'header' => $header,
            'body' => $this->safeResponseBody($response),
        ]);
    }

    private function safeResponseBody(Response $response): string
    {
        try {
            $body = (string) $response->body();
            return $this->truncate($this->normalize($body), 2000);
        } catch (Throwable) {
            return '<<unavailable>>';
        }
    }

    private function hasFileLine(string $file, string|int|null $line): bool
    {
        if ($this->normalize($file) === '') {
            return false;
        }

        // `empty()` would treat 0 as empty; allow 0.
        return $line !== null && $line !== '';
    }

    private function truncate(string $text, int $maxLen): string
    {
        if ($maxLen <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen - 1) . '…';
    }

    private function chunkByLength(string $text, int $chunkLen): array
    {
        if ($chunkLen <= 0) {
            return [$text];
        }

        $len = mb_strlen($text);
        if ($len <= $chunkLen) {
            return [$text];
        }

        $chunks = [];
        for ($i = 0; $i < $len; $i += $chunkLen) {
            $chunks[] = mb_substr($text, $i, $chunkLen);
        }

        return $chunks;
    }

    private function normalize(string $text): string
    {
        $text = trim($text);
        // Normalize line endings for Slack
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return $text;
    }

    private function normalizePath(string $path): string
    {
        $path = $this->normalize($path);
        // Avoid overly long paths
        return $this->truncate($path, 160);
    }

    private function escapeInlineCode(string $text): string
    {
        // Prevent breaking mrkdwn inline code
        return str_replace('`', "'", $text);
    }

    private function hostname(): string
    {
        try {
            return gethostname() ?: 'unknown';
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
