<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Conversation;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_delete_conversation(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->deleteJson(route('ai-chat.conversations.destroy', $conversation));

        // Assert
        $response->assertOk()
            ->assertJsonPath('message', '会話を削除しました。');

        $this->assertDatabaseMissing('ai_chat_conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_other_student_cannot_delete_conversation(): void
    {
        // Arrange
        $owner = $this->createActiveStudent();
        $otherStudent = $this->createActiveStudent();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
            'enrollment_id' => $owner->default_enrollment_id,
        ]);

        // Act
        $response = $this->actingAs($otherStudent)
            ->deleteJson(route('ai-chat.conversations.destroy', $conversation));

        // Assert
        $response->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_delete_redirects_to_latest_remaining_conversation(): void
    {
        // Arrange
        $student = $this->createActiveStudent();

        $deletedConversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'last_message_at' => now()->subDays(2),
        ]);

        $remainingConversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
            'last_message_at' => now()->subDay(),
        ]);

        // Act
        $response = $this->actingAs($student)
            ->delete(route('ai-chat.conversations.destroy', $deletedConversation));

        // Assert
        $response->assertRedirect(
            route('ai-chat.conversations.show', $remainingConversation),
        );

        $this->assertDatabaseMissing('ai_chat_conversations', [
            'id' => $deletedConversation->id,
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
