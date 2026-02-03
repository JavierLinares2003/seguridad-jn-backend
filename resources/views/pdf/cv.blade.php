<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>CV - {{ $personal->nombres }} {{ $personal->apellidos }}</title>
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
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            position: relative;
        }
        .logo-empresa {
            position: absolute;
            top: 0;
            right: 0;
            width: 100px; /* Ajusta el tamaño según sea necesario */
        }
        .foto-principal {
            text-align: center;
            margin-bottom: 20px;
        }
        .foto-principal img {
            max-width: 150px;
            max-height: 200px;
            border: 2px solid #333;
            border-radius: 8px;
        }
        .nombre-completo {
            font-weight: bold;
            font-size: 18pt;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .puesto {
            font-size: 12pt;
            color: #666;
            margin-bottom: 10px;
        }
        .seccion {
            margin-bottom: 20px;
        }
        .seccion-titulo {
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ccc;
            text-transform: uppercase;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 35%;
            font-weight: bold;
            padding: 4px 0;
        }
        .info-value {
            display: table-cell;
            padding: 4px 0;
        }
        .referencia-item {
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 3px solid #ccc;
        }
        .referencia-empresa {
            font-weight: bold;
            font-size: 11pt;
        }
        .referencia-puesto {
            color: #666;
            font-style: italic;
        }
        .referencia-periodo {
            font-size: 9pt;
            color: #999;
        }
        .redes-sociales {
            margin-top: 10px;
        }
        .red-social {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 5px;
        }
        .fotografias-grid {
            display: table;
            width: 100%;
        }
        .foto-row {
            display: table-row;
        }
        .foto-cell {
            display: table-cell;
            width: 50%;
            padding: 10px;
            text-align: center;
        }
        .foto-cell img {
            max-width: 200px;
            max-height: 250px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .foto-nombre {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <!-- Logo Empresa -->
        <img src="{{ public_path('images/imagen-removebg-preview.png') }}" class="logo-empresa" alt="Logo Empresa">
        
        <div class="nombre-completo">{{ $personal->nombres }} {{ $personal->apellidos }}</div>
        <div class="puesto">{{ $personal->puesto }}</div>
    </div>

    <!-- Foto Principal -->
    @if($fotoPrincipal)
    <div class="foto-principal">
        @php
            // Intentar primero con private/, si no existe intentar sin private/
            $fotoPath = storage_path('app/private/' . $fotoPrincipal->archivo_path);
            if (!file_exists($fotoPath)) {
                $fotoPath = storage_path('app/' . $fotoPrincipal->archivo_path);
            }

            if (file_exists($fotoPath)) {
                $imageData = base64_encode(file_get_contents($fotoPath));
                $extension = strtolower(str_replace('.', '', $fotoPrincipal->archivo_extension));

                // Determinar el tipo MIME basado en la extensión
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp'
                ];
                $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

                $src = 'data:' . $mimeType . ';base64,' . $imageData;
            } else {
                $src = '';
            }
        @endphp
        @if($src)
        <img src="{{ $src }}" alt="Foto Principal">
        @endif
    </div>
    @endif

    <!-- Información Personal -->
    <div class="seccion">
        <div class="seccion-titulo">Información Personal</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Fecha de Nacimiento:</div>
                <div class="info-value">{{ $personal->fecha_nacimiento?->format('d/m/Y') }}</div>
            </div>
            @if($personal->altura)
            <div class="info-row">
                <div class="info-label">Altura:</div>
                <div class="info-value">{{ $personal->altura }} m</div>
            </div>
            @endif
            @if($personal->peso)
            <div class="info-row">
                <div class="info-label">Peso:</div>
                <div class="info-value">{{ $personal->peso }} kg</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">Teléfono:</div>
                <div class="info-value">{{ $personal->telefono }}</div>
            </div>
            @if($personal->email)
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $personal->email }}</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Dirección -->
    @if($personal->direccion)
    <div class="seccion">
        <div class="seccion-titulo">Dirección</div>
        <div class="info-grid">
            @if($personal->direccion->direccion_completa)
            <div class="info-row">
                <div class="info-label">Dirección:</div>
                <div class="info-value">{{ $personal->direccion->direccion_completa }}</div>
            </div>
            @endif
            @if($personal->direccion->departamentoGeografico)
            <div class="info-row">
                <div class="info-label">Departamento:</div>
                <div class="info-value">{{ $personal->direccion->departamentoGeografico->nombre }}</div>
            </div>
            @endif
            @if($personal->direccion->municipio)
            <div class="info-row">
                <div class="info-label">Municipio:</div>
                <div class="info-value">{{ $personal->direccion->municipio->nombre }}</div>
            </div>
            @endif
            @if($personal->direccion->zona)
            <div class="info-row">
                <div class="info-label">Zona:</div>
                <div class="info-value">{{ $personal->direccion->zona }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Experiencia Laboral -->
    @if($personal->referenciasLaborales->count() > 0)
    <div class="seccion">
        <div class="seccion-titulo">Experiencia Laboral @if($personal->experiencia_laboral_formateada ?? false) ({{ $personal->experiencia_laboral_formateada }}) @endif</div>
        @foreach($personal->referenciasLaborales as $referencia)
        <div class="referencia-item">
            <div class="referencia-empresa">{{ $referencia->nombre_empresa }}</div>
            <div class="referencia-puesto">{{ $referencia->puesto_ocupado }}</div>
            <div class="referencia-periodo">
                {{ $referencia->fecha_inicio->format('d/m/Y') }} - {{ $referencia->fecha_fin->format('d/m/Y') }}
                @if($referencia->tiempo_laborado_formateado ?? false)
                ({{ $referencia->tiempo_laborado_formateado }})
                @endif
            </div>
            @if($referencia->telefono)
            <div>Teléfono: {{ $referencia->telefono }}</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Redes Sociales -->
    @if($personal->redesSociales->count() > 0)
    <div class="seccion">
        <div class="seccion-titulo">Redes Sociales</div>
        <div class="redes-sociales">
            @foreach($personal->redesSociales as $red)
            <div class="red-social">
                <strong>{{ ucfirst($red->redSocial->nombre ?? 'Red Social') }}:</strong> {{ $red->nombre_usuario }}
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Fotografías adicionales -->
    @if($fotografias->count() > 0)
    <div class="page-break"></div>
    <div class="seccion">
        <div class="seccion-titulo">Fotografías</div>
        <div class="fotografias-grid">
            @foreach($fotografias->chunk(2) as $chunk)
            <div class="foto-row">
                @foreach($chunk as $foto)
                <div class="foto-cell">
                    @php
                        // Intentar primero con private/, si no existe intentar sin private/
                        $fotoPath = storage_path('app/private/' . $foto->archivo_path);
                        if (!file_exists($fotoPath)) {
                            $fotoPath = storage_path('app/' . $foto->archivo_path);
                        }

                        if (file_exists($fotoPath)) {
                            $imageData = base64_encode(file_get_contents($fotoPath));
                            $extension = strtolower(str_replace('.', '', $foto->archivo_extension));

                            // Determinar el tipo MIME basado en la extensión
                            $mimeTypes = [
                                'jpg' => 'image/jpeg',
                                'jpeg' => 'image/jpeg',
                                'png' => 'image/png',
                                'gif' => 'image/gif',
                                'webp' => 'image/webp'
                            ];
                            $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

                            $src = 'data:' . $mimeType . ';base64,' . $imageData;
                        } else {
                            $src = '';
                        }
                    @endphp
                    @if($src)
                    <img src="{{ $src }}" alt="{{ $foto->nombre_documento }}">
                    @endif
                    <div class="foto-nombre">{{ $foto->nombre_documento }}</div>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
    @endif
</body>
</html>
