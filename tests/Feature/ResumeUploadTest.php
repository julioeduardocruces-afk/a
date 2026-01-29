<?php

namespace Tests\Feature;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResumeUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_user_can_upload(): void
    {
        $response = $this->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('resumes', [
            'original_mime' => 'application/pdf',
            'status' => 'draft',
        ]);

        // Verify access_token was generated
        $resume = Resume::first();
        $this->assertNotNull($resume->access_token);
        $this->assertEquals(64, strlen($resume->access_token));
        $this->assertNull($resume->user_id);
    }

    public function test_session_gets_access_token_after_upload(): void
    {
        $response = $this->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]);

        $resume = Resume::first();

        // Session should contain the access token
        $response->assertSessionHas('resume_tokens');
        $tokens = session('resume_tokens');
        $this->assertContains($resume->access_token, $tokens);
    }

    public function test_rejects_oversized_files(): void
    {
        $response = $this->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('cv_file');
    }

    public function test_session_ownership_protection(): void
    {
        // User A uploads a CV
        $responseA = $this->post(route('upload.store'), [
            'cv_file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]);

        $resume = Resume::first();

        // New session (user B) tries to access user A's resume
        $this->flushSession();
        $response = $this->get(route('resumes.target-role', $resume->id));

        $response->assertForbidden();
    }
}
