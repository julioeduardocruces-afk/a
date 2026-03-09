<?php

namespace App\Console\Commands;

use App\Models\Resume;
use App\Services\TextExtractorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DebugExtractText extends Command
{
    protected $signature = 'debug:extract
                            {--resume= : Resume ID to test extraction on}
                            {--file= : Direct file path (relative to storage/app) to test}
                            {--mime= : MIME type (pdf or docx) when using --file}';

    protected $description = 'Debug text extraction from a CV file (PDF/DOCX)';

    public function handle(): int
    {
        $extractor = app(TextExtractorService::class);

        // Option 1: Test by resume ID
        if ($resumeId = $this->option('resume')) {
            return $this->debugResume((int) $resumeId, $extractor);
        }

        // Option 2: Test by direct file path
        if ($filePath = $this->option('file')) {
            $mime = $this->option('mime');
            if (!$mime) {
                $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                $mime = match (strtolower($ext)) {
                    'pdf' => 'application/pdf',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    default => null,
                };
            }
            if (!$mime) {
                $this->error("No se pudo determinar el MIME. Usa --mime=application/pdf o --mime=application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                return 1;
            }
            return $this->debugFile($filePath, $mime, $extractor);
        }

        // Option 3: Show last 10 resumes with errors
        $this->info('=== Ultimos resumes con estado Failed ===');
        $failed = Resume::where('status', 'Failed')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'status', 'original_path', 'original_mime', 'failure_reason', 'failure_detail', 'updated_at']);

        if ($failed->isEmpty()) {
            $this->info('No hay resumes fallidos.');
            $this->newLine();
        } else {
            $rows = $failed->map(fn($r) => [
                $r->id,
                $r->status->value ?? $r->status,
                $r->original_mime,
                $r->original_path,
                \Str::limit($r->failure_detail ?? $r->failure_reason ?? '-', 60),
                $r->updated_at?->format('d/m H:i'),
            ])->toArray();

            $this->table(['ID', 'Status', 'MIME', 'Path', 'Error', 'Fecha'], $rows);
        }

        // Also show last 10 resumes in Processing (stuck?)
        $this->info('=== Ultimos resumes en Processing (posiblemente atascados) ===');
        $processing = Resume::where('status', 'Processing')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'status', 'original_path', 'original_mime', 'updated_at']);

        if ($processing->isEmpty()) {
            $this->info('No hay resumes en Processing.');
        } else {
            $rows = $processing->map(fn($r) => [
                $r->id,
                $r->original_mime,
                $r->original_path,
                $r->updated_at?->format('d/m H:i'),
            ])->toArray();

            $this->table(['ID', 'MIME', 'Path', 'Fecha'], $rows);
        }

        $this->newLine();
        $this->info('Uso: php artisan debug:extract --resume=ID');
        $this->info('     php artisan debug:extract --file=resumes/abc.pdf');

        return 0;
    }

    private function debugResume(int $resumeId, TextExtractorService $extractor): int
    {
        $resume = Resume::find($resumeId);
        if (!$resume) {
            $this->error("Resume #{$resumeId} no encontrado.");
            return 1;
        }

        $this->info("=== Debug Resume #{$resume->id} ===");
        $this->table(['Campo', 'Valor'], [
            ['Status', $resume->status->value ?? $resume->status],
            ['MIME', $resume->original_mime ?? 'NULL'],
            ['Path', $resume->original_path ?? 'NULL'],
            ['Email', $resume->getEmail() ?? 'NULL'],
            ['Failure reason', $resume->failure_reason ?? '-'],
            ['Failure detail', \Str::limit($resume->failure_detail ?? '-', 120)],
            ['Created', $resume->created_at?->format('d/m/Y H:i')],
            ['Updated', $resume->updated_at?->format('d/m/Y H:i')],
        ]);

        if (!$resume->original_path || !$resume->original_mime) {
            $this->error('Resume no tiene original_path o original_mime.');
            return 1;
        }

        return $this->debugFile($resume->original_path, $resume->original_mime, $extractor);
    }

    private function debugFile(string $storagePath, string $mime, TextExtractorService $extractor): int
    {
        $disk = Storage::disk('local');
        $diskRoot = config('filesystems.disks.local.root', storage_path('app'));

        $this->info("=== Debug File ===");
        $this->line("Storage path:  {$storagePath}");
        $this->line("MIME:          {$mime}");
        $this->line("Disk root:     {$diskRoot}");

        // Check file exists
        $exists = $disk->exists($storagePath);
        $this->line("Exists (disk): " . ($exists ? 'SI' : 'NO'));

        if (!$exists) {
            // Try to find the file
            $fullPath = $diskRoot . '/' . $storagePath;
            $this->line("Full path:     {$fullPath}");
            $this->line("file_exists(): " . (file_exists($fullPath) ? 'SI' : 'NO'));

            // Check legacy path
            $legacyPath = storage_path("app/{$storagePath}");
            if ($legacyPath !== $fullPath) {
                $this->line("Legacy path:   {$legacyPath}");
                $this->line("Legacy exists: " . (file_exists($legacyPath) ? 'SI' : 'NO'));
            }

            // List contents of resumes directory
            $dir = dirname($storagePath);
            $this->newLine();
            $this->warn("Contenido del directorio '{$dir}':");
            try {
                $files = $disk->files($dir);
                foreach (array_slice($files, 0, 20) as $f) {
                    $this->line("  - {$f} (" . $disk->size($f) . " bytes)");
                }
                if (count($files) > 20) {
                    $this->line("  ... y " . (count($files) - 20) . " archivos mas");
                }
            } catch (\Exception $e) {
                $this->error("No se pudo listar directorio: {$e->getMessage()}");
            }

            $this->error('Archivo no encontrado en storage.');
            return 1;
        }

        // File info
        $fullPath = $disk->path($storagePath);
        $size = $disk->size($storagePath);
        $this->line("Full path:     {$fullPath}");
        $this->line("Size:          {$size} bytes (" . round($size / 1024, 1) . " KB)");
        $this->line("Readable:      " . (is_readable($fullPath) ? 'SI' : 'NO'));

        // Check magic bytes
        $header = file_get_contents($fullPath, false, null, 0, 16);
        $hexHeader = bin2hex(substr($header, 0, 8));
        $this->line("Magic bytes:   {$hexHeader}");

        if (str_starts_with($header, '%PDF')) {
            $this->line("Detected:      PDF");
        } elseif (str_starts_with($header, "PK\x03\x04")) {
            $this->line("Detected:      ZIP/DOCX");
        } else {
            $this->warn("Detected:      DESCONOCIDO - los magic bytes no coinciden con PDF ni DOCX");
        }

        // Memory info
        $this->newLine();
        $memBefore = memory_get_usage(true);
        $this->line("Memory antes:  " . round($memBefore / 1024 / 1024, 1) . " MB");
        $this->line("Memory limit:  " . ini_get('memory_limit'));

        // Try extraction
        $this->newLine();
        $this->info('=== Intentando extraccion ===');

        try {
            $startTime = microtime(true);
            $text = $extractor->extract($storagePath, $mime);
            $elapsed = round((microtime(true) - $startTime) * 1000);
            $memAfter = memory_get_usage(true);

            $this->info("EXITO en {$elapsed}ms");
            $this->line("Memory despues: " . round($memAfter / 1024 / 1024, 1) . " MB (+" . round(($memAfter - $memBefore) / 1024 / 1024, 1) . " MB)");
            $this->line("Texto extraido: " . strlen($text) . " caracteres, " . str_word_count($text) . " palabras");
            $this->newLine();
            $this->info('=== Primeros 500 caracteres ===');
            $this->line(\Str::limit($text, 500));

        } catch (\Exception $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000);
            $this->newLine();
            $this->error("FALLO en {$elapsed}ms");
            $this->error("Clase: " . get_class($e));
            $this->error("Mensaje: " . $e->getMessage());
            $this->newLine();
            $this->warn("Stack trace:");
            $this->line($e->getTraceAsString());

            // Check for common issues
            $this->newLine();
            $this->info('=== Diagnostico ===');

            $msg = $e->getMessage();
            if (str_contains($msg, 'memory') || str_contains($msg, 'Memory')) {
                $this->warn('-> Posible problema de memoria. Intenta aumentar memory_limit en php.ini');
            }
            if (str_contains($msg, 'escaneado') || str_contains($msg, 'imagen')) {
                $this->warn('-> El PDF parece ser una imagen escaneada. No se puede extraer texto sin OCR.');
            }
            if (str_contains($msg, 'danado') || str_contains($msg, 'corrupted')) {
                $this->warn('-> El archivo parece estar danado o corrupto.');
            }
            if (str_contains($msg, 'protegido') || str_contains($msg, 'encrypted')) {
                $this->warn('-> El archivo esta protegido con password.');
            }
            if (str_contains($msg, 'ZipArchive') || str_contains($msg, 'zip')) {
                $this->warn('-> Posible problema con la extension zip de PHP. Verifica: php -m | grep zip');
            }

            return 1;
        }

        // Try structuring
        $this->newLine();
        $this->info('=== Intentando estructuracion ===');
        try {
            $structured = $extractor->structureText($text);
            $this->info('EXITO');
            foreach ($structured as $key => $value) {
                $preview = is_array($value)
                    ? (empty($value) ? '(vacio)' : \Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE), 80))
                    : (empty($value) ? '(vacio)' : \Str::limit($value, 80));
                $this->line("  {$key}: {$preview}");
            }
        } catch (\Exception $e) {
            $this->error("FALLO: " . $e->getMessage());
        }

        return 0;
    }
}
