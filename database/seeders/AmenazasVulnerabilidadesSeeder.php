<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Amenaza;
use App\Models\Vulnerabilidad;
use Illuminate\Database\Seeder;

class AmenazasVulnerabilidadesSeeder extends Seeder
{
    public function run(): void
    {
        $amenazas = [
            ['nombre' => 'Acceso no autorizado', 'categoria' => 'humana_intencional'],
            ['nombre' => 'Malware / ransomware', 'categoria' => 'tecnica'],
            ['nombre' => 'Phishing dirigido', 'categoria' => 'humana_intencional'],
            ['nombre' => 'Falla de hardware', 'categoria' => 'tecnica'],
            ['nombre' => 'Corte de energía eléctrica', 'categoria' => 'ambiental'],
            ['nombre' => 'Error humano en configuración', 'categoria' => 'humana_no_intencional'],
            ['nombre' => 'Robo físico de equipo', 'categoria' => 'humana_intencional'],
            ['nombre' => 'Inundación / desastre natural', 'categoria' => 'ambiental'],
            ['nombre' => 'Denegación de servicio (DoS)', 'categoria' => 'tecnica'],
            ['nombre' => 'Fuga de información por terceros', 'categoria' => 'humana_intencional'],
        ];

        $vulnerabilidades = [
            ['nombre' => 'Contraseñas débiles o reutilizadas', 'categoria' => 'organizacional'],
            ['nombre' => 'Software sin parches de seguridad', 'categoria' => 'tecnica'],
            ['nombre' => 'Falta de cifrado en datos sensibles', 'categoria' => 'tecnica'],
            ['nombre' => 'Ausencia de respaldo periódico', 'categoria' => 'organizacional'],
            ['nombre' => 'Control de acceso físico insuficiente', 'categoria' => 'fisica'],
            ['nombre' => 'Personal sin capacitación en seguridad', 'categoria' => 'humana'],
            ['nombre' => 'Segmentación de red inexistente', 'categoria' => 'tecnica'],
            ['nombre' => 'Falta de monitoreo/logging centralizado', 'categoria' => 'tecnica'],
        ];

        foreach ($amenazas as $a) {
            Amenaza::query()->firstOrCreate(['nombre' => $a['nombre']], $a);
        }
        foreach ($vulnerabilidades as $v) {
            Vulnerabilidad::query()->firstOrCreate(['nombre' => $v['nombre']], $v);
        }
    }
}