<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThread モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 3 リレーション (certification / user / replies) +
 * 3 scope (forCertification / byStatus / keyword ) + 2 cast (status enum / resolved_at datetime)
 * を網羅する。
 */
class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_cast_to_qa_thread_status_enum(): void
    {
        $openThread = QaThread::factory()->create(['status' => QaThreadStatus::Open->value]);
        $resolvedThread = QaThread::factory()->create(['status' => QaThreadStatus::Resolved->value]);

        $this->assertInstanceOf(QaThreadStatus::class, $openThread->status);
        $this->assertInstanceOf(QaThreadStatus::class, $resolvedThread->status);
        $this->assertSame(QaThreadStatus::Open, $openThread->status);
        $this->assertSame(QaThreadStatus::Resolved, $resolvedThread->status);
    }

    public function test_resolved_at_is_cast_to_datetime(): void
    {
        $thread = QaThread::factory()->create([
            'status' => QaThreadStatus::Resolved->value,
            'resolved_at' => '2026-05-01 12:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $thread->resolved_at);
    }

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $thread = QaThread::factory()->for($user)->create();

        // Act
        $owner = $thread->user;

        // Assert
        $this->assertTrue($owner->is($user));
    }

    public function test_certification_relation_returns_target_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($cert)->create();

        // Act
        $target = $thread->certification;

        // Assert
        $this->assertTrue($target->is($cert), '対象 certification と enrollment->certification は一致するはず');
    }

    public function test_replies_relation_returns_attached_replies(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->create();
        QaReply::factory()->forThread($thread)->create();
        QaReply::factory()->create();

        // Act
        $replies = $thread->replies;

        // Assert
        $this->assertCount(2, $replies, '対象 thread の reply のみが取得されるはず');
    }

    public function test_scope_for_certification_filters_by_certification_id(): void
    {
        // Arrange
        $targetCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $targetThread = QaThread::factory()->forCertification($targetCert)->create();
        QaThread::factory()->forCertification($otherCert)->create();

        // Act
        $results = QaThread::forCertification($targetCert->id)->get();

        // Assert
        $this->assertCount(1, $results, '指定した certification_id の qaThread のみが取得されるはず');
        $this->assertTrue($results->first()->is($targetThread));
    }

    public function test_scope_by_status_filters_unresolved_as_open(): void
    {
        // Arrange
        $thread = QaThread::factory()->open()->create();
        QaThread::factory()->resolved()->create();

        // Act
        $results = QaThread::query()->byStatus('unresolved')->get();

        // Assert
        $this->assertCount(1, $results, 'open ステータスのみが scope で抽出されるはず');
        $this->assertTrue($results->first()->is($thread));
    }

    public function test_scope_by_status_filters_resolved(): void
    {
        // Arrange
        $thread = QaThread::factory()->resolved()->create();
        QaThread::factory()->open()->create();

        // Act
        $results = QaThread::query()->byStatus('resolved')->get();

        // Assert
        $this->assertCount(1, $results, 'resolved ステータスのみが scope で抽出されるはず');
        $this->assertTrue($results->first()->is($thread));
    }

    public function test_scope_by_status_does_not_filter_when_null(): void
    {
        // Arrange
        QaThread::factory()->open()->count(2)->create();
        QaThread::factory()->resolved()->count(2)->create();

        // Act
        $threads = QaThread::query()->byStatus(null)->get();

        // Assert
        $this->assertCount(4, $threads);
        $this->assertCount(2, $threads->where('status', QaThreadStatus::Open));
        $this->assertCount(2, $threads->where('status', QaThreadStatus::Resolved));
    }

    public function test_scope_keyword_matches_title(): void
    {
        // Arrange
        $test = QaThread::factory()->create(['title' => 'テスト']);
        QaThread::factory()->create(['title' => '対象外']);

        // Act
        $byTest = QaThread::query()->keyword('テスト')->get();

        // Assert
        $this->assertCount(1, $byTest);
        $this->assertTrue($byTest->first()->is($test));
    }

    public function test_scope_keyword_matches_body(): void
    {
        // Arrange
        $test = QaThread::factory()->create(['body' => 'テスト']);
        QaThread::factory()->create(['body' => '対象外']);

        // Act
        $byTest = QaThread::query()->keyword('テスト')->get();

        // Assert
        $this->assertCount(1, $byTest);
        $this->assertTrue($byTest->first()->is($test));
    }

    public function test_scope_keyword_matches_reply_body(): void
    {
        // Arrange
        $test = QaThread::factory()->create();
        QaReply::factory()->forThread($test)->create(['body' => 'テスト']);

        $other = QaThread::factory()->create();
        QaReply::factory()->forThread($other)->create(['body' => '対象外']);

        // Act
        $byTest = QaThread::query()->keyword('テスト')->get();

        // Assert
        $this->assertCount(1, $byTest);
        $this->assertTrue($byTest->first()->is($test));
    }
}
