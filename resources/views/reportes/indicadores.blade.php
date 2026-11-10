<x-reportes.layouts.base :subtitulo="'Reporte de Indicadores Ejecutivos'">
    <table>
        <thead>
        <tr>
            <th>Indicador</th>
            <th>Valor</th>
        </tr>
        </thead>
        <tbody>
            <tr>
                <td>Índice de cumplimiento ISO/IEC 27001:2022</td>
                <td>{{ $indicadores['indice_cumplimiento'] }}%</td>
            </tr>
            <tr>
                <td>Riesgos en nivel crítico</td>
                <td class="badge-critico">{{ $indicadores['riesgos_criticos'] }}</td>
            </tr>
            <tr>
                <td>Incidentes actualmente abiertos</td>
                <td>{{ $indicadores['incidentes_abiertos'] }}</td>
            </tr>
            <tr>
                <td>Controles del Anexo A implementados</td>
                <td>{{ $indicadores['controles_implementados'] }} / {{ $indicadores['controles_totales'] }}</td>
            </tr>
        </tbody>
    </table>

    <br>

    <table>
        <thead>
        <tr>
            <th>Mes</th>
            <th>Incidentes reportados</th>
        </tr>
        </thead>
        <tbody>
        @foreach($indicadores['incidentes_por_mes'] as $mes => $total)
            <tr>
                <td>{{ $mes }}</td>
                <td>{{ $total }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <br>

    <table>
        <thead>
        <tr>
            <th>Nivel de riesgo</th>
            <th>Cantidad</th>
        </tr>
        </thead>
        <tbody>
        @foreach($indicadores['riesgos_por_nivel'] as $nivel => $total)
            <tr>
                <td class="badge-{{ $nivel }}">{{ strtoupper($nivel) }}</td>
                <td>{{ $total }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-reportes.layouts.base>
