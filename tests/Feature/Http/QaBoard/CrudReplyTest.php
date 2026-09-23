<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrudReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_and_assigned_coach_can_store_reply(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedPublishedCert = Certification::factory()->published()->create();
        $otherPublishedCert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($assignedPublishedCert)->create();
        $otherThread = QaThread::factory()->for($otherPublishedCert)->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedPublishedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答の本文です。',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '回答の本文です。',
        ]);

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答の本文です。',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => '回答の本文です。',
        ]);

        $this->actingAs($admin)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答の本文です。',
            ])
            ->assertForbidden();

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $otherThread), [
                'body' => '回答の本文です。',
            ])
            ->assertForbidden();
    }

    public function test_reply_author_can_update_and_others_cannot(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->create();
        $reply = QaReply::factory()->forUser($student)->forThread($thread)->create();

        $this->actingAs($student)->patch(
            route('qa-board.replies.update', [
                'thread' => $reply->thread,
                'reply' => $reply,
            ]),
            [
                'body' => '更新後の回答本文です。',
            ],
        );

        $reply->refresh();
        $this->assertSame('更新後の回答本文です。', $reply->body);

        $this->actingAs($otherStudent)->patch(
            route('qa-board.replies.update', [
                'thread' => $reply->thread,
                'reply' => $reply,
            ]),
            [
                'body' => '更新後の回答本文です。',
            ],
        )->assertForbidden();
    }

    public function test_author_and_admin_can_destroy_reply(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $reply = QaReply::factory()->forUser($student)->create();
        $otherReply = QaReply::factory()->forUser($student)->create();
        $adminReply = QaReply::factory()->forUser($student)->create();

        $this->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $reply->thread,
                'reply' => $reply,
            ]))
            ->assertRedirect();
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);

        $this->actingAs($otherStudent)
            ->delete(route('qa-board.replies.destroy', [
                'thread' => $otherReply->thread,
                'reply' => $otherReply,
            ]))
            ->assertForbidden();
        $this->assertDatabaseHas('qa_replies', ['id' => $otherReply->id]);

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [
                'thread' => $adminReply->thread,
                'reply' => $adminReply,
            ]))
            ->assertRedirect();
        $this->assertDatabaseMissing('qa_replies', ['id' => $adminReply->id]);
    }
}
