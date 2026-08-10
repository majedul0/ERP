import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { Product } from '@/modules/products';
import { create, edit } from '@/routes/products';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

export default function Products({ products }: { products: Product[] }) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Products" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-xl font-bold text-coffee-900">Products</h1>
                <Button asChild className="bg-coffee-600 hover:bg-coffee-700">
                    <Link href={create(currentTeam?.slug ?? '')}>
                        + Add Product
                    </Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[60rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Photo</th>
                                <th className={headCell}>Name</th>
                                <th className={headCell}>SKU</th>
                                <th className={headCell}>CTN Size</th>
                                <th className={`${headCell} text-right`}>
                                    Distributor Price
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Trade Price
                                </th>
                                <th className={`${headCell} text-right`}>
                                    MRP
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Stock
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Update</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {products.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={9}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No products registered yet.
                                    </td>
                                </tr>
                            )}

                            {products.map((product) => (
                                <tr
                                    key={product.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>
                                        {product.photoUrl ? (
                                            <img
                                                src={product.photoUrl}
                                                alt=""
                                                className="size-10 rounded object-contain"
                                            />
                                        ) : (
                                            <div className="size-10 rounded bg-coffee-50" />
                                        )}
                                    </td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {product.name}
                                    </td>
                                    <td className={bodyCell}>{product.sku}</td>
                                    <td className={bodyCell}>
                                        {product.cartonSize}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatMoney(
                                            product.distributorPrice,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatMoney(
                                            product.tradePrice,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatMoney(
                                            product.mrp,
                                            brand.currencySymbol,
                                        )}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right font-medium tabular-nums`}
                                    >
                                        {formatAmount(product.stockQuantity)}
                                    </td>
                                    <td className={bodyCell}>
                                        <Link
                                            href={edit({
                                                current_team:
                                                    currentTeam?.slug ?? '',
                                                product: product.id,
                                            })}
                                            className="text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                        >
                                            Update
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
