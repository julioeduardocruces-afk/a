<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use RuntimeException;

class TextExtractorService
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * MIME-to-extension mapping for cross-validation.
     */
    private const MIME_EXT_MAP = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    /**
     * Magic bytes signatures for content-based type detection.
     */
    private const MAGIC_SIGNATURES = [
        'pdf'  => '%PDF',
        'docx' => "PK\x03\x04", // DOCX is a ZIP archive
    ];

    public function validateFile(UploadedFile $file): void
    {
        // 1. Size check first (cheapest)
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('El archivo excede el limite de 10 MB.');
        }

        // 2. Extension whitelist
        $ext = strtolower($file->getClientOriginalExtension());
        $allowedExts = ['pdf', 'docx'];
        if (!in_array($ext, $allowedExts, true)) {
            throw new RuntimeException(
                "Extension no permitida: .{$ext}. Solo .pdf y .docx."
            );
        }

        // 3. Block dangerous double extensions (e.g. file.php.pdf, file.phtml.docx)
        $originalName = $file->getClientOriginalName();
        if (preg_match('/\.(php|phtml|phar|sh|exe|bat|cmd|com|cgi|pl|py|rb|js|svg|html?|xml)\./i', $originalName)) {
            throw new RuntimeException('Nombre de archivo con extension peligrosa detectada.');
        }

        // 4. MIME from finfo (content-based, not user-controlled)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file->getRealPath());

        // finfo returns inconclusive types for empty/small or DOCX (ZIP) files
        $inconclusiveMimes = ['application/x-empty', 'application/zip', 'application/octet-stream'];
        $effectiveMime = $detectedMime;

        if (in_array($detectedMime, $inconclusiveMimes, true)) {
            // Fall back to Laravel's getMimeType() which uses both finfo and extension
            $effectiveMime = $file->getMimeType();
        }

        if (!in_array($effectiveMime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException(
                "Tipo de archivo no permitido: {$detectedMime}. Solo PDF y DOCX."
            );
        }

        // 5. Cross-validate: extension must match detected MIME
        $expectedExt = self::MIME_EXT_MAP[$effectiveMime] ?? null;
        if ($expectedExt !== $ext) {
            throw new RuntimeException(
                "Extension .{$ext} no coincide con contenido detectado ({$detectedMime})."
            );
        }

        // 6. Block dangerous file types masquerading as PDF/DOCX
        // Check actual content for executable signatures (PHP, ELF, shell scripts)
        $fileSize = $file->getSize();
        if ($fileSize > 0) {
            $header = file_get_contents($file->getRealPath(), false, null, 0, 64);
            $dangerousSignatures = [
                '<?php',
                '<?=',
                '#!/',
                "\x7FELF",       // Linux ELF binary
                "MZ",            // Windows PE binary
            ];
            foreach ($dangerousSignatures as $sig) {
                if (str_starts_with($header, $sig)) {
                    throw new RuntimeException(
                        "El archivo contiene contenido ejecutable peligroso."
                    );
                }
            }
        }
    }

    public function extract(string $storagePath, string $mime): string
    {
        // Path traversal protection first
        if (str_contains($storagePath, '..') || str_contains($storagePath, "\0")) {
            throw new RuntimeException('Path traversal detectado.');
        }

        // Use Storage facade to get the real disk path — this respects
        // the configured disk root and works consistently with storeAs().
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        if (!$disk->exists($storagePath)) {
            // Log diagnostic info to help debug on shared hosting
            $diskRoot = config('filesystems.disks.local.root', storage_path('app'));
            $legacyPath = storage_path("app/{$storagePath}");
            throw new RuntimeException(
                "Archivo no encontrado en storage: {$storagePath}. "
                . "Disk root: {$diskRoot}. "
                . "Legacy path: {$legacyPath}, exists: " . (file_exists($legacyPath) ? 'YES' : 'NO')
            );
        }

        $fullPath = $disk->path($storagePath);

        // Additional traversal check with realpath if available
        $realPath = realpath($fullPath);
        $allowedBase = realpath($disk->path(''));

        if ($realPath !== false && $allowedBase !== false && !str_starts_with($realPath, $allowedBase)) {
            throw new RuntimeException('Path traversal detectado.');
        }

        // Use realPath if resolved, otherwise fall back to direct path
        $filePath = $realPath ?: $fullPath;

        return match ($mime) {
            'application/pdf' => $this->extractFromPdf($filePath),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                => $this->extractFromDocx($filePath),
            default => throw new RuntimeException("Formato no soportado: {$mime}"),
        };
    }

    private function extractFromPdf(string $path): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        $text = $pdf->getText();

        if (empty(trim($text))) {
            throw new RuntimeException(
                'No se pudo extraer texto del PDF. Puede ser un PDF escaneado (imagen).'
            );
        }

        return $this->normalizeText($text);
    }

    private function extractFromDocx(string $path): string
    {
        $phpWord = PhpWordIOFactory::load($path, 'Word2007');
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text .= $this->extractElementText($element) . "\n";
            }
        }

        if (empty(trim($text))) {
            throw new RuntimeException('No se pudo extraer texto del DOCX.');
        }

        return $this->normalizeText($text);
    }

    private function extractElementText(mixed $element, int $depth = 0): string
    {
        if ($depth > 50) {
            return ''; // Prevent stack overflow from deeply nested/circular structures
        }
        if (method_exists($element, 'getText')) {
            return $element->getText();
        }
        if (method_exists($element, 'getElements')) {
            $parts = [];
            foreach ($element->getElements() as $child) {
                $parts[] = $this->extractElementText($child, $depth + 1);
            }
            return implode(' ', $parts);
        }
        return '';
    }

    private function normalizeText(string $text): string
    {
        // Normalize whitespace, remove null bytes, trim
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/\r\n?/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Attempt to structure extracted text into sections.
     */
    public function structureText(string $text): array
    {
        $sections = [
            'header' => '',
            'summary' => '',
            'experience' => [],
            'education' => [],
            'skills' => [],
            'certifications' => [],
            'languages' => [],
            'other' => '',
        ];

        $lines = explode("\n", $text);
        $currentSection = 'header';
        $buffer = [];

        $sectionPatterns = [
            'experience' => '/^(experiencia|experience|historial|trayectoria|work)/iu',
            'education' => '/^(educaci[oó]n|education|formaci[oó]n|estudios|academic)/iu',
            'skills' => '/^(habilidades|skills|competencias|conocimientos|technical)/iu',
            'certifications' => '/^(certificaci|certification|cursos|courses|diplomas)/iu',
            'languages' => '/^(idiomas|languages)/iu',
            'summary' => '/^(resumen|summary|perfil|profile|objetivo|about)/iu',
        ];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                $buffer[] = '';
                continue;
            }

            $matched = false;
            foreach ($sectionPatterns as $section => $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    // Save buffer to previous section
                    $this->saveBuffer($sections, $currentSection, $buffer);
                    $buffer = [];
                    $currentSection = $section;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $buffer[] = $trimmed;
            }
        }

        $this->saveBuffer($sections, $currentSection, $buffer);

        return $sections;
    }

    private function saveBuffer(array &$sections, string $section, array $buffer): void
    {
        $content = trim(implode("\n", $buffer));
        if (empty($content)) {
            return;
        }

        if (is_array($sections[$section]) && empty($sections[$section])) {
            $sections[$section] = $content;
        } elseif (is_string($sections[$section]) && empty($sections[$section])) {
            $sections[$section] = $content;
        } else {
            if (is_array($sections[$section])) {
                $sections[$section][] = $content;
            } else {
                $sections[$section] .= "\n" . $content;
            }
        }
    }
}
