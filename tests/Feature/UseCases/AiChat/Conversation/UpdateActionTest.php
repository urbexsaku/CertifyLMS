<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat\Conversation;

use App\Models\AiChatConversation;
use App\Models\User;
use App\UseCases\AiChat\Conversation\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_title_and_records_manual_edit_timestamp(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student, 'user')->create([
            'title' => '変更前タイトル',
            'title_manually_edited_at' => null,
        ]);

        // Act
        $updated = app(UpdateAction::class)($conversation, ['title' => '変更後タイトル']);

        // Assert
        $this->assertSame('変更後タイトル', $updated->title);
        $this->assertNotNull($updated->title_manually_edited_at);
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更後タイトル',
        ]);
    }
}
