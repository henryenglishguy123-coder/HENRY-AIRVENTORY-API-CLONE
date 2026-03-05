<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class State extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'country_id', 'country_code', 'iso2'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    protected static function booted()
    {
        static::saved(function ($state) {
            Cache::forget("public_states_list_country_{$state->country_id}");
        });

        static::deleted(function ($state) {
            Cache::forget("public_states_list_country_{$state->country_id}");
        });
    }
}
