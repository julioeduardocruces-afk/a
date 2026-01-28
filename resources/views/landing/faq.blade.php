@extends('layouts.app')
@section('title', 'Preguntas Frecuentes')

@section('meta_extra')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        ['@type' => 'Question', 'name' => 'Que es un sistema ATS?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'ATS (Applicant Tracking System) es software que usan las empresas para filtrar CVs automaticamente antes de que un reclutador los revise.']],
        ['@type' => 'Question', 'name' => 'El servicio inventa o modifica mi experiencia?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'No. Toda tu experiencia laboral, fechas, empresas y cargos se mantienen exactamente como en tu CV original. Solo se optimiza formato, estructura y keywords.']],
        ['@type' => 'Question', 'name' => 'Que formatos de archivo acepta?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Aceptamos PDF y DOCX. El archivo no debe superar los 10 MB. No se aceptan archivos DOC antiguos.']],
        ['@type' => 'Question', 'name' => 'Como funciona el pago?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'El pago se realiza de forma segura a traves de Flow (Webpay). Solo despues del pago se desbloquea la descarga del CV final.']],
        ['@type' => 'Question', 'name' => 'El CV sirve para Laborum, ChileTrabajos, LinkedIn?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Si. El formato ATS generado es compatible con los principales portales de empleo y sus sistemas de filtrado automatico.']],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
@endsection

@section('content')
<h1 style="margin-bottom:24px;">Preguntas Frecuentes</h1>

<div class="card">
    <h3>Que es un sistema ATS?</h3>
    <p>ATS (Applicant Tracking System) es software que usan las empresas para filtrar CVs automaticamente antes de que un reclutador los revise. Si tu CV no esta optimizado para ATS, puede ser descartado automaticamente.</p>
</div>

<div class="card">
    <h3>El servicio inventa o modifica mi experiencia?</h3>
    <p>No. Toda tu experiencia laboral, fechas, empresas y cargos se mantienen exactamente como en tu CV original. Solo se optimiza formato, estructura y keywords para el rubro objetivo.</p>
</div>

<div class="card">
    <h3>Que formatos de archivo acepta?</h3>
    <p>Aceptamos PDF y DOCX. El archivo no debe superar los 10 MB. No se aceptan archivos DOC antiguos por razones de compatibilidad.</p>
</div>

<div class="card">
    <h3>Como funciona el pago?</h3>
    <p>Antes de pagar puedes ver un preview de tu CV optimizado con puntaje ATS. El pago se realiza via Flow (Webpay). Una vez confirmado, recibes descarga PDF+DOCX y envio por email.</p>
</div>

<div class="card">
    <h3>Sirve para Laborum, ChileTrabajos, LinkedIn?</h3>
    <p>Si. El formato ATS generado es compatible con los principales portales de empleo: Laborum, ChileTrabajos, Trabajando.com, LinkedIn, Indeed, Computrabajo y otros.</p>
</div>

<div class="card">
    <h3>Mis datos estan seguros?</h3>
    <p>Si. Usamos encriptacion para almacenar tus archivos. No compartimos tus datos con terceros. Los archivos se almacenan de forma segura fuera del directorio publico.</p>
</div>

<div class="card">
    <h3>Puedo solicitar un reembolso?</h3>
    <p>Si el servicio no puede procesar tu CV (archivo ilegible, PDF escaneado sin texto), no se cobra. Para otros casos, contactanos.</p>
</div>
@endsection
