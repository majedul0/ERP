import { Form, Head } from '@inertiajs/react';
import PlatformController from '@/actions/App/Http/Controllers/Platform/PlatformController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * The platform sign-in.
 *
 * Deliberately plain and unbranded: it is not a company's login page, and it
 * should give away as little as possible about what sits behind it.
 */
export default function PlatformLogin() {
    return (
        <div className="flex min-h-svh items-center justify-center bg-coffee-900 p-6">
            <Head title="Platform" />

            <div className="w-full max-w-sm rounded-lg bg-white p-8 shadow-lg">
                <h1 className="text-xl font-bold text-coffee-900">
                    Platform administration
                </h1>
                <p className="mt-1 mb-6 text-sm text-coffee-800/60">
                    Sign in to manage companies.
                </p>

                <Form
                    {...PlatformController.login.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoFocus
                                    autoComplete="username"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    autoComplete="current-password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-coffee-700 hover:bg-coffee-800"
                                data-test="platform-login-button"
                            >
                                {processing && <Spinner />}
                                Sign in
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
