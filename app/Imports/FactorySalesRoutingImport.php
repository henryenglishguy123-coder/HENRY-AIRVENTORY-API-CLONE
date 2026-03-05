<?php

namespace App\Imports;

use App\Models\Factory\FactorySalesRouting;
use App\Models\Location\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FactorySalesRoutingImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {

            $clearedFactoryIds = [];

            foreach ($rows as $row) {
                if (
                    empty($row['factory_id']) ||
                    empty($row['priority']) ||
                    empty($row['country_codes'])
                ) {
                    continue;
                }

                $factoryId = (int) $row['factory_id'];

                $countryCodes = array_map(
                    'trim',
                    explode(',', $row['country_codes'])
                );

                $countryIds = Country::whereIn('iso2', $countryCodes)
                    ->pluck('id')
                    ->toArray();

                if (empty($countryIds)) {
                    continue;
                }
                FactorySalesRouting::where('factory_id', $factoryId)
                    ->where('priority', (int) $row['priority'])
                    ->whereNotIn('country_id', $countryIds)
                    ->delete();
                foreach ($countryIds as $countryId) {
                    FactorySalesRouting::updateOrCreate(
                        [
                            'factory_id' => $factoryId,
                            'country_id' => $countryId,
                        ],
                        [
                            'priority' => (int) $row['priority'],
                        ]
                    );
                }
            }

        });
    }
}
