<?php

namespace App\Http\Controllers\Admin\Settings\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Currency\Currency;
use Illuminate\Http\Request;

class ShippingRateController extends Controller
{
    public function shippingRates(Request $request)
    {
        $defaultCurrency = Currency::getDefaultCurrencyOrNull();

        return view('admin.settings.shipping.index', compact('defaultCurrency'));
    }
}
