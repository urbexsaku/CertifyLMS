<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 管理者・担当コーチによる受講登録メモの登録 Action。
 *
 * - 受講登録に紐づくメモを登録する
 * - ログインユーザーを投稿者として設定する
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(Enrollment $enrollment, User $user, array $validated): EnrollmentNote
    {
        return DB::transaction(fn () => EnrollmentNote::create([
            'enrollment_id' => $enrollment->id,
            'author_id' => $user->id,
            'body' => $validated['body'],
        ]));
    }
}
