<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * 管理者・担当コーチによる受講登録メモの更新 Action。
 *
 * - 登録済みの受講登録メモの本文を更新する
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(EnrollmentNote $note, array $validated): EnrollmentNote
    {
        return DB::transaction(function () use ($note, $validated) {
            $note->update(['body' => $validated['body']]);

            return $note->refresh();
        });
    }
}
