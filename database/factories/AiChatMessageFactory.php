<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User->value,
            'content' => fake()->realText(100),
            'status' => AiChatMessageStatus::Completed->value,
            'model_name' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
            'error_message' => null,
        ];
    }

    /**
     * AI の回答メッセージを作成する。
     */
    public function assistant(): static
    {
        return $this->state(fn (): array => [
            'role' => AiChatMessageRole::Assistant->value,
        ]);
    }

    /**
     * 処理中のメッセージを作成する。
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => AiChatMessageStatus::Pending->value,
        ]);
    }

    /**
     * エラーになったメッセージを作成する。
     */
    public function error(): static
    {
        return $this->state(fn (): array => [
            'status' => AiChatMessageStatus::Error->value,
            'error_message' => 'Gemini API request failed.',
        ]);
    }

    /**
     * 利用量情報を持つメッセージを作成する。
     */
    public function withUsage(): static
    {
        return $this->state(fn (): array => [
            'model_name' => 'gemini-2.5-flash',
            'input_tokens' => 120,
            'output_tokens' => 80,
            'response_time_ms' => 1500,
        ]);
    }
}
