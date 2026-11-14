<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    public function test_refreshes_access_token_before_fetching_busy_periods(): void
    {
        $coach = $this->createCoachWithExpiredToken();

        $service = $this->createService([
            $this->successfulRefreshResponse(),
            $this->freeBusyResponse(),
        ]);

        $periods = $service->busyPeriods(
            $coach,
            Carbon::parse('2026-11-13 09:00:00'),
            Carbon::parse('2026-11-13 18:00:00'),
        );

        $this->assertCount(0, $periods);
        $this->assertCount(2, $this->history);
        $this->assertStringContainsString('/token', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('/freeBusy', (string) $this->history[1]['request']->getUri());

        $this->assertUpdatedToken($coach);
    }

    public function test_refreshes_access_token_before_creating_meeting_event(): void
    {
        $coach = $this->createCoachWithExpiredToken();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'scheduled_at' => Carbon::parse('2026-11-16 10:00:00'),
                'topic' => '面談テスト',
                'meeting_url_snapshot' => 'https://meet.example.com/test',
            ]);

        $service = $this->createService([
            $this->successfulRefreshResponse(),
            $this->createdEventResponse(),
        ]);

        $eventId = $service->createMeetingEvent($meeting);

        $this->assertSame('created-event-123', $eventId);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => 'created-event-123',
        ]);

        $this->assertCount(2, $this->history);
        $this->assertStringContainsString('/token', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('/events', (string) $this->history[1]['request']->getUri());

        $this->assertUpdatedToken($coach);
    }

    public function test_refreshes_access_token_before_deleting_meeting_event(): void
    {
        $coach = $this->createCoachWithExpiredToken();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $service = $this->createService([
            $this->successfulRefreshResponse(),
            new Response(204),
        ]);

        $service->deleteMeetingEvent($meeting);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => null,
        ]);

        $this->assertCount(2, $this->history);
        $this->assertStringContainsString('/token', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('/events/google-event-123', (string) $this->history[1]['request']->getUri());

        $this->assertUpdatedToken($coach);
    }

    public function test_throws_exception_when_token_refresh_fails_before_fetching_busy_periods(): void
    {
        $coach = $this->createCoachWithExpiredToken();

        $service = $this->createService([
            $this->failedRefreshResponse(),
        ]);

        try {
            $service->busyPeriods(
                $coach,
                Carbon::parse('2026-11-13 09:00:00'),
                Carbon::parse('2026-11-13 18:00:00'),
            );

            $this->fail('トークン更新失敗時に例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Google access token refresh failed.',
                $exception->getMessage()
            );
        }

        $this->assertCount(1, $this->history);
    }

    public function test_throws_exception_when_token_refresh_fails_before_creating_meeting_event(): void
    {
        $coach = $this->createCoachWithExpiredToken();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'scheduled_at' => Carbon::parse('2026-11-16 10:00:00'),
                'topic' => '面談テスト',
                'meeting_url_snapshot' => 'https://meet.example.com/test',
            ]);

        $service = $this->createService([
            $this->failedRefreshResponse(),
        ]);

        try {
            $service->createMeetingEvent($meeting);

            $this->fail('トークン更新失敗時に例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Google access token refresh failed.',
                $exception->getMessage()
            );
        }

        $this->assertCount(1, $this->history);
        $this->assertNull($meeting->fresh()->google_calendar_event_id);
    }

    public function test_throws_exception_when_token_refresh_fails_before_deleting_meeting_event(): void
    {
        $coach = $this->createCoachWithExpiredToken();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $service = $this->createService([
            $this->failedRefreshResponse(),
        ]);

        try {
            $service->deleteMeetingEvent($meeting);

            $this->fail('トークン更新失敗時に例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Google access token refresh failed.',
                $exception->getMessage()
            );
        }

        $this->assertCount(1, $this->history);
        $this->assertSame(
            'google-event-123',
            $meeting->fresh()->google_calendar_event_id
        );
    }

    public function test_retries_busy_period_request_after_unauthorized_response(): void
    {
        $coach = $this->createCoachWithValidToken();

        $service = $this->createService([
            $this->unauthorizedResponse(),
            $this->successfulRefreshResponse(),
            $this->freeBusyResponse(),
        ]);

        $periods = $service->busyPeriods(
            $coach,
            Carbon::parse('2026-11-13 09:00:00'),
            Carbon::parse('2026-11-13 18:00:00'),
        );

        $this->assertCount(0, $periods);
        $this->assertCount(3, $this->history);

        $this->assertStringContainsString('/freeBusy', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('/token', (string) $this->history[1]['request']->getUri());
        $this->assertStringContainsString('/freeBusy', (string) $this->history[2]['request']->getUri());

        $this->assertUpdatedToken($coach);
    }

    public function test_retries_event_creation_after_unauthorized_response(): void
    {
        $coach = $this->createCoachWithValidToken();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'scheduled_at' => Carbon::parse('2026-11-16 10:00:00'),
                'topic' => '面談テスト',
                'meeting_url_snapshot' => 'https://meet.example.com/test',
            ]);

        $service = $this->createService([
            $this->unauthorizedResponse(),
            $this->successfulRefreshResponse(),
            $this->createdEventResponse(),
        ]);

        $eventId = $service->createMeetingEvent($meeting);

        $this->assertSame('created-event-123', $eventId);
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => 'created-event-123',
        ]);

        $this->assertCount(3, $this->history);
        $this->assertStringContainsString('/events', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('/token', (string) $this->history[1]['request']->getUri());
        $this->assertStringContainsString('/events', (string) $this->history[2]['request']->getUri());

        $this->assertUpdatedToken($coach);
    }

    public function test_retries_event_deletion_after_unauthorized_response(): void
    {
        $coach = $this->createCoachWithValidToken();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $service = $this->createService([
            $this->unauthorizedResponse(),
            $this->successfulRefreshResponse(),
            new Response(204),
        ]);

        $service->deleteMeetingEvent($meeting);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => null,
        ]);

        $this->assertCount(3, $this->history);
        $this->assertStringContainsString('/events/google-event-123', (string) $this->history[0]['request']->getUri());
        $this->assertStringContainsString('/token', (string) $this->history[1]['request']->getUri());
        $this->assertStringContainsString('/events/google-event-123', (string) $this->history[2]['request']->getUri());

        $this->assertUpdatedToken($coach);
    }

    public function test_does_not_retry_again_when_retry_returns_unauthorized(): void
    {
        $coach = $this->createCoachWithValidToken();

        $service = $this->createService([
            $this->unauthorizedResponse(),
            $this->successfulRefreshResponse(),
            $this->unauthorizedResponse(),
        ]);

        try {
            $service->busyPeriods(
                $coach,
                Carbon::parse('2026-11-13 09:00:00'),
                Carbon::parse('2026-11-13 18:00:00'),
            );

            $this->fail('再試行後の401で例外が発生しませんでした。');
        } catch (GoogleServiceException $exception) {
            $this->assertSame(401, $exception->getCode());
        }

        // 初回API、トークン更新、再試行の3リクエストのみ。
        $this->assertCount(3, $this->history);
    }

    public function test_does_not_retry_when_api_fails_with_non_unauthorized_error(): void
    {
        $coach = $this->createCoachWithValidToken();

        $service = $this->createService([
            new Response(
                403,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error' => [
                        'code' => 403,
                        'message' => 'Forbidden',
                    ],
                ], JSON_THROW_ON_ERROR)
            ),
        ]);

        try {
            $service->busyPeriods(
                $coach,
                Carbon::parse('2026-11-13 09:00:00'),
                Carbon::parse('2026-11-13 18:00:00'),
            );

            $this->fail('403エラーで例外が発生しませんでした。');
        } catch (GoogleServiceException $exception) {
            $this->assertSame(403, $exception->getCode());
        }

        // トークン更新リクエストは発生しない。
        $this->assertCount(1, $this->history);
    }

    public function test_throws_exception_when_token_refresh_fails_after_unauthorized_response(): void
    {
        $coach = $this->createCoachWithValidToken();

        $service = $this->createService([
            $this->unauthorizedResponse(),
            $this->failedRefreshResponse(),
        ]);

        try {
            $service->busyPeriods(
                $coach,
                Carbon::parse('2026-11-13 09:00:00'),
                Carbon::parse('2026-11-13 18:00:00'),
            );

            $this->fail('トークン更新失敗時に例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Google access token refresh failed.',
                $exception->getMessage()
            );
        }

        // 初回APIとトークン更新だけで終了し、APIを再試行しない。
        $this->assertCount(2, $this->history);
    }

    private function createCoachWithExpiredToken(): User
    {
        $coach = User::factory()->coach()->create();

        GoogleCredential::factory()
            ->for($coach, 'user')
            ->create([
                'access_token' => json_encode([
                    'access_token' => 'expired-access-token',
                    'expires_in' => 3600,
                    'created' => time() - 7200,
                ], JSON_THROW_ON_ERROR),
                'refresh_token' => 'refresh-token',
                'expires_at' => now()->subHour(),
            ]);

        return $coach->fresh();
    }

    private function createCoachWithValidToken(): User
    {
        $coach = User::factory()->coach()->create();

        GoogleCredential::factory()
            ->for($coach, 'user')
            ->create([
                'access_token' => json_encode([
                    'access_token' => 'valid-access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'created' => time(),
                ], JSON_THROW_ON_ERROR),
                'refresh_token' => 'refresh-token',
                'expires_at' => now()->addHour(),
            ]);

        return $coach->fresh();
    }

    private function assertUpdatedToken(User $coach): void
    {
        $credential = GoogleCredential::query()
            ->where('user_id', $coach->id)
            ->firstOrFail();

        $token = json_decode(
            $credential->access_token,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('new-access-token', $token['access_token']);
        $this->assertSame('refresh-token', $credential->refresh_token);
    }

    private function createService(array $responses): GoogleCalendarService
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $httpClient = new GuzzleClient([
            'handler' => $stack,
        ]);

        $client = new Client;
        $client->setClientId('test-client-id');
        $client->setClientSecret('test-client-secret');
        $client->setRedirectUri(route('settings.google-calendar.callback'));
        $client->setHttpClient($httpClient);

        return new class($client) extends GoogleCalendarService
        {
            public function __construct(
                private Client $testClient
            ) {}

            protected function createClient(): Client
            {
                return $this->testClient;
            }
        };
    }

    private function successfulRefreshResponse(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
                'created' => time(),
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR)
        );
    }

    private function failedRefreshResponse(): Response
    {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'Refresh token is invalid.',
            ], JSON_THROW_ON_ERROR)
        );
    }

    private function freeBusyResponse(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'kind' => 'calendar#freeBusy',
                'calendars' => [
                    'primary' => [
                        'busy' => [],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );
    }

    private function createdEventResponse(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode([
                'id' => 'created-event-123',
            ], JSON_THROW_ON_ERROR)
        );
    }

    private function unauthorizedResponse(): Response
    {
        return new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => [
                    'code' => 401,
                    'message' => 'Invalid Credentials',
                    'errors' => [
                        [
                            'domain' => 'global',
                            'reason' => 'authError',
                            'message' => 'Invalid Credentials',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );
    }
}
