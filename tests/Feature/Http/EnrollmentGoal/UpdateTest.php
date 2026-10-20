<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => '更新後の目標',
            'description' => '更新後の説明',
            'target_date' => today()->addDays(60)->toDateString(),
        ], $override);
    }

    public function test_student_can_update_own_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();
        $payload = $this->payload();

        // Act
        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), $payload);

        // Assert
        $response->assertRedirect(route('enrollments.show', $goal->enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新後の目標',
            'description' => '更新後の説明',
            'target_date' => today()->addDays(60)->toDateString(),
        ]);
    }

    public function test_omitted_optional_fields_are_cleared(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '更新後の目標',
            'description' => '',
            'target_date' => '',
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $goal->enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新後の目標',
            'description' => null,
            'target_date' => null,
        ]);
    }

    public function test_other_student_cannot_update_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();
        $payload = $this->payload();

        // Act
        $response = $this->actingAs($otherStudent)->patch(route('enrollment-goals.update', $goal), $payload);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => $goal->description,
        ]);
    }

    public function test_non_student_cannot_update_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();
        $payload = $this->payload();

        $response = $this->actingAs($admin)->patch(route('enrollment-goals.update', $goal), $payload);
        $response->assertForbidden();

        $response = $this->actingAs($coach)->patch(route('enrollment-goals.update', $goal), $payload);
        $response->assertForbidden();
    }

    public function test_required_fields_are_validated(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');
        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => $goal->title,
            'description' => $goal->description,
        ]);
    }
}
