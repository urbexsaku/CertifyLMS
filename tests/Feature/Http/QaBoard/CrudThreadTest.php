<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_store_thread_for_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $cert->id,
                'title' => '質問のタイトル',
                'body' => '質問の本文です。',
            ])
            ->assertRedirect();

        $thread = QaThread::firstOrFail();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
        $this->assertSame($student->id, $thread->user_id);
    }

    public function test_store_thread_rejects_non_published_or_non_student(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $draftCert = Certification::factory()->draft()->create();
        $archivedCert = Certification::factory()->archived()->create();
        $publishedCert = Certification::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $draftCert->id,
                'title' => '質問のタイトル',
                'body' => '質問の本文です。',
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $archivedCert->id,
                'title' => '質問のタイトル',
                'body' => '質問の本文です。',
            ])
            ->assertForbidden();

        $this->actingAs($coach)
            ->post(route('qa-board.store'), [
                'certification_id' => $publishedCert->id,
                'title' => '質問のタイトル',
                'body' => '質問の本文です。',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('qa-board.store'), [
                'certification_id' => $publishedCert->id,
                'title' => '質問のタイトル',
                'body' => '質問の本文です。',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_update_thread_and_other_student_cannot(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $thread = QaThread::factory()->forUser($student)->create();
        $originalCertId = $thread->certification_id;

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '改題後',
                'body' => '更新された本文',
            ])
            ->assertRedirect();

        $thread->refresh();
        $this->assertSame('改題後', $thread->title);
        $this->assertSame('更新された本文', $thread->body);
        $this->assertSame($originalCertId, $thread->certification_id);

        $this->actingAs($otherStudent)
            ->patch(route('qa-board.update', $thread), [
                'title' => '改題後',
                'body' => '更新された本文',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_delete_without_replies_and_admin_can_delete_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->forUser($student)->create();
        $threadWithReply = QaThread::factory()->forUser($student)->create();
        $reply = QaReply::factory()->forThread($threadWithReply)->create();

        $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect();
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);

        $this->actingAs($student)
            ->deleteJson(route('qa-board.destroy', $threadWithReply))
            ->assertStatus(409);
        $this->assertDatabaseHas('qa_threads', ['id' => $threadWithReply->id]);

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $threadWithReply))
            ->assertRedirect();
        $this->assertDatabaseMissing('qa_threads', ['id' => $threadWithReply->id]);
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }
}
