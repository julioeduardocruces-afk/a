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
                    <option value="Tecnologias de la Informacion" {{ old('target_industry', $resume->target_industry) === 'Tecnologias de la Informacion' ? 'selected' : '' }}>Tecnologias de la Informacion (TI)</option>
                    <option value="Salud / Hemodialisis" {{ old('target_industry', $resume->target_industry) === 'Salud / Hemodialisis' ? 'selected' : '' }}>Salud / Hemodialisis</option>
                    <option value="Ventas y Comercial" {{ old('target_industry', $resume->target_industry) === 'Ventas y Comercial' ? 'selected' : '' }}>Ventas y Comercial</option>
                    <option value="Finanzas / Factoring" {{ old('target_industry', $resume->target_industry) === 'Finanzas / Factoring' ? 'selected' : '' }}>Finanzas / Factoring</option>
                    <option value="Ingenieria" {{ old('target_industry', $resume->target_industry) === 'Ingenieria' ? 'selected' : '' }}>Ingenieria</option>
                    <option value="Educacion" {{ old('target_industry', $resume->target_industry) === 'Educacion' ? 'selected' : '' }}>Educacion</option>
                    <option value="Logistica y Transporte" {{ old('target_industry', $resume->target_industry) === 'Logistica y Transporte' ? 'selected' : '' }}>Logistica y Transporte</option>
                    <option value="Marketing Digital" {{ old('target_industry', $resume->target_industry) === 'Marketing Digital' ? 'selected' : '' }}>Marketing Digital</option>
                    <option value="Recursos Humanos" {{ old('target_industry', $resume->target_industry) === 'Recursos Humanos' ? 'selected' : '' }}>Recursos Humanos</option>
                    <option value="Administracion" {{ old('target_industry', $resume->target_industry) === 'Administracion' ? 'selected' : '' }}>Administracion</option>
                    <option value="Construccion" {{ old('target_industry', $resume->target_industry) === 'Construccion' ? 'selected' : '' }}>Construccion</option>
                    <option value="Legal" {{ old('target_industry', $resume->target_industry) === 'Legal' ? 'selected' : '' }}>Legal</option>
                    <option value="Mineria" {{ old('target_industry', $resume->target_industry) === 'Mineria' ? 'selected' : '' }}>Mineria</option>
                    <option value="Otro" {{ old('target_industry', $resume->target_industry) === 'Otro' ? 'selected' : '' }}>Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label for="target_role">Cargo Objetivo</label>
                <input type="text" id="target_role" name="target_role"
                       value="{{ old('target_role', $resume->target_role) }}"
                       placeholder="Ej: Desarrollador Full Stack, Enfermera Jefe, Ejecutivo de Ventas..."
                       required maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Continuar y Procesar</button>
        </form>
    </div>
</div>
@endsection
