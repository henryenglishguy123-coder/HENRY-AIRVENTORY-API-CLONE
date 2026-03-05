<?php

namespace App\Policies;

use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;

class VendorDesignTemplatePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(Authenticatable $user, VendorDesignTemplate $template): bool
    {
        // Check if user is an admin first (highest privilege)
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Check if user is a vendor
        if (method_exists($user, 'isVendor') && $user->isVendor()) {
            return $user->id === $template->vendor_id;
        }

        // Fallback for standard auth user (Customer/Vendor) check by ID
        return $user->id === $template->vendor_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, VendorDesignTemplate $template): bool
    {
        return $user->id === $template->vendor_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, VendorDesignTemplate $template): bool
    {
        return $user->id === $template->vendor_id;
    }
}
