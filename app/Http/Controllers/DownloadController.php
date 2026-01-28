<?php

namespace App\Http\Controllers;

use App\Enums\ResumeStatus;
use App\Models\AuditLog;
use App\Models\DownloadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    /**
     * GET /download/{token} - Download final CV via signed single-use token.
     */
    public function download(string $token)
    {
        $downloadToken = DownloadToken::where('token', $token)->first();

        if (!$downloadToken) {
            abort(404, 'Token no encontrado.');
        }

        // Atomically check validity and mark as used to prevent race conditions
        // (two concurrent requests could both pass isValid() before markUsed())
        $claimed = DB::table('download_tokens')
            ->where('id', $downloadToken->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->update(['used' => true]);

        if ($claimed === 0) {
            abort(403, 'El enlace ha expirado o ya fue utilizado.');
        }

        $resume = $downloadToken->resume;

        if ($resume->status !== ResumeStatus::Delivered && $resume->status !== ResumeStatus::Paid) {
            abort(403, 'El CV aun no esta disponible para descarga.');
        }

        // Check PDF exists
        $pdfPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.pdf";
        if (!Storage::exists($pdfPath)) {
            abort(404, 'Archivo no encontrado.');
        }

        AuditLog::record('resume.downloaded', $downloadToken->user_id, 'user', [
            'resume_id' => $resume->id,
            'token_id' => $downloadToken->id,
        ]);

        $format = request()->query('format', 'pdf');
        if ($format === 'docx') {
            $docxPath = "finals/{$resume->id}/cv_optimizado_{$resume->id}.docx";
            if (Storage::exists($docxPath)) {
                return Storage::download($docxPath, "cv_optimizado_{$resume->id}.docx");
            }
        }

        return Storage::download($pdfPath, "cv_optimizado_{$resume->id}.pdf");
    }
}
