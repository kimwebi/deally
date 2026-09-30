<?php

namespace Deally\Core\Http\Controllers;

class LegalController extends Controller
{
    public function terms()
    {
        return view('core::pages.legal.terms');
    }

    public function privacy()
    {
        return view('core::pages.legal.privacy');
    }
}
