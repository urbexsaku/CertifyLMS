<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnmarkAchievedTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_unmark_own_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'achieved_at' => '2026-10-21 12:00:00',
        ]);

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $goal->enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'achieved_at' => null,
        ]);
    }

    public function test_unmarking_unachieved_goal_keeps_unachieved_state(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $goal->enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'achieved_at' => null,
        ]);
    }

    public function test_other_student_cannot_unmark_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'achieved_at' => '2026-10-21 12:00:00',
        ]);

        // Act
        $response = $this->actingAs($otherStudent)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'achieved_at' => '2026-10-21 12:00:00',
        ]);
    }

    public function test_non_student_cannot_unmark_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'achieved_at' => '2026-10-21 12:00:00',
        ]);
        $response = $this->actingAs($admin)->delete(route('enrollment-goals.unmarkAchieved', $goal));
        $response->assertForbidden();

        $response = $this->actingAs($coach)->delete(route('enrollment-goals.unmarkAchieved', $goal));
        $response->assertForbidden();
    }
}
