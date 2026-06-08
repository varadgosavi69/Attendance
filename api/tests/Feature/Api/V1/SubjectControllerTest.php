<?php

namespace Tests\Feature\Api\V1;

use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\CreatesApiTestUsers;
use Tests\TestCase;

class SubjectControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesApiTestUsers;

    private function makeSubject(array $attributes = []): Subject
    {
        return Subject::create(array_merge([
            'subject_name' => 'Test Subject',
            'subject_code' => 'CS' . random_int(1000, 9999),
            'department'   => 'CSE',
            'semester'     => 3,
        ], $attributes));
    }

    // ── GET /subjects — happy path (paginated) ────────────────────────────────
    public function test_authenticated_user_can_list_subjects(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $this->makeSubject();
        $this->makeSubject();

        $response = $this->getJson('/api/v1/subjects', $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonStructure(['success', 'data', 'meta' => ['page', 'per_page', 'total']])
                 ->assertJsonPath('meta.total', 2);
    }

    public function test_listing_subjects_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/subjects');

        $response->assertStatus(401);
    }

    public function test_listing_subjects_filters_by_department_and_semester(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $this->makeSubject(['department' => 'CSE', 'semester' => 3]);
        $this->makeSubject(['department' => 'ME', 'semester' => 5]);

        $response = $this->getJson('/api/v1/subjects?department=CSE&semester=3', $headers);

        $response->assertStatus(200)
                 ->assertJsonPath('meta.total', 1);
    }

    // ── GET /subjects/{id} — happy + 404 ───────────────────────────────────────
    public function test_authenticated_user_can_view_a_subject(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $subject = $this->makeSubject();

        $response = $this->getJson("/api/v1/subjects/{$subject->subject_id}", $headers);

        $response->assertStatus(200)
                 ->assertJsonPath('data.subject_id', $subject->subject_id);
    }

    public function test_viewing_nonexistent_subject_returns_404(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->getJson('/api/v1/subjects/999999', $headers);

        $response->assertStatus(404);
    }

    // ── POST /subjects — admin happy path ─────────────────────────────────────
    public function test_admin_can_create_a_subject(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->postJson('/api/v1/subjects', [
            'subject_name' => 'Operating Systems',
            'subject_code' => 'CS401',
            'department'   => 'CSE',
            'semester'     => 4,
        ], $headers);

        $response->assertStatus(201)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.subject_code', 'CS401');

        $this->assertDatabaseHas('subjects', ['subject_code' => 'CS401']);
    }

    // ── POST /subjects — auth failure (non-admin) ─────────────────────────────
    public function test_non_admin_cannot_create_a_subject(): void
    {
        [, $headers] = $this->userWithRole('teacher');

        $response = $this->postJson('/api/v1/subjects', [
            'subject_name' => 'Operating Systems',
            'subject_code' => 'CS402',
            'department'   => 'CSE',
            'semester'     => 4,
        ], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /subjects — validation errors ────────────────────────────────────
    public function test_create_subject_validates_required_fields(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->postJson('/api/v1/subjects', [], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_create_subject_rejects_duplicate_code(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $existing = $this->makeSubject(['subject_code' => 'CS500']);

        $response = $this->postJson('/api/v1/subjects', [
            'subject_name' => 'Duplicate Subject',
            'subject_code' => $existing->subject_code,
            'department'   => 'CSE',
            'semester'     => 5,
        ], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ── PUT /subjects/{id} — admin happy + auth failure ───────────────────────
    public function test_admin_can_update_a_subject(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $subject = $this->makeSubject(['subject_name' => 'Old Name']);

        $response = $this->putJson("/api/v1/subjects/{$subject->subject_id}", [
            'subject_name' => 'New Name',
        ], $headers);

        $response->assertStatus(200)
                 ->assertJsonPath('data.subject_name', 'New Name');

        $this->assertDatabaseHas('subjects', ['subject_id' => $subject->subject_id, 'subject_name' => 'New Name']);
    }

    public function test_non_admin_cannot_update_a_subject(): void
    {
        [, $headers] = $this->userWithRole('hod', ['department' => 'CSE']);
        $subject = $this->makeSubject();

        $response = $this->putJson("/api/v1/subjects/{$subject->subject_id}", [
            'subject_name' => 'Hacked Name',
        ], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── DELETE /subjects/{id} — admin happy + auth failure ────────────────────
    public function test_admin_can_delete_a_subject(): void
    {
        [, $headers] = $this->userWithRole('admin');
        $subject = $this->makeSubject();

        $response = $this->deleteJson("/api/v1/subjects/{$subject->subject_id}", [], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('subjects', ['subject_id' => $subject->subject_id]);
    }

    public function test_non_admin_cannot_delete_a_subject(): void
    {
        [, $headers] = $this->userWithRole('teacher');
        $subject = $this->makeSubject();

        $response = $this->deleteJson("/api/v1/subjects/{$subject->subject_id}", [], $headers);

        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    // ── POST /subjects/upload — bulk CSV upload ───────────────────────────────
    public function test_admin_can_bulk_upload_subjects_via_csv(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $csv = "subject_name,subject_code,department,semester\n"
             . "Algorithms,CS601,CSE,6\n"
             . "Compiler Design,CS602,CSE,6\n";

        $file = UploadedFile::fake()->createWithContent('subjects.csv', $csv);

        $response = $this->post('/api/v1/subjects/upload', [
            'subject_csv' => $file,
        ], $headers);

        $response->assertStatus(200)
                 ->assertJson(['success' => true])
                 ->assertJsonPath('data.added', 2)
                 ->assertJsonPath('data.errors', 0);

        $this->assertDatabaseHas('subjects', ['subject_code' => 'CS601']);
        $this->assertDatabaseHas('subjects', ['subject_code' => 'CS602']);
    }

    public function test_upload_validates_file_is_required(): void
    {
        [, $headers] = $this->userWithRole('admin');

        $response = $this->post('/api/v1/subjects/upload', [], $headers);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
