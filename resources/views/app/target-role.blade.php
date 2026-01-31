@extends('layouts.app')
@section('title', 'Seleccionar Rubro y Cargo')
@section('content')
<div style="max-width:600px;margin:30px auto;">
    <h1 style="margin-bottom:8px;">Paso 2: Rubro y Cargo Objetivo</h1>
    <p style="color:#666;margin-bottom:20px;">Archivo: {{ $resume->original_filename }}</p>

    <div class="card">
        <form method="POST" action="{{ route('resumes.set-target-role', $resume->id) }}">
            @csrf
            <div class="form-group">
                <label for="target_industry">Rubro / Industria Objetivo</label>
                <select id="target_industry" name="target_industry" required>
                    <option value="">Selecciona un rubro...</option>
                    @php
                        $industries = [
                            'Tecnologias de la Informacion',
                            'Salud / Hemodialisis',
                            'Ventas y Comercial',
                            'Finanzas / Factoring',
                            'Ingenieria',
                            'Educacion',
                            'Logistica y Transporte',
                            'Marketing Digital',
                            'Recursos Humanos',
                            'Administracion',
                            'Construccion',
                            'Legal',
                            'Mineria',
                            'Otro',
                        ];
                        $currentIndustry = old('target_industry', $resume->target_industry);
                        $isCustom = $currentIndustry && !in_array($currentIndustry, $industries);
                    @endphp
                    @foreach($industries as $ind)
                        <option value="{{ $ind }}" {{ ($currentIndustry === $ind || ($isCustom && $ind === 'Otro')) ? 'selected' : '' }}>
                            {{ $ind === 'Tecnologias de la Informacion' ? 'Tecnologias de la Informacion (TI)' : $ind }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" id="custom-industry-group" style="display:{{ ($isCustom || old('target_industry') === 'Otro') ? 'block' : 'none' }};">
                <label for="custom_industry">Especifica tu rubro</label>
                <input type="text" id="custom_industry" name="custom_industry"
                       value="{{ old('custom_industry', $isCustom ? $currentIndustry : '') }}"
                       placeholder="Ej: Gastronomia, Agricultura, Telecomunicaciones..."
                       maxlength="255">
            </div>
            <div class="form-group">
                <label for="target_role">Cargo Objetivo</label>
                <input type="text" id="target_role" name="target_role"
                       value="{{ old('target_role', $resume->target_role) }}"
                       placeholder="Ej: Desarrollador Full Stack, Enfermera Jefe, Ejecutivo de Ventas..."
                       required maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Continuar al Pago</button>
        </form>
    </div>
</div>

<script>
document.getElementById('target_industry').addEventListener('change', function() {
    var custom = document.getElementById('custom-industry-group');
    var input = document.getElementById('custom_industry');
    if (this.value === 'Otro') {
        custom.style.display = 'block';
        input.setAttribute('required', 'required');
    } else {
        custom.style.display = 'none';
        input.removeAttribute('required');
        input.value = '';
    }
});
// On load, if "Otro" is selected, mark custom_industry as required
if (document.getElementById('target_industry').value === 'Otro') {
    document.getElementById('custom_industry').setAttribute('required', 'required');
}
</script>
@endsection
