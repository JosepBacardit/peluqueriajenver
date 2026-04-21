<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu código de descuento – Peluquería Jenver</title>
</head>
<body style="margin:0;padding:0;background-color:#0A0A0A;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0A0A0A;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#111111;border:1px solid #2A2A2A;">

                    <!-- Header -->
                    <tr>
                        <td style="padding:32px 40px;border-bottom:1px solid #2A2A2A;text-align:center;">
                            <p style="margin:0;color:#C9A84C;font-size:12px;letter-spacing:3px;text-transform:uppercase;font-weight:600;">
                                Peluquería Jenver · Montcada i Reixac
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:40px 40px 32px;">
                            <h1 style="margin:0 0 16px;color:#FFFFFF;font-size:26px;font-weight:700;line-height:1.3;">
                                Tu código de descuento está aquí
                            </h1>
                            <p style="margin:0 0 32px;color:#9CA3AF;font-size:15px;line-height:1.6;">
                                Gracias por tu interés en Peluquería Jenver. Aquí tienes tu código exclusivo de
                                <strong style="color:#FFFFFF;">{{ $percentage }}% de descuento</strong>
                                para tu primera visita:
                            </p>

                            <!-- Code Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
                                <tr>
                                    <td style="background-color:#1A1A1A;border:2px solid #C9A84C;padding:24px;text-align:center;">
                                        <p style="margin:0 0 6px;color:#9CA3AF;font-size:12px;letter-spacing:2px;text-transform:uppercase;">
                                            Tu código
                                        </p>
                                        <p style="margin:0;color:#C9A84C;font-size:32px;font-weight:700;letter-spacing:4px;font-family:monospace;">
                                            {{ $discountCode->code }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px;color:#9CA3AF;font-size:14px;line-height:1.6;">
                                Muestra este código al llegar a la peluquería para obtener tu descuento. Válido para cualquier servicio en tu primera visita.
                            </p>
                            <p style="margin:0 0 32px;color:#9CA3AF;font-size:14px;line-height:1.6;">
                                <strong style="color:#FFFFFF;">¿Cómo reservar?</strong> Llámanos al
                                <a href="tel:+34633912050" style="color:#C9A84C;text-decoration:none;">633 912 050</a>
                                o escríbenos por
                                <a href="https://wa.me/34633912050" style="color:#C9A84C;text-decoration:none;">WhatsApp</a>.
                            </p>

                            <!-- CTA Button -->
                            <table cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color:#C9A84C;">
                                        <a href="tel:+34633912050"
                                           style="display:inline-block;padding:14px 32px;color:#000000;font-size:14px;font-weight:700;text-decoration:none;letter-spacing:0.5px;">
                                            Reservar cita ahora →
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 40px;border-top:1px solid #2A2A2A;text-align:center;">
                            <p style="margin:0 0 4px;color:#6B7280;font-size:12px;">
                                Peluquería Jenver · Carrer Lleida, 21 · 08110 Montcada i Reixac · Barcelona
                            </p>
                            <p style="margin:0;color:#4B5563;font-size:11px;">
                                Recibiste este email porque lo solicitaste en nuestra web.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
