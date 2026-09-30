<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 質問掲示板スレッド一覧 (`admin.qa-board.index`) の N+1 非回帰を検証する Feature テスト。
 * 対象資格 / 作成者 / 回答数 を一括取得することで、スレッドの件数を増やしても
 * 発行クエリ数がほぼ増えない (各行の関連参照で遅延ロードが発火しない) ことを担保する。
 */
class QaThreadIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_thread_count(): void
    {
        // Arrange: 管理者 + 共通資格に紐づスレッド 2 件 (基準)
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $this->createQaThread($certification, 2);

        // Act: 基準のクエリ数を計測 → スレッドを 10 件追加して再計測 (1 ページに収まる件数)
        $baseline = $this->countQueriesFor(
            fn () => $this->actingAs($admin)->get(route('admin.qa-board.index'))
        );
        $this->createQaThread($certification, 10);
        $scaled = $this->countQueriesFor(
            fn () => $this->actingAs($admin)->get(route('admin.qa-board.index'))
        );

        // Assert: スレッドが増えても発行クエリ数はほぼ一定 (N+1 なら件数分増える)
        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問スレッド一覧で N+1 が再発している (基準 {$baseline} → 増加後 {$scaled})。対象資格 / 作成者 / 回答数を Eager Loading + withCount で一括取得しているか確認",
        );
    }

    private function createQaThread(Certification $certification, int $count): void
    {
        QaThread::factory()->count($count)->forCertification($certification)->create();
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $closure();

        return $count;
    }
}
