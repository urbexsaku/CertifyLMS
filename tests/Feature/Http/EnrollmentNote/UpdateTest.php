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

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_update_own_note(): void
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
        $response = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ本文']);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを更新しました。');
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新後のメモ本文',
        ]);
    }

    public function test_admin_can_update_note(): void
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
        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ本文']);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを更新しました。');
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'author_id' => $coach->id,
            'body' => '更新後のメモ本文',
        ]);
    }

    public function test_coach_cannot_update_other_coach_note(): void
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

        $certification->coaches()->attach($otherCoach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($otherCoach)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ本文']);

        // Assert
        $response->assertForbidden();
    }

    public function test_unassigned_coach_cannot_update_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ本文']);

        // Assert
        $response->assertForbidden();
    }

    public function test_student_cannot_update_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();

        // Act
        $response = $this->actingAs($student)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ本文']);

        // Assert
        $response->assertForbidden();
    }

    public function test_body_is_required_when_updating(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create(['body' => 'メモ本文']);

        // Act
        $response = $this->actingAs($coach)
            ->from(route('enrollment-notes.edit', $note))
            ->patch(route('enrollment-notes.update', $note), ['body' => '']);

        // Assert
        $response->assertRedirect(route('enrollment-notes.edit', $note));
        $response->assertSessionHasErrors('body');

        $editResponse = $this->get(route('enrollment-notes.edit', $note));
        $editResponse->assertOk();
        $editResponse->assertSee('メモ本文 を入力してください。');
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => 'メモ本文',
        ]);
    }

    public function test_body_max_length(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create(['body' => 'メモ本文']);

        // Act
        $invalidBody = str_repeat('a', 2001);
        $response = $this->actingAs($coach)
            ->from(route('enrollment-notes.edit', $note))
            ->patch(route('enrollment-notes.update', $note), ['body' => $invalidBody]);

        // Assert
        $response->assertRedirect(route('enrollment-notes.edit', $note));
        $response->assertSessionHasErrors('body');
        $response->assertSessionHas('_old_input.body', $invalidBody);

        $editResponse = $this->get(route('enrollment-notes.edit', $note));
        $editResponse->assertOk();
        $editResponse->assertSee('メモ本文 は 2000 文字以内で入力してください。');
        $editResponse->assertSee($invalidBody);

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => 'メモ本文',
        ]);
    }

    public function test_coach_cannot_update_note_for_deleted_enrollment(): void
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
        $response = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ本文']);

        // Assert
        $response->assertForbidden();
    }
}
