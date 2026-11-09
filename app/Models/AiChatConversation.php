<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiChatConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 受講生の AI 相談会話を表す Model。
 *
 * Section を相談コンテキストとして保持し、会話に属するメッセージを管理する。
 *
 * 関連: User(作成者) / Section(相談対象教材) / AiChatMessage(会話メッセージ)
 */
class AiChatConversation extends Model
{
    /** @use HasFactory<AiChatConversationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'section_id',
        'title',
        'last_message_at',
        'title_generated_at',
        'title_manually_edited_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'title_generated_at' => 'datetime',
        'title_manually_edited_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'ai_chat_conversation_id');
    }
}
