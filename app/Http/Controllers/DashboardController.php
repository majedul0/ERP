<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesCurrentTeam;
use App\Enums\DeliveryStatus;
use App\Models\Invoice;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Support\InvoicePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesCurrentTeam;

    public function __invoke(Request $request): Response
    {
        $team = $this->currentTeam($request);
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'stats' => $this->stats($team),
            'todaysSales' => $this->todaysSales($team),
        ]);
    }

    /**
     * Headline figures for the welcome banner.
     *
     * Aggregated in the database rather than by loading rows and summing in
     * PHP — these run on every dashboard load, and the row count grows without
     * bound. Cancelled invoices are excluded: they are not sales.
     *
     * @return array{total: int, sales: int, distributorPayments: int, expenses: int, promotions: int}
     */
    private function stats(Team $team): array
    {
        $today = $team->invoices()
            ->whereDate('sold_at', today())
            ->whereNot('delivery_status', DeliveryStatus::Cancelled)
            ->selectRaw('COALESCE(SUM(invoice_total - discount_total - scheme_amount), 0) AS net')
            ->selectRaw('COALESCE(SUM(scheme_amount), 0) AS schemes')
            ->first();

        $outstanding = (int) $team->distributors()->sum('balance');

        return [
            // What distributors owe the company overall.
            'total' => $outstanding,
            'sales' => (int) ($today->net ?? 0),

            // Payments and expenses land here when those modules ship; the
            // figures are honest zeros until then, not invented numbers.
            'distributorPayments' => 0,
            'expenses' => 0,
            'promotions' => (int) ($today->schemes ?? 0),
        ];
    }

    /**
     * Invoices sold today, newest last, exactly as the table renders them.
     *
     * `whereDate` compares in the application timezone, so an invoice belongs
     * to the day the seller was having — not to a UTC day that rolls over
     * mid-afternoon in Dhaka.
     *
     * @return array<int, array<string, mixed>>
     */
    private function todaysSales(Team $team): array
    {
        return $team->invoices()
            ->with('distributor')
            ->whereDate('sold_at', today())
            ->orderBy('sold_at')
            ->get()
            ->map(fn (Invoice $invoice) => InvoicePresenter::summary($invoice, $team->slug))
            ->all();
    }
}
