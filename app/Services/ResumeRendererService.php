<?php

namespace App\Services;

use RuntimeException;

class ResumeRendererService
{
    /**
     * Render optimized CV text as a rasterized preview image with watermark.
     * Returns PNG binary data for a given page.
     *
     * Defense in depth: text is rendered server-side to image,
     * never sent as selectable text to the client.
     */
    public function renderPreviewPage(
        string $markdownText,
        string $watermarkEmail,
        int $resumeId,
        int $page = 1,
    ): string {
        $html = $this->markdownToHtml($markdownText);
        $pages = $this->paginateHtml($html);

        if ($page < 1 || $page > count($pages)) {
            throw new RuntimeException("Pagina {$page} no existe.");
        }

        $pageHtml = $pages[$page - 1];
        $fullHtml = $this->buildPreviewHtml($pageHtml, $watermarkEmail, $resumeId);

        return $this->htmlToImage($fullHtml, $watermarkEmail, $resumeId);
    }

    public function getPageCount(string $markdownText): int
    {
        $html = $this->markdownToHtml($markdownText);
        return count($this->paginateHtml($html));
    }

    private function markdownToHtml(string $md): string
    {
        $lines = explode("\n", $md);
        $htmlLines = [];
        $afterH1 = false; // Track lines right after name for subtitle/contact styling
        $inEntry = false; // Track if we're inside an h3 entry block

        foreach ($lines as $line) {
            $safe = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');

            // Headers
            if (preg_match('/^### (.+)$/', $safe, $m)) {
                $afterH1 = false;
                // Close previous entry if open
                if ($inEntry) {
                    $htmlLines[] = '</div>';
                }
                // Start new entry block
                $htmlLines[] = '<div class="entry">';
                $inEntry = true;
                $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $m[1]);
                $htmlLines[] = '<h3>' . $content . '</h3>';
                continue;
            }
            if (preg_match('/^## (.+)$/', $safe, $m)) {
                $afterH1 = false;
                // Close previous entry if open
                if ($inEntry) {
                    $htmlLines[] = '</div>';
                    $inEntry = false;
                }
                $htmlLines[] = '<h2>' . $m[1] . '</h2>';
                continue;
            }
            if (preg_match('/^# (.+)$/', $safe, $m)) {
                // Close previous entry if open
                if ($inEntry) {
                    $htmlLines[] = '</div>';
                    $inEntry = false;
                }
                $htmlLines[] = '<h1>' . $m[1] . '</h1>';
                $afterH1 = true;
                continue;
            }

            // Unicode bullet items (•)
            if (preg_match('/^[\x{2022}]\s*(.+)$/u', $line, $m)) {
                $afterH1 = false;
                $item = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                $item = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $item);
                $htmlLines[] = '<li class="bullet-item">' . $item . '</li>';
                continue;
            }

