<?php

namespace App\Providers;

use App\Models\MatchingCsvDownloadSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('csv-download', function (Request $request) {
            $perMinute = Cache::remember('csv_download_rate_limit_setting', 60, function () {
                $s = MatchingCsvDownloadSetting::latest()->first();
                return (int) ($s?->rate_limit_per_minute ?? 3);
            });
            $key     = (string) $request->header('access-token');
            $tooMany = fn () => response()->json(['message' => 'ダウンロードに失敗しました。'], 429);

            return Limit::perMinute($perMinute)->by($key)->response($tooMany);
        });
    }
}
