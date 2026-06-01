<div class="seccion">
    <div class="seccion-titulo">Historial de Permisos</div>
    @if($personal->permisos->count() > 0)
    <table class="datos">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Cantidad Aprobada</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Descripción</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal->permisos->sortByDesc('fecha_inicio') as $permiso)
            <tr>
                <td>
                    <span class="badge badge-neutro">{{ ucfirst($permiso->tipo ?? '-') }}</span>
                </td>
                <td style="text-align:center;">
                    {{ $permiso->cantidad_aprobada ?? '-' }}
                    {{ $permiso->tipo === 'horas' ? 'h' : ($permiso->tipo === 'dias' ? 'd' : '') }}
                </td>
                <td>{{ $permiso->fecha_inicio?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $permiso->fecha_fin?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $permiso->descripcion ?? '-' }}</td>
                <td>{{ $permiso->observaciones ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="sin-datos">Sin registro de permisos.</div>
    @endif
</div>
