<?php

namespace App\Http\Controllers\Admin\Factory;

use App\Http\Controllers\Controller;

class FactoryController extends Controller
{
    /**
     * Display a listing of the factories.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin.factory.index');
    }

    /**
     * Display the business information of a factory.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function businessInformation($id)
    {
        abort_if(! ctype_digit((string)$id), 404);
        $factory = \App\Models\Factory\Factory::findOrFail($id);
        return view('admin.factory.business-information', ['id' => $factory->id]);
    }

    /**
     * Display the branding settings of a factory.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function branding($id)
    {
        abort_if(! ctype_digit((string)$id), 404);
        $factory = \App\Models\Factory\Factory::findOrFail($id);
        return view('admin.factory.branding', ['id' => $factory->id]);
    }
}
