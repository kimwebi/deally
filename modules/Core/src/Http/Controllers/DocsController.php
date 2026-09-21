<?php

namespace Deally\Core\Http\Controllers;

class DocsController extends Controller
{
    public function ai()
    {
        return view('core::pages.docs.ai');
    }

    public function overview()
    {
        return view('core::pages.docs.overview');
    }
}
