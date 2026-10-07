<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;use Illuminate\Support\Facades\Auth;
class PickerController extends BaseController
{
    public function index()
    {
        return view('picker');
    }
}
