<?php

namespace App\Http\Controllers;

use App\Services\RoleDashboardResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RoleDashboardResolver $resolver): RedirectResponse
    {
        return redirect($resolver->pathFor($request->user()));
    }
}
