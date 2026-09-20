<?php

namespace App\Services;

use App\Models\PlantRecommendationRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlantRecommendationService
{
    private ?Collection $rules = null;

    /**
     * Pilih rekomendasi tanaman berbasis master komoditas RHL Jawa Barat.
     *
     * Data CPI saat ini selalu menyediakan wilayah dan umumnya menyediakan
     * kemiringan rata-rata. Parameter elevasi, curah hujan, dan jenis tanah
     * ikut dinilai jika engine memasoknya, sehingga rule ini future-proof.
     */
    public function recommend(array $row): array
    {
        $rules = $this->rules();
        if ($rules->isEmpty()) {
            return $this->emptyRecommendation('Master rekomendasi tanaman belum tersedia. Jalankan seeder PlantRecommendationRuleSeeder.');
        }

        $context = [
            'region' => $this->normalize((string) ($row['kota_kabupaten'] ?? $row['kabupaten'] ?? '')),
            'slope' => $this->number($row, ['slope_percent_rata2', 'slope_percent']),
            'elevation' => $this->number($row, ['elevation_rata2', 'elevation_mean', 'ketinggian_rata2']),
            'rainfall' => $this->number($row, ['rainfall_mm_rata2', 'rainfall_rata2', 'curah_hujan_rata2']),
            'soil' => $this->normalize((string) ($row['jenis_tanah_dominan'] ?? $row['soil_dominant'] ?? $row['jenis_tanah'] ?? '')),
        ];

        $ranked = $rules->map(function (PlantRecommendationRule $rule) use ($context) {
            return $this->scoreRule($rule, $context);
        })->sortByDesc('score')->values();

        $best = $ranked->first();
        if (!$best || $best['score'] < 0) {
            // Rule tanpa region_keywords adalah fallback generik dari master
            // "Lahan masyarakat sekitar kawasan hutan". Jangan memilih rule
            // wilayah lain hanya karena priority-nya lebih tinggi.
            $fallback = $rules->first(function (PlantRecommendationRule $rule) {
                return empty(array_filter($rule->region_keywords ?? []));
            });
            if (!$fallback) {
                return $this->emptyRecommendation('Tidak ada rule tanaman generik yang aktif untuk fallback.');
            }
            $best = [
                'rule' => $fallback,
                'score' => 0,
                'reasons' => ['Wilayah tidak cocok dengan rule spesifik; menggunakan rule generik lahan masyarakat sekitar kawasan hutan.'],
            ];
        }

        /** @var PlantRecommendationRule $rule */
        $rule = $best['rule'];

        return [
            'rule_id' => $rule->id,
            'rule_code' => $rule->code,
            'plants' => array_values($rule->recommended_plants ?? []),
            'category' => $rule->category,
            'region_name' => $rule->region_name,
            'rhl_function' => $rule->rhl_function,
            'notes' => $rule->notes,
            'reason' => implode(' ', $best['reasons']),
            'match_score' => round((float) $best['score'], 2),
        ];
    }

    private function rules(): Collection
    {
        return $this->rules ??= PlantRecommendationRule::active()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    private function scoreRule(PlantRecommendationRule $rule, array $context): array
    {
        $score = (float) $rule->priority / 10.0;
        $reasons = [];
        $keywords = array_values(array_filter(array_map(fn ($value) => $this->normalize((string) $value), $rule->region_keywords ?? [])));

        if ($keywords) {
            $regionMatch = $context['region'] !== '' && collect($keywords)->contains(
                fn (string $keyword) => Str::contains($context['region'], $keyword) || Str::contains($keyword, $context['region'])
            );

            if (!$regionMatch) {
                return ['rule' => $rule, 'score' => -1000.0, 'reasons' => []];
            }

            $score += 100.0;
            $reasons[] = "Wilayah cocok dengan {$rule->region_name}.";
        } else {
            $score += 5.0;
            $reasons[] = 'Rule bersifat umum untuk lahan masyarakat sekitar kawasan hutan.';
        }

        $score += $this->rangeScore($context['slope'], $rule->slope_min, $rule->slope_max, 30.0, 'kemiringan', $reasons, '%');
        $score += $this->rangeScore($context['elevation'], $rule->elevation_min, $rule->elevation_max, 20.0, 'ketinggian', $reasons, ' mdpl');
        $score += $this->rangeScore($context['rainfall'], $rule->rainfall_min, $rule->rainfall_max, 20.0, 'curah hujan', $reasons, ' mm/tahun');

        $soilKeywords = array_values(array_filter(array_map(fn ($value) => $this->normalize((string) $value), $rule->soil_keywords ?? [])));
        if ($context['soil'] !== '' && $soilKeywords) {
            $soilMatch = collect($soilKeywords)->contains(
                fn (string $keyword) => Str::contains($context['soil'], $keyword) || Str::contains($keyword, $context['soil'])
            );
            if ($soilMatch) {
                $score += 25.0;
                $reasons[] = 'Jenis tanah sesuai master komoditas.';
            } else {
                $score -= 10.0;
            }
        }

        return ['rule' => $rule, 'score' => $score, 'reasons' => $reasons];
    }

    private function rangeScore(?float $value, $min, $max, float $weight, string $label, array &$reasons, string $unit): float
    {
        if ($value === null || $min === null || $max === null) {
            return 0.0;
        }

        $min = (float) $min;
        $max = (float) $max;
        if ($value >= $min && $value <= $max) {
            $reasons[] = sprintf('%s %.2f%s berada pada rentang rule %.2f–%.2f%s.', ucfirst($label), $value, $unit, $min, $max, $unit);
            return $weight;
        }

        $distance = $value < $min ? $min - $value : $value - $max;
        $span = max(1.0, $max - $min);
        return -min($weight, ($distance / $span) * $weight);
    }

    private function number(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }
            if (is_numeric($row[$key])) {
                return (float) $row[$key];
            }
        }
        return null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();
    }

    private function emptyRecommendation(string $reason): array
    {
        return [
            'rule_id' => null,
            'rule_code' => null,
            'plants' => [],
            'category' => null,
            'region_name' => null,
            'rhl_function' => null,
            'notes' => null,
            'reason' => $reason,
            'match_score' => null,
        ];
    }
}
