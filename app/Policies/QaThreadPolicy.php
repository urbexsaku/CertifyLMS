<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の認可ルール。
 *
 * - admin: 全資格配下の QaThread を 閲覧・削除 可
 * - coach: 公開中かつ担当資格配下の QaThread を閲覧可
 * - student: 公開中資格配下の QaThread を閲覧可、質問の投稿・編集・解決/未解決変更可
 */
class QaThreadPolicy
{
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $auth, QaThread $thread): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $thread->certification)
                && $this->isPublished($thread->certification),
            UserRole::Student => $this->isPublished($thread->certification),
        };
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Student;
    }

    public function update(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $this->isPublished($thread->certification)
            && $thread->user_id === $auth->id;
    }

    public function delete(User $auth, QaThread $thread): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role !== UserRole::Student) {
            return false;
        }

        return $this->isPublished($thread->certification)
            && $thread->user_id === $auth->id;
    }

    public function resolve(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $this->isPublished($thread->certification)
            && $thread->user_id === $auth->id;
    }

    public function unresolve(User $auth, QaThread $thread): bool
    {
        return $auth->role === UserRole::Student
            && $this->isPublished($thread->certification)
            && $thread->user_id === $auth->id;
    }

    private function isPublished(Certification $certification): bool
    {
        return $certification->status === CertificationStatus::Published;
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
