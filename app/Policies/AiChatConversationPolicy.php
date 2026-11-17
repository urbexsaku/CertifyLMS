<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AI 相談会話リソースの認可ポリシー。
 *
 * - 学習中の受講生のみ利用可能
 * - 他の受講生の会話は閲覧・編集・削除できない
 * - 管理者・コーチも受講生の会話にはアクセスできない
 */
class AiChatConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress;
    }

    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $this->viewAny($user)
            && $conversation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
