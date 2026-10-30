<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_relation_returns_note_author(): void
    {
        // Arrange
        $author = User::factory()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($author)->create();

        // Act
        $parent = $note->author;

        // Assert
        $this->assertTrue($parent->is($author));
    }

    public function test_enrollment_relation_returns_parent_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->create();

        // Act
        $parent = $note->enrollment;

        // Assert
        $this->assertTrue($parent->is($enrollment));
    }
}
