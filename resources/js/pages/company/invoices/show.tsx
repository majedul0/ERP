import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileSpreadsheet, PencilLine, Printer, Truck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCompanyBrand } from '@/modules/company';
import type { DeliveryStatusOption, InvoiceDetail } from '@/modules/invoices';
import { DeleteInvoiceDialog, InvoiceDocument } from '@/modules/invoices';
import { challan, edit, excel } from '@/routes/invoices';
import { update } from '@/routes/invoices/status';

type Props = {
    invoice: InvoiceDetail;
    statuses: DeliveryStatusOption[];
};

export default function ShowInvoice({ invoice, statuses }: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const routeArgs = {
        current_team: currentTeam?.slug ?? '',
        invoice: invoice.id,
    };

    const setStatus = (value: string) =>
        router.patch(
            update(routeArgs).url,
            { delivery_status: value },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={`Invoice ${invoice.invoiceNumber}`} />

            {/* Screen-only controls; the print output is the invoice alone. */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                <h1 className="text-xl font-bold text-coffee-900">
                    Sales Invoice
                </h1>

                <div className="flex flex-wrap items-center gap-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                className="font-semibold text-emerald-700"
                            >
                                <Truck className="size-4" />
                                {invoice.deliveryStatusLabel}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-44">
                            {statuses.map((status) => (
                                <DropdownMenuItem
                                    key={status.value}
                                    disabled={
                                        status.value === invoice.deliveryStatus
                                    }
                                    onSelect={() => setStatus(status.value)}
                                >
                                    {status.label}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button asChild variant="outline">
                        <Link href={edit(routeArgs)}>
                            <PencilLine className="size-4" />
                            Update
                        </Link>
                    </Button>

                    <Button asChild variant="outline">
                        <Link href={challan(routeArgs)}>Challan</Link>
                    </Button>

                    <DeleteInvoiceDialog
                        teamSlug={currentTeam?.slug ?? ''}
                        invoiceId={invoice.id}
                        invoiceNumber={invoice.invoiceNumber}
                    />

                    {/* A file download, so a plain anchor, not an Inertia visit. */}
                    <Button asChild variant="outline">
                        <a href={excel(routeArgs).url}>
                            <FileSpreadsheet className="size-4" />
                            Excel
                        </a>
                    </Button>

                    <Button
                        type="button"
                        onClick={() => window.print()}
                        className="bg-coffee-700 hover:bg-coffee-800"
                    >
                        <Printer className="size-4" />
                        Print
                    </Button>
                </div>
            </div>

            <InvoiceDocument brand={brand} invoice={invoice} />
        </>
    );
}
