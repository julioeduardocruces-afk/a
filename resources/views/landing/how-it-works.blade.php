@extends('layouts.app')
@section('title', 'Como Funciona')
@section('meta_description', 'Aprende como optimizar tu CV para sistemas ATS en 5 simples pasos. Sube tu CV, selecciona tu rubro, y recibe tu curriculum optimizado para Laborum y ChileTrabajos.')
@section('meta_keywords', 'como optimizar CV, pasos optimizar curriculum, tutorial CV ATS, mejorar CV Chile, proceso optimizacion CV')

@section('schema_extra')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "HowTo",
    "name": "Como Optimizar tu CV para Sistemas ATS",
    "description": "Guia paso a paso para optimizar tu curriculum vitae para sistemas de seguimiento de candidatos",
    "step": [
        {
            "@type": "HowToStep",
            "position": 1,
            "name": "Sube o Crea tu CV",
            "text": "Sube tu CV actual en formato PDF o DOCX, o crealo desde cero con nuestro formulario guiado."
        },
        {
            "@type": "HowToStep",
            "position": 2,
            "name": "Selecciona tu Rubro",
            "text": "Elige el area y cargo al que deseas postular para optimizar las palabras clave especificas."
        },
        {
            "@type": "HowToStep",
            "position": 3,
            "name": "Optimizacion Especializada",
            "text": "Nuestro sistema analiza tu CV y lo reestructura para maximizar compatibilidad con filtros ATS."
        },
        {
            "@type": "HowToStep",
            "position": 4,
            "name": "Revisa el Preview",
            "text": "Visualiza tu CV optimizado con puntaje ATS antes de pagar."
        },
        {
            "@type": "HowToStep",
            "position": 5,
            "name": "Paga y Descarga",
            "text": "Realiza el pago seguro y recibe tu CV en PDF y Word por email."
        }
    ]
}
</script>
@endsection

@section('content')
<h1 style="margin-bottom:24px;">Como Funciona CV Optimizer ATS</h1>

<div class="card">
    <h2>Paso 1: Sube o Crea tu CV</h2>
    <p>Sube tu CV actual en formato PDF o DOCX (maximo 10 MB), o crealo desde cero con nuestro formulario guiado. No necesitas crear cuenta. Tu archivo se almacena de forma segura y encriptada.</p>
</div>

<div class="card">
    <h2>Paso 2: Selecciona tu Rubro y Cargo Objetivo</h2>
    <p>Elige el area y cargo al que deseas postular. Esto permite que nuestro sistema optimice las palabras clave especificas de tu industria y adapte la estructura a lo que buscan los reclutadores en ese rubro.</p>
</div>

<div class="card">
    <h2>Paso 3: Optimizacion Especializada</h2>
    <p>Nuestro sistema especializado en ATS analiza tu CV, extrae la informacion relevante y la reestructura para maximizar tu compatibilidad con los filtros automaticos:</p>
    <ul style="padding-left:20px;margin-top:8px;">
        <li>Incorporacion de palabras clave del rubro (keywords ATS)</li>
        <li>Reestructuracion de secciones al formato estandar que exigen los ATS</li>
        <li>Resumen profesional enfocado en logros y resultados</li>
        <li>Formato limpio compatible con ATS (sin tablas, sin columnas, sin graficos)</li>
    </ul>
    <p style="margin-top:8px;"><strong>Importante:</strong> TODA tu experiencia laboral se mantiene intacta. Nunca se inventan datos ni se omiten experiencias.</p>
</div>

<div class="card">
    <h2>Paso 4: Revisa el Preview y Puntaje ATS</h2>
    <p>Antes de pagar, puedes ver un preview de tu CV optimizado junto con el puntaje ATS (0-100) que evalua keywords, estructura, longitud y formato. Asi puedes verificar la calidad del resultado.</p>
</div>

<div class="card">
    <h2>Paso 5: Paga y Descarga</h2>
    <p>Realiza el pago seguro via Webpay/Flow. Una vez confirmado, recibiras tu CV final en PDF y Word directamente en tu email, con hasta 3 descargas disponibles.</p>
</div>
@endsection
