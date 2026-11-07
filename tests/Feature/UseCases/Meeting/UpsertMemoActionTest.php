<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_memo(): void
    {
        // Arrange
        $meeting = Meeting::factory()->reserved()->create();

        // Act
        $result = app(UpsertMemoAction::class)($meeting, '面談メモテスト');

        // Assert
        $this->assertInstanceOf(MeetingMemo::class, $result);
        $this->assertSame($meeting->id, $result->meeting_id);
        $this->assertSame('面談メモテスト', $result->body);

        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '面談メモテスト',
        ]);
    }

    public function test_updates_existing_memo(): void
    {
        // Arrange
        $meeting = Meeting::factory()->reserved()->create();
        $memo = MeetingMemo::factory()->forMeeting($meeting)->create(['body' => '既存のメモ']);

        // Act
        app(UpsertMemoAction::class)($meeting, '更新後のメモ');

        // Assert
        $this->assertDatabaseHas('meeting_memos', [
            'id' => $memo->id,
            'meeting_id' => $meeting->id,
            'body' => '更新後のメモ',
        ]);

        $this->assertDatabaseCount('meeting_memos', 1);
    }

    public function test_throws_when_meeting_is_canceled(): void
    {
        // Arrange
        $meeting = Meeting::factory()->canceled()->create();

        // Act / Assert
        try {
            app(UpsertMemoAction::class)($meeting, '面談メモテスト');

            $this->fail('キャンセル済みの面談にはメモを保存できないはず');
        } catch (MeetingStatusTransitionException) {
            // 期待した例外
        }

        // Assert
        $this->assertDatabaseMissing('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '面談メモテスト',
        ]);
    }
}
