<?php

namespace App\Models\Factory;

use App\Models\Location\Country;
use App\Models\Location\State;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FactoryBusiness extends Model
{
    use HasFactory;

    protected $table = 'factory_business';

    protected $fillable = [
        'company_name',
        'registration_number',
        'tax_vat_number',
        'registered_address',
        'country_id',
        'state_id',
        'city',
        'postal_code',
        'registration_certificate',
        'tax_certificate',
        'import_export_certificate',
        'factory_id',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function countryData()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    public function stateData()
    {
        return $this->belongsTo(State::class, 'state_id');
    }
}
