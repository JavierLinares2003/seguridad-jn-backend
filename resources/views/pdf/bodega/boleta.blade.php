<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta de bodega {{ $entrega->numero_boleta }}</title>
    <style>
        @page { margin: 18px 22px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
        .boleta { border: 2px solid #111; padding: 12px 14px 16px; }
        .top { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .brand { font-size: 13px; font-weight: bold; letter-spacing: 0.4px; }
        .title { font-size: 16px; font-weight: bold; text-align: center; }
        .numero { color: #b00020; font-size: 15px; font-weight: bold; text-align: right; }
        .logo { height: 42px; }
        .fecha { margin: 6px 0 10px; }
        .fecha span { display: inline-block; border-bottom: 1px solid #111; min-width: 36px; text-align: center; margin: 0 4px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 4px 0; vertical-align: bottom; }
        .lbl { font-weight: bold; width: 70px; }
        .line { border-bottom: 1px solid #111; }
        .checks { margin: 10px 0 8px; }
        .box { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; text-align: center; font-size: 9px; line-height: 11px; margin-right: 3px; }
        .checks strong { margin-right: 10px; }
        .obs { border: 1px solid #111; min-height: 90px; padding: 6px 8px; margin-top: 4px; }
        .obs-line { margin-bottom: 4px; }
        .firmas { width: 100%; margin-top: 28px; border-collapse: collapse; }
        .firmas td { width: 50%; text-align: center; padding-top: 18px; }
        .firma-line { border-top: 1px solid #111; margin: 0 18px; padding-top: 4px; font-size: 10px; }
        .muted { color: #444; font-size: 9px; }
    </style>
</head>
<body>
@php
    $fecha = $entrega->fecha_entrega;
    $dia = $fecha ? $fecha->format('d') : '';
    $mes = $fecha ? $fecha->format('m') : '';
    $anio = $fecha ? $fecha->format('y') : '';
    $dpi = preg_replace('/\D/', '', (string) ($personal->dpi ?? ''));
    $dpiFmt = strlen($dpi) === 13
        ? substr($dpi, 0, 4).' '.substr($dpi, 4, 5).' '.substr($dpi, 9, 4)
        : ($personal->dpi ?? '');
    $puesto = $personal->puesto ?: 'NUEVO INGRESO';
    $numero = $entrega->numero_boleta ?: str_pad((string) $entrega->id, 7, '0', STR_PAD_LEFT);
@endphp
<div class="boleta">
    <table class="top">
        <tr>
            <td style="width: 28%;">
                @if($logoPath)
                    <img src="{{ $logoPath }}" class="logo" alt="JN">
                @endif
                <div class="brand">SEGURIDAD JN</div>
            </td>
            <td class="title">BOLETA DE BODEGA</td>
            <td class="numero" style="width: 28%;">Nº {{ $numero }}</td>
        </tr>
    </table>

    <div class="fecha">
        <strong>FECHA:</strong>
        DIA: <span>{{ $dia }}</span>
        MES: <span>{{ $mes }}</span>
        AÑO: <span>{{ $anio }}</span>
    </div>

    <table class="grid">
        <tr>
            <td class="lbl">NOMBRE:</td>
            <td class="line">{{ strtoupper($personal->nombres.' '.$personal->apellidos) }}</td>
        </tr>
        <tr>
            <td class="lbl">DPI:</td>
            <td class="line">{{ $dpiFmt }}</td>
        </tr>
        <tr>
            <td class="lbl">PUESTO:</td>
            <td class="line">{{ strtoupper($puesto) }}</td>
        </tr>
        @if($entrega->personalOperaciones)
        <tr>
            <td class="lbl">VÍA OPS:</td>
            <td class="line">{{ strtoupper($entrega->personalOperaciones->nombres.' '.$entrega->personalOperaciones->apellidos) }} (lleva al punto)</td>
        </tr>
        @endif
        @if($entrega->cambio_por_dano)
        <tr>
            <td class="lbl">DAÑO:</td>
            <td class="line">CAMBIO POR DAÑO — prenda dañada no reingresa a stock</td>
        </tr>
        @endif
    </table>

    <div class="checks">
        <strong>UNIFORME:</strong>
        <span class="box">{{ !empty($esNuevo) ? 'X' : '' }}</span> NUEVO
        &nbsp;&nbsp;
        <span class="box">{{ !empty($esUsado) ? 'X' : '' }}</span> USADO
        &nbsp;&nbsp;&nbsp;
        <strong>CONTROL:</strong>
        <span class="box">{{ empty($esSalida) ? 'X' : '' }}</span> Entrada
        &nbsp;&nbsp;
        <span class="box">{{ !empty($esSalida) ? 'X' : '' }}</span> Salida
        &nbsp;&nbsp;&nbsp;
        <strong>PRECIO:</strong> Q. {{ number_format((float) $entrega->monto_total, 2) }}
    </div>

    <div><strong>OBSERVACIONES:</strong></div>
    <div class="obs">
        @foreach($entrega->items as $item)
            <div class="obs-line">
                {{ $item->cantidad }}
                {{ $item->variante?->producto?->nombre }}
                @if($item->variante?->etiqueta && $item->variante->etiqueta !== 'Única')
                    ({{ $item->variante->etiqueta }})
                @endif
                @if($entrega->cobrar)
                    — Q{{ number_format((float) $item->precio_unitario, 2) }}
                @endif
            </div>
        @endforeach
        @if($entrega->observaciones)
            <div class="obs-line">{{ $entrega->observaciones }}</div>
        @endif
        @if($entrega->motivo_reposicion)
            <div class="obs-line">Reposición: {{ $entrega->motivo_reposicion }}</div>
        @endif
    </div>

    <table class="firmas">
        <tr>
            <td>
                <div class="firma-line">{{ strtoupper($personal->nombres.' '.$personal->apellidos) }}</div>
            </td>
            <td>
                <div class="firma-line">AUTORIZADO POR BODEGA</div>
                <div class="muted">{{ $entrega->registradoPor?->name }}</div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
