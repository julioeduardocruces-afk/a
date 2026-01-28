<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
    <h1 style="color:#1a1a2e;">Tu CV Optimizado esta Listo</h1>

    <p>Hola {{ $userName }},</p>

    <p>Tu CV optimizado para ATS (ID: #{{ $resumeId }}) ha sido generado exitosamente.</p>

    <p>
        <a href="{{ $downloadPdfUrl }}"
           style="display:inline-block;padding:12px 30px;background:#0066ff;color:white;text-decoration:none;border-radius:6px;font-weight:bold;">
            Descargar CV (PDF)
        </a>
        &nbsp;&nbsp;
        <a href="{{ $downloadDocxUrl }}"
           style="display:inline-block;padding:12px 30px;background:#28a745;color:white;text-decoration:none;border-radius:6px;font-weight:bold;">
            Descargar CV (DOCX)
        </a>
    </p>

    <p style="color:#999;font-size:0.9rem;">
        Cada enlace es de un solo uso y expiran el {{ $expiresAt }}.
        Si necesitas nuevos enlaces, accede a tu dashboard.
    </p>

    <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
    <p style="color:#999;font-size:0.8rem;">CV Optimizer ATS - No respondas a este email.</p>
</body>
</html>
