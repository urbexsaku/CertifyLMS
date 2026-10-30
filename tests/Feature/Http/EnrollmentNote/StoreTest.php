<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_can_create_note_for_assigned_enrollment(): void
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

        // Act
        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), ['body' => 'テストメモ本文']);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを追加しました。');
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $coach->id,
            'body' => 'テストメモ本文',
        ]);
    }

    public function test_admin_can_create_note_for_any_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), ['body' => 'テストメモ本文']);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを追加しました。');
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $admin->id,
            'body' => 'テストメモ本文',
        ]);
    }

    public function test_coach_cannot_create_note_for_unassigned_enrollment(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), ['body' => 'テストメモ本文']);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $coach->id,
            'body' => 'テストメモ本文',
        ]);
    }

    public function test_student_cannot_create_note(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.notes.store', $enrollment), ['body' => 'テストメモ本文']);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $student->id,
            'body' => 'テストメモ本文',
        ]);
    }

    public function test_guest_cannot_create_note(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();

        // Act
        $response = $this->post(route('enrollments.notes.store', $enrollment), ['body' => 'テストメモ本文']);

        // Assert
        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $student->id,
            'body' => 'テストメモ本文',
        ]);
    }

    public function test_body_is_required(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($admin)
            ->from(route('enrollments.show', $enrollment))
            ->post(route('enrollments.notes.store', $enrollment), ['body' => '']);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHasErrors('body');

        $showResponse = $this->get(route('enrollments.show', $enrollment));
        $showResponse->assertOk();
        $showResponse->assertSee('メモ本文 を入力してください。');

        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $admin->id,
        ]);
    }

    public function test_body_max_length(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $invalidBody = str_repeat('a', 2001);
        $response = $this->actingAs($admin)
            ->from(route('enrollments.show', $enrollment))
            ->post(route('enrollments.notes.store', $enrollment), ['body' => $invalidBody]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHasErrors('body');
        $response->assertSessionHas('_old_input.body', $invalidBody);

        $showResponse = $this->get(route('enrollments.show', $enrollment));
        $showResponse->assertOk();
        $showResponse->assertSee($invalidBody);

        $showResponse->assertSee('メモ本文 は 2000 文字以内で入力してください。');
        $this->assertDatabaseMissing('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $admin->id,
        ]);
    }
}
