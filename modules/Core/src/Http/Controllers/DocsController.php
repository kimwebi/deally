<?php

namespace Deally\Core\Http\Controllers;

class DocsController extends Controller
{
    public function index()
    {
        return redirect()->route('docs.overview');
    }

    public function ai()
    {
        return view('core::pages.docs.ai');
    }

    public function overview()
    {
        return view('core::pages.docs.overview');
    }

    public function calls()
    {
        return view('core::pages.docs.calls');
    }
}
