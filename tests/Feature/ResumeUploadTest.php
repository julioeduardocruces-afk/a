<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResumeUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $response = $this->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_upload_pdf(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('resumes', [
            'user_id' => $user->id,
            'original_mime' => 'application/pdf',
            'status' => 'draft',
        ]);
    }

    public function test_rejects_oversized_files(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('cv_file');
    }

    public function test_idor_protection_on_resume(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        // Owner uploads
        $this->actingAs($owner)->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]);

        $resume = $owner->resumes()->first();

        // Attacker tries to access owner's resume
        $response = $this->actingAs($attacker)
            ->get(route('resumes.target-role', $resume->id));

        $response->assertForbidden();
    }
}
