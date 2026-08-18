<?php

namespace App\Http\Controllers\Products;

use App\Concerns\ResolvesCurrentTeam;
use App\Http\Controllers\Controller;
use App\Support\ProductStockReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReportController extends Controller
{
    use ResolvesCurrentTeam;

    public function index(Request $request): Response
    {
        $team = $this->currentTeam($request);
        [$from, $to] = $this->month($request);

        return Inertia::render('company/products/stock-report', [
            'report' => ProductStockReport::build($team, $from, $to),
        ]);
    }

    /**
     * The same report as a spreadsheet.
     */
    public function excel(Request $request): StreamedResponse
    {
        $team = $this->currentTeam($request);
        [$from, $to] = $this->month($request);

        $report = ProductStockReport::build($team, $from, $to);
        $filename = "stock-report-{$from->format('Y-m')}.csv";

        return response()->streamDownload(function () use ($team, $report) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                throw new RuntimeException('Could not open the output stream for the stock report export.');
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
     * The month being reported on, as a pair of inclusive dates.
     *
     * A month rather than a free range: opening and closing stock are the
     * figures a warehouse closes its books on, and "12th of March to the 3rd of
     * May" is not a period anybody reconciles against. Defaults to this month
     * in the business's timezone — see `APP_TIMEZONE`.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function month(Request $request): array
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $now = Carbon::now();

        $from = Carbon::create(
            (int) ($validated['year'] ?? $now->year),
            (int) ($validated['month'] ?? $now->month),
            1,
        )->startOfMonth();

        return [$from, $from->copy()->endOfMonth()];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int>>
     */
    private function exportRows(string $companyName, array $report): array
    {
        /** @var array<string, mixed> $period */
        $period = $report['period'];
        /** @var list<array<string, mixed>> $lines */
        $lines = $report['rows'];
        /** @var array<string, int> $totals */
        $totals = $report['totals'];

        $rows = [
            [$companyName],
            ['Product Stock Report'],
            [(string) $period['label']],
            [],
            [
                'S/L', 'Product', 'SKU', 'Opening Stock', 'Productions', 'Total',
                'Sales', 'Sales Value', 'Fresh Returns', 'Damaged',
                'Closing Stock', 'Closing Stock Value', 'Balance',
            ],
        ];

        foreach ($lines as $index => $line) {
            $rows[] = [
                $index + 1,
                (string) $line['name'],
                (string) $line['sku'],
                (int) $line['opening'],
                (int) $line['productions'],
                (int) $line['total'],
                (int) $line['sales'],
                (int) $line['salesValue'],
                (int) $line['freshReturns'],
                (int) $line['damaged'],
                (int) $line['closing'],
                (int) $line['closingValue'],
                (int) $line['balance'],
            ];
        }

        $rows[] = [
            '',
            'Total',
            '',
            $totals['opening'],
            $totals['productions'],
            $totals['total'],
            $totals['sales'],
            $totals['salesValue'],
            $totals['freshReturns'],
            $totals['damaged'],
            $totals['closing'],
            $totals['closingValue'],
            $totals['balance'],
        ];

        return $rows;
    }
}
