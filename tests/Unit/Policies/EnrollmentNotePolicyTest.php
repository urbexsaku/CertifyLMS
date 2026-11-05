<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_full_access(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($admin, $enrollment));
        $this->assertTrue($policy->create($admin, $enrollment));
        $this->assertTrue($policy->update($admin, $note));
        $this->assertTrue($policy->delete($admin, $note));
    }

    public function test_coach_view_any_by_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($coach, $enrollment));
        $this->assertFalse($policy->viewAny($otherCoach, $enrollment));
    }

    public function test_coach_create_by_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->create($coach, $enrollment));
        $this->assertFalse($policy->create($otherCoach, $enrollment));
    }

    public function test_student_cannot_access_notes(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($student)->create();

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->viewAny($student, $enrollment));
        $this->assertFalse($policy->create($student, $enrollment));
        $this->assertFalse($policy->update($student, $note));
        $this->assertFalse($policy->delete($student, $note));
    }

    public function test_coach_update_by_assignment_and_author(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $otherAssignedCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $certification->coaches()->attach($otherAssignedCoach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->update($coach, $note));
        $this->assertFalse($policy->update($otherAssignedCoach, $note));
        $this->assertFalse($policy->update($otherCoach, $note));
    }

    public function test_coach_delete_by_assignment_and_author(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $otherAssignedCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $certification->coaches()->attach($otherAssignedCoach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->forAuthor($coach)->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->delete($coach, $note));
        $this->assertFalse($policy->delete($otherAssignedCoach, $note));
        $this->assertFalse($policy->delete($otherCoach, $note));
    }

    public function test_deleted_enrollment_denies_view_any_and_create(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $enrollment->delete();

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->viewAny($admin, $enrollment));
        $this->assertFalse($policy->create($admin, $enrollment));
    }

    public function test_deleted_enrollment_denies_update_and_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->create();
        $enrollment->delete();

        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->update($admin, $note));
        $this->assertFalse($policy->delete($admin, $note));
    }
}
