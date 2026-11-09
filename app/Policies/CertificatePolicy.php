<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function download(User $user, Certificate $certificate)
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $certificate->certification->coaches()->where('users.id', $user->id)->exists(),
            UserRole::Student => $certificate->user_id === $user->id,
            default => false,
        };
    }
}
