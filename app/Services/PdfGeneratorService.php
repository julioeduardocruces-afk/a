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
            'marginTop' => 720,    // 0.5 inch
            'marginBottom' => 720,
            'marginLeft' => 1080,  // 0.75 inch
            'marginRight' => 1080,
        ]);

        $lines = explode("\n", $plainText);
        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (empty($trimmed)) {
                $section->addTextBreak();
                continue;
            }

            // Detect headers (ALL CAPS lines or lines starting with known section titles)
            if ($this->isSectionHeader($trimmed)) {
                $section->addText(
                    htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'),
                    ['bold' => true, 'size' => 13, 'allCaps' => true],
                    ['spaceAfter' => 60]
                );
                continue;
            }

            // Detect sub-headers (bold-like lines)
            if ($this->isSubHeader($trimmed)) {
                $section->addText(
                    htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'),
                    ['bold' => true, 'size' => 11],
                    ['spaceAfter' => 40]
                );
                continue;
            }

            // Bullet points
            if (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ')) {
                $bulletText = ltrim($trimmed, '-* ');
                $section->addListItem(
                    htmlspecialchars($bulletText, ENT_QUOTES, 'UTF-8'),
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'cv_docx_') . '.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tmpFile);

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $content;
    }

    private function isSectionHeader(string $line): bool
    {
        $headers = [
            'RESUMEN', 'PERFIL', 'EXPERIENCIA', 'EDUCACION', 'EDUCACIÓN',
            'HABILIDADES', 'CERTIFICACIONES', 'IDIOMAS', 'DATOS PERSONALES',
            'SUMMARY', 'EXPERIENCE', 'EDUCATION', 'SKILLS', 'CERTIFICATIONS',
            'LANGUAGES', 'PROFESSIONAL', 'FORMACION', 'FORMACIÓN',
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
        // Lines that look like "Company Name - Role (Date - Date)"
        return (bool)preg_match('/^[A-ZÁÉÍÓÚÑ].+\s[-–|]\s.+\d{4}/', $line);
    }
}
