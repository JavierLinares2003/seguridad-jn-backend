<div class="seccion">
    <div class="seccion-titulo">Información Personal</div>
    <div class="info-grid">
        @if($personal->dpi)
        <div class="info-row">
            <div class="info-label">DPI:</div>
            <div class="info-value">{{ $personal->dpi }}</div>
        </div>
        @endif
        @if($personal->nit)
        <div class="info-row">
            <div class="info-label">NIT:</div>
            <div class="info-value">{{ $personal->nit }}</div>
        </div>
        @endif
        @if($personal->fecha_nacimiento)
        <div class="info-row">
            <div class="info-label">Fecha de Nacimiento:</div>
            <div class="info-value">{{ $personal->fecha_nacimiento->format('d/m/Y') }}</div>
        </div>
        @endif
        @if($personal->sexo)
        <div class="info-row">
            <div class="info-label">Sexo:</div>
            <div class="info-value">{{ $personal->sexo->nombre }}</div>
        </div>
        @endif
        @if($personal->estadoCivil)
        <div class="info-row">
            <div class="info-label">Estado Civil:</div>
            <div class="info-value">{{ $personal->estadoCivil->nombre }}</div>
        </div>
        @endif
        @if($personal->tipoSangre)
        <div class="info-row">
            <div class="info-label">Tipo de Sangre:</div>
            <div class="info-value">{{ $personal->tipoSangre->nombre }}</div>
        </div>
        @endif
        @if($personal->telefono)
        <div class="info-row">
            <div class="info-label">Teléfono:</div>
            <div class="info-value">{{ $personal->telefono }}</div>
        </div>
        @endif
        @if($personal->email)
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value">{{ $personal->email }}</div>
        </div>
        @endif
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
        @if($personal->nivelEstudio)
        <div class="info-row">
            <div class="info-label">Nivel de Estudio:</div>
            <div class="info-value">{{ $personal->nivelEstudio->nombre }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Sabe Leer:</div>
            <div class="info-value">
                <span class="badge {{ $personal->sabe_leer ? 'badge-si' : 'badge-no' }}">{{ $personal->sabe_leer ? 'Sí' : 'No' }}</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Sabe Escribir:</div>
            <div class="info-value">
                <span class="badge {{ $personal->sabe_escribir ? 'badge-si' : 'badge-no' }}">{{ $personal->sabe_escribir ? 'Sí' : 'No' }}</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Sabe Usar Computadora:</div>
            <div class="info-value">
                <span class="badge {{ $personal->sabe_usar_computadora ? 'badge-si' : 'badge-no' }}">{{ $personal->sabe_usar_computadora ? 'Sí' : 'No' }}</span>
            </div>
        </div>
        @if($personal->numero_igss)
        <div class="info-row">
            <div class="info-label">No. IGSS:</div>
            <div class="info-value">{{ $personal->numero_igss }}</div>
        </div>
        @endif
        @if($personal->observaciones)
        <div class="info-row">
            <div class="info-label">Observaciones:</div>
            <div class="info-value">{{ $personal->observaciones }}</div>
        </div>
        @endif
    </div>
</div>
