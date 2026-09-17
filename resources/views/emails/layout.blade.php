<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo', $marca)</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    <!-- Encabezado de marca -->
                    <tr>
                        <td style="background-color:#4f46e5; padding:20px 28px;">
                            <span style="font-family:Arial,Helvetica,sans-serif; font-size:18px; font-weight:bold; letter-spacing:0.5px; color:#ffffff;">
                                {{ $marca }}
                            </span>
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:28px; font-family:Arial,Helvetica,sans-serif; color:#1f2937; line-height:1.55; font-size:15px;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:0 28px 24px;">
                            @include('emails.partials.footer-no-reply')
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
