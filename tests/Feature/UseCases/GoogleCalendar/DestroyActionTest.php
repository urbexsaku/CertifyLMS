<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\GoogleCalendar;

use App\Models\GoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\UseCases\GoogleCalendar\DestroyAction as GoogleCalendarDestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revokes_google_access_and_preserves_meeting_event_ids(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        GoogleCredential::factory()->for($coach, 'user')->create();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $otherMeeting = Meeting::factory()
            ->forCoach($otherCoach)
            ->create([
                'google_calendar_event_id' => 'other-google-event-456',
            ]);

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('revokeAccess')
            ->once()
            ->with(Mockery::on(
                fn (User $user) => $user->id === $coach->id
            ));

        $this->app->instance(GoogleCalendarService::class, $service);

        // Act
        app(GoogleCalendarDestroyAction::class)($coach);

        // Assert
        $this->assertDatabaseMissing('google_credentials', [
            'user_id' => $coach->id,
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => 'google-event-123',
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $otherMeeting->id,
            'google_calendar_event_id' => 'other-google-event-456',
        ]);
    }

    public function test_redirects_with_success_message_when_destroyed(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $service = Mockery::mock(GoogleCalendarService::class);
        $service->shouldNotReceive('revokeAccess');

        $this->app->instance(GoogleCalendarService::class, $service);

        // Act / Assert
        $this->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect(route('settings.availability.index'))
            ->assertSessionHas(
                'success',
                'Googleカレンダー連携を解除しました。'
            );
    }

    public function test_keeps_google_credential_when_google_access_revocation_fails(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        $credential = GoogleCredential::factory()
            ->for($coach, 'user')
            ->create();

        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->create([
                'google_calendar_event_id' => 'google-event-123',
            ]);

        $service = Mockery::mock(GoogleCalendarService::class);

        $service->shouldReceive('revokeAccess')
            ->once()
            ->with(Mockery::on(
                fn (User $user) => $user->id === $coach->id
            ))
            ->andThrow(
                new RuntimeException('Google access token revocation failed.')
            );

        $this->app->instance(GoogleCalendarService::class, $service);

        // Act
        try {
            app(GoogleCalendarDestroyAction::class)($coach);

            $this->fail('Googleトークン失効失敗時に例外が発生しませんでした。');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Google access token revocation failed.',
                $exception->getMessage()
            );
        }

        // Assert: LMS側のGoogleCredentialは削除されない
        $this->assertDatabaseHas('google_credentials', [
            'id' => $credential->id,
            'user_id' => $coach->id,
        ]);

        // Assert: GoogleイベントIDも保持される
        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'google_calendar_event_id' => 'google-event-123',
        ]);
    }
}
