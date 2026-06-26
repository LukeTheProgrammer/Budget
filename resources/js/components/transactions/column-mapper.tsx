import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type FieldKey = 'posted_at' | 'amount' | 'description' | 'currency';

export type ColumnMapping = {
    fields: Record<FieldKey, string>;
    amount_sign: 'as_is' | 'invert';
    date_format: string | null;
};

/** Sentinel for the "not mapped" option, since Select values must be non-empty. */
const UNMAPPED = '__unmapped';

const FIELDS: { key: FieldKey; label: string; required: boolean }[] = [
    { key: 'posted_at', label: 'Date', required: true },
    { key: 'amount', label: 'Amount', required: true },
    { key: 'description', label: 'Description / Merchant', required: true },
    { key: 'currency', label: 'Currency (optional)', required: false },
];

const REQUIRED_FIELDS: FieldKey[] = ['posted_at', 'amount', 'description'];

/**
 * Whether every required field is mapped and no header is assigned to more than
 * one required field. Used to gate the import action (FR-006, FR-007).
 */
export function isMappingComplete(mapping: ColumnMapping): boolean {
    const assigned = REQUIRED_FIELDS.map((field) => mapping.fields[field]);

    if (assigned.some((header) => header === '')) {
        return false;
    }

    return new Set(assigned).size === assigned.length;
}

type ColumnMapperProps = {
    headers: string[];
    previewRows: string[][];
    mapping: ColumnMapping;
    onChange: (mapping: ColumnMapping) => void;
    /** Server-side validation errors keyed by `mapping.fields.<key>`. */
    errors: Partial<Record<string, string>>;
};

export function ColumnMapper({
    headers,
    previewRows,
    mapping,
    onChange,
    errors,
}: ColumnMapperProps) {
    function setField(key: FieldKey, header: string): void {
        onChange({
            ...mapping,
            fields: { ...mapping.fields, [key]: header },
        });
    }

    // Headers assigned to more than one required field (client-side conflict).
    const conflicts = useMemo(() => {
        const counts = new Map<string, number>();

        for (const field of REQUIRED_FIELDS) {
            const header = mapping.fields[field];

            if (header !== '') {
                counts.set(header, (counts.get(header) ?? 0) + 1);
            }
        }

        return new Set(
            [...counts.entries()]
                .filter(([, count]) => count > 1)
                .map(([header]) => header),
        );
    }, [mapping.fields]);

    function fieldMessage(
        key: FieldKey,
        required: boolean,
    ): string | undefined {
        const header = mapping.fields[key];

        if (required && header === '') {
            return 'Map this field to a column.';
        }

        if (header !== '' && conflicts.has(header)) {
            return 'This column is mapped to more than one field.';
        }

        return errors[`mapping.fields.${key}`];
    }

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                {FIELDS.map(({ key, label, required }) => (
                    <div key={key} className="space-y-2">
                        <Label htmlFor={`field-${key}`}>
                            {label}
                            {required && (
                                <span className="text-red-600"> *</span>
                            )}
                        </Label>
                        <Select
                            value={
                                mapping.fields[key] === ''
                                    ? UNMAPPED
                                    : mapping.fields[key]
                            }
                            onValueChange={(value) =>
                                setField(key, value === UNMAPPED ? '' : value)
                            }
                        >
                            <SelectTrigger id={`field-${key}`}>
                                <SelectValue placeholder="Select a column" />
                            </SelectTrigger>
                            <SelectContent>
                                {!required && (
                                    <SelectItem value={UNMAPPED}>
                                        Not mapped
                                    </SelectItem>
                                )}
                                {headers.map((header, position) => (
                                    <SelectItem
                                        key={`${header}-${position}`}
                                        value={header}
                                    >
                                        {header}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={fieldMessage(key, required)} />
                    </div>
                ))}
            </div>

            <div className="space-y-2">
                <Label htmlFor="amount-sign">Amount sign</Label>
                <Select
                    value={mapping.amount_sign}
                    onValueChange={(value) =>
                        onChange({
                            ...mapping,
                            amount_sign: value as ColumnMapping['amount_sign'],
                        })
                    }
                >
                    <SelectTrigger id="amount-sign" className="sm:w-80">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="as_is">
                            Positive values are purchases
                        </SelectItem>
                        <SelectItem value="invert">
                            Negative values are purchases (invert)
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                    Choose “invert” for exports (like Chase) that sign purchases
                    negative.
                </p>
            </div>

            {previewRows.length > 0 && (
                <div className="space-y-2">
                    <Label>Preview</Label>
                    <div className="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {FIELDS.map(({ key, label }) => (
                                        <TableHead key={key}>{label}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {previewRows.map((row, rowIndex) => (
                                    <TableRow key={rowIndex}>
                                        {FIELDS.map(({ key }) => {
                                            const header = mapping.fields[key];
                                            const position =
                                                header === ''
                                                    ? -1
                                                    : headers.indexOf(header);

                                            return (
                                                <TableCell key={key}>
                                                    {position >= 0
                                                        ? (row[position] ?? '')
                                                        : ''}
                                                </TableCell>
                                            );
                                        })}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Suggest a mapping by matching header names to fields (FR-008). Returns a
 * mapping with best-guess assignments; unmatched fields are left empty.
 */
export function suggestMapping(headers: string[]): ColumnMapping {
    const lower = headers.map((header) => header.toLowerCase());

    const match = (patterns: string[]): string => {
        const position = lower.findIndex((header) =>
            patterns.some((pattern) => header.includes(pattern)),
        );

        return position >= 0 ? headers[position] : '';
    };

    return {
        fields: {
            posted_at: match(['post date', 'posted', 'date']),
            amount: match(['amount', 'value', 'debit']),
            description: match(['description', 'merchant', 'name', 'payee']),
            currency: match(['currency']),
        },
        amount_sign: 'as_is',
        date_format: null,
    };
}

export function emptyMapping(): ColumnMapping {
    return {
        fields: { posted_at: '', amount: '', description: '', currency: '' },
        amount_sign: 'as_is',
        date_format: null,
    };
}
