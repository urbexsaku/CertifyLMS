<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaReply モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 2 リレーション (qaThread / user) +
 * 1 scope (order)
 * を網羅する。
 */
class QaReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_relation_returns_parent_thread(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        // Act
        $parent = $reply->thread;

        // Assert
        $this->assertTrue($parent->is($thread));
    }

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $reply = QaReply::factory()->for($user)->create();

        // Act
        $owner = $reply->user;

        // Assert
        $this->assertTrue($owner->is($user));
    }

    public function test_ordered_scope_orders_by_created_at_ascending(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();
        $second = QaReply::factory()->forThread($thread)->create(['created_at' => '2026-05-02 12:00:00']);
        $first = QaReply::factory()->forThread($thread)->create(['created_at' => '2026-05-01 12:00:00']);

        // Act
        $results = QaReply::ordered()->get();

        // Assert
        $this->assertTrue($results->first()->is($first));
    }
}
