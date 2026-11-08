<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\Learning\LearningProgressService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_zero_summary_when_no_published_contents(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->draft()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->draft()->forPart($part)->create();
        Section::factory()->draft()->forChapter($chapter)->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_calculates_section_completion_summary(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $completedSections = Section::factory()->published()->forChapter($chapter)->count(2)->create();
        Section::factory()->published()->forChapter($chapter)->create();
        foreach ($completedSections as $section) {
            SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->completedNow()->create();
        }

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.6667, $summary->sectionCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.6667, $summary->overallCompletionRatio);

        $this->assertSame($summary->sectionCompletionRatio, $summary->overallCompletionRatio);
    }

    public function test_calculates_chapter_completion_summary(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $completedChapter = Chapter::factory()->published()->forPart($part)->create();
        $uncompletedChapters = Chapter::factory()->published()->forPart($part)->count(2)->create();
        $completedSection = Section::factory()->published()->forChapter($completedChapter)->create();
        foreach ($uncompletedChapters as $chapter) {
            Section::factory()->published()->forChapter($chapter)->create();
        }
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($completedSection)->completedNow()->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(0.3333, $summary->sectionCompletionRatio);

        $this->assertSame(3, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.3333, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.3333, $summary->overallCompletionRatio);
        $this->assertSame($summary->sectionCompletionRatio, $summary->overallCompletionRatio);
    }

    public function test_calculates_part_completion_summary(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $parts = Part::factory()->published()->forCertification($enrollment->certification)->count(6)->create();
        foreach ($parts as $index => $part) {
            $chapter = Chapter::factory()->published()->forPart($part)->create();
            $sections = Section::factory()->published()->forChapter($chapter)->count(2)->create();

            foreach ($sections as $sectionIndex => $section) {
                // 最初の5つのPartは全Sectionを完了。
                // 最後のPartは1つ目のSectionだけ完了。
                if ($index < 5 || $sectionIndex === 0) {
                    SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->completedNow()->create();
                }
            }
        }

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(12, $summary->sectionsTotal);
        $this->assertSame(11, $summary->sectionsCompleted);
        $this->assertSame(0.9167, $summary->sectionCompletionRatio);

        $this->assertSame(6, $summary->chaptersTotal);
        $this->assertSame(5, $summary->chaptersCompleted);
        $this->assertSame(0.8333, $summary->chapterCompletionRatio);

        $this->assertSame(6, $summary->partsTotal);
        $this->assertSame(5, $summary->partsCompleted);
        $this->assertSame(0.8333, $summary->partCompletionRatio);

        $this->assertSame(0.9167, $summary->overallCompletionRatio);
        $this->assertSame($summary->sectionCompletionRatio, $summary->overallCompletionRatio);
    }

    public function test_counts_only_published_sections(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        Section::factory()->published()->forChapter($chapter)->count(2)->create();
        Section::factory()->draft()->forChapter($chapter)->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(2, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_counts_only_published_chapters(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        Chapter::factory()->published()->forPart($part)->count(2)->create();
        Chapter::factory()->draft()->forPart($part)->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_counts_only_published_parts(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        Part::factory()->published()->forCertification($enrollment->certification)->count(2)->create();
        Part::factory()->draft()->forCertification($enrollment->certification)->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_does_not_count_empty_chapters_as_completed(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        Section::factory()->draft()->forChapter($chapter)->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_does_not_count_empty_parts_as_completed(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapters = Chapter::factory()->published()->forPart($part)->count(2)->create();
        foreach ($chapters as $chapter) {
            Section::factory()->draft()->forChapter($chapter)->create();
        }

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_rounds_ratios_to_four_decimal_places(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $completedSection = Section::factory()->published()->forChapter($chapter)->create();
        Section::factory()->published()->forChapter($chapter)->count(2)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($completedSection)->completedNow()->create();

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(0.3333, $summary->sectionCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.3333, $summary->overallCompletionRatio);

        $this->assertSame($summary->sectionCompletionRatio, $summary->overallCompletionRatio);
    }

    public function test_ignores_progress_of_other_enrollments(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($cert, 'certification')->create();
        $otherEnrollment = Enrollment::factory()->for($cert, 'certification')->create();
        $part = Part::factory()->published()->for($cert, 'certification')->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $sections = Section::factory()->published()->forChapter($chapter)->count(3)->create();
        foreach ($sections as $section) {
            SectionProgress::factory()->forEnrollment($otherEnrollment)->forSection($section)->completedNow()->create();
        }

        // Act
        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // Assert
        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);

        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);

        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);

        $this->assertSame(0.0, $summary->overallCompletionRatio);

        $this->assertSame($summary->sectionCompletionRatio, $summary->overallCompletionRatio);
    }

    public function test_returns_empty_array_when_enrollments_are_empty(): void
    {
        // Act
        $ratios = app(LearningProgressService::class)->summarizeSectionCompletionRatios(new EloquentCollection);

        // Assert
        $this->assertSame([], $ratios);
    }

    public function test_returns_correct_ratios_for_multiple_enrollments(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();

        $enrollmentA = Enrollment::factory()->for($cert, 'certification')->create();
        $enrollmentB = Enrollment::factory()->for($cert, 'certification')->create();
        $enrollmentC = Enrollment::factory()->for($cert, 'certification')->create();

        $part = Part::factory()->published()->for($cert, 'certification')->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $sections = Section::factory()->published()->forChapter($chapter)->count(3)->create();

        // Enrollment A：1 Section完了
        SectionProgress::factory()->forEnrollment($enrollmentA)->forSection($sections[0])->completedNow()->create();

        // Enrollment B：2 Sections完了
        foreach ($sections->take(2) as $section) {
            SectionProgress::factory()->forEnrollment($enrollmentB)->forSection($section)->completedNow()->create();
        }

        // Enrollment C：3 Sections完了
        foreach ($sections as $section) {
            SectionProgress::factory()->forEnrollment($enrollmentC)->forSection($section)->completedNow()->create();
        }

        $enrollments = new EloquentCollection([
            $enrollmentA,
            $enrollmentB,
            $enrollmentC,
        ]);

        // Act
        $ratios = app(LearningProgressService::class)->summarizeSectionCompletionRatios($enrollments);

        // Assert
        $this->assertSame(0.3333, $ratios[$enrollmentA->id]);
        $this->assertSame(0.6667, $ratios[$enrollmentB->id]);
        $this->assertSame(1.0, $ratios[$enrollmentC->id]);
    }

    public function test_returns_zero_for_enrollment_without_published_sections(): void
    {
        // Arrange
        $certificationWithContents = Certification::factory()->published()->create();
        $certificationWithoutContents = Certification::factory()->published()->create();

        $enrollmentWithContents = Enrollment::factory()->for($certificationWithContents, 'certification')->create();
        $enrollmentWithoutContents = Enrollment::factory()->for($certificationWithoutContents, 'certification')->create();

        $part = Part::factory()->published()->for($certificationWithContents, 'certification')->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $sections = Section::factory()->published()->forChapter($chapter)->count(2)->create();
        SectionProgress::factory()->forEnrollment($enrollmentWithContents)->forSection($sections[0])->create();

        $draftPart = Part::factory()->draft()->for($certificationWithoutContents, 'certification')->create();
        $draftChapter = Chapter::factory()->draft()->forPart($draftPart)->create();

        Section::factory()->draft()->forChapter($draftChapter)->create();

        $enrollments = new EloquentCollection([
            $enrollmentWithContents,
            $enrollmentWithoutContents,
        ]);

        // Act
        $ratios = app(LearningProgressService::class)->summarizeSectionCompletionRatios($enrollments);

        // Assert
        $this->assertSame(0.0, $ratios[$enrollmentWithoutContents->id]);
        $this->assertSame(0.5, $ratios[$enrollmentWithContents->id]);
    }

    public function test_uses_enrollment_ids_as_string_array_keys(): void
    {
        // Arrange
        $certification = Certification::factory()->published()->create();

        $enrollmentA = Enrollment::factory()->for($certification, 'certification')->create();
        $enrollmentB = Enrollment::factory()->for($certification, 'certification')->create();

        $part = Part::factory()->published()->for($certification, 'certification')->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $section = Section::factory()->published()->forChapter($chapter)->create();

        SectionProgress::factory()->forEnrollment($enrollmentA)->forSection($section)->completedNow()->create();

        $enrollments = new EloquentCollection([
            $enrollmentA,
            $enrollmentB,
        ]);

        // Act
        $ratios = app(LearningProgressService::class)->summarizeSectionCompletionRatios($enrollments);

        // Assert
        $this->assertSame(
            [$enrollmentA->id, $enrollmentB->id],
            array_keys($ratios),
        );

        foreach (array_keys($ratios) as $key) {
            $this->assertIsString($key);
        }

        $this->assertSame(1.0, $ratios[$enrollmentA->id]);
        $this->assertSame(0.0, $ratios[$enrollmentB->id]);
    }
}
