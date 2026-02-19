<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura - CV Optimizer ATS</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);">
                    {{-- Header --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);padding:30px 40px;text-align:center;">
                            <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:700;">CV Optimizer ATS</h1>
                            <p style="color:#ffffff;opacity:0.8;margin:8px 0 0;font-size:14px;">Factura de Compra</p>
                        </td>
                    </tr>

                    {{-- Content --}}
                    <tr>
                        <td style="padding:40px;">
                            <p style="color:#333;font-size:16px;line-height:1.6;margin:0 0 20px;">
                                Estimado/a <strong>{{ $resume->billing_name ?: 'Cliente' }}</strong>,
                            </p>

                            <p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 24px;">
                                Adjuntamos la factura correspondiente a su compra del servicio de Optimizacion de CV para sistemas ATS.
                            </p>

                            {{-- Invoice Details Box --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-radius:8px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:24px;">
                                        <h3 style="color:#1a1a2e;margin:0 0 16px;font-size:16px;border-bottom:1px solid #e0e0e0;padding-bottom:12px;">
                                            Detalles de la Compra
                                        </h3>
                                        <table width="100%" style="font-size:14px;">
                                            <tr>
                                                <td style="color:#666;padding:6px 0;">Servicio:</td>
                                                <td style="color:#333;padding:6px 0;text-align:right;font-weight:500;">Optimizacion CV ATS</td>
                                            </tr>
                                            <tr>
                                                <td style="color:#666;padding:6px 0;">Fecha:</td>
                                                <td style="color:#333;padding:6px 0;text-align:right;">{{ $payment?->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
                                            </tr>
                                            @if($resume->invoice_number)
                                            <tr>
                                                <td style="color:#666;padding:6px 0;">N° Factura:</td>
                                                <td style="color:#333;padding:6px 0;text-align:right;font-family:monospace;">{{ $resume->invoice_number }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td colspan="2" style="border-top:1px solid #e0e0e0;padding-top:12px;margin-top:12px;"></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#333;padding:6px 0;font-weight:700;font-size:16px;">Total:</td>
                                                <td style="color:#0066ff;padding:6px 0;text-align:right;font-weight:700;font-size:18px;">
                                                    ${{ number_format($payment?->amount ?? config('ats.price_clp', 4990), 0, ',', '.') }} CLP
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Attachment Notice --}}
                            <div style="background:#e8f4fd;border-radius:8px;padding:16px;margin-bottom:24px;">
                                <p style="margin:0;color:#0066ff;font-size:14px;">
                                    <strong>📎 Archivo adjunto:</strong> Factura_CVOptimizerATS.pdf
                                </p>
                            </div>

                            {{-- Billing Data --}}
                            <h3 style="color:#1a1a2e;margin:0 0 12px;font-size:14px;">Datos de Facturacion:</h3>
                            <p style="color:#666;font-size:13px;line-height:1.6;margin:0 0 24px;">
                                {{ $resume->billing_name }}<br>
                                RUT: {{ $resume->getFormattedRut() }}<br>
                                {{ $resume->billing_address }}<br>
                                {{ $resume->billing_city }}
                            </p>

                            <p style="color:#555;font-size:14px;line-height:1.6;margin:0;">
                                Si tiene alguna consulta sobre esta factura, no dude en contactarnos.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f8f9fa;padding:24px 40px;text-align:center;border-top:1px solid #e0e0e0;">
                            <p style="color:#888;font-size:12px;margin:0 0 8px;">
                                CV Optimizer ATS - Optimiza tu curriculum para sistemas de seguimiento de candidatos
                            </p>
                            <p style="color:#aaa;font-size:11px;margin:0;">
                                Este correo fue enviado a {{ $resume->customer_email }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
