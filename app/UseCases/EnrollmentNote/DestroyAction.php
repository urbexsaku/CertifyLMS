<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * 管理者・担当コーチによる受講登録メモの削除 Action。
 *
 * - 登録済みの受講登録メモを削除する
 */
final class DestroyAction
{
    public function __invoke(EnrollmentNote $note): void
    {
        DB::transaction(fn () => $note->delete());
    }
}
