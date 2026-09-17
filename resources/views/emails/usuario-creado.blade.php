@extends('emails.layout')

@section('titulo', 'Cuenta creada')

@section('content')
    <p style="margin:0 0 16px;">Hola {{ $usuario->nombre }},</p>

    <p style="margin:0 0 16px;">
        Se creó una cuenta para vos en <strong>{{ $marca }}</strong>. Esta es tu contraseña temporal:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
        <tr>
            <td style="background-color:#eef2ff; border:1px solid #c7d2fe; border-radius:8px; padding:14px 20px;
                       font-family:'Courier New',Courier,monospace; font-size:18px; font-weight:bold; color:#3730a3; letter-spacing:1px;">
                {{ $passwordTemporal }}
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;">
        Al iniciar sesión con ella se te pedirá definir una contraseña nueva antes de continuar.
    </p>

    <p style="margin:0;">— El equipo de {{ $marca }}</p>
@endsection
