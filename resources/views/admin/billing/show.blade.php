@extends('layouts.admin')
@section('title', 'Facturar - #' . $resume->id)
@section('admin-content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
        <a href="{{ route('admin.billing.index') }}" style="color:#666;text-decoration:none;font-size:13px;">&larr; Volver al listado</a>
        <h1 style="margin-top:8px;">Facturar Venta #{{ $resume->id }}</h1>
    </div>
    <div>
        @if($resume->invoice_status === 'pending')
            <span class="badge" style="background:#fff3cd;color:#856404;padding:8px 16px;font-size:14px;">Pendiente</span>
        @elseif($resume->invoice_status === 'issued')
            <span class="badge" style="background:#cce5ff;color:#004085;padding:8px 16px;font-size:14px;">Emitida</span>
        @else
            <span class="badge" style="background:#d4edda;color:#155724;padding:8px 16px;font-size:14px;">Enviada</span>
        @endif
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    {{-- Billing Data --}}
    <div class="card">
        <h3 style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee;">Datos de Facturacion</h3>

        <table style="width:100%;">
            <tr>
                <td style="color:#666;width:140px;padding:8px 0;">Nombre:</td>
                <td style="font-weight:600;padding:8px 0;">{{ $resume->billing_name ?: 'No proporcionado' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">RUT:</td>
                <td style="font-weight:600;font-family:monospace;padding:8px 0;">{{ $resume->getFormattedRut() ?: 'No proporcionado' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Direccion:</td>
                <td style="padding:8px 0;">{{ $resume->billing_address ?: '-' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Ciudad:</td>
                <td style="padding:8px 0;">{{ $resume->billing_city ?: '-' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Telefono:</td>
                <td style="padding:8px 0;">{{ $resume->billing_phone ?: '-' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Email:</td>
                <td style="padding:8px 0;">
                    <a href="mailto:{{ $resume->customer_email }}">{{ $resume->customer_email ?: '-' }}</a>
                </td>
            </tr>
        </table>
    </div>

    {{-- Payment Info --}}
    <div class="card">
        <h3 style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee;">Datos del Pago</h3>

        <table style="width:100%;">
            <tr>
                <td style="color:#666;width:140px;padding:8px 0;">Monto:</td>
                <td style="font-weight:700;font-size:1.3rem;color:#0066ff;padding:8px 0;">
                    ${{ number_format($payment?->amount ?? config('ats.price_clp', 4990), 0, ',', '.') }} CLP
                </td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Fecha Pago:</td>
                <td style="padding:8px 0;">{{ $payment?->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Metodo:</td>
                <td style="padding:8px 0;">{{ ucfirst($payment?->provider ?? 'Flow') }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Ref. Pago:</td>
                <td style="font-family:monospace;font-size:12px;padding:8px 0;">{{ $payment?->provider_order_id ?? '-' }}</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Servicio:</td>
                <td style="padding:8px 0;">Optimizacion CV ATS</td>
            </tr>
            <tr>
                <td style="color:#666;padding:8px 0;">Archivo CV:</td>
                <td style="font-size:12px;padding:8px 0;">{{ $resume->original_filename }}</td>
            </tr>
        </table>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">
    {{-- Mark as Issued --}}
    <div class="card">
        <h3 style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee;">1. Marcar como Emitida</h3>

        @if($resume->invoice_status === 'pending')
            <form method="POST" action="{{ route('admin.billing.mark-issued', $resume->id) }}">
                @csrf
                <div class="form-group">
                    <label>Numero de Factura/Boleta (opcional)</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number', $resume->invoice_number) }}" placeholder="Ej: 12345">
                </div>
                <button type="submit" class="btn btn-primary">Marcar como Emitida</button>
            </form>
        @else
            <div style="background:#d4edda;padding:16px;border-radius:8px;color:#155724;">
                <strong>Factura emitida</strong>
                @if($resume->invoice_number)
                    <br>Numero: {{ $resume->invoice_number }}
                @endif
                @if($resume->invoice_issued_at)
                    <br>Fecha: {{ $resume->invoice_issued_at->format('d/m/Y H:i') }}
                @endif
            </div>
        @endif
    </div>

    {{-- Upload Invoice --}}
    <div class="card">
        <h3 style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee;">2. Adjuntar Factura (PDF)</h3>

        @if($resume->invoice_file && \Illuminate\Support\Facades\Storage::disk('local')->exists($resume->invoice_file))
            <div style="background:#e8f4fd;padding:16px;border-radius:8px;margin-bottom:16px;">
                <strong style="color:#0066ff;">Archivo adjunto:</strong>
                <br>{{ basename($resume->invoice_file) }}
                <div style="margin-top:12px;">
                    <form method="POST" action="{{ route('admin.billing.remove-invoice', $resume->id) }}" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar archivo?')">Eliminar</button>
                    </form>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.billing.upload-invoice', $resume->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>{{ $resume->invoice_file ? 'Reemplazar archivo' : 'Seleccionar archivo' }}</label>
                <input type="file" name="invoice_file" accept=".pdf" required>
                <small style="color:#888;">Solo archivos PDF, maximo 5MB</small>
            </div>
            <button type="submit" class="btn btn-primary">Subir Factura</button>
        </form>
    </div>
</div>

{{-- Send Email --}}
<div class="card" style="margin-top:24px;">
    <h3 style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee;">3. Enviar Factura por Email</h3>

    @if(!$resume->invoice_file || !\Illuminate\Support\Facades\Storage::disk('local')->exists($resume->invoice_file))
        <div class="alert alert-info" style="margin-bottom:0;">
            Primero debes adjuntar el archivo PDF de la factura antes de enviarla.
        </div>
    @else
        <div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;">
            <p style="margin-bottom:12px;"><strong>Vista previa del email:</strong></p>
            <div style="background:white;padding:20px;border-radius:8px;border:1px solid #ddd;">
                <p><strong>Para:</strong> {{ $resume->customer_email }}</p>
                <p><strong>Asunto:</strong> Factura - CV Optimizer ATS</p>
                <hr style="margin:12px 0;">
                <p>Estimado/a {{ $resume->billing_name ?: 'Cliente' }},</p>
                <p>Adjuntamos la factura correspondiente a su compra de Optimizacion de CV.</p>
                <p><strong>Monto:</strong> ${{ number_format($payment?->amount ?? config('ats.price_clp', 4990), 0, ',', '.') }} CLP</p>
                <p><strong>Archivo adjunto:</strong> Factura_CVOptimizerATS.pdf</p>
            </div>
        </div>

        @if($resume->invoice_status === 'sent')
            <div class="alert alert-success" style="margin-bottom:16px;">
                <strong>Factura ya enviada</strong> el {{ $resume->invoice_sent_at?->format('d/m/Y H:i') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.billing.send-invoice', $resume->id) }}">
            @csrf
            <button type="submit" class="btn btn-success" style="font-size:1.1rem;padding:12px 32px;" onclick="return confirm('Enviar factura a {{ $resume->customer_email }}?')">
                {{ $resume->invoice_status === 'sent' ? 'Reenviar Factura' : 'Enviar Factura' }}
            </button>
        </form>
    @endif
</div>
@endsection
