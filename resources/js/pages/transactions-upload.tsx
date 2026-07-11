import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/Transactions/UploadController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ColumnMapper, emptyMapping, isMappingComplete, suggestMapping } from '@/components/transactions/column-mapper';
import type { ColumnMapping } from '@/components/transactions/column-mapper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { parseDelimited } from '@/lib/csv';

type AccountOption = { id: number; name: string; currency: string };

type ImportResult = {
    imported: number;
    skipped: number;
    failed: number;
    needsReview: number;
    failures: { line: number; reason: string }[];
};

type PageProps = {
    accounts: AccountOption[];
    savedMappings: Record<number, ColumnMapping>;
    importResult?: ImportResult;
};

const PREVIEW_ROWS = 5;

export default function TransactionsUpload() {
    const { accounts, savedMappings, importResult } = usePage<PageProps>().props;

    const [headers, setHeaders] = useState<string[]>([]);
    const [previewRows, setPreviewRows] = useState<string[][]>([]);
    const [parseError, setParseError] = useState<string | null>(null);

    const { data, setData, post, processing, errors, transform } = useForm<{
        account_id: string;
        file: File | null;
        mapping: ColumnMapping;
    }>({
        account_id: '',
        file: null,
        mapping: emptyMapping(),
    });

    function chooseAccount(accountId: string): void {
        setData('account_id', accountId);

        // Pre-fill from a saved mapping for this account, else suggest from
        // headers if a file has already been parsed.
        const saved = savedMappings[Number(accountId)];

        if (saved) {
            setData('mapping', saved);
        } else if (headers.length > 0) {
            setData('mapping', suggestMapping(headers));
        }
    }

    async function chooseFile(event: ChangeEvent<HTMLInputElement>): Promise<void> {
        const file = event.target.files?.[0] ?? null;
        setData('file', file);
        setParseError(null);
        setHeaders([]);
        setPreviewRows([]);

        if (file === null) {
            return;
        }

        const rows = parseDelimited(await file.text());

        if (rows.length === 0 || rows[0].length === 0) {
            setParseError('This file has no header row.');

            return;
        }

        if (rows.length < 2) {
            setParseError('This file has no data rows.');
        }

        const parsedHeaders = rows[0];
        setHeaders(parsedHeaders);
        setPreviewRows(rows.slice(1, 1 + PREVIEW_ROWS));

        // Prefer a saved mapping for the selected account, else suggest.
        const saved = savedMappings[Number(data.account_id)];
        setData('mapping', saved ?? suggestMapping(parsedHeaders));
    }

    const canSubmit =
        data.account_id !== '' && data.file !== null && headers.length > 0 && isMappingComplete(data.mapping);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        transform((payload) => ({
            ...payload,
            mapping: {
                ...payload.mapping,
                date_format: payload.mapping.date_format ?? '',
            },
        }));

        post(store().url, { forceFormData: true, preserveScroll: true });
    }

    return (
        <div className="mx-auto w-full max-w-3xl px-4 py-8">
            <Head title="Upload transactions" />

            <Heading
                title="Upload transactions"
                description="Upload a CSV file, map its columns, and import the transactions into an account."
            />

            {importResult && (
                <Alert className="mt-6">
                    <AlertTitle>Import complete</AlertTitle>
                    <AlertDescription>
                        <p>
                            Imported {importResult.imported}, skipped {importResult.skipped}, failed{' '}
                            {importResult.failed}.
                            {importResult.needsReview > 0 &&
                                ` ${importResult.needsReview} new merchant(s) need review.`}
                        </p>
                        {importResult.failures.length > 0 && (
                            <ul className="mt-2 list-disc pl-5 text-sm">
                                {importResult.failures.map((failure) => (
                                    <li key={failure.line}>
                                        Row {failure.line}: {failure.reason}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="account">Account</Label>
                    <Select value={data.account_id} onValueChange={chooseAccount}>
                        <SelectTrigger id="account" className="w-full">
                            <SelectValue placeholder="Select an account" />
                        </SelectTrigger>
                        <SelectContent>
                            {accounts.map((account) => (
                                <SelectItem key={account.id} value={String(account.id)}>
                                    {account.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.account_id} />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="file">File</Label>
                    <input
                        id="file"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        onChange={chooseFile}
                        className="block w-full text-sm text-muted-foreground file:mr-4 file:rounded-md file:border-0 file:bg-secondary file:px-4 file:py-2 file:text-sm file:font-medium"
                    />
                    {parseError && <InputError message={parseError} />}
                    <InputError message={errors.file} />
                </div>

                {headers.length > 0 && (
                    <ColumnMapper
                        headers={headers}
                        previewRows={previewRows}
                        mapping={data.mapping}
                        onChange={(mapping) => setData('mapping', mapping)}
                        errors={errors as Partial<Record<string, string>>}
                    />
                )}

                <div className="flex justify-end">
                    <Button type="submit" disabled={!canSubmit || processing}>
                        Import transactions
                    </Button>
                </div>
            </form>
        </div>
    );
}
