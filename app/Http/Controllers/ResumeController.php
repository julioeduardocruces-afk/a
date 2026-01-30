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
     * Show upload form (public, no login required).
     */
    public function showUpload()
    {
        return view('app.upload');
    }

    /**
     * POST /upload - Handle CV file upload (anonymous).
     */
    public function upload(Request $request)
    {
        $request->validate([
            'cv_file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        $file = $request->file('cv_file');

        // Validate file type and security
        try {
            $this->extractor->validateFile($file);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['cv_file' => $e->getMessage()]);
        }

        // Generate random filename (prevent path traversal)
        $ext = $file->getClientOriginalExtension();
        $safeName = Str::uuid() . '.' . $ext;
        $path = $file->storeAs('uploads/anonymous', $safeName);

        if (!$path) {
            return back()->withErrors(['cv_file' => 'Error al almacenar el archivo. Verifica permisos de storage.']);
        }

        $resume = Resume::create([
            'original_filename' => $file->getClientOriginalName(),
            'original_mime' => $file->getMimeType(),
            'original_path' => $path,
            'status' => ResumeStatus::Draft,
        ]);

        // Store access_token in session for ownership verification
        // Cap at 50 tokens to prevent session bloat from repeated uploads
        $tokens = $request->session()->get('resume_tokens', []);
        $tokens[] = $resume->access_token;
        if (count($tokens) > 50) {
            $tokens = array_slice($tokens, -50);
        }
        $request->session()->put('resume_tokens', $tokens);

        MetricsDaily::incrementToday('uploads');

        AuditLog::record('resume.uploaded', null, 'anonymous', [
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

        if (!in_array($resume->status, [ResumeStatus::Draft, ResumeStatus::Failed])) {
            return redirect()->route('resumes.status', $resume->id)
                ->with('info', 'El CV ya fue procesado. No se puede cambiar el rubro.');
        }

        return view('app.target-role', compact('resume'));
    }

    /**
     * Whitelist of allowed industries from the select dropdown.
     */
    private const ALLOWED_INDUSTRIES = [
        'Tecnologias de la Informacion',
        'Salud / Hemodialisis',
        'Ventas y Comercial',
        'Finanzas / Factoring',
        'Ingenieria',
        'Educacion',
        'Logistica y Transporte',
        'Marketing Digital',
        'Recursos Humanos',
        'Administracion',
        'Construccion',
        'Legal',
        'Mineria',
        'Otro',
    ];

    /**
     * POST /resumes/{id}/target-role - Set target role and industry.
     */
    public function setTargetRole(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        if (!in_array($resume->status, [ResumeStatus::Draft, ResumeStatus::Failed])) {
            return back()->withErrors(['status' => 'No se puede cambiar el rubro/cargo en el estado actual. El CV ya fue procesado.']);
        }

        $rules = [
            'target_industry' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_INDUSTRIES)],
            'target_role' => ['required', 'string', 'max:255'],
        ];

        // If "Otro" is selected, require and validate custom_industry
        if ($request->input('target_industry') === 'Otro') {
            $rules['custom_industry'] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        // Resolve final industry: use custom text if "Otro"
        $industry = $validated['target_industry'];
        if ($industry === 'Otro') {
            $industry = $this->sanitizeUserText($validated['custom_industry']);
            if (empty($industry)) {
                return back()->withErrors(['custom_industry' => 'Debes especificar un rubro valido.'])->withInput();
            }
        }

        // Sanitize target_role too (free-text field)
        $role = $this->sanitizeUserText($validated['target_role']);
        if (empty($role)) {
            return back()->withErrors(['target_role' => 'Debes especificar un cargo valido.'])->withInput();
        }

        $resume->update([
            'target_industry' => $industry,
            'target_role' => $role,
        ]);

        AuditLog::record('resume.target_set', null, 'anonymous', [
            'resume_id' => $resume->id,
            'target_industry' => $industry,
            'target_role' => $role,
        ], $request->ip());

        return $this->process($request, $id);
    }

    /**
     * POST /resumes/{id}/process - Trigger CV processing.
     */
    public function process(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

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
                \Illuminate\Support\Facades\Log::error('CV text extraction failed', [
                    'resume_id' => $resume->id,
                    'path' => $resume->original_path,
                    'mime' => $resume->original_mime,
                    'error' => $e->getMessage(),
                    'file_exists' => file_exists(storage_path('app/' . $resume->original_path)),
                ]);

                $resume->markFailed('extraction_error', $e->getMessage());

                $userMsg = 'Error al extraer el texto del CV.';
                if (str_contains($e->getMessage(), 'escaneado') || str_contains($e->getMessage(), 'imagen')) {
                    $userMsg = 'El PDF parece ser una imagen escaneada. Sube un PDF con texto seleccionable.';
                } elseif (str_contains($e->getMessage(), 'no encontrado')) {
                    $userMsg = 'Archivo no encontrado en el servidor. Intenta subir el CV nuevamente.';
                } elseif (str_contains($e->getMessage(), 'Path traversal')) {
                    $userMsg = 'Error de seguridad en la ruta del archivo.';
                }

                return back()->withErrors(['extraction' => $userMsg]);
            }
        }

        $resume->error_code = null;
        $resume->error_message = null;
        $resume->transitionTo(ResumeStatus::Processing);

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
            $errorMsg = $resume->error_message
                ? 'Ocurrió un error procesando tu CV. Puedes reintentar.'
                : null;

            return response()->json([
                'status' => $resume->status->value,
                'score' => $resume->latestVersion?->score_json['overall'] ?? null,
                'error' => $errorMsg,
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

        $resume->loadMissing('latestVersion');
        $version = $resume->latestVersion;
        if (!$version) {
            abort(404, 'Sin version generada.');
        }

        $page = max(1, (int)$request->query('page', 1));

        // Use resume email or session ID for watermark
        $watermarkId = $resume->getEmail() ?? 'session:' . substr($request->session()->getId(), 0, 8);

        $imageData = $this->renderer->renderPreviewPage(
            $version->optimized_text_md,
            $watermarkId,
            $resume->id,
            $page,
        );

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

        MetricsDaily::incrementToday('previews');

        $score = $resume->latestVersion?->score_json ?? [];
        $pageCount = 1;

        if ($resume->latestVersion) {
            $pageCount = $this->renderer->getPageCount($resume->latestVersion->optimized_text_md);
        }

        return view('app.preview', compact('resume', 'score', 'pageCount'));
    }

    /**
     * Sanitize free-text input: strip tags, control chars, collapse whitespace.
     * Returns only safe alphanumeric + basic punctuation text.
     */
    private function sanitizeUserText(string $input): string
    {
        // Strip HTML/PHP tags
        $clean = strip_tags($input);
        // Remove control characters (null bytes, tabs, etc.) except spaces and newlines
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        // Remove any characters that could be used for injection attacks
        // Allow: letters (unicode), numbers, spaces, common punctuation (.,;:-/()')
        $clean = preg_replace('/[^\p{L}\p{N}\s\.\,\;\:\-\/\(\)\'\"\&]/u', '', $clean);
        // Collapse multiple spaces
        $clean = preg_replace('/\s+/', ' ', $clean);

        return trim($clean);
    }
}
