<?php

namespace App\Http\Controllers\Admin\Sales\Order;

use App\Http\Controllers\Controller;
use App\Models\Sales\Order\SalesOrder;

class OrderController extends Controller
{
    public function index()
    {
        return view('admin.sales.order.index');
    }

    public function show($id)
    {
        $salesOrder = SalesOrder::findOrFail($id);
        $orderNumber = $salesOrder->order_number;

        return view('admin.sales.order.show', compact('id', 'orderNumber'));
    }
}
