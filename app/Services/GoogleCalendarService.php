<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Exception;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Google OAuth 認可 URL を生成する。
 *
 * 要求する権限:
 * - Calendar FreeBusy: コーチの既存予定と面談候補枠の重複判定
 * - Calendar Events: LMS で確定・キャンセルした面談予定の作成・削除
 * - openid / email: 連携先 Google アカウントの識別
 *
 * LMS は Google 側で既存の予定を更新・削除せず、
 * 自身が作成した面談イベントのみを Meeting の event ID 経由で削除する。
 */
class GoogleCalendarService
{
    /**
     * OAuth 認証 URL を生成する。
     */
    public function authorizationUrl(string $state): string
    {
        $client = $this->createClient();

        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setState($state);
        $client->setScopes([
            Calendar::CALENDAR_FREEBUSY,
            Calendar::CALENDAR_EVENTS,
            'openid',
            'email',
        ]);

        return $client->createAuthUrl();
    }

    /**
     * 認証コードをアクセストークンへ交換する。
     *
     * @return array{access_token: string, refresh_token: string|null, expires_at: Carbon, google_user_id: string}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $client = $this->createClient();

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error']) || empty($token['access_token'])) {
            throw new RuntimeException('Google OAuth token exchange failed.');
        }

        $idToken = $token['id_token'] ?? null;
        $payload = $idToken ? $client->verifyIdToken($idToken) : false;

        if (! is_array($payload) || empty($payload['sub'])) {
            throw new RuntimeException('Google account information could not be retrieved.');
        }

        return [
            'access_token' => json_encode($token, JSON_THROW_ON_ERROR),
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'google_user_id' => (string) $payload['sub'],
        ];
    }

    /**
     * 指定コーチの Google Calendar API クライアントを生成する。
     */
    public function calendarClient(User $coach): Calendar
    {
        $credential = $coach->googleCredential;

        if ($credential === null) {
            throw new RuntimeException('Google Calendar is not connected.');
        }

        $client = $this->createClient();

        $accessTokenPayload = json_decode(
            $credential->access_token,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $client->setAccessToken($accessTokenPayload);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $credential->refresh_token
                ?: ($accessTokenPayload['refresh_token'] ?? null);

            if (! $refreshToken) {
                throw new RuntimeException('Google refresh token is unavailable.');
            }

            $newToken = $this->fetchRefreshedToken($client, $refreshToken);

            $credential->update([
                'access_token' => json_encode($newToken, JSON_THROW_ON_ERROR),
                'refresh_token' => $newToken['refresh_token'],
                'expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
            ]);

            $client->setAccessToken($newToken);
        }

        return new Calendar($client);
    }

    /**
     * primary カレンダーの指定期間における予定時間帯を取得する。
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    public function busyPeriods(
        User $coach,
        Carbon $timeMin,
        Carbon $timeMax,
    ): Collection {
        return $this->executeWithTokenRefresh(
            $coach,
            function () use ($coach, $timeMin, $timeMax): Collection {
                $calendar = $this->calendarClient($coach);

                $request = new FreeBusyRequest([
                    'timeMin' => $timeMin->toRfc3339String(),
                    'timeMax' => $timeMax->toRfc3339String(),
                    'timeZone' => config('app.timezone'),
                    'items' => [
                        ['id' => 'primary'],
                    ],
                ]);

                $response = $calendar->freebusy->query($request);

                $busy = $response->getCalendars()['primary']->getBusy() ?? [];

                return collect($busy)->map(fn ($period) => [
                    'start' => Carbon::parse($period->getStart()),
                    'end' => Carbon::parse($period->getEnd()),
                ]);
            },
        );
    }

    /**
     * 面談予定を primary カレンダーに登録する。
     */
    public function createMeetingEvent(Meeting $meeting): string
    {
        return $this->executeWithTokenRefresh(
            $meeting->coach,
            function () use ($meeting): string {
                $calendar = $this->calendarClient($meeting->coach);

                $start = $meeting->scheduled_at->copy();
                $end = $start->copy()->addHour();

                $event = new Event([
                    'summary' => 'Certify LMS 面談',
                    'description' => implode("\n", [
                        '面談テーマ: '.$meeting->topic,
                        '面談URL: '.$meeting->meeting_url_snapshot,
                    ]),
                    'start' => [
                        'dateTime' => $start->toRfc3339String(),
                        'timeZone' => config('app.timezone'),
                    ],
                    'end' => [
                        'dateTime' => $end->toRfc3339String(),
                        'timeZone' => config('app.timezone'),
                    ],
                ]);

                $created = $calendar->events->insert('primary', $event);

                $eventId = (string) $created->getId();

                $meeting->update([
                    'google_calendar_event_id' => $eventId,
                ]);

                return $eventId;
            },
        );
    }

