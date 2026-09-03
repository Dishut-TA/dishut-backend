<?php

namespace Database\Seeders;

use App\Models\InterventionRecommendation;
use App\Models\InterventionType;
use Illuminate\Database\Seeder;

class InterventionRecommendationSeeder extends Seeder
{
    public function run()
    {
        $reboisasi = InterventionType::where('code', 'JI-001')->first();
        $agro = InterventionType::where('code', 'JI-002')->first();
        if ($reboisasi) {
            InterventionRecommendation::create(['intervention_type_id' => $reboisasi->id, 'name' => 'Penanaman Pohon Jati', 'description' => 'Pohon jati cocok untuk lahan dengan tanah kapur', 'status' => 'active']);
        }
        if ($agro) {
            InterventionRecommendation::create(['intervention_type_id' => $agro->id, 'name' => 'Sistem Lorong Tanaman Semusim', 'description' => 'Kombinasi tanaman semusim dengan pohon berkayu di lorong', 'status' => 'active']);
        }
    }
}
