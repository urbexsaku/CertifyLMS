<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\GoogleCalendar;

use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RedirectActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_google_authorization_url(): void
    {
        $coach = User::factory()->coach()->create();

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('authorizationUrl')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn('https://accounts.google.com/o/oauth2/auth?test=1');

        $this->app->instance(GoogleCalendarService::class, $service);

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.redirect'));

        $response->assertRedirect(
            'https://accounts.google.com/o/oauth2/auth?test=1'
        );

        $this->assertIsString(session('google_calendar_oauth_state'));
        $this->assertSame(
            40,
            strlen(session('google_calendar_oauth_state'))
        );

        $this->assertSame(
            route('settings.availability.index', absolute: false),
            session('google_calendar_redirect_path')
        );
    }

    public function test_stores_safe_redirect_path_in_session(): void
    {
        $coach = User::factory()->coach()->create();

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('authorizationUrl')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn('https://accounts.google.com/auth');

        $this->app->instance(GoogleCalendarService::class, $service);

        $this->actingAs($coach)->get(route('settings.google-calendar.redirect', [
            'redirect_path' => '/settings/profile?tab=meeting',
        ]))
            ->assertRedirect('https://accounts.google.com/auth');

        $this->assertSame(
            '/settings/profile?tab=meeting',
            session('google_calendar_redirect_path')
        );
    }

    public function test_uses_default_redirect_path_for_external_redirect_path(): void
    {
        $coach = User::factory()->coach()->create();

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldReceive('authorizationUrl')
            ->twice()
            ->with(Mockery::type('string'))
            ->andReturn('https://accounts.google.com/auth');

        $this->app->instance(GoogleCalendarService::class, $service);

        $defaultPath = route(
            'settings.availability.index',
            absolute: false
        );

        foreach ([
            'https://evil.example.com',
            '//evil.example.com/path',
        ] as $redirectPath) {
            $this->actingAs($coach)
                ->get(route('settings.google-calendar.redirect', [
                    'redirect_path' => $redirectPath,
                ]))
                ->assertRedirect('https://accounts.google.com/auth');

            $this->assertSame(
                $defaultPath,
                session('google_calendar_redirect_path')
            );
        }
    }
}
