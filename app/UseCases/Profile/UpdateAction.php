<?php

declare(strict_types=1);

namespace App\UseCases\Profile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Profile の更新ユースケース。
 *
 * 本人の name / bio、およびコーチの meeting_url を更新する。
 * アバター画像は別ユースケースで更新する。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        $attributes = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach) {
            $attributes['meeting_url'] = $validated['meeting_url'] ?? null;
        }

        return DB::transaction(function () use ($user, $attributes): User {
            $user->update($attributes);

            return $user->fresh();
        });
    }
}
