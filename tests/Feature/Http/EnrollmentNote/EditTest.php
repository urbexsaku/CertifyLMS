<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_view_own_note_edit_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create(['body' => 'メモ本文']);

        // Act
        $response = $this->actingAs($coach)->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-note.edit');
        $response->assertSeeText('メモ本文');
    }

    public function test_admin_can_view_edit_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create(['body' => 'コーチメモ本文']);

        // Act
        $response = $this->actingAs($admin)->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-note.edit');
        $response->assertSeeText('コーチメモ本文');
    }

    public function test_coach_cannot_view_other_coach_note_edit_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($otherCoach)->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_unassigned_coach_cannot_view_note_edit_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($coach)->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_student_cannot_view_note_edit_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($student)->get(route('enrollment-notes.edit', $note));

        // Assert
        $response->assertForbidden();
    }
}
