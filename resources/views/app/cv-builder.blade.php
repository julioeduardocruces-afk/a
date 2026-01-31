@extends('layouts.app')
@section('title', 'Crear CV - Formulario')
@section('content')
<div style="max-width:700px;margin:30px auto;">
    <h1 style="margin-bottom:8px;">Paso 1: Crea tu CV</h1>
    <p style="color:#666;margin-bottom:20px;">Completa tus datos y nosotros generamos y optimizamos tu CV para sistemas ATS.</p>

    <form method="POST" action="{{ route('cv-builder.store') }}" id="cv-builder-form">
        @csrf

        {{-- DATOS PERSONALES --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Datos Personales</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label for="full_name">Nombre Completo *</label>
                    <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="255" placeholder="Ej: Juan Perez Lopez">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="Ej: juan@email.com">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label for="phone">Telefono</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" placeholder="Ej: +56 9 1234 5678">
                </div>
                <div class="form-group">
                    <label for="location">Ciudad / Region</label>
                    <input type="text" id="location" name="location" value="{{ old('location') }}" maxlength="255" placeholder="Ej: Santiago, Chile">
                </div>
            </div>
            <div class="form-group">
                <label for="linkedin">LinkedIn (opcional)</label>
                <input type="text" id="linkedin" name="linkedin" value="{{ old('linkedin') }}" maxlength="255" placeholder="Ej: linkedin.com/in/juanperez">
            </div>
        </div>

        {{-- PERFIL / RESUMEN --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Perfil Profesional</h3>
            <div class="form-group">
                <label for="summary">Resumen breve de tu perfil (2-4 lineas) *</label>
                <textarea id="summary" name="summary" rows="4" required maxlength="2000" placeholder="Ej: Profesional con 5 anos de experiencia en desarrollo de software, especializado en aplicaciones web con PHP y JavaScript. Orientado a resultados con enfoque en soluciones escalables.">{{ old('summary') }}</textarea>
            </div>
        </div>

        {{-- EXPERIENCIA LABORAL --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Experiencia Laboral</h3>
            <p style="font-size:0.85rem;color:#666;margin-bottom:12px;">Agrega tus experiencias laborales, desde la mas reciente a la mas antigua.</p>
            <div id="experience-container">
                @php $oldExp = old('experiences', [['company'=>'','position'=>'','period'=>'','description'=>'']]); @endphp
                @foreach($oldExp as $i => $exp)
                <div class="experience-entry" style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:12px;position:relative;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Empresa *</label>
                            <input type="text" name="experiences[{{ $i }}][company]" value="{{ $exp['company'] ?? '' }}" required maxlength="255" placeholder="Ej: Empresa ABC Ltda.">
                        </div>
                        <div class="form-group">
                            <label>Cargo *</label>
                            <input type="text" name="experiences[{{ $i }}][position]" value="{{ $exp['position'] ?? '' }}" required maxlength="255" placeholder="Ej: Desarrollador Senior">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Periodo</label>
                        <input type="text" name="experiences[{{ $i }}][period]" value="{{ $exp['period'] ?? '' }}" maxlength="100" placeholder="Ej: Marzo 2020 - Presente">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Descripcion / Logros (uno por linea)</label>
                        <textarea name="experiences[{{ $i }}][description]" rows="3" maxlength="3000" placeholder="Ej: Desarrollo de APIs REST con Laravel&#10;Liderazgo de equipo de 4 desarrolladores&#10;Reduccion de tiempos de carga en 40%">{{ $exp['description'] ?? '' }}</textarea>
                    </div>
                    @if($i > 0)
                    <button type="button" class="btn-remove-entry" onclick="this.closest('.experience-entry').remove()" style="position:absolute;top:8px;right:8px;background:#dc3545;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;">&times;</button>
                    @endif
                </div>
                @endforeach
            </div>
            <button type="button" onclick="addExperience()" class="btn btn-secondary btn-sm" style="margin-top:4px;">+ Agregar Experiencia</button>
        </div>

        {{-- EDUCACION --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Educacion</h3>
            <div id="education-container">
                @php $oldEdu = old('education', [['institution'=>'','degree'=>'','period'=>'']]); @endphp
                @foreach($oldEdu as $i => $edu)
                <div class="education-entry" style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:12px;position:relative;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Institucion *</label>
                            <input type="text" name="education[{{ $i }}][institution]" value="{{ $edu['institution'] ?? '' }}" required maxlength="255" placeholder="Ej: Universidad de Chile">
                        </div>
                        <div class="form-group">
                            <label>Titulo / Grado *</label>
                            <input type="text" name="education[{{ $i }}][degree]" value="{{ $edu['degree'] ?? '' }}" required maxlength="255" placeholder="Ej: Ingenieria Civil Informatica">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Periodo</label>
                        <input type="text" name="education[{{ $i }}][period]" value="{{ $edu['period'] ?? '' }}" maxlength="100" placeholder="Ej: 2015 - 2020">
                    </div>
                    @if($i > 0)
                    <button type="button" class="btn-remove-entry" onclick="this.closest('.education-entry').remove()" style="position:absolute;top:8px;right:8px;background:#dc3545;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;">&times;</button>
                    @endif
                </div>
                @endforeach
            </div>
            <button type="button" onclick="addEducation()" class="btn btn-secondary btn-sm" style="margin-top:4px;">+ Agregar Educacion</button>
        </div>

        {{-- HABILIDADES --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Habilidades</h3>
            <div class="form-group" style="margin-bottom:0;">
                <label for="skills">Lista tus habilidades separadas por coma *</label>
                <textarea id="skills" name="skills" rows="3" required maxlength="2000" placeholder="Ej: PHP, Laravel, JavaScript, React, MySQL, Git, Docker, Trabajo en equipo, Liderazgo">{{ old('skills') }}</textarea>
            </div>
        </div>

        {{-- CERTIFICACIONES --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Certificaciones / Cursos (opcional)</h3>
            <div class="form-group" style="margin-bottom:0;">
                <label for="certifications">Una certificacion por linea</label>
                <textarea id="certifications" name="certifications" rows="3" maxlength="2000" placeholder="Ej: AWS Solutions Architect - 2023&#10;Scrum Master Certified - 2022&#10;Google Analytics - 2021">{{ old('certifications') }}</textarea>
            </div>
        </div>

        {{-- IDIOMAS --}}
        <div class="card">
            <h3 style="margin-bottom:12px;color:#1a1a2e;">Idiomas (opcional)</h3>
            <div class="form-group" style="margin-bottom:0;">
                <label for="languages">Ej: Espanol (Nativo), Ingles (Avanzado)</label>
                <input type="text" id="languages" name="languages" value="{{ old('languages') }}" maxlength="500" placeholder="Espanol (Nativo), Ingles (Intermedio)">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;margin-bottom:16px;">Continuar</button>
    </form>

    <div style="text-align:center;">
        <a href="{{ route('upload.form') }}">Tengo mi CV en PDF/DOCX, prefiero subirlo</a>
        &nbsp;|&nbsp;
        <a href="{{ route('home') }}">Volver al inicio</a>
    </div>
</div>

<script>
var expIndex = {{ count($oldExp) }};
var eduIndex = {{ count($oldEdu) }};

function addExperience() {
    var html = '<div class="experience-entry" style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:12px;position:relative;">'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
        + '<div class="form-group"><label>Empresa *</label><input type="text" name="experiences['+expIndex+'][company]" required maxlength="255" placeholder="Ej: Empresa ABC Ltda."></div>'
        + '<div class="form-group"><label>Cargo *</label><input type="text" name="experiences['+expIndex+'][position]" required maxlength="255" placeholder="Ej: Desarrollador Senior"></div>'
        + '</div>'
        + '<div class="form-group"><label>Periodo</label><input type="text" name="experiences['+expIndex+'][period]" maxlength="100" placeholder="Ej: Marzo 2020 - Presente"></div>'
        + '<div class="form-group" style="margin-bottom:0;"><label>Descripcion / Logros (uno por linea)</label><textarea name="experiences['+expIndex+'][description]" rows="3" maxlength="3000" placeholder="Ej: Desarrollo de APIs REST con Laravel"></textarea></div>'
        + '<button type="button" class="btn-remove-entry" onclick="this.closest(\'.experience-entry\').remove()" style="position:absolute;top:8px;right:8px;background:#dc3545;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;">&times;</button>'
        + '</div>';
    document.getElementById('experience-container').insertAdjacentHTML('beforeend', html);
    expIndex++;
}

function addEducation() {
    var html = '<div class="education-entry" style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:12px;position:relative;">'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
        + '<div class="form-group"><label>Institucion *</label><input type="text" name="education['+eduIndex+'][institution]" required maxlength="255" placeholder="Ej: Universidad de Chile"></div>'
        + '<div class="form-group"><label>Titulo / Grado *</label><input type="text" name="education['+eduIndex+'][degree]" required maxlength="255" placeholder="Ej: Ingenieria Civil Informatica"></div>'
        + '</div>'
        + '<div class="form-group" style="margin-bottom:0;"><label>Periodo</label><input type="text" name="education['+eduIndex+'][period]" maxlength="100" placeholder="Ej: 2015 - 2020"></div>'
        + '<button type="button" class="btn-remove-entry" onclick="this.closest(\'.education-entry\').remove()" style="position:absolute;top:8px;right:8px;background:#dc3545;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;">&times;</button>'
        + '</div>';
    document.getElementById('education-container').insertAdjacentHTML('beforeend', html);
    eduIndex++;
}
</script>
@endsection
