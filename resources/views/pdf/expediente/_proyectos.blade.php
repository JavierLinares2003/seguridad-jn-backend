<div class="seccion">
    <div class="seccion-titulo">Historial de Proyectos Asignados</div>
    @if($personal->asignaciones->count() > 0)
    <table class="datos">
        <thead>
            <tr>
                <th>Proyecto</th>
                <th>Puesto</th>
                <th>Turno</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal->asignaciones->sortByDesc('fecha_inicio') as $asignacion)
            <tr>
                <td>{{ $asignacion->proyecto?->nombre_proyecto ?? '-' }}</td>
                <td>{{ $asignacion->configuracionPuesto?->nombre_puesto ?? '-' }}</td>
                <td>{{ $asignacion->turno?->nombre ?? '-' }}</td>
                <td>{{ $asignacion->fecha_inicio?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $asignacion->fecha_fin?->format('d/m/Y') ?? 'Actual' }}</td>
                <td>
                    @php
                        $estadoMap = [
                            'activo'     => ['label' => 'Activo',     'class' => 'badge-activo'],
                            'inactivo'   => ['label' => 'Inactivo',   'class' => 'badge-inactivo'],
                            'suspendido' => ['label' => 'Suspendido', 'class' => 'badge-neutro'],
                        ];
                        $estadoInfo = $estadoMap[$asignacion->estado_asignacion] ?? ['label' => ucfirst($asignacion->estado_asignacion ?? '-'), 'class' => 'badge-neutro'];
                    @endphp
                    <span class="badge {{ $estadoInfo['class'] }}">{{ $estadoInfo['label'] }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="sin-datos">Sin proyectos asignados.</div>
    @endif
</div>
