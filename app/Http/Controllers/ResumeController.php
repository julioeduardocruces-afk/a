<?php

namespace App\Http\Controllers;

use App\Enums\ResumeStatus;
use App\Jobs\ProcessResumeJob;
use App\Jobs\SendMetaCapiEvent;
use App\Models\AuditLog;
use App\Models\MetricsDaily;
use App\Models\Resume;
use App\Services\MetaConversionsService;
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
    public function showUpload(Request $request)
    {
        // Meta CAPI: ViewContent (async) — once per session
        $capiKey = 'meta_capi_vc_upload';
        if (!$request->session()->has($capiKey)) {
            $eventId = 'vc_upload_' . substr($request->session()->getId(), 0, 16);
            SendMetaCapiEvent::dispatch(
                'ViewContent',
                $eventId,
                MetaConversionsService::buildUserData($request),
                ['content_name' => 'CV Upload Page', 'content_category' => 'ATS Optimization'],
                route('upload.form'),
            );
            $request->session()->put($capiKey, $eventId);
        }

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

        // Ensure the upload directory exists
        $uploadDir = storage_path('app/uploads/anonymous');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $path = $file->storeAs('uploads/anonymous', $safeName);

        if (!$path) {
            return back()->withErrors(['cv_file' => 'Error al almacenar el archivo. Verifica permisos de storage.']);
        }

        // Verify the file actually landed on disk
        if (!Storage::exists($path)) {
            \Illuminate\Support\Facades\Log::error('Upload verification failed: file not on disk', [
                'storage_path' => $path,
                'full_path' => storage_path('app/' . $path),
                'storage_base' => storage_path('app'),
                'disk_root' => config('filesystems.disks.local.root'),
                'upload_dir_exists' => is_dir($uploadDir),
                'upload_dir_writable' => is_writable($uploadDir),
            ]);
            return back()->withErrors(['cv_file' => 'El archivo se subio pero no se guardo correctamente. Verifica permisos en storage/app/uploads/anonymous/']);
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

        // Meta CAPI: Lead event (async — client-side fires on redirect with matching eventID)
        $leadEventId = 'lead_upload_' . $resume->id;
        SendMetaCapiEvent::dispatch(
            'Lead',
            $leadEventId,
            MetaConversionsService::buildUserData($request),
            ['content_name' => 'CV Upload', 'content_category' => 'ATS Optimization'],
            route('resumes.target-role', $resume->id),
        );

        // Flash lead event data so the client-side pixel fires with matching eventID
        $request->session()->flash('fb_lead_event', [
            'event_id' => $leadEventId,
            'content_name' => 'CV Upload',
        ]);

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

        // Extract text now (free operation) so we catch invalid files before payment
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
                    'error' => $e->getMessage(),
                ]);

                $userMsg = 'Error al extraer el texto del CV.';
                if (str_contains($e->getMessage(), 'escaneado') || str_contains($e->getMessage(), 'imagen')) {
                    $userMsg = 'El PDF parece ser una imagen escaneada. Sube un PDF con texto seleccionable.';
                } elseif (str_contains($e->getMessage(), 'no encontrado')) {
                    $userMsg = 'Archivo no encontrado en el servidor. Intenta subir el CV nuevamente.';
                }

                return back()->withErrors(['extraction' => $userMsg]);
            }
        }

        AuditLog::record('resume.target_set', null, 'anonymous', [
            'resume_id' => $resume->id,
            'target_industry' => $industry,
            'target_role' => $role,
        ], $request->ip());

        return redirect()->route('resumes.payment', $resume->id);
    }

    /**
     * GET /resumes/{id}/payment - Show payment page.
     */
    public function showPayment(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        if (!$resume->target_role || !$resume->target_industry) {
            return redirect()->route('resumes.target-role', $resume->id);
        }

        // If already paid or beyond, redirect to status
        if (in_array($resume->status, [ResumeStatus::Paid, ResumeStatus::Processing, ResumeStatus::Delivered])) {
            return redirect()->route('resumes.status', $resume->id);
        }

        // Store user browsing context for later use in Purchase CAPI (webhook context)
        if (empty($resume->meta_user_context)) {
            $resume->update([
                'meta_user_context' => MetaConversionsService::buildUserData($request, $resume->customer_email),
            ]);
        }

        // Meta CAPI: InitiateCheckout (async) — only fire once per session
        $capiKey = 'meta_capi_checkout_' . $resume->id;
        if (!$request->session()->has($capiKey)) {
            SendMetaCapiEvent::dispatch(
                'InitiateCheckout',
                'checkout_' . $resume->id,
                MetaConversionsService::buildUserData($request, $resume->customer_email),
                [
                    'value' => (int) config('ats.price_clp', 4990),
                    'currency' => 'CLP',
                    'content_name' => 'CV ATS Optimization',
                    'content_type' => 'product',
                    'content_ids' => [(string) $resume->id],
                    'num_items' => 1,
                ],
                route('resumes.payment', $resume->id),
            );
            $request->session()->put($capiKey, true);
        }

        return view('app.payment', compact('resume'));
    }

    /**
     * POST /resumes/{id}/process - Trigger CV processing (requires paid payment).
     */
    public function process(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        if (!$resume->target_role || !$resume->target_industry) {
            return back()->withErrors(['target_role' => 'Debes seleccionar rubro y cargo objetivo.']);
        }

        // Only allow processing from Paid or Failed (with paid payment) status
        $hasPaidPayment = \App\Models\Payment::where('resume_id', $resume->id)
            ->where('status', \App\Enums\PaymentStatus::Paid)
            ->exists();

        if (!$hasPaidPayment) {
            return redirect()->route('resumes.payment', $resume->id)
                ->withErrors(['payment' => 'Debes pagar antes de procesar el CV.']);
        }

        if (!in_array($resume->status, [ResumeStatus::Paid, ResumeStatus::Failed])) {
            return back()->withErrors(['status' => 'El CV no puede ser procesado en su estado actual.']);
        }

        $resume->error_code = null;
        $resume->error_message = null;
        $resume->transitionTo(ResumeStatus::Processing);

        try {
            ProcessResumeJob::dispatch($resume->id);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ProcessResumeJob failed (sync)', [
                'resume_id' => $resume->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $resume->refresh();
            if ($resume->status === ResumeStatus::Processing) {
                $resume->markFailed('processing_error', $e->getMessage());
            }

            return redirect()->route('resumes.status', $resume->id)
                ->withErrors(['processing' => 'Error al procesar el CV. Puedes reintentar.']);
        }

        return redirect()->route('resumes.status', $resume->id);
    }

    /**
     * GET /resumes/{id}/status - Show processing status (polling endpoint).
     */
    public function status(Request $request, int $id)
    {
        $resume = $request->attributes->get('resume');

        if ($request->wantsJson()) {
            // Cache JSON polling responses for 3 seconds to reduce DB load
            // during high-traffic status polling (JS clients poll every 2-5s)
            $cacheKey = "resume_status:{$resume->id}";
            $data = cache()->remember($cacheKey, 3, function () use ($resume) {
                $resume->load('latestVersion');
                $errorMsg = $resume->error_message
                    ? 'Ocurrió un error procesando tu CV. Puedes reintentar.'
                    : null;

                return [
                    'status' => $resume->status->value,
                    'score' => $resume->latestVersion?->score_json['overall'] ?? null,
                    'error' => $errorMsg,
                ];
            });

            return response()->json($data);
        }

        $resume->load('latestVersion');
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
     * Show CV builder form (alternative to file upload).
     */
    public function showCvBuilder(Request $request)
    {
        // Meta CAPI: ViewContent (async) — once per session
        $capiKey = 'meta_capi_vc_builder';
        if (!$request->session()->has($capiKey)) {
            $eventId = 'vc_builder_' . substr($request->session()->getId(), 0, 16);
            SendMetaCapiEvent::dispatch(
                'ViewContent',
                $eventId,
                MetaConversionsService::buildUserData($request),
                ['content_name' => 'CV Builder Page', 'content_category' => 'ATS Optimization'],
                route('cv-builder.form'),
            );
            $request->session()->put($capiKey, $eventId);
        }

        return view('app.cv-builder');
    }

    /**
     * POST /cv-builder - Process CV builder form and create resume.
     */
    public function storeCvBuilder(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'rut' => ['required', 'string', 'max:12'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'experiences' => ['required', 'array', 'min:1'],
            'experiences.*.company' => ['required', 'string', 'max:255'],
            'experiences.*.position' => ['required', 'string', 'max:255'],
            'experiences.*.period' => ['nullable', 'string', 'max:100'],
            'experiences.*.description' => ['nullable', 'string', 'max:3000'],
            'education' => ['required', 'array', 'min:1'],
            'education.*.institution' => ['required', 'string', 'max:255'],
            'education.*.degree' => ['required', 'string', 'max:255'],
            'education.*.period' => ['nullable', 'string', 'max:100'],
            'skills' => ['required', 'string', 'max:2000'],
            'certifications' => ['nullable', 'string', 'max:2000'],
            'languages' => ['nullable', 'string', 'max:500'],
        ]);

        // Sanitize all text fields
        $name = $this->sanitizeUserText($validated['full_name']);
        $rut = $this->sanitizeUserText($validated['rut']);
        $email = $validated['email'];
        $phone = $this->sanitizeUserText($validated['phone'] ?? '');
        $address = $this->sanitizeUserText($validated['address']);
        $location = $this->sanitizeUserText($validated['location'] ?? '');
        $linkedin = $this->sanitizeUserText($validated['linkedin'] ?? '');
        $summary = $this->sanitizeUserText($validated['summary'] ?? '');
        $skillsRaw = $this->sanitizeUserText($validated['skills']);
        $certsRaw = $this->sanitizeUserText($validated['certifications'] ?? '', true);
        $langsRaw = $this->sanitizeUserText($validated['languages'] ?? '');

        // Build structured_json (same format as TextExtractorService::structureText)
        $experienceEntries = [];
        $experienceText = [];
        foreach ($validated['experiences'] as $exp) {
            $company = $this->sanitizeUserText($exp['company']);
            $position = $this->sanitizeUserText($exp['position']);
            $period = $this->sanitizeUserText($exp['period'] ?? '');
            $desc = $this->sanitizeUserText($exp['description'] ?? '', true);

            $entry = $position . ' - ' . $company;
            if ($period) $entry .= ' (' . $period . ')';
            $experienceEntries[] = $entry;

            $block = $company . ' - ' . $position;
            if ($period) $block .= "\n" . $period;
            if ($desc) $block .= "\n" . $desc;
            $experienceText[] = $block;
        }

        $educationEntries = [];
        $educationText = [];
        foreach ($validated['education'] as $edu) {
            $institution = $this->sanitizeUserText($edu['institution']);
            $degree = $this->sanitizeUserText($edu['degree']);
            $period = $this->sanitizeUserText($edu['period'] ?? '');

            $entry = $degree . ' - ' . $institution;
            if ($period) $entry .= ' (' . $period . ')';
            $educationEntries[] = $entry;

            $block = $institution . ' - ' . $degree;
            if ($period) $block .= "\n" . $period;
            $educationText[] = $block;
        }

        $skills = array_map('trim', explode(',', $skillsRaw));
        $skills = array_filter($skills);

        $certs = [];
        if ($certsRaw) {
            $certs = array_filter(array_map('trim', preg_split('/[\n,]+/', $certsRaw)));
        }

        $langs = [];
        if ($langsRaw) {
            $langs = array_filter(array_map('trim', preg_split('/[\n,]+/', $langsRaw)));
        }

        // Header text
        $headerParts = [$name];
        if ($rut) $headerParts[] = 'RUT: ' . $rut;
        if ($email) $headerParts[] = $email;
        if ($phone) $headerParts[] = $phone;
        if ($address) $headerParts[] = $address;
        if ($location) $headerParts[] = $location;
        if ($linkedin) $headerParts[] = $linkedin;

        $structuredJson = [
            'header' => implode("\n", $headerParts),
            'rut' => $rut,
            'address' => $address,
            'summary' => $summary,
            'experience' => $experienceEntries,
            'education' => $educationEntries,
            'skills' => array_values($skills),
            'certifications' => array_values($certs),
            'languages' => array_values($langs),
            'other' => '',
        ];

        // Build extracted_text (plain text CV, same as PDF extraction would produce)
        $textParts = [];
        $contactLine = $name;
        if ($rut) $contactLine .= ' | RUT: ' . $rut;
        if ($email) $contactLine .= ' | ' . $email;
        if ($phone) $contactLine .= ' | ' . $phone;
        $textParts[] = $contactLine;
        if ($address) $textParts[] = 'Direccion: ' . $address;
        if ($location) $textParts[] = $location;
        if ($linkedin) $textParts[] = $linkedin;
        $textParts[] = '';
        $textParts[] = 'PERFIL PROFESIONAL';
        $textParts[] = $summary ?: '[GENERAR AUTOMATICAMENTE BASADO EN EXPERIENCIA LABORAL]';
        $textParts[] = '';
        $textParts[] = 'EXPERIENCIA LABORAL';
        $textParts[] = implode("\n\n", $experienceText);
        $textParts[] = '';
        $textParts[] = 'EDUCACION';
        $textParts[] = implode("\n\n", $educationText);
        $textParts[] = '';
        $textParts[] = 'HABILIDADES';
        $textParts[] = implode(', ', $skills);
        if (!empty($certs)) {
            $textParts[] = '';
            $textParts[] = 'CERTIFICACIONES';
            $textParts[] = implode("\n", $certs);
        }
        if (!empty($langs)) {
            $textParts[] = '';
            $textParts[] = 'IDIOMAS';
            $textParts[] = implode(', ', $langs);
        }

        $extractedText = implode("\n", $textParts);

        // Split name for Meta CAPI Match Quality (fn/ln)
        $nameParts = preg_split('/\s+/', $name, 2);

        // Create Resume (no file upload, mark as form-built)
        $resume = Resume::create([
            'original_filename' => 'formulario_cv_' . Str::slug($name) . '.txt',
            'original_mime' => 'text/plain',
            'original_path' => '',
            'extracted_text' => $extractedText,
            'structured_json' => $structuredJson,
            'customer_email' => $email,
            'meta_user_context' => MetaConversionsService::buildUserData($request, $email, array_filter([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'phone' => $phone,
                'city' => $location,
                'country' => 'cl',
            ])),
            'status' => ResumeStatus::Draft,
        ]);

        // Store access_token in session
        $tokens = $request->session()->get('resume_tokens', []);
        $tokens[] = $resume->access_token;
        if (count($tokens) > 50) {
            $tokens = array_slice($tokens, -50);
        }
        $request->session()->put('resume_tokens', $tokens);

        MetricsDaily::incrementToday('uploads');

        AuditLog::record('resume.created_from_form', null, 'anonymous', [
            'resume_id' => $resume->id,
            'name' => $name,
        ], $request->ip());

        // Meta CAPI: Lead event (async — client-side fires on redirect with matching eventID)
        // Pass additional PII from the CV Builder form for improved Match Quality
        $leadEventId = 'lead_builder_' . $resume->id;
        SendMetaCapiEvent::dispatch(
            'Lead',
            $leadEventId,
            MetaConversionsService::buildUserData($request, $email, array_filter([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
                'phone' => $phone,
                'city' => $location,
                'country' => 'cl',
            ])),
            ['content_name' => 'CV Builder', 'content_category' => 'ATS Optimization'],
            route('resumes.target-role', $resume->id),
        );

        // Flash lead event data so the client-side pixel fires with matching eventID
        $request->session()->flash('fb_lead_event', [
            'event_id' => $leadEventId,
            'content_name' => 'CV Builder',
        ]);

        return redirect()->route('resumes.target-role', $resume->id);
    }

    /**
     * Sanitize free-text input: strip tags, control chars, collapse whitespace.
     * Returns only safe alphanumeric + basic punctuation text.
     */
    private function sanitizeUserText(string $input, bool $preserveNewlines = false): string
    {
        // Strip HTML/PHP tags
        $clean = strip_tags($input);
        // Remove control characters (null bytes, tabs, etc.) except spaces and newlines
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        // Remove any characters that could be used for injection attacks
        // Allow: letters (unicode), numbers, spaces, common punctuation (.,;:-/()')
        $clean = preg_replace('/[^\p{L}\p{N}\s\.\,\;\:\-\/\(\)\'\"\&\@\+\%\#]/u', '', $clean);

        if ($preserveNewlines) {
            // Collapse spaces within lines but keep newlines
            $lines = explode("\n", $clean);
            $lines = array_map(fn($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), $lines);
            $clean = implode("\n", array_filter($lines, fn($l) => $l !== ''));
        } else {
            // Collapse all whitespace to single space
            $clean = preg_replace('/\s+/', ' ', $clean);
        }

        return trim($clean);
    }
}
