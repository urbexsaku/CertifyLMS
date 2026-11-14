<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\GoogleCalendar;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CallbackActionTest extends TestCase
{
    use RefreshDatabase;

    private function prepareCallback(
        User $coach,
        string $state = 'valid-state'
    ): void {
        $this->actingAs($coach);

        $this->withSession([
            'google_calendar_oauth_state' => $state,
            'google_calendar_redirect_path' => '/settings/availability',
        ]);
    }

    private function token(
        string $googleUserId = 'google-user-1'
    ): array {
        return [
            'google_user_id' => $googleUserId,
            'access_token' => '{"access_token":"access"}',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
        ];
    }

    public function test_creates_google_credential_after_successful_callback(): void
    {
        $coach = User::factory()->coach()->create();

        $this->prepareCallback($coach);

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->with('auth-code')
            ->andReturn($this->token());

        $this->app->instance(GoogleCalendarService::class, $service);

        $response = $this->get(route(
            'settings.google-calendar.callback',
            [
                'state' => 'valid-state',
                'code' => 'auth-code',
            ]
        ));

        $response->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'success',
                'Googleカレンダーと連携しました。'
            );

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'google_user_id' => 'google-user-1',
            'calendar_id' => 'primary',
            'access_token' => '{"access_token":"access"}',
            'refresh_token' => 'refresh-token',
        ]);

        $credential = GoogleCredential::query()
            ->where('user_id', $coach->id)
            ->firstOrFail();

        $this->assertNotNull($credential->expires_at);
        $this->assertNotNull($credential->connected_at);
    }

    public function test_updates_existing_google_credential_after_successful_callback(): void
    {
        $coach = User::factory()->coach()->create();

        GoogleCredential::factory()
            ->for($coach, 'user')
            ->create([
                'google_user_id' => 'old-google-user',
                'access_token' => 'old-access',
                'refresh_token' => 'old-refresh',
            ]);

        $this->prepareCallback($coach);

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->andReturn($this->token('new-google-user'));

        $this->app->instance(GoogleCalendarService::class, $service);

        $this->get(route(
            'settings.google-calendar.callback',
            [
                'state' => 'valid-state',
                'code' => 'auth-code',
            ]
        ))->assertRedirect('/settings/availability');

        $this->assertDatabaseCount('google_credentials', 1);

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'google_user_id' => 'new-google-user',
            'calendar_id' => 'primary',
            'refresh_token' => 'refresh-token',
        ]);

        $this->assertDatabaseMissing('google_credentials', [
            'user_id' => $coach->id,
            'google_user_id' => 'old-google-user',
        ]);
    }

    public function test_aborts_when_oauth_state_is_invalid(): void
    {
        $coach = User::factory()->coach()->create();

        $this->prepareCallback($coach);

        $this->get(route(
            'settings.google-calendar.callback',
            [
                'state' => 'invalid-state',
                'code' => 'auth-code',
            ]
        ))->assertBadRequest();

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_redirects_when_authorization_is_canceled(): void
    {
        $coach = User::factory()->coach()->create();

        $this->prepareCallback($coach);

        $this->get(route(
            'settings.google-calendar.callback',
            [
                'state' => 'valid-state',
                'error' => 'access_denied',
            ]
        ))
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'error',
                'Googleカレンダーとの連携がキャンセルされました。'
            );

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_redirects_when_authorization_code_is_missing(): void
    {
        $coach = User::factory()->coach()->create();

        $this->prepareCallback($coach);

        $this->get(route(
            'settings.google-calendar.callback',
            ['state' => 'valid-state']
        ))
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'error',
                'Googleの認証情報を取得できませんでした。'
            );

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_redirects_when_token_exchange_fails(): void
    {
        $coach = User::factory()->coach()->create();

        $this->prepareCallback($coach);

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->with('auth-code')
            ->andThrow(new RuntimeException('Token exchange failed.'));

        $this->app->instance(GoogleCalendarService::class, $service);

        $this->get(route(
            'settings.google-calendar.callback',
            [
                'state' => 'valid-state',
                'code' => 'auth-code',
            ]
        ))
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'error',
                'Googleの認証情報を取得できませんでした。'
            );

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_aborts_when_refresh_token_is_missing(): void
    {
        $coach = User::factory()->coach()->create();

        $this->prepareCallback($coach);

        $token = $this->token();
        $token['refresh_token'] = null;

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->andReturn($token);

        $this->app->instance(GoogleCalendarService::class, $service);

        $this->get(route(
            'settings.google-calendar.callback',
            [
                'state' => 'valid-state',
                'code' => 'auth-code',
            ]
        ))->assertBadRequest();

        $this->assertDatabaseCount('google_credentials', 0);
    }
}
