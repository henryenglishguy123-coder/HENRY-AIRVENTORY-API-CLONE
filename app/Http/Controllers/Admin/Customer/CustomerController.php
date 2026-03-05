<?php

namespace App\Http\Controllers\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer\Vendor;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        return view('admin.customer.index');
    }

    public function show(Request $request, Vendor $customer)
    {
        return view('admin.customer.show', compact('customer'));
    }

    public function wallet(Request $request, Vendor $customer)
    {
        return view('admin.customer.wallet', compact('customer'));
    }

    public function stores(Request $request, Vendor $customer)
    {
        return view('admin.customer.stores', compact('customer'));
    }

    public function templates(Request $request, Vendor $customer)
    {
        return view('admin.customer.templates', compact('customer'));
    }

    public function wallets(Request $request)
    {
        return view('admin.customer.wallets');
    }
}
