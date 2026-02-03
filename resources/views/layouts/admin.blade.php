@extends('layouts.app')
@section('content')
<div class="admin-layout" style="margin-top: -30px; margin-bottom: -30px;">
    <aside class="admin-sidebar">
        <div class="sidebar-section">General</div>
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('admin.resumes') }}" class="{{ request()->routeIs('admin.resumes') ? 'active' : '' }}">Gestionar CVs</a>
        <a href="{{ route('admin.free-process') }}" class="{{ request()->routeIs('admin.free-process') ? 'active' : '' }}">Procesar CV Gratis</a>
        <a href="{{ route('admin.credentials') }}" class="{{ request()->routeIs('admin.credentials') ? 'active' : '' }}">Credenciales</a>
        <a href="{{ route('admin.email-template') }}" class="{{ request()->routeIs('admin.email-template') ? 'active' : '' }}">Plantilla Email</a>

        <div class="sidebar-section">Finanzas</div>
        <a href="{{ route('admin.finance.dashboard') }}" class="{{ request()->routeIs('admin.finance.dashboard') ? 'active' : '' }}">Panel Financiero</a>
        <a href="{{ route('admin.finance.sales') }}" class="{{ request()->routeIs('admin.finance.sales') ? 'active' : '' }}">Ventas</a>
        <a href="{{ route('admin.finance.refunds') }}" class="{{ request()->routeIs('admin.finance.refunds') ? 'active' : '' }}">Reembolsos</a>
        <a href="{{ route('admin.finance.settlement') }}" class="{{ request()->routeIs('admin.finance.settlement') ? 'active' : '' }}">Balance / Liquidacion</a>
        <a href="{{ route('admin.finance.downloads') }}" class="{{ request()->routeIs('admin.finance.downloads') ? 'active' : '' }}">Descargas</a>
        <a href="{{ route('admin.finance.ai-usage') }}" class="{{ request()->routeIs('admin.finance.ai-usage') ? 'active' : '' }}">Uso de IA</a>

        <div class="sidebar-section">Reportes</div>
        <a href="{{ route('admin.metrics') }}" class="{{ request()->routeIs('admin.metrics') ? 'active' : '' }}">Metricas Operativas</a>
        <a href="{{ route('admin.audit-logs') }}" class="{{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}">Audit Logs</a>
    </aside>
    <div class="admin-main" style="padding: 30px 20px;">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @yield('admin-content')
    </div>
</div>
@endsection
