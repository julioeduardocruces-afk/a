<?php

namespace Tests\Feature;

use App\Models\DownloadToken;
use App\Models\Resume;
use App\Models\User;
use App\Enums\ResumeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_allows_download(): void
    {
        Storage::fake();

        $user = User::factory()->create();
        $resume = Resume::create([
            'user_id' => $user->id,
            'original_filename' => 'test.pdf',
            'original_mime' => 'application/pdf',
            'original_path' => 'uploads/test.pdf',
            'status' => ResumeStatus::Delivered,
        ]);

        $pdfPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.pdf";
        Storage::put($pdfPath, 'fake pdf content');

        $token = DownloadToken::generate($resume->id, $user->id, 15);

        $response = $this->get(route('download.token', ['token' => $token->token]));

        $response->assertOk();
        $this->assertTrue($token->fresh()->used);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $resume = Resume::create([
            'user_id' => $user->id,
            'original_filename' => 'test.pdf',
            'original_mime' => 'application/pdf',
            'original_path' => 'uploads/test.pdf',
            'status' => ResumeStatus::Delivered,
        ]);

        $token = DownloadToken::create([
            'resume_id' => $resume->id,
            'user_id' => $user->id,
            'token' => 'expired-token-123',
            'expires_at' => now()->subHour(),
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->get(route('download.token', ['token' => $token->token]));

        $response->assertForbidden();
    }

    public function test_used_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $resume = Resume::create([
            'user_id' => $user->id,
            'original_filename' => 'test.pdf',
            'original_mime' => 'application/pdf',
            'original_path' => 'uploads/test.pdf',
            'status' => ResumeStatus::Delivered,
        ]);

        $token = DownloadToken::create([
            'resume_id' => $resume->id,
            'user_id' => $user->id,
            'token' => 'used-token-456',
            'used' => true,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
        ]);

        $response = $this->get(route('download.token', ['token' => $token->token]));

        $response->assertForbidden();
    }

    public function test_nonexistent_token_returns_404(): void
    {
        $response = $this->get(route('download.token', ['token' => 'does-not-exist']));

        $response->assertNotFound();
    }
}
