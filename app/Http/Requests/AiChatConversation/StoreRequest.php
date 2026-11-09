<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChatConversation;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AI 相談会話の新規作成リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            AiChatConversation::class,
        ) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'section_id' => [
                'nullable',
                'ulid',
                'exists:sections,id',
            ],
            'message' => [
                'nullable',
                'string',
                'min:1',
                'max:2000',
            ],
            'source' => [
                'nullable',
                'string',
                'in:widget,full-screen',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'section_id.exists' => '指定したセクションが見つかりません。',
            'message.min' => 'メッセージを入力してください。',
            'source.in' => '相談の開始元が不正です。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'section_id' => 'セクション',
            'message' => 'メッセージ',
            'source' => '相談の開始元',
        ];
    }
}
