<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyNestedBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_reply_routes_return_404_for_parent_mismatch(): void
    {
        $student = User::factory()->student()->create();

        $threadA = QaThread::factory()->forUser($student)->create();
        $threadB = QaThread::factory()->forUser($student)->create();

        $replyB = QaReply::factory()->forThread($threadB)->forUser($student)->create();

        $this->actingAs($student)
            ->get(route('qa-board.replies.edit', [
                'thread' => $threadA,
                'reply' => $replyB,
            ]))
            ->assertNotFound();

        $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [
                'thread' => $threadA,
                'reply' => $replyB,
            ]), [
                'body' => '更新された本文',
            ])
            ->assertNotFound();

        $this->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $threadA,
                'reply' => $replyB,
            ]))
            ->assertNotFound();
    }
}
