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

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_delete_own_note(): void
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
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($coach)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを削除しました。');
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_admin_can_delete_note(): void
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
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを削除しました。');
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_coach_cannot_delete_other_coach_note(): void
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
        $response = $this->actingAs($otherCoach)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_unassigned_coach_cannot_delete_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($coach)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_student_cannot_delete_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();
    }

    public function test_coach_cannot_delete_note_for_deleted_enrollment(): void
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
        $enrollment->delete();

        // Act
        $response = $this->actingAs($coach)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();
    }
}
