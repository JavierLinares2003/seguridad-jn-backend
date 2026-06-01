<div class="seccion">
    <div class="seccion-titulo">Historial de Salarios</div>
    @if($personal->historialSalarios->count() > 0)
    <table class="datos">
        <thead>
            <tr>
                <th>Fecha de Cambio</th>
                <th>Salario Anterior</th>
                <th>Salario Nuevo</th>
                <th>Motivo</th>
                <th>Cambiado Por</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal->historialSalarios->sortByDesc('fecha_cambio') as $historial)
            <tr>
                <td>{{ $historial->fecha_cambio?->format('d/m/Y') ?? '-' }}</td>
                <td>Q {{ number_format($historial->salario_anterior, 2) }}</td>
                <td>Q {{ number_format($historial->salario_nuevo, 2) }}</td>
                <td>{{ $historial->motivo ?? '-' }}</td>
                <td>{{ $historial->cambiadoPor?->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="sin-datos">Sin historial de cambios de salario.</div>
    @endif
</div>
