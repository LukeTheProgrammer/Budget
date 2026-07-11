import { router } from '@inertiajs/react';
import { useState } from 'react';
import MerchantGroupController from '@/actions/App/Http/Controllers/Merchants/MerchantGroupController';
import { Button } from '@/components/ui/button';
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
import type { Merchant } from '@/types';

/**
 * Merge the selected merchants into one. The primary survives; the others hand
 * over their transactions and become aliases of it.
 */
export function GroupMerchantsDialog({
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
    const selected = merchants.filter((merchant) => selectedIds.includes(merchant.id));
    const [primaryId, setPrimaryId] = useState<number | null>(selectedIds[0] ?? null);
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
                        Pick the merchant to keep. The others are merged into it — their transactions move over and
                        their names become aliases.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">Primary merchant</legend>
                        {selected.map((merchant) => (
                            <label key={merchant.id} className="flex items-center gap-2 text-sm">
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
                    <Button type="button" onClick={submit} disabled={processing || primaryId === null}>
                        Group {selectedIds.length} merchants
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
