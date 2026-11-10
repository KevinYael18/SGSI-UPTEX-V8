<x-reportes.layouts.base :subtitulo="'Reporte de Riesgos'">
    <table>
        <thead>
        <tr>
            <th>Activo</th>
            <th>Amenaza</th>
            <th>Vulnerabilidad</th>
            <th>Nivel</th>
            <th>Tratamiento</th>
        </tr>
        </thead>
        <tbody>
        @foreach($riesgos as $riesgo)
            <tr>
                <td>{{ $riesgo->activo->nombre }}</td>
                <td>{{ $riesgo->amenaza->nombre }}</td>
                <td>{{ $riesgo->vulnerabilidad->nombre }}</td>
                <td class="badge-{{ $riesgo->nivel_calculado->value }}">{{ strtoupper($riesgo->nivel_calculado->value) }}</td>
                <td>{{ $riesgo->tipo_tratamiento->value ?? 'Sin definir' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-reportes.layouts.base>