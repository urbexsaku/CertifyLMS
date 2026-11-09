<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat\Conversation;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChat\Conversation\StoreAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_conversation_with_section_context(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $section = $this->createSection($certification);

        // Act
        $result = app(StoreAction::class)($student, ['section_id' => $section->id]);

        // Assert
        $this->assertFalse($result['was_reused']);
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $result['conversation']->id,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'title' => '新規相談',
        ]);
    }

    public function test_reuses_existing_conversation_for_same_section(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $section = $this->createSection($certification);
        $existing = AiChatConversation::factory()->forSection($section, $enrollment)->for($student, 'user')->create();

        // Act
        $result = app(StoreAction::class)($student, ['section_id' => $section->id]);

        // Assert
        $this->assertTrue($result['was_reused']);
        $this->assertTrue($result['conversation']->is($existing));
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_creates_separate_conversations_for_different_sections(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $sectionA = $this->createSection($certification);
        $sectionB = $this->createSection($certification);
        $action = app(StoreAction::class);

        // Act
        $first = $action($student, ['section_id' => $sectionA->id]);
        $second = $action($student, ['section_id' => $sectionB->id]);

        // Assert
        $this->assertFalse($first['was_reused']);
        $this->assertFalse($second['was_reused']);
        $this->assertNotSame($first['conversation']->id, $second['conversation']->id);
        $this->assertDatabaseCount('ai_chat_conversations', 2);
    }

    public function test_creates_general_conversation_without_section(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();

        // Act
        $result = app(StoreAction::class)($student, []);

        // Assert
        $this->assertFalse($result['was_reused']);
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $result['conversation']->id,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => null,
            'title' => '新規相談',
        ]);
    }

    public function test_uses_default_eligible_enrollment_for_general_conversation(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $defaultCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $defaultEnrollment = Enrollment::factory()->for($student)->for($defaultCertification)->learning()->create();
        Enrollment::factory()->for($student)->for($otherCertification)->learning()->create();
        $student->update(['default_enrollment_id' => $defaultEnrollment->id]);

        // Act
        $result = app(StoreAction::class)($student, []);

        // Assert
        $this->assertSame($defaultEnrollment->id, $result['conversation']->enrollment_id);
    }

    public function test_uses_latest_eligible_enrollment_when_default_is_unavailable(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $firstCertification = Certification::factory()->published()->create();
        $latestCertification = Certification::factory()->published()->create();
        $firstEnrollment = Enrollment::factory()->for($student)->for($firstCertification)->learning()->create([
            'created_at' => now()->subDay(),
        ]);
        $latestEnrollment = Enrollment::factory()->for($student)->for($latestCertification)->passed()->create([
            'created_at' => now(),
        ]);
        $student->update(['default_enrollment_id' => $firstEnrollment->id]);
        $firstEnrollment->update(['status' => EnrollmentStatus::Failed->value]);

        // Act
        $result = app(StoreAction::class)($student, []);

        // Assert
        $this->assertSame($latestEnrollment->id, $result['conversation']->enrollment_id);
    }

    public function test_rejects_section_without_eligible_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->failed()->create();
        $section = $this->createSection($certification);

        // Act
        try {
            app(StoreAction::class)($student, ['section_id' => $section->id]);
            $this->fail('対象資格の有効な受講登録がない場合は例外が発生するはず');
        } catch (AuthorizationException) {
            // 期待した例外
        }

        // Assert
        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }

    private function createSection(Certification $certification): Section
    {
        $part = Part::factory()->for($certification)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();

        return Section::factory()->for($chapter)->published()->create();
    }
}
