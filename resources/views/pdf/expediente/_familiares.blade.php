<div class="seccion">
    <div class="seccion-titulo">Familiares y Contactos de Emergencia</div>
    @if($personal->familiares->count() > 0)
    <table class="datos">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Parentesco</th>
                <th>Teléfono</th>
                <th>Contacto Emergencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($personal->familiares as $familiar)
            <tr>
                <td>{{ $familiar->nombre_completo }}</td>
                <td>{{ $familiar->parentesco?->nombre ?? '-' }}</td>
                <td>{{ $familiar->telefono ?? '-' }}</td>
                <td>
                    <span class="badge {{ $familiar->es_contacto_emergencia ? 'badge-si' : 'badge-neutro' }}">
                        {{ $familiar->es_contacto_emergencia ? 'Sí' : 'No' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="sin-datos">Sin familiares registrados.</div>
    @endif
</div>
