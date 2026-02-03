@extends('layouts.admin')
@section('title', 'Procesar CV Gratis')
@section('admin-content')
<h1 style="margin-bottom:24px;">Procesar CV Gratis (sin pago)</h1>
<p style="color:#666;margin-bottom:20px;">Procesa CVs sin requerir pago. El CV optimizado se enviara al correo indicado.</p>

{{-- SECTION 1: Process existing draft resumes --}}
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:12px;color:#1a1a2e;">Procesar CV Existente (Draft)</h3>
    @if($draftResumes->isEmpty())
        <p style="color:#888;">No hay CVs en estado draft disponibles.</p>
    @else
        <form method="POST" action="" id="process-existing-form">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="form-group">
                    <label for="resume_id">Seleccionar CV *</label>
                    <select id="resume_id" name="resume_id" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
                        <option value="">-- Seleccionar --</option>
                        @foreach($draftResumes as $resume)
                            <option value="{{ $resume->id }}">
                                #{{ $resume->id }} - {{ $resume->customer_email ?? 'Sin email' }}
                                ({{ $resume->created_at->format('d/m/Y H:i') }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="existing_target_industry">Rubro Objetivo *</label>
                    <input type="text" id="existing_target_industry" name="target_industry" required maxlength="255" placeholder="Ej: Tecnologia, Banca, Retail">
                </div>
                <div class="form-group">
                    <label for="existing_target_role">Cargo Objetivo *</label>
                    <input type="text" id="existing_target_role" name="target_role" required maxlength="255" placeholder="Ej: Desarrollador Senior, Analista">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label for="existing_recipient_email">Email para enviar el CV optimizado *</label>
                <input type="email" id="existing_recipient_email" name="recipient_email" required maxlength="255" placeholder="correo@ejemplo.com">
            </div>
            <button type="submit" class="btn btn-primary" id="btn-process-existing">Procesar CV</button>
        </form>
    @endif
</div>

{{-- SECTION 2: Upload PDF/DOCX --}}
<div class="card" style="margin-bottom:24px;">
    <h3 style="margin-bottom:12px;color:#1a1a2e;">Subir CV (PDF/DOCX)</h3>
    <form method="POST" action="{{ route('admin.free-process.upload') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group" style="margin-bottom:12px;">
            <label for="cv_file">Archivo CV (PDF o DOCX, max 10MB) *</label>
            <input type="file" id="cv_file" name="cv_file" required accept=".pdf,.docx" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div class="form-group">
                <label for="upload_target_industry">Rubro Objetivo *</label>
                <input type="text" id="upload_target_industry" name="target_industry" required maxlength="255" placeholder="Ej: Tecnologia, Banca, Retail">
            </div>
            <div class="form-group">
                <label for="upload_target_role">Cargo Objetivo *</label>
                <input type="text" id="upload_target_role" name="target_role" required maxlength="255" placeholder="Ej: Desarrollador Senior, Analista">
            </div>
        </div>
        <div class="form-group" style="margin-bottom:12px;">
            <label for="upload_recipient_email">Email para enviar el CV optimizado *</label>
            <input type="email" id="upload_recipient_email" name="recipient_email" required maxlength="255" placeholder="correo@ejemplo.com">
        </div>
        <button type="submit" class="btn btn-primary">Subir y Procesar</button>
    </form>
</div>

{{-- SECTION 3: CV Builder Form --}}
<div class="card">
    <h3 style="margin-bottom:12px;color:#1a1a2e;">Construir CV Manualmente</h3>
    <form method="POST" action="{{ route('admin.free-process.builder') }}" id="admin-cv-builder-form">
        @csrf

        {{-- Target and Email --}}
        <div style="background:#f8f9fa;padding:16px;border-radius:6px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Configuracion del Proceso</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label for="builder_target_industry">Rubro Objetivo *</label>
                    <input type="text" id="builder_target_industry" name="target_industry" required maxlength="255" placeholder="Ej: Tecnologia">
                </div>
                <div class="form-group">
                    <label for="builder_target_role">Cargo Objetivo *</label>
                    <input type="text" id="builder_target_role" name="target_role" required maxlength="255" placeholder="Ej: Desarrollador">
                </div>
                <div class="form-group">
                    <label for="builder_recipient_email">Email Destinatario *</label>
                    <input type="email" id="builder_recipient_email" name="recipient_email" required maxlength="255" placeholder="correo@ejemplo.com">
                </div>
            </div>
        </div>

        {{-- Personal Data --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Datos Personales</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label for="full_name">Nombre Completo *</label>
                    <input type="text" id="full_name" name="full_name" required maxlength="255" placeholder="Ej: Juan Perez Lopez">
                </div>
                <div class="form-group">
                    <label for="rut">RUT *</label>
                    <input type="text" id="rut" name="rut" required maxlength="12" placeholder="Ej: 12.345.678-9">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label for="email">Email del Candidato *</label>
                    <input type="email" id="email" name="email" required maxlength="255" placeholder="Ej: juan@email.com">
                </div>
                <div class="form-group">
                    <label for="phone">Telefono</label>
                    <input type="text" id="phone" name="phone" maxlength="30" placeholder="Ej: +56 9 1234 5678">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label for="address">Direccion *</label>
                    <input type="text" id="address" name="address" required maxlength="500" placeholder="Ej: Av. Libertador 1234, Depto 56">
                </div>
                <div class="form-group">
                    <label for="location">Ciudad / Region</label>
                    <input type="text" id="location" name="location" maxlength="255" placeholder="Ej: Santiago, Chile">
                </div>
            </div>
            <div class="form-group">
                <label for="linkedin">LinkedIn (opcional)</label>
                <input type="text" id="linkedin" name="linkedin" maxlength="255" placeholder="Ej: linkedin.com/in/juanperez">
            </div>
        </div>

        {{-- Profile --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Perfil Profesional (opcional)</h4>
            <p style="font-size:0.85rem;color:#666;margin-bottom:12px;">Si se deja vacio, se generara automaticamente basado en la experiencia laboral.</p>
            <div class="form-group" style="margin-bottom:0;">
                <textarea id="summary" name="summary" rows="3" maxlength="2000" placeholder="Resumen breve del perfil profesional..."></textarea>
            </div>
        </div>

        {{-- Experience --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Experiencia Laboral</h4>
            <div id="experience-container">
                <div class="experience-entry" style="border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px;position:relative;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Empresa *</label>
                            <input type="text" name="experiences[0][company]" required maxlength="255" placeholder="Empresa ABC">
                        </div>
                        <div class="form-group">
                            <label>Cargo *</label>
                            <input type="text" name="experiences[0][position]" required maxlength="255" placeholder="Desarrollador Senior">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Periodo</label>
                        <input type="text" name="experiences[0][period]" maxlength="100" placeholder="Marzo 2020 - Presente">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Descripcion / Logros</label>
                        <textarea name="experiences[0][description]" rows="2" maxlength="3000" placeholder="Logros y responsabilidades..."></textarea>
                    </div>
                </div>
            </div>
            <button type="button" onclick="addExperience()" class="btn btn-secondary btn-sm" style="margin-top:4px;">+ Agregar Experiencia</button>
        </div>

        {{-- Education --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Educacion</h4>
            <div id="education-container">
                <div class="education-entry" style="border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px;position:relative;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Institucion *</label>
                            <input type="text" name="education[0][institution]" required maxlength="255" placeholder="Universidad de Chile">
                        </div>
                        <div class="form-group">
                            <label>Titulo / Grado *</label>
                            <input type="text" name="education[0][degree]" required maxlength="255" placeholder="Ingenieria Civil">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Periodo</label>
                        <input type="text" name="education[0][period]" maxlength="100" placeholder="2015 - 2020">
                    </div>
                </div>
            </div>
            <button type="button" onclick="addEducation()" class="btn btn-secondary btn-sm" style="margin-top:4px;">+ Agregar Educacion</button>
        </div>

        {{-- Skills --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Competencias Clave (opcional)</h4>
            <p style="font-size:0.85rem;color:#666;margin-bottom:12px;">Si se deja vacio, se generaran automaticamente basadas en la experiencia.</p>
            <div class="form-group" style="margin-bottom:0;">
                <textarea id="skills" name="skills" rows="2" maxlength="2000" placeholder="Gestion de proyectos, Liderazgo, Excel avanzado..."></textarea>
            </div>
        </div>

        {{-- Certifications --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Certificaciones / Cursos (opcional)</h4>
            <div class="form-group" style="margin-bottom:0;">
                <textarea id="certifications" name="certifications" rows="2" maxlength="2000" placeholder="AWS Solutions Architect - 2023&#10;Scrum Master - 2022"></textarea>
            </div>
        </div>

        {{-- Languages --}}
        <div style="border:1px solid #eee;border-radius:6px;padding:16px;margin-bottom:16px;">
            <h4 style="margin-bottom:12px;color:#333;">Idiomas (opcional)</h4>
            <div class="form-group" style="margin-bottom:0;">
                <input type="text" id="languages" name="languages" maxlength="500" placeholder="Espanol (Nativo), Ingles (Avanzado)">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;">Crear y Procesar CV</button>
    </form>
</div>

<script>
var expIndex = 1;
var eduIndex = 1;

function addExperience() {
    var html = '<div class="experience-entry" style="border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px;position:relative;">'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
        + '<div class="form-group"><label>Empresa *</label><input type="text" name="experiences['+expIndex+'][company]" required maxlength="255" placeholder="Empresa ABC"></div>'
        + '<div class="form-group"><label>Cargo *</label><input type="text" name="experiences['+expIndex+'][position]" required maxlength="255" placeholder="Desarrollador Senior"></div>'
        + '</div>'
        + '<div class="form-group"><label>Periodo</label><input type="text" name="experiences['+expIndex+'][period]" maxlength="100" placeholder="Marzo 2020 - Presente"></div>'
        + '<div class="form-group" style="margin-bottom:0;"><label>Descripcion / Logros</label><textarea name="experiences['+expIndex+'][description]" rows="2" maxlength="3000" placeholder="Logros y responsabilidades..."></textarea></div>'
        + '<button type="button" onclick="this.closest(\'.experience-entry\').remove()" style="position:absolute;top:8px;right:8px;background:#dc3545;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;">&times;</button>'
        + '</div>';
    document.getElementById('experience-container').insertAdjacentHTML('beforeend', html);
    expIndex++;
}

function addEducation() {
    var html = '<div class="education-entry" style="border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px;position:relative;">'
        + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'
        + '<div class="form-group"><label>Institucion *</label><input type="text" name="education['+eduIndex+'][institution]" required maxlength="255" placeholder="Universidad de Chile"></div>'
        + '<div class="form-group"><label>Titulo / Grado *</label><input type="text" name="education['+eduIndex+'][degree]" required maxlength="255" placeholder="Ingenieria Civil"></div>'
        + '</div>'
        + '<div class="form-group" style="margin-bottom:0;"><label>Periodo</label><input type="text" name="education['+eduIndex+'][period]" maxlength="100" placeholder="2015 - 2020"></div>'
        + '<button type="button" onclick="this.closest(\'.education-entry\').remove()" style="position:absolute;top:8px;right:8px;background:#dc3545;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;">&times;</button>'
        + '</div>';
    document.getElementById('education-container').insertAdjacentHTML('beforeend', html);
    eduIndex++;
}

// Handle process existing form - set action dynamically
document.getElementById('process-existing-form')?.addEventListener('submit', function(e) {
    var resumeId = document.getElementById('resume_id').value;
    if (!resumeId) {
        e.preventDefault();
        alert('Por favor selecciona un CV');
        return;
    }
    this.action = '{{ url("admin/resumes") }}/' + resumeId + '/process-free';
});
</script>
@endsection
