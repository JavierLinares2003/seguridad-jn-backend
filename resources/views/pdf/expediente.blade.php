<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Expediente - {{ $personal->nombres }} {{ $personal->apellidos }}</title>
    <style>
        @page {
            margin: 40px 50px;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            position: relative;
        }
        .logo-empresa {
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
        }
        .nombre-completo {
            font-weight: bold;
            font-size: 16pt;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .puesto-header {
            font-size: 11pt;
            color: #555;
            margin-bottom: 6px;
        }
        .fecha-generacion {
            font-size: 8pt;
            color: #999;
            margin-top: 8px;
        }
        .seccion {
            margin-bottom: 18px;
        }
        .seccion-titulo {
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 8px;
            padding: 4px 8px;
            background-color: #f0f0f0;
            border-left: 4px solid #555;
            text-transform: uppercase;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 35%;
            font-weight: bold;
            padding: 3px 0;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            padding: 3px 0;
            vertical-align: top;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        table.datos th {
            background-color: #e8e8e8;
            font-weight: bold;
            padding: 5px 6px;
            text-align: left;
            border: 1px solid #ccc;
        }
        table.datos td {
            padding: 4px 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        table.datos tr:nth-child(even) td {
            background-color: #fafafa;
        }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-si {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-no {
            background-color: #f8d7da;
            color: #721c24;
        }
        .badge-activo {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-inactivo {
            background-color: #f8d7da;
            color: #721c24;
        }
        .badge-neutro {
            background-color: #e2e3e5;
            color: #383d41;
        }
        .page-break {
            page-break-before: always;
        }
        .sin-datos {
            color: #999;
            font-style: italic;
            font-size: 9pt;
            padding: 4px 0;
        }
        .firma-seccion {
            margin-top: 50px;
            padding-top: 20px;
        }
        .firma-linea {
            display: inline-block;
            width: 250px;
            border-top: 1px solid #333;
            margin-top: 50px;
            text-align: center;
            padding-top: 4px;
        }
        .firma-nombre {
            font-weight: bold;
            font-size: 10pt;
        }
        .firma-puesto {
            font-size: 9pt;
            color: #555;
        }
    </style>
</head>
<body>

@include('pdf.expediente._header')

@if($modulos['info_personal'])
    @include('pdf.expediente._info_personal')
@endif

@if($modulos['info_laboral'])
    @include('pdf.expediente._info_laboral')
@endif

@if($modulos['direccion'])
    @include('pdf.expediente._direccion')
@endif

@if($modulos['salud_alergias'])
    @include('pdf.expediente._salud')
@endif

@if($modulos['familiares'])
    @include('pdf.expediente._familiares')
@endif

@if($modulos['proyectos'])
    @include('pdf.expediente._proyectos')
@endif

@if($modulos['vacaciones'])
    @include('pdf.expediente._vacaciones')
@endif

@if($modulos['permisos'])
    @include('pdf.expediente._permisos')
@endif

@if($modulos['historial_salario'])
    @include('pdf.expediente._historial_salario')
@endif

@if($modulos['documentos'])
    @include('pdf.expediente._documentos')
@endif

<div class="firma-seccion">
    <div class="firma-linea">
        <div class="firma-nombre">{{ strtoupper($personal->nombres . ' ' . $personal->apellidos) }}</div>
        <div class="firma-puesto">{{ $personal->puesto ?? 'Personal' }}</div>
    </div>
</div>

</body>
</html>
