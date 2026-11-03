<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', 'string', 'in:all_students,certification,user'],
            'target_certification_id' => [
                'required_if:target_type,certification',
                'nullable',
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'required_if:target_type,user',
                'nullable',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象タイプ',
            'target_certification_id' => '配信先資格',
            'target_user_id' => '配信先ユーザー',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_certification_id.required_if' => '配信対象タイプが「資格指定」の場合、配信先資格を選択してください。',
            'target_user_id.required_if' => '配信対象タイプが「ユーザー指定」の場合、配信先ユーザーを選択してください。',
        ];
    }
}
