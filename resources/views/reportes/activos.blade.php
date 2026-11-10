<x-reportes.layouts.base :subtitulo="'Reporte de Inventario de Activos'">
    <table>
        <thead>
        <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Departamento</th>
            <th>Criticidad</th>
            <th>Responsable</th>
        </tr>
        </thead>
        <tbody>
        @foreach($activos as $activo)
            <tr>
                <td>{{ $activo->nombre }}</td>
                <td>{{ $activo->tipo->value }}</td>
                <td>{{ $activo->departamento->nombre }}</td>
                <td>{{ $activo->criticidad_calculada }}</td>
                <td>{{ $activo->responsable->nombre ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-reportes.layouts.base>