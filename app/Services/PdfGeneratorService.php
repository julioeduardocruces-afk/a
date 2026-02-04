<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class PdfGeneratorService
{
    /**
     * Generate final PDF from HTML (ATS-friendly, no watermark).
     */
    public function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Generate DOCX from plain text (ATS-friendly, simple layout).
     */
    public function generateDocx(string $plainText, string $userName): string
    {
        $phpWord = new PhpWord();

        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1134,   // ~2cm (1 inch = 1440 twips)
            'marginBottom' => 1000,
            'marginLeft' => 1418,  // ~2.5cm
            'marginRight' => 1418,
        ]);

        $lines = explode("\n", $plainText);
        $isFirstLine = true;
        $foundName = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (empty($trimmed)) {
                $section->addTextBreak();
                continue;
            }

            // First non-empty line is the name - make it large, bold, centered
            if ($isFirstLine && !$foundName) {
                $isFirstLine = false;
                // Check if this looks like a name (not a section header)
                if (!$this->isSectionHeader($trimmed) && !str_starts_with($trimmed, '-') && !str_starts_with($trimmed, '•')) {
                    $section->addText(
                        htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'),
                        ['bold' => true, 'size' => 18, 'color' => '1a1a2e'],
                        ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
                    );
                    $foundName = true;
                    continue;
                }
            }

            // Detect headers (ALL CAPS lines or lines starting with known section titles)
            if ($this->isSectionHeader($trimmed)) {
                $section->addText(
                    htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'),
                    ['bold' => true, 'size' => 14, 'color' => '1a1a2e'],
                    ['spaceAfter' => 80, 'spaceBefore' => 200]
                );
                continue;
            }

            // Detect sub-headers (job titles, education entries)
            if ($this->isSubHeader($trimmed)) {
                $section->addText(
                    htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'),
                    ['bold' => true, 'size' => 12, 'color' => '333333'],
                    ['spaceAfter' => 60, 'spaceBefore' => 120]
                );
                continue;
            }

            // Bullet points (- , * , or • prefix)
            if (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ') || str_starts_with($trimmed, '• ')) {
                $bulletText = $trimmed;
                if (str_starts_with($bulletText, '• ')) {
                    $bulletText = mb_substr($bulletText, 2);
                } else {
                    $bulletText = ltrim($bulletText, '-* ');
                }
                $section->addListItem(
                    htmlspecialchars(trim($bulletText), ENT_QUOTES, 'UTF-8'),
                    0,
                    ['size' => 11],
                );
                continue;
            }

            // Regular text
            $section->addText(
                htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'),
                ['size' => 11],
                ['spaceAfter' => 40]
            );
        }

        $tmpDir = sys_get_temp_dir();
        $unique = bin2hex(random_bytes(16));
        $tmpFile = "{$tmpDir}/cv_docx_{$unique}.docx";

        try {
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tmpFile);

            return file_get_contents($tmpFile);
        } finally {
            if (file_exists($tmpFile) && !unlink($tmpFile)) {
                \Illuminate\Support\Facades\Log::warning("Failed to clean temp file: {$tmpFile}");
            }
        }
    }

    private function isSectionHeader(string $line): bool
    {
        $headers = [
            'RESUMEN', 'PERFIL', 'EXPERIENCIA', 'EDUCACION', 'EDUCACIÓN',
            'HABILIDADES', 'CERTIFICACIONES', 'IDIOMAS', 'DATOS PERSONALES',
            'SUMMARY', 'EXPERIENCE', 'EDUCATION', 'SKILLS', 'CERTIFICATIONS',
            'LANGUAGES', 'PROFESSIONAL', 'FORMACION', 'FORMACIÓN',
            'COMPETENCIAS', 'HERRAMIENTAS', 'TECNOLOGÍAS', 'TECNOLOGIAS',
            'DISPONIBILIDAD', 'LOGROS', 'REFERENCIAS',
        ];

        $upper = mb_strtoupper(trim($line));
        foreach ($headers as $h) {
            if (str_contains($upper, $h)) {
                return true;
            }
        }

        // All caps line
        return $upper === $line && mb_strlen($line) < 60 && mb_strlen($line) > 2;
    }

    private function isSubHeader(string $line): bool
    {
        // Lines that look like "Company Name - Role (Date - Date)" or similar patterns
        // Pattern 1: Contains dash/hyphen with year (e.g., "Empresa - Cargo | 2020 - 2023")
        if (preg_match('/^[A-ZÁÉÍÓÚÑ].+\s[-–|]\s.+\d{4}/', $line)) {
            return true;
        }

        // Pattern 2: Starts with capital letter, contains year range (e.g., "Analista Senior (2019 - 2022)")
        if (preg_match('/^[A-ZÁÉÍÓÚÑ][^#\n]{10,}.*\(\d{4}\s*[-–]\s*(\d{4}|Presente|Actual)\)/ui', $line)) {
            return true;
        }

        // Pattern 3: Job title pattern with separator (e.g., "Gerente de Finanzas – Banco XYZ")
        if (preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(\s+[A-Za-záéíóúñÁÉÍÓÚÑ]+){1,5}\s*[–\-|]\s*[A-ZÁÉÍÓÚÑ]/u', $line)) {
            return true;
        }

        // Pattern 4: Education entry (e.g., "Ingeniero Comercial - Universidad de Chile")
        if (preg_match('/^(Ingenier[oa]|Licenciad[oa]|Técnico|Magíster|Master|MBA|Diplomado|Contador|Abogad[oa]|Doctor|PhD|Bachiller)/ui', $line) &&
            mb_strlen($line) > 15 && mb_strlen($line) < 120) {
            return true;
        }

        // Pattern 5: Institution pattern (e.g., "Universidad de Santiago | 2015 - 2019")
        if (preg_match('/^(Universidad|Instituto|Centro|Escuela|Colegio|Liceo|Academia)/ui', $line) &&
            mb_strlen($line) > 10 && mb_strlen($line) < 120) {
            return true;
        }

        return false;
    }
}
