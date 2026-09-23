<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    /**
     * 質問掲示板スレッドの新規作成リクエスト。資格ID、タイトル、本文を受け取る。
     */
    public function authorize(): bool
    {
        if (! ($this->user()?->can('create', QaThread::class) ?? false)) {
            return false;
        }

        $certificationId = $this->input('certification_id');

        if (! is_string($certificationId)) {
            return true;
        }

        $certification = Certification::find($certificationId);

        return $certification === null
            || $certification->status === CertificationStatus::Published;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => ['required', 'ulid', 'exists:certifications,id'],
            'title' => ['required', 'string', 'max:200', 'regex:/^(?![ 　]+$).+$/u'],
            'body' => ['required', 'string', 'max:5000', 'regex:/^(?![ 　]+$).+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'certification_id' => '資格',
            'title' => 'タイトル',
            'body' => '本文',
        ];
    }
}
