<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use Database\Factories\AiChatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI 相談のメッセージを表す Model。
 *
 * 受講生の質問および AI の回答を保持し、API 処理状態や利用トークン数を管理する。
 *
 * 関連: AiChatConversation(所属する会話)
 */
class AiChatMessage extends Model
{
    /** @use HasFactory<AiChatMessageFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'ai_chat_conversation_id',
        'role',
        'content',
        'status',
        'model_name',
        'input_tokens',
        'output_tokens',
        'response_time_ms',
        'error_message',
    ];

    protected $casts = [
        'role' => AiChatMessageRole::class,
        'status' => AiChatMessageStatus::class,
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /**
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            AiChatConversation::class,
            'ai_chat_conversation_id',
        );
    }
}
