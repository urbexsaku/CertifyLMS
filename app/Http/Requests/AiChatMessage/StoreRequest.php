<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChatMessage;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiChatConversation
            && $this->user()?->can('view', $conversation) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                'min:1',
                'max:2000',
                'regex:/^(?![ 　]+$).+$/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.min' => '質問内容を入力してください。',
            'content.regex' => '質問内容を入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content' => '質問内容',
        ];
    }
}
