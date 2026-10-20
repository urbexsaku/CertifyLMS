<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
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
            'title' => '新規目標',
            'description' => '説明文',
            'target_date' => today()->addDays(30),
        ], $override);
    }

    public function test_student_can_create_own_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $payload = $this->payload();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), $payload);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '新規目標',
            'description' => '説明文',
            'achieved_at' => null,
        ]);
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '新規目標',
            'description' => '',
            'target_date' => '',
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '新規目標',
            'description' => null,
            'target_date' => null,
        ]);
    }

    public function test_other_student_cannot_create_goal_for_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $payload = $this->payload();

        // Act
        $response = $this->actingAs($otherStudent)->post(route('enrollments.goals.store', $enrollment), $payload);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseMissing('enrollment_goals', [
            'title' => '新規目標',
        ]);
    }

    public function test_non_student_cannot_create_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $payload = $this->payload();

        $response = $this->actingAs($admin)->post(route('enrollments.goals.store', $enrollment), $payload);
        $response->assertForbidden();

        $response = $this->actingAs($coach)->post(route('enrollments.goals.store', $enrollment), $payload);
        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $payload = $this->payload();

        $response = $this->post(route('enrollments.goals.store', $enrollment), $payload);
        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('enrollment_goals', [
            'title' => '新規目標',
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');
        $this->assertDatabaseMissing('enrollment_goals', [
            'title' => '新規目標',
        ]);
    }
}
