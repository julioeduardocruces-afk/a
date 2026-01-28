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

        // Determine requested format FIRST, then check the corresponding file.
        // Previous logic always checked PDF existence even for DOCX requests,
        // which would 404 if PDF was missing but DOCX existed. It also silently
        // fell back to PDF when DOCX was missing, confusing the user.
        $format = request()->query('format', 'pdf');
        $basePath = "finals/{$resume->id}/cv_optimizado_{$resume->id}";

        if ($format === 'docx') {
            $filePath = "{$basePath}.docx";
            $fileName = "cv_optimizado_{$resume->id}.docx";
        } else {
            $filePath = "{$basePath}.pdf";
            $fileName = "cv_optimizado_{$resume->id}.pdf";
        }

        if (!Storage::exists($filePath)) {
            abort(404, 'Archivo no encontrado.');
        }

        AuditLog::record('resume.downloaded', $downloadToken->user_id, 'user', [
            'resume_id' => $resume->id,
            'token_id' => $downloadToken->id,
            'format' => $format,
        ]);

        return Storage::download($filePath, $fileName);
    }
}
