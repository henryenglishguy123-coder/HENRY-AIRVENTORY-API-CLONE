<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index()
    {
        return view('admin.catalog.product.index');
    }
}
