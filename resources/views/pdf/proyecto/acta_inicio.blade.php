<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Aceptación de Servicio</title>
    <style>
        @page {
            margin: 40px 50px 50px 50px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            min-height: 60px;
        }
        .logo-empresa {
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
        }
        .company-name {
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 5px;
            padding-top: 10px;
        }
        .license {
            font-size: 10pt;
            color: #666;
        }
        .title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 30px 0;
        }
        .content {
            text-align: justify;
            margin: 0;
            line-height: 1.8;
        }
        .paragraph {
            text-align: justify;
            margin: 15px 0;
            line-height: 1.8;
        }
        .bold {
            font-weight: bold;
        }
        .field-group {
            margin: 15px 0;
        }
        .checkbox-group {
            margin: 15px 0;
        }
        .checkbox {
            display: inline-block;
            width: 20px;
            height: 15px;
            border: 1px solid #000;
            margin: 0 10px;
            vertical-align: middle;
            position: relative;
        }
        /* Custom checkbox simulation for PDF */
        .checkbox.checked .check-mark {
            position: absolute;
            top: -2px;
            left: 4px;
            content: "X";
            font-weight: bold;
        }
        .signatures {
            margin-top: 80px;
            width: 100%;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-cell {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 20px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 0 auto;
            width: 80%;
            padding-top: 60px;
            margin-bottom: 5px;
        }
        .footer {
            position: fixed;
            bottom: 30px;
            left: 50px;
            right: 50px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('images/imagen-removebg-preview.png') }}" class="logo-empresa" alt="Logo Empresa">
        <div class="company-name">SEGURIDAD JN</div>
        <div class="license">LICENCIA TIPO A</div>
    </div>

    <div class="title">ACEPTACION DE SERVICIO</div>

    <div class="paragraph">
        En la presente fecha <span class="bold">{{ $aceptacion->fecha_aceptacion->locale('es')->isoFormat('D [de] MMMM [del] YYYY') }}</span> nos encontramos reunidos para prestar los servicios de seguridad privada en: <span class="bold">{{ $proyecto->nombre_proyecto }}</span> ubicado en: <span class="bold">{{ 
            trim(
                ($proyecto->ubicacion->direccion_completa ?? '') .
                ($proyecto->ubicacion->zona ? ', Zona ' . $proyecto->ubicacion->zona : '') .
                ($proyecto->ubicacion->municipio ? ', ' . $proyecto->ubicacion->municipio->nombre : '') .
                ($proyecto->ubicacion->departamentoGeografico ? ', ' . $proyecto->ubicacion->departamentoGeografico->nombre : '')
            ) 
        }}</span>, con la asesoría de la empresa de seguridad de SEGURIDAD JN.
    </div>

    <div class="paragraph">
        Yo: <span class="bold">{{ $aceptacion->firmante_nombre }}</span>, quien me identifico con documento único de identificación DPI: <span class="bold">{{ $aceptacion->firmante_dpi }}</span>, fungiendo como: <span class="bold">{{ $aceptacion->firmante_puesto }}</span>.
    </div>

    <div class="paragraph">
        Autorizo con fecha: <span class="bold">{{ $aceptacion->fecha_inicio_servicio ? $aceptacion->fecha_inicio_servicio->locale('es')->isoFormat('D [de] MMMM [del] YYYY') : 'Por definir' }}</span>, para iniciar los servicios de seguridad privada, en la dirección antes mencionada.
    </div>

    <div class="paragraph">
        Cantidad de agente(s) de seguridad serán distribuidos de la siguiente manera: <span class="bold">{{ $totalAgentes }}</span> agente(s) en turno de <span class="bold">{{ $turno }}</span>
    </div>

    <div class="paragraph">
        Forma de pago: 
        TRANSFERENCIA <span class="checkbox {{ str_contains($formaPago, 'transferencia') ? 'checked' : '' }}"><span class="check-mark">@if(str_contains($formaPago, 'transferencia')) X @endif</span></span>
        CHEQUE <span class="checkbox {{ str_contains($formaPago, 'cheque') ? 'checked' : '' }}"><span class="check-mark">@if(str_contains($formaPago, 'cheque')) X @endif</span></span>
        EFECTIVO <span class="checkbox {{ str_contains($formaPago, 'efectivo') ? 'checked' : '' }}"><span class="check-mark">@if(str_contains($formaPago, 'efectivo')) X @endif</span></span>.
    </div>

    <div class="paragraph">
        Precio por agente: <span class="bold">Q{{ number_format($precioAgente, 2) }}</span> Costo total del servicio: <span class="bold">Q{{ number_format($costoTotal, 2) }}</span>.
    </div>

    @if($proyecto->facturacion)
        <div class="paragraph">
            Datos facturación / Nombre: <span class="bold">{{ $proyecto->facturacion->nombre_facturacion ?? 'N/A' }}</span>. Dirección: <span class="bold">{{ $proyecto->facturacion->direccion_facturacion ?? 'N/A' }}</span>. NIT: <span class="bold">{{ $proyecto->facturacion->nit_cliente ?? 'N/A' }}</span>.
        </div>
    @endif

    <div class="signatures">
        <table class="signature-table">
            <tr>
                <td class="signature-cell">
                    <div class="signature-line">F_____________________________</div>
                    <div style="font-size: 8pt; margin-top: 5px;">CLIENTE</div>
                </td>
                <td class="signature-cell">
                    <div class="signature-line">F_____________________________</div>
                    <div style="font-size: 8pt; margin-top: 5px;">SEGURIDAD JN</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        6ta avenida 10-09 zona 11<br>
        ☎ 2308-8000 | ⓦ www.seguridadjn.com
    </div>
</body>
</html>
