<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfParser;
use Smalot\PdfParser\Config as PdfParserConfig;
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
        // Strategy 1: smalot/pdfparser with image decoding disabled
        $text = $this->extractPdfWithSmalot($path);

        if (!empty(trim($text))) {
            return $this->normalizeText($text);
        }

        // Strategy 2: pdftotext (poppler-utils) — handles fonts without
        // ToUnicode CMap tables that smalot cannot decode
        $text = $this->extractPdfWithPdftotext($path);

        if (!empty(trim($text))) {
            return $this->normalizeText($text);
        }

        // Strategy 3: raw stream extraction — last resort for PDFs where
        // neither library can decode the text properly
        $text = $this->extractPdfRawStreams($path);

        if (!empty(trim($text))) {
            return $this->normalizeText($text);
        }

        throw new RuntimeException(
            'No se pudo extraer texto del PDF. Puede ser un PDF escaneado (imagen).'
        );
    }

    private function extractPdfWithSmalot(string $path): string
    {
        $config = new PdfParserConfig();
        $config->setDecodeMemoryLimit(0);
        $config->setRetainImageContent(false);

        $parser = new PdfParser([], $config);

        try {
            $pdf = $parser->parseFile($path);
            return $pdf->getText();
        } catch (\Exception $e) {
            // Second pass: ignore encryption
            try {
                $config2 = new PdfParserConfig();
                $config2->setDecodeMemoryLimit(0);
                $config2->setRetainImageContent(false);
                $config2->setIgnoreEncryption(true);
                $parser2 = new PdfParser([], $config2);
                $pdf = $parser2->parseFile($path);
                return $pdf->getText();
            } catch (\Exception $e2) {
                return '';
            }
        }
    }

    private function extractPdfWithPdftotext(string $path): string
    {
        // Check if pdftotext is available on the system
        $which = @exec('which pdftotext 2>/dev/null', $output, $code);
        if ($code !== 0 || empty($which)) {
            return '';
        }

        $escapedPath = escapeshellarg($path);
        $command = "pdftotext -layout {$escapedPath} - 2>/dev/null";

        $text = @shell_exec($command);

        return is_string($text) ? $text : '';
    }

    private function extractPdfRawStreams(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            return '';
        }

        $text = '';

        // Extract text from BT...ET blocks (PDF text objects)
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                // Extract strings in parentheses: (text here)
                if (preg_match_all('/\(([^)]*)\)/', $block, $strings)) {
                    $text .= implode(' ', $strings[1]) . "\n";
                }
                // Extract hex strings: <48656C6C6F>
                if (preg_match_all('/<([0-9A-Fa-f]+)>/', $block, $hexStrings)) {
                    foreach ($hexStrings[1] as $hex) {
                        $decoded = @hex2bin($hex);
                        if ($decoded !== false && mb_detect_encoding($decoded, 'UTF-8, ISO-8859-1', true)) {
                            $text .= $decoded . ' ';
                        }
                    }
                    $text .= "\n";
                }
            }
        }

        // Only return if we got meaningful text (not just whitespace/garbage)
        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $text);
        $cleaned = trim($cleaned);

        // Heuristic: if less than 20% of characters are printable letters, it's garbage
        if (strlen($cleaned) < 10) {
            return '';
        }
        $letterCount = preg_match_all('/[\p{L}\p{N}]/u', $cleaned);
        if ($letterCount / max(strlen($cleaned), 1) < 0.2) {
            return '';
        }

        return $text;
    }

    private function extractFromDocx(string $path): string
    {
        try {
            $phpWord = PhpWordIOFactory::load($path, 'Word2007');

            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $text .= $this->extractElementText($element) . "\n";
                }
            }

            if (!empty(trim($text))) {
                return $this->normalizeText($text);
            }
        } catch (\Exception $e) {
            // PhpWord can fail on DOCX files with unsupported embedded objects
            // (e.g. EMF images, OLE objects). Fall through to raw XML extraction.
            \Illuminate\Support\Facades\Log::warning('PhpWord load failed, trying raw XML fallback', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: read text directly from the DOCX ZIP (word/document.xml)
        $text = $this->extractFromDocxRawXml($path);

        if (empty(trim($text))) {
            throw new RuntimeException('No se pudo extraer texto del DOCX.');
        }

        return $this->normalizeText($text);
    }

    /**
     * Fallback DOCX extraction: open the ZIP, read word/document.xml,
     * and strip XML tags to get plain text. Ignores images entirely.
     */
    private function extractFromDocxRawXml(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo DOCX como ZIP.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('El archivo DOCX no contiene word/document.xml.');
        }

        // Replace paragraph and line-break tags with newlines before stripping
        $xml = preg_replace('/<w:p[\s>]/i', "\n<w:p ", $xml);
        $xml = preg_replace('/<w:br[^>]*>/i', "\n", $xml);
        // Replace tab tags with tab character
        $xml = preg_replace('/<w:tab[^>]*>/i', "\t", $xml);

        // Strip all XML tags to get plain text
        $text = strip_tags($xml);

        // Decode XML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $text;
    }

    private function extractElementText(mixed $element, int $depth = 0): string
    {
        if ($depth > 50) {
            return ''; // Prevent stack overflow from deeply nested/circular structures
        }

        // Skip image/drawing elements — they have no useful text and can
        // cause errors when the document contains profile photos or logos.
        if ($element instanceof \PhpOffice\PhpWord\Element\Image
            || $element instanceof \PhpOffice\PhpWord\Element\Drawing
            || $element instanceof \PhpOffice\PhpWord\Element\OLEObject
            || $element instanceof \PhpOffice\PhpWord\Element\Chart
        ) {
            return '';
        }

        // For table elements, iterate rows → cells → elements
        if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
            $parts = [];
            foreach ($element->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    foreach ($cell->getElements() as $cellElement) {
                        $cellText = $this->extractElementText($cellElement, $depth + 1);
                        if (!empty(trim($cellText))) {
                            $parts[] = trim($cellText);
                        }
                    }
                }
            }
            return implode(' | ', $parts);
        }

        if (method_exists($element, 'getText')) {
            try {
                return $element->getText();
            } catch (\Exception $e) {
                return ''; // Skip elements that fail to extract text
            }
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
            'rut' => '',
            'address' => '',
            'summary' => '',
            'experience' => [],
            'education' => [],
            'skills' => [],
            'certifications' => [],
            'languages' => [],
            'other' => '',
        ];

        // Extract RUT from text (Chilean ID format: XX.XXX.XXX-X or XXXXXXXX-X)
        if (preg_match('/\b(?:RUT[:\s]*)?(\d{1,2}\.?\d{3}\.?\d{3}-[\dkK])\b/i', $text, $rutMatch)) {
            $sections['rut'] = $rutMatch[1];
        }

        // Extract address (look for common Chilean address patterns)
        if (preg_match('/(?:direcci[oó]n|domicilio)[:\s]*([^\n]+)/iu', $text, $addressMatch)) {
            $sections['address'] = trim($addressMatch[1]);
        } elseif (preg_match('/\b((?:Av(?:enida)?|Calle|Pasaje|Pje)\s*\.?\s*[^\n,]+(?:,\s*(?:Depto|Dpto|Apt|Casa|Of)\.?\s*\d+[A-Za-z]?)?)/iu', $text, $addressMatch)) {
            $sections['address'] = trim($addressMatch[1]);
        }

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
