<?php

namespace App\Http\Controllers\Admin\Settings\Shipping;

use App\Http\Controllers\Controller;

class ShippingPartnerController extends Controller
{
    public function index()
    {
        return view('admin.settings.shipping.partners');
    }
}
