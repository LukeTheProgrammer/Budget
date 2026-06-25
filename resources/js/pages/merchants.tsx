import { Head, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import MerchantController from '@/actions/App/Http/Controllers/Merchants/MerchantController';
import MerchantGroupController from '@/actions/App/Http/Controllers/Merchants/MerchantGroupController';
import { EditMerchantDialog } from '@/components/merchants/edit-merchant-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/merchants';
import type { BreadcrumbItem, Merchant, MerchantTag } from '@/types';

type MerchantsPageProps = {
    merchants: Merchant[];
    available_tags: MerchantTag[];
};

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency: 'usd',
    }).format(value / 100);
}

function GroupDialog({
    merchants,
    selectedIds,
    open,
    onOpenChange,
    onGrouped,
}: {
    merchants: Merchant[];
    selectedIds: number[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onGrouped: () => void;
}) {
    const selected = merchants.filter((merchant) =>
        selectedIds.includes(merchant.id),
    );
    const [primaryId, setPrimaryId] = useState<number | null>(
        selectedIds[0] ?? null,
    );
    const [name, setName] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        if (primaryId === null) {
            return;
        }

        setProcessing(true);
        router.post(
            MerchantGroupController.store.url(),
            {
                primary_merchant_id: primaryId,
                merchant_ids: selectedIds,
                name: name.trim() === '' ? null : name,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onGrouped();
                    onOpenChange(false);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Group merchants</DialogTitle>
                    <DialogDescription>
                        Pick the merchant to keep. The others are merged into it
                        — their transactions move over and their names become
                        aliases.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            Primary merchant
                        </legend>
                        {selected.map((merchant) => (
                            <label
                                key={merchant.id}
                                className="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="radio"
                                    name="primary_merchant_id"
                                    value={merchant.id}
                                    checked={primaryId === merchant.id}
                                    onChange={() => setPrimaryId(merchant.id)}
                                />
                                <span>{merchant.name}</span>
                            </label>
                        ))}
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="group-name">Name (optional)</Label>
                        <Input
                            id="group-name"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            placeholder="e.g. Hy-Vee"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={processing || primaryId === null}
                    >
                        Group {selectedIds.length} merchants
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function MerchantsIndex({
    merchants,
    available_tags,
}: MerchantsPageProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const editingMerchant =
        merchants.find((merchant) => merchant.id === editingId) ?? null;

    const reviewCount = merchants.filter(
        (merchant) => !merchant.confirmed,
    ).length;

    const toggle = (id: number, checked: boolean) => {
        setSelectedIds((current) =>
            checked
                ? [...current, id]
                : current.filter((value) => value !== id),
        );
    };

    const confirmSuggested = (merchant: Merchant) => {
        router.patch(
            MerchantController.update.url(merchant.id),
            { name: merchant.suggested_name ?? merchant.name },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Merchants" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Merchants</h1>
                        <p className="text-sm text-muted-foreground">
                            {reviewCount > 0
                                ? `${reviewCount} merchant(s) need review.`
                                : 'Manage how merchants appear and group store variants together.'}
                        </p>
                    </div>
                    {selectedIds.length >= 2 && (
                        <Button onClick={() => setDialogOpen(true)}>
                            Group {selectedIds.length} merchants
                        </Button>
                    )}
                </div>

                {merchants.length === 0 ? (
                    <div className="rounded-xl border border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border">
                        No merchants yet. Import some transactions to get
                        started.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                                <tr>
                                    <th className="w-10 px-4 py-3" />
                                    <th className="px-4 py-3 font-medium">
                                        Merchant
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Aliases
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Transaction Count
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Transaction Sum
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {merchants.map((merchant) => (
                                    <tr
                                        key={merchant.id}
                                        className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40"
                                    >
                                        <td className="px-4 py-3">
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    merchant.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        merchant.id,
                                                        checked === true,
                                                    )
                                                }
                                                aria-label={`Select ${merchant.name}`}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {merchant.name}
                                                </span>
                                                {!merchant.confirmed && (
                                                    <Badge variant="outline">
                                                        Needs review
                                                    </Badge>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7 text-muted-foreground"
                                                    aria-label={`Edit ${merchant.name}`}
                                                    onClick={() =>
                                                        setEditingId(
                                                            merchant.id,
                                                        )
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Button>
                                            </div>
                                            {!merchant.confirmed &&
                                                merchant.suggested_name && (
                                                    <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                                        <span>
                                                            Suggested:{' '}
                                                            {
                                                                merchant.suggested_name
                                                            }
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            variant="link"
                                                            className="h-auto p-0 text-xs"
                                                            onClick={() =>
                                                                confirmSuggested(
                                                                    merchant,
                                                                )
                                                            }
                                                        >
                                                            Confirm
                                                        </Button>
                                                    </div>
                                                )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground tabular-nums">
                                            {merchant.aliases.length}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {merchant.transactions_count}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatCurrency(
                                                merchant.transactions_sum,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {dialogOpen && (
                <GroupDialog
                    merchants={merchants}
                    selectedIds={selectedIds}
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    onGrouped={() => setSelectedIds([])}
                />
            )}

            {editingMerchant && (
                <EditMerchantDialog
                    merchant={editingMerchant}
                    availableTags={available_tags}
                    open={editingId !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingId(null);
                        }
                    }}
                />
            )}
        </>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Merchants',
        href: index(),
    },
];

MerchantsIndex.layout = {
    breadcrumbs,
};
