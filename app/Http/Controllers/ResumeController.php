<?php

namespace App\Http\Controllers;

use App\Enums\ResumeStatus;
use App\Jobs\ProcessResumeJob;
use App\Models\AuditLog;
use App\Models\MetricsDaily;
use App\Models\Resume;
use App\Services\ResumeRendererService;
use App\Services\TextExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumeController extends Controller
{
    public function __construct(
        private readonly TextExtractorService $extractor,
        private readonly ResumeRendererService $renderer,
    ) {}

    /**
     * Show the user dashboard with their resumes.
     */
    public function dashboard(Request $request)
    {
        $resumes = $request->user()
            ->resumes()
            ->with('latestVersion')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('app.dashboard', compact('resumes'));
    }

    /**
     * Show upload form.
     */
    public function showUpload()
    {
        return view('app.upload');
    }

    /**
     * POST /upload - Handle CV file upload.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'cv_file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        $file = $request->file('cv_file');

        // Validate file type and security
        $this->extractor->validateFile($file);

        // Generate random filename (prevent path traversal)
        $ext = $file->getClientOriginalExtension();
        $safeName = Str::uuid() . '.' . $ext;
        $path = $file->storeAs('uploads/' . $request->user()->id, $safeName);

        $resume = Resume::create([
            'user_id' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'original_mime' => $file->getMimeType(),
            'original_path' => $path,
            'status' => ResumeStatus::Draft,
        ]);

        MetricsDaily::incrementToday('uploads');

        AuditLog::record('resume.uploaded', $request->user()->id, 'user', [
            'resume_id' => $resume->id,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ], $request->ip());

        return redirect()->route('resumes.target-role', $resume->id);
    }

    /**
     * Show target role/industry selection form.
     */
    public function showTargetRole(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        return view('app.target-role', compact('resume'));
    }

    /**
     * POST /resumes/{id}/target-role - Set target role and industry.
     */
    public function setTargetRole(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        $validated = $request->validate([
            'target_industry' => ['required', 'string', 'max:255'],
            'target_role' => ['required', 'string', 'max:255'],
        ]);

        $resume->update($validated);

        AuditLog::record('resume.target_set', $request->user()->id, 'user', [
            'resume_id' => $resume->id,
            'target_industry' => $validated['target_industry'],
            'target_role' => $validated['target_role'],
        ], $request->ip());

        return redirect()->route('resumes.process', $resume->id);
    }

    /**
     * POST /resumes/{id}/process - Trigger CV processing.
     */
    public function process(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        // Validate prerequisites
        if (!$resume->target_role || !$resume->target_industry) {
            return back()->withErrors(['target_role' => 'Debes seleccionar rubro y cargo objetivo.']);
        }

        if (!in_array($resume->status, [ResumeStatus::Draft, ResumeStatus::Failed])) {
            return back()->withErrors(['status' => 'El CV no puede ser procesado en su estado actual.']);
        }

        // Extract text first if needed
        if (empty($resume->extracted_text)) {
            try {
                $text = $this->extractor->extract($resume->original_path, $resume->original_mime);
                $structured = $this->extractor->structureText($text);
                $resume->update([
                    'extracted_text' => $text,
                    'structured_json' => $structured,
                ]);
            } catch (\Exception $e) {
                $resume->update([
                    'status' => ResumeStatus::Failed,
                    'error_code' => 'extraction_error',
                    'error_message' => $e->getMessage(),
                ]);
                return back()->withErrors(['extraction' => $e->getMessage()]);
            }
        }

        // Transition to processing
        $resume->transitionTo(ResumeStatus::Processing);

        // Dispatch async job
        ProcessResumeJob::dispatch($resume->id);

        return redirect()->route('resumes.status', $resume->id);
    }

    /**
     * GET /resumes/{id}/status - Show processing status (polling endpoint).
     */
    public function status(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');
        $resume->load('latestVersion');

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $resume->status->value,
                'score' => $resume->latestVersion?->score_json['overall'] ?? null,
                'error' => $resume->error_message,
            ]);
        }

        return view('app.status', compact('resume'));
    }

    /**
     * GET /resumes/{id}/preview - Render rasterized preview (no text).
     */
    public function preview(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        if (!in_array($resume->status, [ResumeStatus::PreviewReady, ResumeStatus::Paid, ResumeStatus::Delivered])) {
            abort(403, 'Preview no disponible.');
        }

        $version = $resume->latestVersion;
        if (!$version) {
            abort(404, 'Sin version generada.');
        }

        $page = max(1, (int)$request->query('page', 1));

        $imageData = $this->renderer->renderPreviewPage(
            $version->optimized_text_md,
            $request->user()->email,
            $resume->id,
            $page,
        );

        MetricsDaily::incrementToday('previews');

        return response($imageData, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * GET /resumes/{id}/preview-page - Show the preview page with score.
     */
    public function previewPage(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        if (!in_array($resume->status, [ResumeStatus::PreviewReady, ResumeStatus::Paid, ResumeStatus::Delivered])) {
            abort(403, 'Preview no disponible.');
        }

        $resume->load('latestVersion');
        $score = $resume->latestVersion?->score_json ?? [];
        $pageCount = 1;

        if ($resume->latestVersion) {
            $pageCount = $this->renderer->getPageCount($resume->latestVersion->optimized_text_md);
        }

        return view('app.preview', compact('resume', 'score', 'pageCount'));
    }
}
