import { router, useForm } from '@inertiajs/react';
import { ImageUp, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { destroy, update } from '@/routes/company/logo';

const ACCEPTED = 'image/jpeg,image/png,image/webp';

type Props = {
    logoUrl: string | null;
    canUpdate: boolean;
    /** Server-configured limit; see config/company.php. */
    maxLogoKilobytes: number;
};

export default function CompanyLogoForm({
    logoUrl,
    canUpdate,
    maxLogoKilobytes,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [sizeError, setSizeError] = useState<string | null>(null);

    const { data, setData, post, processing, progress, errors, reset } =
        useForm<{ logo: File | null }>({ logo: null });

    const maxBytes = maxLogoKilobytes * 1024;
    const maxLabel = `${Math.round(maxLogoKilobytes / 1024)}MB`;

    // Object URLs leak unless revoked once the preview stops being shown.
    useEffect(() => {
        if (!preview) {
            return;
        }

        return () => URL.revokeObjectURL(preview);
    }, [preview]);

    const clearInput = () => {
        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };

    /**
     * Reject oversized files here rather than letting them go to the server.
     * PHP's `upload_max_filesize` would kill the request before validation
     * runs, and the error it produces cannot explain itself.
     */
    const choose = (file: File | null) => {
        if (file && file.size > maxBytes) {
            setSizeError(
                `That image is ${(file.size / (1024 * 1024)).toFixed(1)}MB. The logo must be ${maxLabel} or smaller.`,
            );
            setPreview(null);
            reset();
            clearInput();

            return;
        }

        setSizeError(null);
        setPreview(file ? URL.createObjectURL(file) : null);
        setData('logo', file);
    };

    const clearSelection = () => {
        setSizeError(null);
        setPreview(null);
        reset();
        clearInput();
    };

    const submit = () => {
        post(update().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: clearSelection,
        });
    };

    const remove = () => {
        router.delete(destroy().url, { preserveScroll: true });
    };

    const shown = preview ?? logoUrl;

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Logo"
                description={`JPG, PNG, or WebP up to ${maxLabel}. Appears in the header and on invoices.`}
            />

            <div className="flex items-center gap-5">
                <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-muted">
                    {shown ? (
                        <img
                            src={shown}
                            alt="Company logo"
                            className="size-full object-contain"
                        />
                    ) : (
                        <ImageUp className="size-7 text-muted-foreground" />
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <input
                        ref={fileInput}
                        type="file"
                        accept={ACCEPTED}
                        className="hidden"
                        onChange={(event) =>
                            choose(event.target.files?.[0] ?? null)
                        }
                    />

                    <Button
                        type="button"
                        variant="outline"
                        disabled={!canUpdate || processing}
                        onClick={() => fileInput.current?.click()}
                    >
                        Choose image
                    </Button>

                    {data.logo && (
                        <>
                            <Button
                                type="button"
                                disabled={processing}
                                onClick={submit}
                                data-test="upload-logo-button"
                            >
                                {processing && <Spinner />}
                                Save logo
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={processing}
                                onClick={clearSelection}
                            >
                                Cancel
                            </Button>
                        </>
                    )}

                    {!data.logo && logoUrl && canUpdate && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={remove}
                            data-test="remove-logo-button"
                        >
                            <Trash2 className="size-4" />
                            Remove
                        </Button>
                    )}
                </div>
            </div>

            {progress && (
                <div
                    className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuenow={progress.percentage ?? 0}
                    aria-valuemin={0}
                    aria-valuemax={100}
                >
                    <div
                        className="h-full bg-ocean-500 transition-all"
                        style={{ width: `${progress.percentage ?? 0}%` }}
                    />
                </div>
            )}

            <InputError message={sizeError ?? errors.logo} />
        </div>
    );
}
