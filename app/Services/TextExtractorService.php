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

    public function validateFile(UploadedFile $file): void
    {
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException(
                "Tipo de archivo no permitido: {$mime}. Solo PDF y DOCX."
            );
        }

        $allowedExts = ['pdf', 'docx'];
        if (!in_array($ext, $allowedExts, true)) {
            throw new RuntimeException(
                "Extension no permitida: .{$ext}. Solo .pdf y .docx."
            );
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('El archivo excede el limite de 10 MB.');
        }
    }

    public function extract(string $storagePath, string $mime): string
    {
        $fullPath = storage_path("app/{$storagePath}");

        if (!file_exists($fullPath)) {
            throw new RuntimeException('Archivo no encontrado en storage.');
        }

        // Path traversal protection
        $realPath = realpath($fullPath);
        $allowedBase = realpath(storage_path('app'));
        if ($realPath === false || $allowedBase === false || !str_starts_with($realPath, $allowedBase)) {
            throw new RuntimeException('Path traversal detectado.');
        }

        return match ($mime) {
            'application/pdf' => $this->extractFromPdf($realPath),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                => $this->extractFromDocx($realPath),
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
