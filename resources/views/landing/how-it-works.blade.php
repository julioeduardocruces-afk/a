@extends('layouts.app')
@section('title', 'Como Funciona')
@section('content')
<h1 style="margin-bottom:24px;">Como Funciona CV Optimizer ATS</h1>

<div class="card">
    <h2>Paso 1: Registrate y Sube tu CV</h2>
    <p>Crea tu cuenta en segundos. Sube tu CV actual en formato PDF o DOCX (maximo 10 MB). Tu archivo se almacena de forma segura y encriptada.</p>
</div>

<div class="card">
    <h2>Paso 2: Selecciona tu Rubro y Cargo Objetivo</h2>
    <p>Elige el area y cargo al que deseas postular. Esto permite que la IA optimice las palabras clave especificas de tu industria.</p>
</div>

<div class="card">
    <h2>Paso 3: Procesamiento con IA</h2>
    <p>Nuestro sistema extrae el texto de tu CV, analiza la estructura, y utiliza inteligencia artificial para optimizar:</p>
    <ul style="padding-left:20px;margin-top:8px;">
        <li>Palabras clave del rubro (keywords ATS)</li>
        <li>Estructura y secciones estandar</li>
        <li>Resumen profesional enfocado</li>
        <li>Formato compatible con ATS (sin tablas, sin columnas)</li>
    </ul>
    <p style="margin-top:8px;"><strong>Importante:</strong> TODA tu experiencia laboral se mantiene. Nunca se inventan datos ni se omiten experiencias.</p>
</div>

<div class="card">
    <h2>Paso 4: Revisa el Preview y Puntaje ATS</h2>
    <p>Antes de pagar, puedes ver un preview de tu CV optimizado junto con el puntaje ATS (0-100) que evalua keywords, estructura, longitud y formato.</p>
</div>

<div class="card">
    <h2>Paso 5: Paga y Descarga</h2>
    <p>Realiza el pago seguro via Webpay/Flow. Una vez confirmado, recibiras tu CV final en PDF y DOCX, tanto para descarga como por email.</p>
</div>
@endsection
