<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_available_slots_and_loads_certification_relationship(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $date = Carbon::parse('2026-11-09');

        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        // Act
        $result = app(FetchAvailabilityAction::class)($enrollment, $date);

        // Assert
        // Assert
        $this->assertTrue($enrollment->relationLoaded('certification'));
        $this->assertCount(3, $result);

        $this->assertSame('09:00', $result[0]['slot_start']->format('H:i'));
        $this->assertSame('10:00', $result[1]['slot_start']->format('H:i'));
        $this->assertSame('11:00', $result[2]['slot_start']->format('H:i'));

        foreach ($result as $slot) {
            $this->assertSame(1, $slot['available_coach_count']);
            $this->assertSame(
                $slot['slot_start']->copy()->addHour()->format('H:i'),
                $slot['slot_end']->format('H:i'),
            );
        }
    }
}
