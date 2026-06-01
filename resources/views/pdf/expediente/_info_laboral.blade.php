<div class="seccion">
    <div class="seccion-titulo">Información Laboral</div>
    <div class="info-grid">
        @if($personal->puesto)
        <div class="info-row">
            <div class="info-label">Puesto:</div>
            <div class="info-value">{{ $personal->puesto }}</div>
        </div>
        @endif
        @if($personal->departamento)
        <div class="info-row">
            <div class="info-label">Departamento:</div>
            <div class="info-value">{{ $personal->departamento->nombre }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Estado:</div>
            <div class="info-value">
                <span class="badge {{ $personal->estado ? 'badge-activo' : 'badge-inactivo' }}">
                    {{ $personal->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
        </div>
        @if($personal->fecha_inicio)
        <div class="info-row">
            <div class="info-label">Fecha de Ingreso:</div>
            <div class="info-value">{{ $personal->fecha_inicio->format('d/m/Y') }}</div>
        </div>
        @endif
        @if($personal->tipoContratacion)
        <div class="info-row">
            <div class="info-label">Tipo de Contratación:</div>
            <div class="info-value">{{ $personal->tipoContratacion->nombre }}</div>
        </div>
        @endif

        @if($personal->tipoPago)
        <div class="info-row">
            <div class="info-label">Tipo de Pago:</div>
            <div class="info-value">{{ $personal->tipoPago->nombre }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">IGSS:</div>
            <div class="info-value">
                <span class="badge {{ $personal->tiene_igss ? 'badge-si' : 'badge-no' }}">{{ $personal->tiene_igss ? 'Sí' : 'No' }}</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Bono 14:</div>
            <div class="info-value">
                <span class="badge {{ $personal->tiene_bono14 ? 'badge-si' : 'badge-no' }}">{{ $personal->tiene_bono14 ? 'Sí' : 'No' }}</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Prestaciones:</div>
            <div class="info-value">
                <span class="badge {{ $personal->tiene_prestaciones ? 'badge-si' : 'badge-no' }}">{{ $personal->tiene_prestaciones ? 'Sí' : 'No' }}</span>
            </div>
        </div>
        @if($personal->banco)
        <div class="info-row">
            <div class="info-label">Banco:</div>
            <div class="info-value">{{ $personal->banco }}</div>
        </div>
        @endif
        @if($personal->tipo_cuenta)
        <div class="info-row">
            <div class="info-label">Tipo de Cuenta:</div>
            <div class="info-value">{{ $personal->tipo_cuenta }}</div>
        </div>
        @endif
        @if($personal->numero_cuenta)
        <div class="info-row">
            <div class="info-label">Número de Cuenta:</div>
            <div class="info-value">{{ $personal->numero_cuenta }}</div>
        </div>
        @endif
        @if($personal->nombre_cuenta)
        <div class="info-row">
            <div class="info-label">Nombre en Cuenta:</div>
            <div class="info-value">{{ $personal->nombre_cuenta }}</div>
        </div>
        @endif
    </div>
</div>