            // Dash bullet list items
            if (preg_match('/^- (.+)$/', $safe, $m)) {
                $afterH1 = false;
                $item = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $m[1]);
                $htmlLines[] = '<li>' . $item . '</li>';
                continue;
            }

            // Bold in normal text
            $safe = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $safe);

            // Mark missing information in red
            $safe = str_replace('[FALTA INFORMACIÓN]', '<span class="missing-info">[FALTA INFORMACIÓN]</span>', $safe);

            // Empty line
            if (trim($safe) === '') {
                $afterH1 = false;
                $htmlLines[] = '<div class="spacer"></div>';
                continue;
            }

            // Lines right after h1 (subtitle, address, contact) get special class
            if ($afterH1) {
                $htmlLines[] = '<div class="contact-line">' . $safe . '</div>';
                continue;
            }

            $htmlLines[] = '<p>' . $safe . '</p>';
        }

        // Close last entry if open
        if ($inEntry) {
            $htmlLines[] = '</div>';
        }

        // Wrap consecutive <li> elements in <ul>
        $html = implode("\n", $htmlLines);
        $html = preg_replace('/(<li class="bullet-item">.*?<\/li>\n?)+/s', '<ul class="bullet-list">$0</ul>', $html);
        $html = preg_replace('/(<li>(?:(?!class=).).*?<\/li>\n?)+/s', '<ul>$0</ul>', $html);

        return $html;
    }

    private function paginateHtml(string $html): array
    {
        // Simple pagination: split by ~3000 chars per page
        $charsPerPage = 3000;
        $lines = explode("\n", $html);
        $pages = [];
        $current = '';
        $charCount = 0;

        foreach ($lines as $line) {
            $lineLen = mb_strlen($line);
            if ($charCount + $lineLen > $charsPerPage && !empty($current)) {
                $pages[] = $current;
                $current = '';
                $charCount = 0;
            }
            $current .= $line . "\n";
            $charCount += $lineLen;
        }

        if (!empty(trim($current))) {
            $pages[] = $current;
        }

        return $pages ?: ['<p>Sin contenido</p>'];
    }

    private function buildPreviewHtml(string $pageContent, string $email, int $resumeId): string
    {
        $timestamp = now()->format('Y-m-d H:i');
        $watermark = htmlspecialchars("{$email} | PREVIEW | {$timestamp} | ID:{$resumeId}");

        // Generate multiple watermark positions for anti-capture
        $watermarks = '';
        $positions = [
            ['top' => '10%', 'left' => '5%', 'rotate' => '-30deg', 'opacity' => '0.08'],
            ['top' => '30%', 'left' => '20%', 'rotate' => '-25deg', 'opacity' => '0.06'],
            ['top' => '50%', 'left' => '10%', 'rotate' => '-35deg', 'opacity' => '0.09'],
            ['top' => '70%', 'left' => '25%', 'rotate' => '-20deg', 'opacity' => '0.07'],
            ['top' => '20%', 'left' => '50%', 'rotate' => '-28deg', 'opacity' => '0.05'],
            ['top' => '60%', 'left' => '45%', 'rotate' => '-32deg', 'opacity' => '0.08'],
            ['top' => '80%', 'left' => '15%', 'rotate' => '-22deg', 'opacity' => '0.06'],
            ['top' => '40%', 'left' => '60%', 'rotate' => '-33deg', 'opacity' => '0.07'],
        ];

        $colors = ['#cc0000', '#0000cc', '#333333', '#660066'];

        foreach ($positions as $i => $pos) {
            $color = $colors[$i % count($colors)];
            $watermarks .= <<<HTML
            <div style="position:absolute;top:{$pos['top']};left:{$pos['left']};
                transform:rotate({$pos['rotate']});opacity:{$pos['opacity']};
                font-size:18px;color:{$color};font-family:Arial;white-space:nowrap;
                pointer-events:none;user-select:none;z-index:1000;">
                {$watermark}
            </div>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="utf-8">
        <style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body {
                width:800px; min-height:1100px; padding:40px 50px;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px; line-height: 1.55; color: #222;
                background: white; position: relative; overflow: hidden;
            }
            h1 {
                font-size: 22px; font-weight: bold; margin-bottom: 2px;
                text-transform: uppercase; color: #111; letter-spacing: 0.5px;
            }
            h2 {
                font-size: 13px; font-weight: bold; margin: 16px 0 6px;
                border-bottom: 1.5px solid #333; padding-bottom: 3px;
                text-transform: uppercase; color: #111; letter-spacing: 0.3px;
            }
            h3 {
                font-size: 12px; font-weight: bold; margin: 10px 0 1px;
                color: #222;
            }
            .contact-line {
                font-size: 11px; color: #444; line-height: 1.4; margin: 0;
            }
            .contact-line strong { color: #222; }
            .spacer { height: 6px; }
            p { margin: 2px 0; }
            ul { padding-left: 16px; margin: 4px 0; }
            ul.bullet-list { padding-left: 12px; list-style: none; }
            ul.bullet-list li { margin-bottom: 2px; }
            ul.bullet-list li::before { content: "• "; font-weight: bold; }
            li { margin-bottom: 2px; }
            strong { font-weight: bold; }
            .blur-zone { filter:blur(6px); }
        </style>
        </head><body>
        {$watermarks}
        <div class="content">{$pageContent}</div>
        </body></html>
        HTML;
    }

    /**
     * Render HTML to PNG image server-side.
     * Uses wkhtmltoimage if available, falls back to GD-based rendering.
     */
    private function htmlToImage(string $html, string $watermarkEmail = '', int $resumeId = 0): string
    {
        // Try wkhtmltoimage first
        $wkhtmltoimage = config('ats.wkhtmltoimage_path', '/usr/local/bin/wkhtmltoimage');
        if (file_exists($wkhtmltoimage) && is_executable($wkhtmltoimage)) {
            return $this->renderWithWkhtmltoimage($html, $wkhtmltoimage);
        }

        // Fallback: GD-based simple rendering (pass user info for watermark traceability)
        return $this->renderWithGd($html, $watermarkEmail, $resumeId);
    }

    private function renderWithWkhtmltoimage(string $html, string $binary): string
    {
        $tmpDir = sys_get_temp_dir();
        $unique = bin2hex(random_bytes(16));
        $tmpHtml = "{$tmpDir}/cv_preview_{$unique}.html";
        $tmpPng = "{$tmpDir}/cv_preview_{$unique}.png";

        try {
            file_put_contents($tmpHtml, $html, LOCK_EX);

            // timeout(30s) prevents resource exhaustion from malicious/complex HTML
            $cmd = 'timeout 30 ' . escapeshellarg($binary)
                . ' --width 800 --quality 85 --disable-javascript'
                . ' --disable-local-file-access --no-stop-slow-scripts'
                . ' ' . escapeshellarg($tmpHtml)
                . ' ' . escapeshellarg($tmpPng)
                . ' 2>&1';

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($tmpPng)) {
                throw new RuntimeException('wkhtmltoimage failed: ' . implode("\n", $output));
            }

            return file_get_contents($tmpPng);
        } finally {
            if (file_exists($tmpHtml) && !unlink($tmpHtml)) {
                \Illuminate\Support\Facades\Log::warning("Failed to clean temp file: {$tmpHtml}");
            }
            if (file_exists($tmpPng) && !unlink($tmpPng)) {
                \Illuminate\Support\Facades\Log::warning("Failed to clean temp file: {$tmpPng}");
            }
        }
    }

    /**
     * Basic GD fallback: render text content onto an image.
     * Not as pretty as wkhtmltoimage but functional.
     */
    private function renderWithGd(string $html, string $watermarkEmail = '', int $resumeId = 0): string
    {
        $width = 800;
        $height = 1100;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 34, 34, 34);
        $gray = imagecolorallocate($img, 150, 150, 150);

        imagefill($img, 0, 0, $white);

        // Strip HTML tags for GD rendering
        $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');

        $lines = explode("\n", wordwrap($plainText, 90, "\n", true));
        $y = 40;
        $fontSize = 3; // GD built-in font size

        foreach ($lines as $line) {
            if ($y > $height - 50) {
                break;
            }
            imagestring($img, $fontSize, 40, $y, $line, $black);
            $y += 16;
        }

        // Add user-specific watermark matching the wkhtmltoimage path.
        // Previously used generic "PREVIEW - NO DESCARGAR" which made leaked
        // GD-rendered previews impossible to trace back to a specific user.
        $timestamp = now()->format('Y-m-d H:i');
        $watermarkText = "{$watermarkEmail} | PREVIEW | {$timestamp} | ID:{$resumeId}";
        $watermarkColor = imagecolorallocatealpha($img, 200, 0, 0, 100);

        // Draw watermark at multiple positions for anti-capture (same concept as HTML path)
        $positions = [
            ['x' => 50, 'y' => (int)($height * 0.15)],
            ['x' => 150, 'y' => (int)($height * 0.35)],
            ['x' => 80, 'y' => (int)($height * 0.55)],
            ['x' => 200, 'y' => (int)($height * 0.75)],
        ];

        foreach ($positions as $pos) {
            imagestring($img, 2, $pos['x'], $pos['y'], $watermarkText, $watermarkColor);
        }

        imagestring($img, 3, 200, (int)($height * 0.92), 'Pague para obtener el documento final', $gray);

        ob_start();
        imagepng($img, null, 8);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    /**
     * Generate final PDF (no watermark, post-payment).
     */
    public function renderFinalPdf(string $markdownText, string $userName): string
    {
        $html = $this->markdownToHtml($markdownText);

        $fullHtml = <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="utf-8">
        <style>
            @page {
                margin: 60px 70px 50px 70px;
            }
            body {
                width: 100%; margin: 0; padding: 0;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px; line-height: 1.55; color: #222;
            }
            h1 {
                font-size: 22px; font-weight: bold; margin-bottom: 2px;
                text-transform: uppercase; color: #111; letter-spacing: 0.5px;
                page-break-after: avoid;
            }
            h2 {
                font-size: 13px; font-weight: bold; margin: 16px 0 6px;
                border-bottom: 1.5px solid #333; padding-bottom: 3px;
                text-transform: uppercase; color: #111; letter-spacing: 0.3px;
                page-break-after: avoid;
            }
            h3 {
                font-size: 12px; font-weight: bold; margin: 10px 0 2px;
                color: #222;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            .contact-line {
                font-size: 10.5px; color: #444; line-height: 1.4; margin: 0;
            }
            .contact-line strong { color: #222; }
            .spacer { height: 6px; }
            p {
                margin: 3px 0;
                orphans: 3;
                widows: 3;
            }
            ul {
                padding-left: 16px; margin: 4px 0;
                page-break-inside: avoid;
            }
            ul.bullet-list { padding-left: 12px; list-style: none; }
            ul.bullet-list li { margin-bottom: 3px; }
            ul.bullet-list li::before { content: "• "; font-weight: bold; }
            li {
                margin-bottom: 3px;
                page-break-inside: avoid;
            }
            strong { font-weight: bold; }
            /* Contenedor de entrada - evita corte de página */
            .entry {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            /* Texto de información faltante en rojo */
            .missing-info {
                color: #cc0000;
                font-weight: bold;
            }
        </style>
        </head><body>{$html}</body></html>
        HTML;

        return $fullHtml; // Will be fed to DOMPDF in PdfGeneratorService
    }
}
