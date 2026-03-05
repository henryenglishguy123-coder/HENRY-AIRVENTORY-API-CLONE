<?php

namespace App\Http\Resources\Api\V1\Factory;

use Illuminate\Http\Resources\Json\JsonResource;

class FactoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $primaryIndustry = null;

        if ($this->relationLoaded('industries')) {
            $industry = $this->industries->sortBy('id')->first();

            if ($industry) {
                $primaryIndustry = [
                    'id' => $industry->id,
                    'name' => optional($industry->meta)->name,
                ];
            }
        }

        return [
            'id' => $this->id,
            'name' => trim("{$this->first_name} {$this->last_name}") ?: $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,

            // Raw values (Enums will be serialized to their backing value or object depending on serialization)
            // But for frontend JS logic which expects integers (0, 1, 2...), better to return the value.
            // Structured Enum Data
            'account_status' => $this->account_status ? [
                'value' => $this->account_status->value,
                'label' => $this->account_status->label(),
                'color' => $this->account_status->color(),
            ] : null,

            'account_verified' => $this->account_verified ? [
                'value' => $this->account_verified->value,
                'label' => $this->account_verified->label(),
                'color' => $this->account_verified->color(),
            ] : null,

            // Email Verification
            'email_verified_at' => $this->email_verified_at,
            'is_email_verified' => (bool) $this->email_verified_at,

            'source' => $this->source,
            'google_id' => $this->google_id,
            'stripe_account_id' => $this->stripe_account_id,

            'business' => [
                'company_name' => $this->business?->company_name,
            ],
            'industry' => $primaryIndustry,
            'created_at' => $this->created_at ? $this->created_at->format(config('admin.datetime_format')) : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format(config('admin.datetime_format')) : null,
            'last_active' => $this->last_login ? $this->last_login->format(config('admin.datetime_format')) : 'Never',
        ];
    }
}
