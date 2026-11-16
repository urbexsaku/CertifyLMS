<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Google OAuth 認証画面へリダイレクトするユースケース。
 */
final class RedirectAction
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(
        User $coach,
        ?string $redirectPath = null,
    ): RedirectResponse {
        $state = Str::random(40);

        session([
            'google_calendar_oauth_state' => $state,
            'google_calendar_redirect_path' => $this->safeRedirectPath($redirectPath),
        ]);

        $url = $this->googleCalendarService->authorizationUrl($state);

        return redirect()->away($url);
    }

    /**
     * リダイレクト先をアプリ内のパスに制限する。
     */
    private function safeRedirectPath(?string $path): string
    {
        if (
            $path === null
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
        ) {
            return route('settings.availability.index', absolute: false);
        }

        return $path;
    }
}
