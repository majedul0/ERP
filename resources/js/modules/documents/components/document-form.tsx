import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CompanyDocumentDetail, DocumentCategoryOption } from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type FormProps = Omit<React.ComponentProps<typeof Form>, 'children'>;

/**
 * The upload/edit form.
 *
 * Editing does not require the file again — correcting an expiry date should
 * not mean finding the scan. Choosing one anyway files a new version and keeps
 * the old, which the hint says out loud so nobody expects a replacement to
 * overwrite last year's licence.
 */
export default function DocumentForm({
    form,
    categories,
    document,
    submitLabel,
    testId,
    maxMegabytes,
}: {
    form: FormProps;
    categories: DocumentCategoryOption[];
    document?: CompanyDocumentDetail;
    submitLabel: string;
    testId: string;
    maxMegabytes: number;
}) {
    const [category, setCategory] = useState(
        document?.category ?? categories[0]?.value ?? 'other',
    );

    const selected = categories.find((option) => option.value === category);

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={!document}
            className="mt-8 space-y-5 pb-16"
        >
            {({ processing, errors: formErrors }) => {
                const errors = formErrors as Record<string, string | undefined>;

                return (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="title" className="text-coffee-900">
                                Title<span aria-hidden="true">*</span>
                            </Label>
                            <Input
                                id="title"
                                name="title"
                                defaultValue={document?.title}
                                required
                                placeholder="Trade Licence 2026"
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="category"
                                    className="text-coffee-900"
                                >
                                    Kind<span aria-hidden="true">*</span>
                                </Label>
                                <select
                                    id="category"
                                    name="category"
                                    className={selectClasses}
                                    value={category}
                                    onChange={(event) =>
                                        setCategory(event.target.value)
                                    }
                                    required
                                >
                                    {categories.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                {selected && !errors.category && (
                                    <p className="text-xs text-coffee-800/60">
                                        {selected.hint}
                                    </p>
                                )}
                                <InputError message={errors.category} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="reference"
                                    className="text-coffee-900"
                                >
                                    Reference number
                                </Label>
                                <Input
                                    id="reference"
                                    name="reference"
                                    defaultValue={document?.reference ?? ''}
                                    placeholder="As printed on the document"
                                />
                                <InputError message={errors.reference} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="issued_on"
                                    className="text-coffee-900"
                                >
                                    Issued on
                                </Label>
                                <Input
                                    id="issued_on"
                                    name="issued_on"
                                    type="date"
                                    defaultValue={document?.issuedOn ?? ''}
                                />
                                <InputError message={errors.issued_on} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="expires_on"
                                    className="text-coffee-900"
                                >
                                    Expires on
                                </Label>
                                <Input
                                    id="expires_on"
                                    name="expires_on"
                                    type="date"
                                    defaultValue={document?.expiresOn ?? ''}
                                />
                                {!errors.expires_on && (
                                    <p className="text-xs text-coffee-800/60">
                                        {selected?.usuallyExpires
                                            ? 'This kind usually renews — the reminder only works if the date is here.'
                                            : 'Leave empty if it never expires.'}
                                    </p>
                                )}
                                <InputError message={errors.expires_on} />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="file" className="text-coffee-900">
                                File
                                {!document && <span aria-hidden="true">*</span>}
                            </Label>
                            <Input
                                id="file"
                                name="file"
                                type="file"
                                accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx"
                                required={!document}
                                data-test="document-file"
                            />
                            {!errors.file && (
                                <p className="text-xs text-coffee-800/60">
                                    {document
                                        ? `Currently ${document.originalName} (version ${document.version}). Choosing a file files a new version and keeps the old one.`
                                        : `PDF, image or Office document, up to ${maxMegabytes} MB.`}
                                </p>
                            )}
                            <InputError message={errors.file} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="note" className="text-coffee-900">
                                Note
                            </Label>
                            <textarea
                                id="note"
                                name="note"
                                rows={3}
                                defaultValue={document?.note ?? ''}
                                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="Where the original is kept, who renews it, anything worth knowing next year."
                            />
                            <InputError message={errors.note} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            data-test={testId}
                            className="bg-coffee-600 hover:bg-coffee-700"
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </>
                );
            }}
        </Form>
    );
}
