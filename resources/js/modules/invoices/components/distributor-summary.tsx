import type { DistributorOption } from '../types';

const headCell =
    'bg-ocean-500 px-4 py-2.5 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 text-ocean-900';

/**
 * The distributor's details, filled in from the chosen distributor rather than
 * retyped — the invoice must carry the address that is on file, not one
 * someone remembered.
 */
export default function DistributorSummary({
    distributor,
}: {
    distributor: DistributorOption | null;
}) {
    return (
        <div className="mt-5 overflow-x-auto rounded-lg border border-ocean-100 bg-white">
            <table className="w-full min-w-[56rem] text-sm">
                <thead>
                    <tr>
                        <th className={headCell}>ID</th>
                        <th className={headCell}>Proprietor Name</th>
                        <th className={headCell}>Phone</th>
                        <th className={headCell}>Address</th>
                        <th className={headCell}>Thana</th>
                        <th className={headCell}>District</th>
                        <th className={headCell}>Division</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        {distributor ? (
                            <>
                                <td className={bodyCell}>{distributor.id}</td>
                                <td className={bodyCell}>
                                    {distributor.proprietorName ?? '—'}
                                </td>
                                <td className={bodyCell}>
                                    {distributor.phone ?? '—'}
                                </td>
                                <td className={bodyCell}>
                                    {distributor.address ?? '—'}
                                </td>
                                <td className={bodyCell}>
                                    {distributor.thana ?? '—'}
                                </td>
                                <td className={bodyCell}>
                                    {distributor.district ?? '—'}
                                </td>
                                <td className={bodyCell}>
                                    {distributor.division ?? '—'}
                                </td>
                            </>
                        ) : (
                            <td
                                colSpan={7}
                                className="px-4 py-6 text-center text-sm text-ocean-800/50"
                            >
                                Choose a distributor to see their details.
                            </td>
                        )}
                    </tr>
                </tbody>
            </table>
        </div>
    );
}
