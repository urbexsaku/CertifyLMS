<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat\Conversation;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai-chat.enabled' => true]);
    }

    public function test_owner_can_view_conversation(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
        ]);

        // Act
        $response = $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation));

        // Assert
        $response->assertOk()
            ->assertViewIs('ai-chat.show')
            ->assertViewHas('conversation', function ($viewConversation) use ($conversation): bool {
                return $viewConversation->is($conversation);
            });
    }

    public function test_other_student_cannot_view_conversation(): void
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
            ->get(route('ai-chat.conversations.show', $conversation));

        // Assert
        $response->assertForbidden();
    }

    public function test_messages_are_displayed_in_created_at_and_id_order(): void
    {
        // Arrange
        $student = $this->createActiveStudent();
        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $student->default_enrollment_id,
        ]);

        $olderMessage = AiChatMessage::factory()->create([
            'ai_chat_conversation_id' => $conversation->id,
            'created_at' => now()->subMinute(),
        ]);

        $sameCreatedAt = now();

        $messageA = AiChatMessage::factory()->create([
            'ai_chat_conversation_id' => $conversation->id,
            'created_at' => $sameCreatedAt,
        ]);

        $messageB = AiChatMessage::factory()->create([
            'ai_chat_conversation_id' => $conversation->id,
            'created_at' => $sameCreatedAt,
        ]);

        $expectedMessageIds = collect([$messageA->id, $messageB->id])
            ->sort()
            ->prepend($olderMessage->id)
            ->values()
            ->all();

        // Act
        $response = $this->actingAs($student)
            ->get(route('ai-chat.conversations.show', $conversation));

        // Assert
        $response->assertOk()
            ->assertViewHas('conversation', function ($viewConversation) use ($conversation, $expectedMessageIds): bool {
                return $viewConversation->is($conversation)
                    && $viewConversation->messages->pluck('id')->all() === $expectedMessageIds;
            });
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
