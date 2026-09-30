<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の回答に関する認可ルール。
 *
 * - admin: 回答の削除可
 * - coach: 公開中かつ担当資格配下のスレッドへの回答登録可、本人の回答を編集・削除可
 * - student: 公開中資格配下のスレッドへの回答登録可、本人の回答を編集・削除可
 */
class QaReplyPolicy
{
    public function create(User $auth, QaThread $thread): bool
    {
        if (! $this->isPublished($thread->certification)) {
            return false;
        }

        return match ($auth->role) {
            UserRole::Student => true,
            UserRole::Coach => $this->assignedCoach($auth, $thread->certification),
            default => false,
        };
    }

    public function update(User $auth, QaReply $reply): bool
    {
        return in_array($auth->role, [UserRole::Coach, UserRole::Student], true)
            && $this->canAccessThread($auth, $reply)
            && $reply->user_id === $auth->id;
    }

    public function delete(User $auth, QaReply $reply): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return in_array($auth->role, [UserRole::Coach, UserRole::Student], true)
            && $this->canAccessThread($auth, $reply)
            && $reply->user_id === $auth->id;
    }

    private function canAccessThread(User $auth, QaReply $reply): bool
    {
        $thread = $reply->thread;

        if ($thread === null || ! $this->isPublished($thread->certification)) {
            return false;
        }

        return match ($auth->role) {
            UserRole::Student => true,
            UserRole::Coach => $this->assignedCoach(
                $auth,
                $thread->certification
            ),
            default => false,
        };
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
