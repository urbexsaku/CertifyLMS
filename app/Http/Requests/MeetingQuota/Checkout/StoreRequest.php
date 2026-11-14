<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota\Checkout;

use App\Enums\MeetingPackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 受講生の追加面談パック購入リクエスト。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchase-meeting-quota') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_pack_id' => [
                'required',
                'ulid',
                Rule::exists('meeting_packs', 'id')
                    ->where('status', MeetingPackStatus::Published->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'meeting_pack_id' => '面談パック',
        ];
    }
}
