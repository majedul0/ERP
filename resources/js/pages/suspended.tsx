import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

/**
 * The page a suspended company sees instead of their books.
 *
 * Says plainly what happened, that nothing has been lost, and what to do about
 * it. It carries none of the company chrome — the nav would only offer links
 * that redirect straight back here.
 */
export default function Suspended({
    company,
}: {
    company: { name: string; suspendedAt: string };
}) {
    return (
        <div className="flex min-h-svh items-center justify-center bg-coffee-900 p-6">
            <Head title="Account suspended" />

            <div className="w-full max-w-lg rounded-lg bg-white p-8 shadow-lg">
                <h1 className="text-xl font-bold text-coffee-900">
                    {company.name} is suspended
                </h1>

                <p className="mt-3 text-sm text-coffee-800/80">
                    This account was suspended on {company.suspendedAt}, so
                    nobody can sign in to it for the moment. This usually means
                    a subscription payment is outstanding.
                </p>

                <p className="mt-3 text-sm text-coffee-800/80">
                    <strong>Nothing has been deleted.</strong> Every invoice,
                    payment and balance is exactly where you left it, and will
                    be there the moment the suspension is lifted.
                </p>

                <p className="mt-3 text-sm text-coffee-800/80">
                    Please contact your provider to have it restored.
                </p>

                <div className="mt-6 flex items-center gap-2">
                    <Button
                        onClick={() => router.post(logout())}
                        className="bg-coffee-700 hover:bg-coffee-800"
                        data-test="suspended-logout"
                    >
                        Sign out
                    </Button>

                    <Button asChild variant="outline">
                        <Link href="/">Home</Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}
