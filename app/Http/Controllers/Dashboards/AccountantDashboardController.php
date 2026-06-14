<?php

namespace App\Http\Controllers\Dashboards;

use App\Enums\DeferralStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentSubmission;
use App\Models\TuitionDeferral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountantDashboardController extends Controller
{
    /**
     * Accountant dashboard: profile card plus the payment-review queue summary
     * (counts per status) that links into the validation surface (B1, #6).
     */
    public function __invoke(Request $request): Response
    {
        $profile = $request->user()->accountantProfile;

        $counts = PaymentSubmission::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusCounts = collect(PaymentStatus::cases())
            ->mapWithKeys(fn (PaymentStatus $status): array => [
                $status->value => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();

        $deferralRaw = TuitionDeferral::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $deferralCounts = collect(DeferralStatus::cases())
            ->mapWithKeys(fn (DeferralStatus $status): array => [
                $status->value => (int) ($deferralRaw[$status->value] ?? 0),
            ])
            ->all();

        return Inertia::render('dashboards/Accountant', [
            'profile' => $profile === null ? null : [
                'bank_desk' => $profile->bank_desk,
                'cashier_window' => $profile->cashier_window,
            ],
            'statusCounts' => $statusCounts,
            'deferralCounts' => $deferralCounts,
        ]);
    }
}
