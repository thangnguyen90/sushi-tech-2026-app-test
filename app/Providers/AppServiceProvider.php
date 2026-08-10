<?php

namespace App\Providers;

use App\Models\MatchingCsvDownloadSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(MatchingCsvDownloadSetting::class, function () {
            return MatchingCsvDownloadSetting::latest()->first();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sau CloudFront: viewer dùng HTTPS nhưng CloudFront gọi origin bằng HTTP
        // (khi ALB chưa có cert), nên ALB gửi X-Forwarded-Proto: http. Laravel tin
        // header đó và sinh URL http:// -> browser chặn CSS/JS vì Mixed Content.
        // Bật FORCE_HTTPS=true để mọi URL sinh ra đều là https.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('csv-download', function (Request $request) {
            $s = app(MatchingCsvDownloadSetting::class);
            $perMinute = (int) ($s?->rate_limit_per_minute ?? 3);
            $key = (string) $request->header('access-token');
            $tooMany = fn () => response()->json(['message' => 'ダウンロードに失敗しました。'], 429);

            return Limit::perMinute($perMinute)->by($key)->response($tooMany);
        });

        DB::listen(function (QueryExecuted $query) {
            Log::debug('SQL', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
    }
}
