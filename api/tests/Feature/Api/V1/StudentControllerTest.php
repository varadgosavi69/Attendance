<?php

namespace Tests\Feature\Api\V1;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    private function makeStudent(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'roll_number'  => 'CSE' . random_int(10000, 99999),
            'student_name' => 'Test Student',
            'email'        => 'student_' . uniqid() . '@college.edu',
            'department'   => 'CSE',
            'semester'     => 3,
        ], $attributes));
    }

    // ── GET /students — happy path (paginated) ────────────────────────────────
    public function test_authenticated_user_can_list_students(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $this->makeStudent();
        $this->makeStudent();

        $response = $this->getJson('/api/v1/students', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['success', 'data', 'meta' => ['page', 'per_page', 'total']])
                 ->assertJsonPath('meta.total', 2);
    }

    public function test_listing_students_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/students');

        $response->assertStatus(401);
    }

    public function test_listing_students_filters_by_department(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $this->makeStudent(['department' => 'CSE']);
        $this->makeStudent(['department' => 'ME']);

        $response = $this->getJson('/api/v1/students?department=CSE', $headers);

        $response->assertStatus(200)
                 ->assertJsonPath('meta.total', 1);
    }

    // ── GET /students/{id} — happy + 404 ───────────────────────────────────────
    public function test_authenticated_user_can_view_a_student(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $student = $this->makeStudent();

        $response = $this->getJson("/api/v1/students/{$student->student_id}", $headers);

        $response->assertStatus(200)
                 ->assertJsonPath('data.student_id', $student->student_id);
    }

    public function test_viewing_nonexistent_student_returns_404(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->getJson('/api/v1/students/999999', $headers);

        $response->assertStatus(404);
    }

    // ── POST /students — admin happy path ─────────────────────────────────────
    public function test_admin_can_create_a_student(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->postJson('/api/v1/students', [
            'roll_number'  => 'CSE99999',
            'student_name' => 'New Student',
            'email'        => 'new.student@college.edu',
            'department'   => 'CSE',
            'semester'     => 2,
        ], $headers);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.roll_number', 'CSE99999');

        $this->assertDatabaseHas('students', ['roll_number' => 'CSE99999']);
    }

    // ── POST /students — auth failure (non-admin) ─────────────────────────────
    public function test_non_admin_cannot_create_a_student(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->postJson('/api/v1/students', [
            'roll_number'  => 'CSE88888',
            'student_name' => 'New Student',
            'email'        => 'another.student@college.edu',
            'department'   => 'CSE',
            'semester'     => 2,
        ], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /students — validation errors ────────────────────────────────────
    public function test_create_student_validates_required_fields(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->postJson('/api/v1/students', [], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_create_student_rejects_duplicate_email(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $existing = $this->makeStudent(['email' => 'duplicate@college.edu']);

        $response = $this->postJson('/api/v1/students', [
            'roll_number'  => 'CSE77777',
            'student_name' => 'Dup Student',
            'email'        => $existing->email,
            'department'   => 'CSE',
            'semester'     => 2,
        ], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ── PUT /students/{id} — admin happy + auth failure ───────────────────────
    public function test_admin_can_update_a_student(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $student = $this->makeStudent(['student_name' => 'Old Name']);

        $response = $this->putJson("/api/v1/students/{$student->student_id}", [
            'student_name' => 'New Name',
        ], $headers);

        $response->assertStatus(200)
                 ->assertJsonPath('data.student_name', 'New Name');

        $this->assertDatabaseHas('students', ['student_id' => $student->student_id, 'student_name' => 'New Name']);
    }

    public function test_non_admin_cannot_update_a_student(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $student = $this->makeStudent();

        $response = $this->putJson("/api/v1/students/{$student->student_id}", [
            'student_name' => 'Hacked Name',
        ], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── DELETE /students/{id} — admin happy + auth failure ────────────────────
    public function test_admin_can_delete_a_student(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $student = $this->makeStudent();

        $response = $this->deleteJson("/api/v1/students/{$student->student_id}", [], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('students', ['student_id' => $student->student_id]);
    }

    public function test_non_admin_cannot_delete_a_student(): void
    {
        [, $headers] = $this->userWithRole('teacher');
        $student = $this->makeStudent();

        $response = $this->deleteJson("/api/v1/students/{$student->student_id}", [], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /students/upload — bulk CSV upload ───────────────────────────────
    public function test_admin_can_bulk_upload_students_via_csv(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $csv = "roll_number,student_name,email,department,semester\n"
             . "CSE10001,Alpha One,alpha.one@college.edu,CSE,1\n"
             . "CSE10002,Beta Two,beta.two@college.edu,CSE,1\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->post('/api/v1/students/upload', [
            'student_csv' => $file,
        ], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.added', 2)
                 ->assertJsonPath('data.errors', 0);

        $this->assertDatabaseHas('students', ['roll_number' => 'CSE10001']);
        $this->assertDatabaseHas('students', ['roll_number' => 'CSE10002']);
    }

    public function test_upload_validates_file_is_required(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->post('/api/v1/students/upload', [], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
