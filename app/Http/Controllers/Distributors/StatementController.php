<?php

namespace App\Http\Controllers\Distributors;

use App\Concerns\ResolvesCurrentTeam;
use App\Data\LedgerEntry;
use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\Team;
use App\Support\DistributorLedger;
use App\Support\InvoicePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handing a distributor their account.
 *
 * Both formats read the same `DistributorLedger` walk the on-screen statement
 * uses, so a downloaded statement and the screen it came from cannot disagree.
 */
class StatementController extends Controller
{
    use ResolvesCurrentTeam;

    /**
     * The statement laid out for paper.
     *
     * Rendered as a print-ready page rather than a generated PDF: the browser's
     * "Save as PDF" produces the file, exactly as the invoice already does, and
     * no PDF library is installed. Whichever library is eventually chosen can
     * render this same data without the screens changing.
     *
     * See InvoiceController::show() for why `$current_team` must be declared.
     */
    public function print(Request $request, string $current_team, Distributor $distributor): Response
    {
        $team = $this->currentTeam($request);

        abort_unless($distributor->team_id === $team->id, 404);

        $entries = DistributorLedger::entries($distributor);

        return Inertia::render('company/distributors/statement', [
            'distributor' => InvoicePresenter::distributor($distributor),
            'statement' => array_map(fn (LedgerEntry $entry) => $entry->toArray(), $entries),
            'totals' => $this->totals($entries, $distributor),
        ]);
    }

    /**
     * The statement as a spreadsheet.
     */
    public function excel(Request $request, string $current_team, Distributor $distributor): StreamedResponse
    {
        $team = $this->currentTeam($request);

        abort_unless($distributor->team_id === $team->id, 404);

        $rows = $this->exportRows($team, $distributor);
        $filename = 'statement-'.$distributor->id.'-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the statement export.');
            }

            // Excel reads a file as the system codepage unless a UTF-8 BOM says
            // otherwise, which would mangle ৳ and any Bangla text.
            fwrite($handle, "\u{FEFF}");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Every row of the exported spreadsheet, in order.
     *
     * @return list<list<string|int>>
     */
    private function exportRows(Team $team, Distributor $distributor): array
    {
        $entries = DistributorLedger::entries($distributor);
        $totals = $this->totals($entries, $distributor);

        $rows = [
            [$team->name],
            ['Statement of Account'],
            [],
            ['Distributor', $distributor->name],
            ['Proprietor', $distributor->proprietor_name ?? ''],
            ['Phone', $distributor->phone ?? ''],
            ['Address', $distributor->fullAddress()],
            ['Generated', now()->toDateTimeString()],
            [],
            ['Date', 'Reference', 'Details', 'Charged', 'Paid', 'Balance'],
        ];

        foreach ($entries as $entry) {
            $rows[] = [
                $entry->occurredOn->toDateString(),
                $entry->reference,
                $entry->description,
                $entry->debit,
                $entry->credit,
                $entry->balanceAfter,
            ];
        }

        $rows[] = [];
        $rows[] = ['Total charged', '', '', $totals['charged']];
        $rows[] = ['Total paid', '', '', '', $totals['paid']];
        $rows[] = ['Due', '', '', '', '', $totals['due']];

        return $rows;
    }

    /**
     * @param  array<int, LedgerEntry>  $entries
     * @return array{charged: int, paid: int, due: int}
     */
    private function totals(array $entries, Distributor $distributor): array
    {
        return [
            'charged' => array_sum(array_map(fn (LedgerEntry $e) => $e->debit, $entries)),
            'paid' => array_sum(array_map(fn (LedgerEntry $e) => $e->credit, $entries)),
            'due' => $distributor->balance,
        ];
    }
}
