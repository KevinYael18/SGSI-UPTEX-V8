<x-reportes.layouts.base :subtitulo="'Bitácora del Sistema'">
    <table>
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Entidad</th>
            <th>Acción</th>
            <th>Usuario</th>
        </tr>
        </thead>
        <tbody>
        @foreach($registros as $r)
            <tr>
                <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $r->entidad }} #{{ $r->entidad_id }}</td>
                <td>{{ $r->accion }}</td>
                <td>{{ $r->usuario?->nombre ?? 'Sistema' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-reportes.layouts.base>