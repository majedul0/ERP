<?php

namespace App\Http\Controllers\Finance;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Support\FinancialReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        [$from, $to] = $this->period($request);

        return Inertia::render('company/finance/reports/index', [
            'report' => FinancialReport::build($team, $from, $to),
        ]);
    }

    /**
     * The same report as a spreadsheet.
     */
    public function excel(Request $request): StreamedResponse
    {
        $team = $this->currentTeam($request);
        [$from, $to] = $this->period($request);

        $report = FinancialReport::build($team, $from, $to);
        $filename = "report-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($team, $report) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the report export.');
            }

            // Excel reads a file as the system codepage unless a UTF-8 BOM says
            // otherwise, which would mangle ৳ and any Bangla text.
            fwrite($handle, "\u{FEFF}");

            foreach ($this->exportRows($team->name, $report) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The period being reported on.
     *
     * Defaults to the current month, which is the question people usually have.
     * Both ends are inclusive dates in the business's timezone — see
     * `APP_TIMEZONE`, which is what makes "this month" mean the right thing.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfMonth();

        // A backwards range is a typo, not a request for nothing.
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int>>
     */
    private function exportRows(string $companyName, array $report): array
    {
        /** @var array<string, string> $period */
        $period = $report['period'];
        /** @var array<string, int> $sales */
        $sales = $report['sales'];
        /** @var array<string, int> $money */
        $money = $report['money'];
        /** @var array<string, int> $standing */
        $standing = $report['standing'];
        /** @var array<int, array{category: string, label: string, amount: int}> $byCategory */
        $byCategory = $report['expensesByCategory'];

        $rows = [
            [$companyName],
            ['Financial Report'],
            ['From', $period['from'], 'To', $period['to']],
            [],
            ['Sales'],
            ['Invoices', $sales['invoiceCount']],
            ['Gross', $sales['gross']],
            ['Discounts', $sales['discounts']],
            ['Schemes', $sales['schemes']],
            ['Net charged', $sales['net']],
            [],
            ['Money'],
            ['Received from distributors', $money['received']],
            ['Expenses', $money['expenses']],
            ['Paid to vendors', $money['vendorPaid']],
            ['Net cash', $money['netCash']],
            ['Billed by vendors', $money['vendorBilled']],
            ['Material purchases', $money['materialPurchases']],
            [],
            ['Expenses by category'],
        ];

        foreach ($byCategory as $line) {
            $rows[] = [$line['label'], $line['amount']];
        }

        $rows[] = [];
        $rows[] = ['Standing (as of today)'];
        $rows[] = ['Receivable from distributors', $standing['receivable']];
        $rows[] = ['Payable to vendors', $standing['payable']];
        $rows[] = ['Material stock value', $standing['materialStockValue']];

        return $rows;
    }
}
