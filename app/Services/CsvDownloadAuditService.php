<?php

namespace App\Services;

use App\Models\CsvDownloadLog;
use App\Models\MatchingCsvDownloadSetting;

class CsvDownloadAuditService
{
    // Fixed thresholds (not configurable via DB)
    private const int MULTI_IP_WINDOW_HOURS         = 24;
    private const int MULTI_IP_THRESHOLD            = 3;
    private const int HIGH_VOLUME_IP_UUID_THRESHOLD = 5;

    public function __construct(
        private readonly SlackWebhookService $slack,
    ) {}

    private function thresholds(): array
    {
        $s = app(MatchingCsvDownloadSetting::class);
        return [
            'large_batch'      => (int) ($s?->large_batch_threshold  ?? 10),
            'high_freq_day'    => (int) ($s?->high_freq_day_threshold ?? 5),
            'burst'            => (int) ($s?->burst_threshold          ?? 3),
            'burst_window_min' => (int) ($s?->burst_window_minutes     ?? 5),
            'off_hours_start'  => (int) ($s?->off_hours_start          ?? 23),
            'off_hours_end'    => (int) ($s?->off_hours_end            ?? 6),
        ];
    }

    /**
     * Detect suspicious patterns BEFORE writing the log row.
     *
     * Returns array of ['flag' => string, 'reason' => string].
     * Empty array = clean request.
     *
     * @param  string[]  $userUuids
     * @return array<int, array{flag: string, reason: string}>
     */
    public function detectFlags(string $ip, ?string $userUuid, array $userUuids): array
    {
        $flags = [];
        $t     = $this->thresholds();

        $count = count($userUuids);
        if ($count > $t['large_batch']) {
            $flags[] = [
                'flag'   => 'LARGE_BATCH',
                'reason' => "Requested {$count} UUIDs in a single download (threshold: {$t['large_batch']})",
            ];
        }

        if ($this->isOffHours($t['off_hours_start'], $t['off_hours_end'])) {
            $hour  = now()->format('H:i');
            $start = str_pad((string) $t['off_hours_start'], 2, '0', STR_PAD_LEFT);
            $end   = str_pad((string) $t['off_hours_end'], 2, '0', STR_PAD_LEFT);
            $flags[] = [
                'flag'   => 'OFF_HOURS',
                'reason' => "Request at {$hour} — outside normal business hours ({$start}:00–{$end}:00)",
            ];
        }

        if ($userUuid !== null) {
            if ($freq = $this->highFrequencyDayCount($userUuid, $t['high_freq_day'])) {
                $flags[] = [
                    'flag'   => 'HIGH_FREQUENCY_DAY',
                    'reason' => "User has downloaded {$freq} times today (threshold: {$t['high_freq_day']})",
                ];
            }

            if ($burst = $this->burstRequestCount($userUuid, $t['burst'], $t['burst_window_min'])) {
                $flags[] = [
                    'flag'   => 'BURST_REQUEST',
                    'reason' => "{$burst} requests from this user in the last {$t['burst_window_min']} minutes (threshold: {$t['burst']})",
                ];
            }

            if ($ipCount = $this->multiIpCount($userUuid, $ip)) {
                $flags[] = [
                    'flag'   => 'MULTI_IP_USER',
                    'reason' => "User UUID seen from {$ipCount} distinct IPs in the last ".self::MULTI_IP_WINDOW_HOURS.'h (threshold: '.self::MULTI_IP_THRESHOLD.') — possible token leak',
                ];
            }
        }

        if ($ipVol = $this->highVolumeIpUuidCount($ip, $userUuid)) {
            $flags[] = [
                'flag'   => 'HIGH_VOLUME_IP',
                'reason' => "IP {$ip} used by {$ipVol} distinct UUIDs today (threshold: ".self::HIGH_VOLUME_IP_UUID_THRESHOLD.') — possible shared attack origin',
            ];
        }

        return $flags;
    }

    /**
     * Send Slack alert when suspicious flags are present.
     * Called AFTER the log row is written. Never throws.
     *
     * @param  array<int, array{flag: string, reason: string}>  $flags
     */
    public function alertIfSuspicious(string $ip, ?string $userUuid, array $flags): void
    {
        if (empty($flags)) {
            return;
        }

        try {
            $this->slack->send(
                '🍣 [CSV-DL][SUSPICIOUS] 不審なダウンロードを検出',
                json_encode([
                    'user_uuid'   => $userUuid,
                    'ip'          => $ip,
                    'detected_at' => now()->toDateTimeString(),
                    'flags'       => $flags,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        } catch (\Throwable) {
        }
    }

    // -------------------------------------------------------------------------

    private function isOffHours(int $start, int $end): bool
    {
        $hour = (int) now()->format('G');
        return $hour >= $start || $hour < $end;
    }

    /** Returns current day count if threshold reached, otherwise null. */
    private function highFrequencyDayCount(string $userUuid, int $threshold): ?int
    {
        $count = CsvDownloadLog::where('user_uuid', $userUuid)
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->count();

        return $count >= $threshold ? $count : null;
    }

    /** Returns request count in burst window if threshold reached, otherwise null. */
    private function burstRequestCount(string $userUuid, int $threshold, int $windowMinutes): ?int
    {
        $count = CsvDownloadLog::where('user_uuid', $userUuid)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        return $count >= $threshold ? $count : null;
    }

    /** Returns distinct IP count (+1 for current) if threshold exceeded, otherwise null. */
    private function multiIpCount(string $userUuid, string $currentIp): ?int
    {
        $stored = CsvDownloadLog::where('user_uuid', $userUuid)
            ->where('created_at', '>=', now()->subHours(self::MULTI_IP_WINDOW_HOURS))
            ->distinct('ip_address')
            ->count('ip_address');

        $total = $stored + 1;

        return $total > self::MULTI_IP_THRESHOLD ? $total : null;
    }

    /** Returns distinct UUID count for IP today (+1 for current) if threshold exceeded, otherwise null. */
    private function highVolumeIpUuidCount(string $ip, ?string $currentUuid): ?int
    {
        $stored = CsvDownloadLog::where('ip_address', $ip)
            ->whereDate('created_at', today())
            ->whereNotNull('user_uuid')
            ->distinct('user_uuid')
            ->count('user_uuid');

        // +1 nếu UUID hiện tại chưa có trong DB (chưa được log)
        $alreadyCounted = $currentUuid !== null && CsvDownloadLog::where('ip_address', $ip)
            ->where('user_uuid', $currentUuid)
            ->whereDate('created_at', today())
            ->exists();

        $total = $stored + ($currentUuid !== null && ! $alreadyCounted ? 1 : 0);

        return $total >= self::HIGH_VOLUME_IP_UUID_THRESHOLD ? $total : null;
    }
}
