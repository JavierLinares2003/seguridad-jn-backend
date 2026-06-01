<div class="header">
    <img src="{{ public_path('images/imagen-removebg-preview.png') }}" class="logo-empresa" alt="Logo Empresa">
    <div class="nombre-completo">{{ $personal->nombres }} {{ $personal->apellidos }}</div>
    <div class="puesto-header">{{ $personal->puesto }}</div>
    <div class="fecha-generacion">
        Expediente generado el {{ $fechaGeneracion->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }} a las {{ $fechaGeneracion->format('H:i:s') }}
    </div>
</div>
