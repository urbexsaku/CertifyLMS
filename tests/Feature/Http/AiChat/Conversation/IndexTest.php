<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Conversation;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_student_can_access_ai_chat_index(): void
    {
        // Arrange
        $student = $this->createActiveStudent();

        // Act
        $response = $this->actingAs($student)
            ->get(route('ai-chat.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('ai-chat.empty-state');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        // Act
        $response = $this->get(route('ai-chat.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_coach_cannot_access_ai_chat_index(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)
            ->get(route('ai-chat.index'));

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_cannot_access_ai_chat_index(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)
            ->get(route('ai-chat.index'));

        // Assert
        $response->assertForbidden();
    }

    public function test_ai_chat_index_returns_not_found_when_feature_is_disabled(): void
    {
        // Arrange
        config(['ai-chat.enabled' => false]);
        $student = $this->createActiveStudent();

        // Act
        $response = $this->actingAs($student)
            ->get(route('ai-chat.index'));

        // Assert
        $response->assertNotFound();
    }

    private function createActiveStudent(): User
    {
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()
            ->learning()
            ->create([
                'user_id' => $student->id,
            ]);

        $student->forceFill([
            'default_enrollment_id' => $enrollment->id,
        ])->save();

        return $student;
    }
}
