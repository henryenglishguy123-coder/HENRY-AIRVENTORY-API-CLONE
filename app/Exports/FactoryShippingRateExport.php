<?php

namespace App\Exports;

use App\Models\Factory\FactoryShippingRate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FactoryShippingRateExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return FactoryShippingRate::with([
            'factory.business',
            'country',
        ])->get();
    }

    public function headings(): array
    {
        return [
            'factory_id',
            'factory_name',
            'company_name',
            'country_code',
            'country_name',
            'shipping_title',
            'min_qty',
            'price',
        ];
    }

    public function map($rate): array
    {
        return [
            $rate->factory_id,
            trim(($rate->factory->first_name ?? '').' '.($rate->factory->last_name ?? '')),
            $rate->factory->business->company_name ?? '',
            $rate->country_code,
            $rate->country->name ?? '',
            $rate->shipping_title,
            $rate->min_qty,
            $rate->price,
        ];
    }
}
