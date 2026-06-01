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
        @if($personal->direccion->zona)
        <div class="info-row">
            <div class="info-label">Zona:</div>
            <div class="info-value">{{ $personal->direccion->zona }}</div>
        </div>
        @endif
        @if($personal->direccion->municipio)
        <div class="info-row">
            <div class="info-label">Municipio:</div>
            <div class="info-value">{{ $personal->direccion->municipio->nombre }}</div>
        </div>
        @endif
        @if($personal->direccion->departamentoGeografico)
        <div class="info-row">
            <div class="info-label">Departamento:</div>
            <div class="info-value">{{ $personal->direccion->departamentoGeografico->nombre }}</div>
        </div>
        @endif
    </div>
</div>
@endif
