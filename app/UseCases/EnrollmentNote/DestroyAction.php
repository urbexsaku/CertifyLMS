<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

/**
 * 担当コーチ・管理人による受講メモを削除するユースケース。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentNote $note): void
    {
        DB::transaction(fn () => $note->delete());
    }
}
