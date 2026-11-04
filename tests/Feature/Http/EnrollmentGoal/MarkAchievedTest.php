<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAchievedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_student_can_mark_own_goal_as_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $goal->enrollment));
        $response->assertSessionHas('success', '目標を達成しました。');
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'achieved_at' => '2026-10-21 12:00:00',
        ]);
    }

    public function test_marking_already_achieved_goal_keeps_achieved_state(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'achieved_at' => '2026-10-20 12:00:00',
        ]);

        // Act
        $response = $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $goal->enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'achieved_at' => '2026-10-20 12:00:00',
        ]);
    }

    public function test_other_student_cannot_mark_goal_as_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($otherStudent)->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'achieved_at' => null,
        ]);
    }

    public function test_non_student_cannot_mark_goal_as_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $response = $this->actingAs($admin)->post(route('enrollment-goals.markAchieved', $goal));
        $response->assertForbidden();

        $response = $this->actingAs($coach)->post(route('enrollment-goals.markAchieved', $goal));
        $response->assertForbidden();
    }
}