    /**
     * 面談イベントを Google Calendar から削除する。
     *
     * イベント ID が未登録の場合は何もしない。
     */
    public function deleteMeetingEvent(Meeting $meeting): void
    {
        if (! $meeting->google_calendar_event_id) {
            return;
        }

        $this->executeWithTokenRefresh(
            $meeting->coach,
            function () use ($meeting): void {
                $calendar = $this->calendarClient($meeting->coach);

                $calendar->events->delete(
                    'primary',
                    $meeting->google_calendar_event_id,
                );

                $meeting->update([
                    'google_calendar_event_id' => null,
                ]);
            },
        );
    }

    /**
     * Google Calendar の OAuth アクセス権を失効させる。
     */
    public function revokeAccess(User $coach): void
    {
        $credential = $coach->googleCredential;

        if ($credential === null) {
            return;
        }

        $refreshToken = $credential->refresh_token;

        if (! $refreshToken) {
            throw new RuntimeException('Google refresh token is unavailable.');
        }

        $client = $this->createClient();

        try {
            $revoked = $client->revokeToken($refreshToken);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Google access token revocation failed.',
                previous: $exception,
            );
        }

        if (! $revoked) {
            throw new RuntimeException('Google access token revocation failed.');
        }
    }

    /**
     * Google API クライアントを生成する。
     */
    protected function createClient(): Client
    {
        $client = new Client;

        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri(route('settings.google-calendar.callback'));

        return $client;
    }

    /**
     * Google Calendar APIで401が発生した場合、トークンを更新して1回だけ再試行する。
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function executeWithTokenRefresh(
        User $coach,
        callable $callback,
    ): mixed {
        try {
            return $callback();
        } catch (Exception $exception) {
            if ($exception->getCode() !== 401) {
                throw $exception;
            }
        }

        $this->refreshAccessToken($coach);

        // 再試行は1回のみ。再試行時の401はそのまま呼び出し元へ返す。
        return $callback();
    }

    /**
     * リフレッシュトークンでアクセストークンを更新する。
     */
    private function refreshAccessToken(User $coach): void
    {
        $credential = $coach->googleCredential()->first();

        if ($credential === null) {
            throw new RuntimeException('Google Calendar is not connected.');
        }

        $accessTokenPayload = json_decode(
            $credential->access_token,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $refreshToken = $credential->refresh_token
            ?: ($accessTokenPayload['refresh_token'] ?? null);

        if (! $refreshToken) {
            throw new RuntimeException('Google refresh token is unavailable.');
        }

        $client = $this->createClient();
        $client->setAccessToken($accessTokenPayload);

        $newToken = $this->fetchRefreshedToken($client, $refreshToken);

        $credential->update([
            'access_token' => json_encode($newToken, JSON_THROW_ON_ERROR),
            'refresh_token' => $newToken['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
        ]);

        // 次回の calendarClient() が更新後の認証情報を使用できるようにする。
        $coach->unsetRelation('googleCredential');
    }

    /**
     * リフレッシュトークンを使用してアクセストークンを取得する。
     *
     * @return array<string, mixed>
     */
    private function fetchRefreshedToken(
        Client $client,
        string $refreshToken,
    ): array {
        try {
            $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Google access token refresh failed.',
                previous: $exception,
            );
        }

        if (isset($newToken['error']) || empty($newToken['access_token'])) {
            throw new RuntimeException('Google access token refresh failed.');
        }

        $newToken['refresh_token'] = $newToken['refresh_token'] ?? $refreshToken;

        return $newToken;
    }
}
