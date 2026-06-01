<div class="seccion">
    <div class="seccion-titulo">Salud y Alergias</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">¿Es Alérgico?</div>
            <div class="info-value">
                <span class="badge {{ $personal->es_alergico ? 'badge-no' : 'badge-si' }}">
                    {{ $personal->es_alergico ? 'Sí' : 'No' }}
                </span>
            </div>
        </div>
        @if($personal->es_alergico && $personal->alergias)
        <div class="info-row">
            <div class="info-label">Descripción de Alergias:</div>
            <div class="info-value">{{ $personal->alergias }}</div>
        </div>
        @endif
    </div>
</div>
