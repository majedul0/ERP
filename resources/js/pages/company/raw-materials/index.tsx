import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import { LowStockBadge } from '@/modules/raw-materials';
import type { RawMaterial } from '@/modules/raw-materials';
import { create, edit } from '@/routes/materials';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function RawMaterials({
    materials,
}: {
    materials: RawMaterial[];
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    const totalValue = materials.reduce(
        (sum, material) => sum + material.stockValue,
        0,
    );

    return (
        <>
            <Head title="Raw Materials" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">
                    Raw Materials
                </h1>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(teamSlug)}>+ Add Material</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[56rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Name</th>
                                <th className={headCell}>Code</th>
                                <th className={headCell}>Unit</th>
                                <th className={`${headCell} text-right`}>
                                    Stock
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Reorder At
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Unit Cost
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Stock Value
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Update</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {materials.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No raw materials registered yet.
                                    </td>
                                </tr>
                            )}

                            {materials.map((material) => (
                                <tr
                                    key={material.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={`${bodyCell} font-medium`}>
                                        <span className="flex items-center gap-2">
                                            {material.name}
                                            {material.isLow && (
                                                <LowStockBadge />
                                            )}
                                        </span>
                                    </td>
                                    <td className={bodyCell}>
                                        {material.code}
                                    </td>
                                    <td className={bodyCell}>
                                        {material.unitShort}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatAmount(material.stockQuantity)}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {material.reorderLevel > 0
                                            ? formatAmount(
                                                  material.reorderLevel,
                                              )
                                            : '—'}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatMoney(
                                            material.unitCost,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatMoney(
                                            material.stockValue,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team: teamSlug,
                                                material: material.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Update
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>

                        {materials.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-coffee-200 bg-coffee-50/60">
                                    <td
                                        className={`${bodyCell} font-semibold`}
                                        colSpan={6}
                                    >
                                        Total stock value
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-bold tabular-nums`}
                                    >
                                        {formatMoney(
                                            totalValue,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td className={bodyCell} />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </>
    );
}
