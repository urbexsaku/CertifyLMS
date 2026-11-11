<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Google OAuth callback を処理するユースケース。
 *
 * session に保存した OAuth state を検証し、認可コードをトークンへ交換して
 * コーチの Google Calendar 連携情報を作成または更新する。
 *
 * state が未保存・欠落・不一致の場合、CSRF の可能性があるため HTTP 400 を返す。
 * Google 側でユーザーが認可をキャンセルした場合や、認可コード取得・トークン交換に
 * 失敗した場合は、設定画面へ戻してエラーメッセージを表示する。
 */
final class CallbackAction
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(
        User $coach,
        Request $request,
    ): RedirectResponse {
        $redirectPath = session()->pull(
            'google_calendar_redirect_path',
            route('settings.availability.index', absolute: false),
        );

        $expectedState = session()->pull('google_calendar_oauth_state');
        $actualState = $request->query('state');

        if (
            ! is_string($expectedState)
            || ! is_string($actualState)
            || ! hash_equals($expectedState, $actualState)
        ) {
            abort(400, 'Google OAuth state validation failed.');
        }

        if ($request->filled('error')) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携がキャンセルされました。');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect($redirectPath)
                ->with('error', 'Googleの認証情報を取得できませんでした。');
        }

        try {
            $token = $this->googleCalendarService->exchangeAuthorizationCode($code);
        } catch (Throwable $exception) {
            report($exception);

            return redirect($redirectPath)
                ->with('error', 'Googleの認証情報を取得できませんでした。');
        }

        if (! is_string($token['refresh_token']) || $token['refresh_token'] === '') {
            abort(400, 'Google refresh token is unavailable.');
        }

        $coach->googleCredential()->updateOrCreate(
            ['user_id' => $coach->id],
            [
                'google_user_id' => $token['google_user_id'],
                'calendar_id' => 'primary',
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'expires_at' => $token['expires_at'],
                'connected_at' => now(),
            ],
        );

        return redirect($redirectPath)
            ->with('success', 'Googleカレンダーと連携しました。');
    }
}
