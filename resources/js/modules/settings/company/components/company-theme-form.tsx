import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import CompanyController from '@/actions/App/Http/Controllers/Settings/CompanyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ThemeRgb } from '../types';

/** `{ red: 139, green: 98, blue: 68 }` -> `#8b6244` */
function toHex({ red, green, blue }: ThemeRgb): string {
    const channel = (value: number) =>
        Math.max(0, Math.min(255, Math.round(value)))
            .toString(16)
            .padStart(2, '0');

    return `#${channel(red)}${channel(green)}${channel(blue)}`;
}

/** `#8b6244` -> `{ red: 139, green: 98, blue: 68 }` */
function toRgb(hex: string): ThemeRgb {
    return {
        red: parseInt(hex.slice(1, 3), 16),
        green: parseInt(hex.slice(3, 5), 16),
        blue: parseInt(hex.slice(5, 7), 16),
    };
}

const channels: Array<{ key: keyof ThemeRgb; label: string }> = [
    { key: 'red', label: 'Red' },
    { key: 'green', label: 'Green' },
    { key: 'blue', label: 'Blue' },
];

/**
 * The company's own colour, entered as red, green and blue.
 *
 * Three numbers rather than a hex box because that is how a brand guide states
 * a colour, and the swatch beside them is a native colour picker for anybody
 * who would rather point at it than type. Both edit the same three values —
 * the picker is a second way in, not a second setting.
 *
 * There is no live preview of the whole app, deliberately. Saving repaints
 * every screen over an Inertia visit, including this one, so the preview *is*
 * the application — and no approximation of the palette lives in the browser to
 * disagree with the one the server derives.
 */
export default function CompanyThemeForm({
    themeRgb,
    usesDefaultTheme,
    appliedThemeColor,
    canUpdate,
}: {
    themeRgb: ThemeRgb;
    usesDefaultTheme: boolean;
    appliedThemeColor: string;
    canUpdate: boolean;
}) {
    const [rgb, setRgb] = useState<ThemeRgb>(themeRgb);
    const chosen = toHex(rgb);

    // Darkened, if the chosen colour was too light to carry white text. Equal
    // to the choice in every ordinary case, and the reason to say so when not.
    const darkened = !usesDefaultTheme && appliedThemeColor !== toHex(themeRgb);

    const reset = () =>
        router.patch(
            CompanyController.updateTheme.url(),
            { reset: true },
            { preserveScroll: true },
        );

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Theme colour"
                description="The colour this company's screens are painted in"
            />

            <Form
                {...CompanyController.updateTheme.form()}
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="theme-picker">Colour</Label>
                                <input
                                    id="theme-picker"
                                    type="color"
                                    value={chosen}
                                    disabled={!canUpdate}
                                    onChange={(event) =>
                                        setRgb(toRgb(event.target.value))
                                    }
                                    className="h-10 w-16 cursor-pointer rounded-md border border-input bg-transparent p-1 disabled:cursor-not-allowed disabled:opacity-50"
                                    aria-label="Pick a colour"
                                />
                            </div>

                            {channels.map(({ key, label }) => (
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={`theme-${key}`}>
                                        {label}
                                    </Label>
                                    <Input
                                        id={`theme-${key}`}
                                        name={key}
                                        type="number"
                                        min={0}
                                        max={255}
                                        className="w-24"
                                        value={rgb[key]}
                                        disabled={!canUpdate}
                                        onChange={(event) =>
                                            setRgb({
                                                ...rgb,
                                                [key]:
                                                    event.target.value === ''
                                                        ? 0
                                                        : Number(
                                                              event.target
                                                                  .value,
                                                          ),
                                            })
                                        }
                                    />
                                </div>
                            ))}
                        </div>

                        <InputError message={errors.red} />
                        <InputError message={errors.green} />
                        <InputError message={errors.blue} />

                        <div className="space-y-2">
                            <p className="text-sm text-muted-foreground">
                                Preview
                            </p>
                            <div
                                className="flex flex-wrap items-center gap-3 rounded-lg border border-coffee-100 p-4"
                                // The chosen colour, straight from the inputs,
                                // so the sample moves as they are typed in.
                                style={{ backgroundColor: `${chosen}14` }}
                            >
                                <span
                                    className="rounded-md px-3 py-2 text-sm font-semibold text-white"
                                    style={{ backgroundColor: chosen }}
                                >
                                    Sample button
                                </span>
                                <span
                                    className="rounded-md px-3 py-2 text-xs font-bold tracking-wide text-white uppercase"
                                    style={{ backgroundColor: chosen }}
                                >
                                    Table header
                                </span>
                                <code className="rounded bg-muted px-2 py-1 text-xs">
                                    rgb({rgb.red}, {rgb.green}, {rgb.blue})
                                </code>
                            </div>

                            {darkened && (
                                <p className="text-sm text-muted-foreground">
                                    The saved colour is darkened slightly when
                                    it is applied, so white text on buttons and
                                    table headers stays readable.
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            <Button
                                type="submit"
                                disabled={processing || !canUpdate}
                                data-test="save-theme"
                            >
                                Save colour
                            </Button>

                            {!usesDefaultTheme && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={reset}
                                    disabled={!canUpdate}
                                    data-test="reset-theme"
                                >
                                    Reset to default
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </div>
    );
}
