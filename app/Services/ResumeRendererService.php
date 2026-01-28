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

        return $this->htmlToImage($fullHtml);
    }

    public function getPageCount(string $markdownText): int
    {
        $html = $this->markdownToHtml($markdownText);
        return count($this->paginateHtml($html));
    }

    private function markdownToHtml(string $md): string
    {
        // Basic markdown to HTML conversion
        $html = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Bullet lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html);

        // Line breaks
        $html = nl2br($html);

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
                font-size: 13px; line-height: 1.5; color: #222;
                background: white; position: relative; overflow: hidden;
            }
            h1 { font-size:20px; margin-bottom:8px; text-transform:uppercase; }
            h2 { font-size:15px; margin:14px 0 6px; border-bottom:1px solid #999;
                 padding-bottom:3px; text-transform:uppercase; color:#333; }
            h3 { font-size:13px; margin:8px 0 4px; }
            ul { padding-left:18px; margin:4px 0; }
            li { margin-bottom:2px; }
            strong { font-weight:bold; }
            /* Blur bottom third for preview */
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
    private function htmlToImage(string $html): string
    {
        // Try wkhtmltoimage first
        $wkhtmltoimage = config('ats.wkhtmltoimage_path', '/usr/local/bin/wkhtmltoimage');
        if (file_exists($wkhtmltoimage) && is_executable($wkhtmltoimage)) {
            return $this->renderWithWkhtmltoimage($html, $wkhtmltoimage);
        }

        // Fallback: GD-based simple rendering
        return $this->renderWithGd($html);
    }

    private function renderWithWkhtmltoimage(string $html, string $binary): string
    {
        $tmpHtml = tempnam(sys_get_temp_dir(), 'cv_preview_') . '.html';
        $tmpPng = tempnam(sys_get_temp_dir(), 'cv_preview_') . '.png';

        try {
            file_put_contents($tmpHtml, $html);

            $cmd = escapeshellarg($binary)
                . ' --width 800 --quality 85 --disable-javascript'
                . ' --disable-local-file-access'
                . ' ' . escapeshellarg($tmpHtml)
                . ' ' . escapeshellarg($tmpPng)
                . ' 2>&1';

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($tmpPng)) {
                throw new RuntimeException('wkhtmltoimage failed: ' . implode("\n", $output));
            }

            return file_get_contents($tmpPng);
        } finally {
            @unlink($tmpHtml);
            @unlink($tmpPng);
        }
    }

    /**
     * Basic GD fallback: render text content onto an image.
     * Not as pretty as wkhtmltoimage but functional.
     */
    private function renderWithGd(string $html): string
    {
        $width = 800;
        $height = 1100;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 34, 34, 34);
        $gray = imagecolorallocate($img, 150, 150, 150);
        $red = imagecolorallocate($img, 200, 50, 50);

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

        // Add watermark
        $watermarkColor = imagecolorallocatealpha($img, 200, 0, 0, 100);
        imagestring($img, 4, 150, $height / 2 - 20, 'PREVIEW - NO DESCARGAR', $watermarkColor);
        imagestring($img, 2, 200, $height / 2, 'Pague para obtener el documento final', $gray);

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
            body {
                width:100%; padding:30px 40px;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 12px; line-height: 1.5; color: #222;
            }
            h1 { font-size:18px; margin-bottom:6px; text-transform:uppercase; }
            h2 { font-size:14px; margin:12px 0 5px; border-bottom:1px solid #999;
                 padding-bottom:2px; text-transform:uppercase; }
            h3 { font-size:12px; margin:6px 0 3px; }
            ul { padding-left:16px; margin:3px 0; }
            li { margin-bottom:2px; }
        </style>
        </head><body>{$html}</body></html>
        HTML;

        return $fullHtml; // Will be fed to DOMPDF in PdfGeneratorService
    }
}
