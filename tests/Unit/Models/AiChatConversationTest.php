<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($user, 'user')->create();

        // Act
        $owner = $conversation->user;

        // Assert
        $this->assertTrue($owner->is($user));
    }

    public function test_enrollment_relation_returns_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $conversation = AiChatConversation::factory()->for($enrollment)->create();

        // Act
        $related = $conversation->enrollment;

        // Assert
        $this->assertTrue($related->is($enrollment));
    }

    public function test_section_relation_returns_section(): void
    {
        // Arrange
        $section = Section::factory()->create();
        $conversation = AiChatConversation::factory()->create(['section_id' => $section->id]);

        // Act
        $related = $conversation->section;

        // Assert
        $this->assertTrue($related->is($section));
    }

    public function test_messages_relation_returns_conversation_messages(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create();
        $conversation->messages()->saveMany([
            AiChatMessage::factory()->make(['content' => '質問']),
            AiChatMessage::factory()->assistant()->make(['content' => '回答']),
        ]);
        $otherConversation = AiChatConversation::factory()->create();
        $otherConversation->messages()->save(
            AiChatMessage::factory()->make(['content' => '別の質問']),
        );

        // Act
        $messages = $conversation->messages;

        // Assert
        $this->assertCount(2, $messages);
        $this->assertSame(['質問', '回答'], $messages->pluck('content')->all());
    }

    public function test_datetime_attributes_are_cast_to_carbon(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create([
            'last_message_at' => now(),
            'title_generated_at' => now(),
            'title_manually_edited_at' => now(),
        ]);

        // Act
        $fresh = $conversation->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->last_message_at);
        $this->assertInstanceOf(Carbon::class, $fresh->title_generated_at);
        $this->assertInstanceOf(Carbon::class, $fresh->title_manually_edited_at);
    }
}
