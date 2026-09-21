<?php

namespace Deally\Core\Http\Controllers;

use Deally\Core\Http\Controllers\Concerns\AuthorizesDeally;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesDeally, AuthorizesRequests;
}
