<?php

namespace App\Exports;

use App\Models\Factory\FactorySalesRouting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FactorySalesRoutingExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'factory_id',
            'factory_name',
            'priority',
            'country_codes',
        ];
    }

    public function array(): array
    {
        return FactorySalesRouting::with(['factory', 'country'])
            ->orderBy('factory_id')
            ->orderBy('priority')
            ->get()
            // Group by composite key to separate different priorities for the same factory
            ->groupBy(function ($item) {
                return $item->factory_id.'-'.$item->priority;
            })
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    $first->factory_id,
                    trim($first->factory?->first_name.' '.$first->factory?->last_name),
                    $first->priority,
                    $rows->pluck('country.iso2')->implode(','),
                ];
            })
            ->values()
            ->toArray();
    }
}
