import { Form, Head } from '@inertiajs/react';
import { BarChart3, FileText, Users } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TeamInvitationAlert from '@/components/team-invitation-alert';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { CompanyLogo, useCompanyBrand, WaveBackdrop } from '@/modules/company';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

const highlights = [
    {
        icon: FileText,
        title: 'Invoice in minutes',
        description:
            'Issue branded invoices, track their status, and download the PDF.',
    },
    {
        icon: Users,
        title: 'Distributors & vendors',
        description:
            'Keep balances, payment history, and contacts in one place.',
    },
    {
        icon: BarChart3,
        title: 'Know where you stand',
        description: 'Sales, payments, and expenses summarised as they happen.',
    },
];

type Props = {
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
};

export default function Welcome({
    status,
    canResetPassword,
    teamInvitation,
}: Props) {
    const brand = useCompanyBrand();

    return (
        <>
            <Head title="Log in" />

            <div className="flex min-h-screen flex-col lg:flex-row">
                {/* Brand panel */}
                <div className="relative overflow-hidden bg-ocean-500 px-6 py-10 lg:flex lg:w-[45%] lg:flex-col lg:justify-between lg:px-12 lg:py-14">
                    <WaveBackdrop />

                    <div className="relative">
                        <CompanyLogo brand={brand} tone="light" />
                    </div>

                    <div className="relative mt-8 hidden lg:block">
                        <h1 className="max-w-md text-4xl font-bold text-white">
                            Sales, invoices, and finances in one workspace.
                        </h1>
                        <p className="mt-4 max-w-md font-display text-white/85">
                            Everything your company bills, buys, and banks —
                            tracked from a single dashboard.
                        </p>

                        <ul className="mt-10 space-y-6">
                            {highlights.map((highlight) => (
                                <li
                                    key={highlight.title}
                                    className="flex gap-4"
                                >
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/25">
                                        <highlight.icon className="size-5 text-white" />
                                    </span>
                                    <span>
                                        <span className="block font-semibold text-white">
                                            {highlight.title}
                                        </span>
                                        <span className="mt-0.5 block text-sm text-white/80">
                                            {highlight.description}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative mt-10 hidden text-sm text-white/70 lg:block">
                        © {new Date().getFullYear()} {brand.name}
                    </p>
                </div>

                {/* Login panel */}
                <div className="flex flex-1 items-center justify-center bg-white px-6 py-12 lg:px-12">
                    <div className="w-full max-w-sm">
                        <h2 className="text-2xl font-bold text-ocean-900">
                            Log in to your account
                        </h2>
                        <p className="mt-2 font-display text-sm text-ocean-800/70">
                            Enter your email and password below to continue.
                        </p>

                        {teamInvitation && (
                            <div className="mt-6">
                                <TeamInvitationAlert
                                    invitation={teamInvitation}
                                    action="Log in"
                                />
                            </div>
                        )}

                        {status && (
                            <div className="mt-6 rounded-md bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {status}
                            </div>
                        )}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="mt-8 flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            Email address
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            required
                                            autoFocus
                                            tabIndex={1}
                                            autoComplete="email"
                                            placeholder="email@example.com"
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center">
                                            <Label htmlFor="password">
                                                Password
                                            </Label>
                                            {canResetPassword && (
                                                <TextLink
                                                    href={request()}
                                                    className="ml-auto text-sm"
                                                    tabIndex={5}
                                                >
                                                    Forgot password?
                                                </TextLink>
                                            )}
                                        </div>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            tabIndex={2}
                                            autoComplete="current-password"
                                            placeholder="Password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center space-x-3">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                        />
                                        <Label htmlFor="remember">
                                            Remember me
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="w-full bg-ocean-500 hover:bg-ocean-600"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing && <Spinner />}
                                        Log in
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}
