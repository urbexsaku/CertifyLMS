<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 目標更新 UpdateRequest の rules() を検証する Unit テスト。
 * Store と同じ rules を valid + invalid で確認する。
 */
class UpdateRequestTest extends TestCase
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

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();
        $payload = $this->payload();

        // Act
        $response = $this->actingAs($student)->patchJson(route('enrollment-goals.update', $goal), $payload);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('enrollment_goals', [
            'title' => '更新後の目標',
            'description' => '更新後の説明',
            'target_date' => today()->addDays(60)->toDateString(),
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->patchJson(route('enrollment-goals.update', $goal), $this->payload($overrides));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'title 未指定で 422' => [['title' => ''], 'title'],
            'title 101 文字で 422' => [['title' => str_repeat('a', 101)], 'title'],
            'description 1001 文字で 422' => [['description' => str_repeat('b', 1001)], 'description'],
            'target_date 不正値で 422' => [['target_date' => 'unknown'], 'target_date'],
        ];
    }
}
