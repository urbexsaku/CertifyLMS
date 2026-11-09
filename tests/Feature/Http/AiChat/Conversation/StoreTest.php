<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Conversation;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.title.provisional' => '新規相談',
        ]);
    }

    public function test_student_can_create_conversation(): void
    {
        // Arrange
        $student = $this->createActiveStudent();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), []);

        // Assert
        $response->assertCreated()
            ->assertJsonPath('was_reused', false)
            ->assertJsonPath('conversation.title', '新規相談');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'section_id' => null,
            'title' => '新規相談',
        ]);
    }

    public function test_invalid_section_id_is_rejected(): void
    {
        // Arrange
        $student = $this->createActiveStudent();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'section_id' => 'invalid-section-id',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('section_id');

        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }

    public function test_initial_message_over_two_thousand_characters_is_rejected(): void
    {
        // Arrange
        $student = $this->createActiveStudent();

        // Act
        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.store'), [
                'message' => str_repeat('あ', 2001),
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('ai_chat_conversations', 0);
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
