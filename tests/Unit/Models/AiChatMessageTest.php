<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_relation_returns_parent_conversation(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->for($conversation, 'conversation')->create();

        // Act
        $related = $message->conversation;

        // Assert
        $this->assertTrue($related->is($conversation));
    }

    public function test_role_and_status_are_cast_to_enums(): void
    {
        // Arrange
        $message = AiChatMessage::factory()->assistant()->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertSame(AiChatMessageRole::Assistant, $fresh->role);
        $this->assertSame(AiChatMessageStatus::Completed, $fresh->status);
    }

    public function test_usage_metadata_are_cast_to_integers(): void
    {
        // Arrange
        $message = AiChatMessage::factory()->assistant()->withUsage()->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertSame(120, $fresh->input_tokens);
        $this->assertSame(80, $fresh->output_tokens);
        $this->assertSame(1500, $fresh->response_time_ms);
    }
}
