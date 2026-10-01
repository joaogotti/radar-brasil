<?php

namespace Database\Seeders;

use App\Models\AgeGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AgeGroupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $groups = config('demography.age_groups');
            if (count($groups) !== 21 || AgeGroup::whereNotIn('ibge_code', array_column($groups, 'ibge_code'))->exists()) {
                throw new RuntimeException('Unexpected age group catalog.');
            }
            foreach ($groups as $group) {
                $existing = AgeGroup::where('ibge_code', $group['ibge_code'])->first();
                if ($existing && ($existing->min_age !== $group['min_age'] || $existing->max_age !== $group['max_age'] || $existing->sort_order !== $group['sort_order'])) {
                    throw new RuntimeException('Age group semantics changed; review the catalog before syncing.');
                }
            }
            AgeGroup::upsert($groups, ['ibge_code'], ['label']);
        });
    }
}
