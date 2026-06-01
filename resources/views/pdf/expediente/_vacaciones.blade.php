<div class="seccion">
    <div class="seccion-titulo">Historial de Vacaciones</div>
    @if($personal->vacaciones->count() > 0)
    <table class="datos">
        <thead>
            <tr>
                <th>Año</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Días Solicitados</th>
                <th>Días Aprobados</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal->vacaciones->sortByDesc('fecha_inicio') as $vacacion)
            <tr>
                <td>{{ $vacacion->anio ?? $vacacion->fecha_inicio?->year ?? '-' }}</td>
                <td>{{ $vacacion->fecha_inicio?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $vacacion->fecha_fin?->format('d/m/Y') ?? '-' }}</td>
                <td style="text-align:center;">{{ $vacacion->dias_solicitados ?? '-' }}</td>
                <td style="text-align:center;">{{ $vacacion->dias_aprobados ?? '-' }}</td>
                <td>{{ $vacacion->descripcion ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="sin-datos">Sin registro de vacaciones.</div>
    @endif
</div>
