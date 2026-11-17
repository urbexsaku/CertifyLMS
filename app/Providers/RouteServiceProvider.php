<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-chat', function (Request $request) {
            $userId = $request->user()?->id ?: $request->ip();

            $limit = (int) config('ai-chat.conversation.daily_limit', 50);

            // 日本時間の日付をキーに含め、0時に別の日のカウントへ切り替える
            $today = now('Asia/Tokyo')->toDateString();

            return Limit::perDay($limit)
                ->by("{$userId}:{$today}")
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => "本日の利用上限に達しました。\n明日 0:00 以降に再度ご利用ください。",
                    ], 429, $headers);
                });
        });

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
