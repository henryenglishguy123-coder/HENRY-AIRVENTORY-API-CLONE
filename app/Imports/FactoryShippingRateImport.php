<?php

namespace App\Imports;

use App\Models\Factory\Factory;
use App\Models\Factory\FactoryShippingRate;
use App\Models\Location\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FactoryShippingRateImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {

            foreach ($rows as $index => $row) {

                $factoryId = (int) ($row['factory_id'] ?? 0);
                $countryCode = strtoupper(trim($row['country_code'] ?? ''));
                $minQty = is_numeric($row['min_qty'] ?? null) ? (int) $row['min_qty'] : null;
                $price = is_numeric($row['price'] ?? null) ? (float) $row['price'] : null;
                $title = trim($row['shipping_title'] ?? '');

                /* =========================
                 | Required Field Validation
                 ========================= */
                if (
                    ! $factoryId ||
                    ! $countryCode ||
                    ! $title ||
                    $minQty === null ||
                    $price === null
                ) {
                    Log::warning('Shipping rate import skipped (missing fields)', [
                        'row' => $index + 1,
                        'data' => $row,
                    ]);

                    continue;
                }

                /* =========================
                 | Factory Validation
                 ========================= */
                if (! Factory::whereKey($factoryId)->exists()) {
                    Log::warning('Shipping rate import skipped (invalid factory)', [
                        'row' => $index + 1,
                        'factory_id' => $factoryId,
                    ]);

                    continue;
                }

                /* =========================
                 | Country Validation
                 ========================= */
                if (! Country::where('iso2', $countryCode)->exists()) {
                    Log::warning('Shipping rate import skipped (invalid country)', [
                        'row' => $index + 1,
                        'country_code' => $countryCode,
                    ]);

                    continue;
                }

                /* =========================
                 | Business Rules
                 ========================= */
                if ($minQty < 1 || $price < 0) {
                    Log::warning('Shipping rate import skipped (invalid qty/price)', [
                        'row' => $index + 1,
                        'min_qty' => $minQty,
                        'price' => $price,
                    ]);

                    continue;
                }

                /* =========================
                 | Create / Update
                 ========================= */
                FactoryShippingRate::updateOrCreate(
                    [
                        'factory_id' => $factoryId,
                        'country_code' => $countryCode,
                        'min_qty' => $minQty,
                    ],
                    [
                        'shipping_title' => $title,
                        'price' => $price,
                    ]
                );
            }
        });
    }
}
