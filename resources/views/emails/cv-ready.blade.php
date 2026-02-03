<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.7;color:#333;max-width:600px;margin:0 auto;padding:20px;background:#f9f9f9;">

    {{-- Header --}}
    <div style="background:#1a1a2e;padding:24px 30px;border-radius:10px 10px 0 0;text-align:center;">
        <h1 style="color:#ffffff;margin:0;font-size:22px;">{{ $emailSubject }}</h1>
    </div>

    <div style="background:#ffffff;padding:30px;border-radius:0 0 10px 10px;border:1px solid #e8e8e8;border-top:none;">

        <p style="font-size:16px;">Hola <strong>{{ $userName }}</strong>,</p>

        {{-- Intro text (editable from admin) --}}
        <p style="font-size:15px;">{!! nl2br(e($emailIntro)) !!}</p>

        {{-- Download buttons --}}
        <div style="text-align:center;margin:28px 0;">
            <a href="{{ $downloadPdfUrl }}"
               style="display:inline-block;padding:14px 32px;background:#0066ff;color:white;text-decoration:none;border-radius:8px;font-weight:bold;font-size:15px;margin:6px;">
                Descargar PDF
            </a>
            <a href="{{ $downloadDocxUrl }}"
               style="display:inline-block;padding:14px 32px;background:#28a745;color:white;text-decoration:none;border-radius:8px;font-weight:bold;font-size:15px;margin:6px;">
                Descargar Word
            </a>
        </div>

        {{-- Expiration warning box --}}
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin-bottom:24px;">
            <p style="color:#856404;font-size:14px;margin:0;text-align:center;">
                <strong>IMPORTANTE:</strong> Tienes <strong>{{ $maxDownloads }} descargas</strong> disponibles (PDF o Word).<br>
                Los enlaces expiran el <strong>{{ $expiresAt }}</strong> (7 dias desde hoy).<br>
                <span style="font-size:13px;">Te recomendamos descargar tu CV ahora y guardarlo en tu computador.</span>
            </p>
        </div>

        <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">

        {{-- ATS explanation (editable from admin) --}}
        <h2 style="color:#1a1a2e;font-size:17px;margin-bottom:8px;">{{ $sectionAtsTitle }}</h2>
        <p style="font-size:14px;color:#444;">{!! nl2br(e($sectionAtsBody)) !!}</p>

        <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">

        {{-- Recommendations (editable from admin) --}}
        <h2 style="color:#1a1a2e;font-size:17px;margin-bottom:8px;">{{ $sectionTipsTitle }}</h2>
        <p style="font-size:14px;color:#444;">{!! nl2br(e($sectionTipsBody)) !!}</p>

        <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">

        {{-- Footer text (editable from admin) --}}
        <p style="font-size:13px;color:#999;text-align:center;">{!! nl2br(e($emailFooter)) !!}</p>
    </div>

    <p style="font-size:11px;color:#bbb;text-align:center;margin-top:16px;">
        Este es un correo automatico, no es necesario responder.
    </p>
</body>
</html>
