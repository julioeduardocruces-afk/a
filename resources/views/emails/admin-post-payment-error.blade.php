<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #dc3545; color: #fff; padding: 20px 30px; }
        .header h1 { margin: 0; font-size: 20px; }
        .body { padding: 25px 30px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .info-table td { padding: 8px 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        .info-table td:first-child { font-weight: bold; color: #555; width: 40%; }
        .error-box { background: #fff3f3; border: 1px solid #f5c6cb; border-radius: 6px; padding: 15px; margin: 15px 0; }
        .error-box pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; font-size: 13px; color: #721c24; }
        .btn { display: inline-block; background: #007bff; color: #fff; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin-top: 15px; }
        .footer { padding: 15px 30px; background: #f8f9fa; color: #666; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Error Post-Pago - Requiere Atencion</h1>
        </div>
        <div class="body">
            <p>Se ha producido un error <strong>despues de que el usuario realizo el pago</strong>. Es necesario revisar y procesar manualmente.</p>

            <table class="info-table">
                <tr>
                    <td>Resume ID</td>
                    <td>#{{ $resume->id }}</td>
                </tr>
                <tr>
                    <td>Email cliente</td>
                    <td>{{ $customerEmail }}</td>
                </tr>
                <tr>
                    <td>Etapa del error</td>
                    <td>{{ $stageLabel }}</td>
                </tr>
                <tr>
                    <td>Estado actual resume</td>
                    <td>{{ $resume->status->value }}</td>
                </tr>
                @if($payment)
                <tr>
                    <td>Monto pagado</td>
                    <td>${{ number_format($payment->amount, 0, ',', '.') }} {{ $payment->currency }}</td>
                </tr>
                <tr>
                    <td>Flow Order</td>
                    <td>{{ $payment->flow_order ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Payment ID</td>
                    <td>#{{ $payment->id }}</td>
                </tr>
                <tr>
                    <td>Fecha pago</td>
                    <td>{{ $payment->updated_at->format('d/m/Y H:i') }}</td>
                </tr>
                @endif
                <tr>
                    <td>Cargo objetivo</td>
                    <td>{{ $resume->target_role ?? 'No especificado' }}</td>
                </tr>
            </table>

            <div class="error-box">
                <strong>Detalle del error:</strong>
                <pre>{{ $error }}</pre>
            </div>

            @if(!empty($extra))
            <p><strong>Contexto adicional:</strong></p>
            <table class="info-table">
                @foreach($extra as $key => $value)
                <tr>
                    <td>{{ $key }}</td>
                    <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
                </tr>
                @endforeach
            </table>
            @endif

            <p><strong>Acciones sugeridas:</strong></p>
            <ul>
                <li>Revisar el estado del resume en el panel de admin</li>
                <li>Si el procesamiento fallo, reintentar manualmente</li>
                <li>Si la entrega fallo, reenviar el email desde el panel</li>
                <li>Si no se puede resolver, contactar al cliente y gestionar reembolso</li>
            </ul>

            <a href="{{ $adminUrl }}" class="btn">Ir al Panel de Admin</a>
        </div>
        <div class="footer">
            Este es un mensaje automatico del sistema CV Optimizer ATS.<br>
            Fecha: {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
