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

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_qa_thread_list(): void
    {
        $admin = User::factory()->admin()->create();
        $publishedCert = Certification::factory()->published()->create();
        $draftCert = Certification::factory()->draft()->create();
        $archivedCert = Certification::factory()->archived()->create();

        QaThread::factory()->for($publishedCert)->create();
        QaThread::factory()->for($draftCert)->create();
        QaThread::factory()->for($archivedCert)->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
        $response->assertViewHas('threads');
    }

    public function test_student_sees_only_published_threads(): void
    {
        $student = User::factory()->student()->create();
        $publishedCert = Certification::factory()->published()->create();
        $draftCert = Certification::factory()->draft()->create();
        $archivedCert = Certification::factory()->archived()->create();

        QaThread::factory()->for($publishedCert)->create(['title' => 'Published Cert Thread']);
        QaThread::factory()->for($draftCert)->create(['title' => 'Draft Cert Thread']);
        QaThread::factory()->for($archivedCert)->create(['title' => 'Archived Cert Thread']);

        $response = $this->actingAs($student)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertSee('Published Cert Thread');
        $response->assertDontSee('Draft Cert Thread');
        $response->assertDontSee('Archived Cert Thread');
    }

    public function test_coach_sees_only_assigned_published_threads(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $assignedPublishedCert = Certification::factory()->published()->create();
        $assignedDraftCert = Certification::factory()->draft()->create();
        $otherPublishedCert = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedPublishedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedDraftCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        QaThread::factory()->for($assignedPublishedCert)->create(['title' => 'My Assigned Published Thread']);
        QaThread::factory()->for($assignedDraftCert)->create(['title' => 'My Assigned Draft Thread']);
        QaThread::factory()->for($otherPublishedCert)->create(['title' => 'Unassigned Published Thread']);

        $response = $this->actingAs($coach)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertSee('My Assigned Published Thread');
        $response->assertDontSee('My Assigned Draft Thread');
        $response->assertDontSee('Unassigned Published Thread');
    }

    public function test_certification_filter_returns_only_matching_threads(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        QaThread::factory()->for($cert)->create(['title' => 'Match']);
        QaThread::factory()->create(['title' => 'OtherCert']);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index', ['certification_id' => $cert->id]));

        $response->assertOk();
        $response->assertSee('Match');
        $response->assertDontSee('OtherCert');
    }

    public function test_status_filter_returns_only_matching_threads(): void
    {
        $admin = User::factory()->admin()->create();

        QaThread::factory()->open()->create(['title' => 'Open']);
        QaThread::factory()->resolved()->create(['title' => 'Resolved']);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index', ['status' => 'unresolved']));

        $response->assertOk();
        $response->assertSee('Open');
        $response->assertDontSee('Resolved');
    }

    public function test_keyword_search_filters_by_title_body_and_reply(): void
    {
        $admin = User::factory()->admin()->create();

        $titleMatch = QaThread::factory()->create([
            'title' => 'Laravelについての質問',
            'body' => 'その他の本文',
        ]);

        $bodyMatch = QaThread::factory()->create([
            'title' => 'その他のタイトル',
            'body' => 'Laravelについての質問',
        ]);

        $replyMatch = QaThread::factory()->create([
            'title' => '回答についての質問',
            'body' => 'その他の本文',
        ]);

        QaReply::factory()->forThread($replyMatch)->create(['body' => 'Laravelについての回答']);

        $noMatch = QaThread::factory()->create([
            'title' => 'PHPについての質問',
            'body' => 'PHPに関する本文',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index', ['keyword' => 'Laravel']));

        $response->assertOk();
        $response->assertSee($titleMatch->title);
        $response->assertSee($bodyMatch->title);
        $response->assertSee($replyMatch->title);
        $response->assertDontSee($noMatch->title);
    }

    public function test_paginates_20_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        QaThread::factory()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk();
        $threads = $response->viewData('threads');
        $this->assertSame(20, $threads->perPage());
        $this->assertSame(22, $threads->total());
    }
}
