<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Exceptions\QaThread\QaThreadHasRepliesException;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThread 削除 Action `DestroyAction` の 削除処理を検証する Feature テスト。
 * Student は回答なしのスレッドを削除でき、回答ありのスレッドは削除できないこと、
 * Admin は対象スレッドとその回答を削除できることを確認する。
 */
class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_delete_thread_without_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();

        // Act
        app(DestroyAction::class)($student, $thread);

        // Assert
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_student_cannot_delete_thread_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();
        QaReply::factory()->forThread($thread)->create();

        $this->expectException(QaThreadHasRepliesException::class);

        app(DestroyAction::class)($student, $thread);
    }

    public function test_deletes_target_thread_and_its_replies_only(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        $otherThread = QaThread::factory()->create();
        $otherReply = QaReply::factory()->forThread($otherThread)->create();

        // Act
        app(DestroyAction::class)($admin, $thread);

        // Assert
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
        $this->assertDatabaseHas('qa_threads', ['id' => $otherThread->id]);
        $this->assertDatabaseHas('qa_replies', ['id' => $otherReply->id]);
    }
}
