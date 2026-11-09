<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Conversation;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_update_conversation_title(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'title' => '変更前タイトル',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->patchJson(route('ai-chat.conversations.update', $conversation), [
                'title' => '変更後タイトル',
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('conversation.title', '変更後タイトル');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更後タイトル',
        ]);

        $this->assertNotNull(
            $conversation->fresh()->title_manually_edited_at,
        );
    }

    public function test_title_over_one_hundred_characters_is_rejected(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'title' => '変更前タイトル',
        ]);

        // Act
        $response = $this->actingAs($student)
            ->patchJson(route('ai-chat.conversations.update', $conversation), [
                'title' => str_repeat('あ', 101),
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors('title');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更前タイトル',
        ]);
    }

    public function test_other_student_cannot_update_conversation_title(): void
    {
        // Arrange
        $owner = $this->createActiveStudent();
        $otherStudent = $this->createActiveStudent();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
            'enrollment_id' => $owner->default_enrollment_id,
            'title' => '変更前タイトル',
        ]);

        // Act
        $response = $this->actingAs($otherStudent)
            ->patchJson(route('ai-chat.conversations.update', $conversation), [
                'title' => '変更後タイトル',
            ]);

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更前タイトル',
        ]);
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
