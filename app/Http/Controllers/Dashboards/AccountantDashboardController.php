<?php

namespace App\Http\Controllers\Dashboards;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountantDashboardController extends Controller
{
    /**
     * Accountant dashboard: profile card while the payment-validation module
     * is pending (AUDIT.md AUD-024; full dashboard arrives with backlog B1).
     */
    public function __invoke(Request $request): Response
    {
        $profile = $request->user()->accountantProfile;

        return Inertia::render('dashboards/Accountant', [
            'profile' => $profile === null ? null : [
                'bank_desk' => $profile->bank_desk,
                'cashier_window' => $profile->cashier_window,
            ],
        ]);
    }
}
