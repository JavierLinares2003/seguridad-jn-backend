<div class="seccion">
    <div class="seccion-titulo">Documentos Registrados</div>
    @if($personal->documentos->count() > 0)
    <table class="datos">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Fecha Vencimiento</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal->documentos->sortBy('nombre_documento') as $documento)
            <tr>
                <td>{{ $documento->tipoDocumento?->nombre ?? '-' }}</td>
                <td>{{ $documento->nombre_documento ?? '-' }}</td>
                <td>{{ $documento->descripcion ?? '-' }}</td>
                <td>{{ $documento->fecha_vencimiento?->format('d/m/Y') ?? 'N/A' }}</td>
                <td>
                    @php
                        $estadoDocMap = [
                            'vigente'  => ['label' => 'Vigente',  'class' => 'badge-activo'],
                            'vencido'  => ['label' => 'Vencido',  'class' => 'badge-inactivo'],
                            'pendiente'=> ['label' => 'Pendiente','class' => 'badge-neutro'],
                        ];
                        $estadoDoc = $estadoDocMap[$documento->estado_documento] ?? ['label' => ucfirst($documento->estado_documento ?? '-'), 'class' => 'badge-neutro'];
                    @endphp
                    <span class="badge {{ $estadoDoc['class'] }}">{{ $estadoDoc['label'] }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="sin-datos">Sin documentos registrados.</div>
    @endif
</div>
