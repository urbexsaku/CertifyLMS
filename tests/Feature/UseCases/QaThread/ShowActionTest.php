<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaThread\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QaThread 詳細取得 Action `ShowAction` の eager load ・回答順序を検証する Feature テスト。
 * 詳細表示に必要な投稿者・資格・回答者・回答数が取得され、回答が古い順に並ぶことを確認する。
 */
class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_loads_required_relations_and_count(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->create();

        // Act
        $result = app(ShowAction::class)($thread);

        // Assert
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('certification'));
        $this->assertTrue($result->relationLoaded('replies'));
        $this->assertTrue($result->replies->first()->relationLoaded('user'));
        $this->assertSame(1, $result->replies_count);
    }

    public function test_show_orders_replies_oldest_first(): void
    {
        // Arrange
        $thread = QaThread::factory()->create();

        $oldest = QaReply::factory()->forThread($thread)->create(['created_at' => now()->subHours(2)]);

        $newest = QaReply::factory()->forThread($thread)->create(['created_at' => now()->subHours(1)]);

        // Act
        $result = app(ShowAction::class)($thread);

        // Assert
        $this->assertSame(
            [$oldest->id, $newest->id],
            $result->replies->pluck('id')->all(),
        );
    }
}
