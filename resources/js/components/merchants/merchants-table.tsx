import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import MerchantController from '@/actions/App/Http/Controllers/Merchants/MerchantController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatMoney } from '@/lib/format';
import type { Merchant } from '@/types';

function MerchantRow({
    merchant,
    selected,
    onSelectedChange,
    onEdit,
}: {
    merchant: Merchant;
    selected: boolean;
    onSelectedChange: (selected: boolean) => void;
    onEdit: () => void;
}) {
    /**
     * Accept the suggested clean name. The rename also confirms the merchant, so
     * the row drops out of the review tab.
     */
    const confirmSuggested = () => {
        router.patch(
            MerchantController.update.url(merchant.id),
            { name: merchant.suggested_name ?? merchant.name },
            { preserveScroll: true },
        );
    };

    return (
        <tr className="border-b border-sidebar-border/40 last:border-0 dark:border-sidebar-border/40">
            <td className="px-4 py-3">
                <Checkbox
                    checked={selected}
                    onCheckedChange={(checked) => onSelectedChange(checked === true)}
                    aria-label={`Select ${merchant.name}`}
                />
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <span className="font-medium">{merchant.name}</span>
                    {!merchant.confirmed && <Badge variant="outline">Needs review</Badge>}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-muted-foreground"
                        aria-label={`Edit ${merchant.name}`}
                        onClick={onEdit}
                    >
                        <Pencil className="size-4" />
                    </Button>
                </div>
                {!merchant.confirmed && merchant.suggested_name && (
                    <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                        <span>Suggested: {merchant.suggested_name}</span>
                        <Button type="button" variant="link" className="h-auto p-0 text-xs" onClick={confirmSuggested}>
                            Confirm
                        </Button>
                    </div>
                )}
            </td>
            <td className="px-4 py-3 text-muted-foreground">{merchant.category_name ?? 'Uncategorized'}</td>
            <td className="px-4 py-3 text-muted-foreground tabular-nums">{merchant.aliases.length}</td>
            <td className="px-4 py-3 text-right tabular-nums">{merchant.transactions_count}</td>
            <td className="px-4 py-3 text-right tabular-nums">{formatMoney(merchant.transactions_sum, 'USD')}</td>
        </tr>
    );
}

export function MerchantsTable({
    merchants,
    selectedIds,
    onSelectedChange,
    onEdit,
}: {
    merchants: Merchant[];
    selectedIds: number[];
    onSelectedChange: (id: number, selected: boolean) => void;
    onEdit: (id: number) => void;
}) {
    return (
        <div className="max-h-[70vh] overflow-x-auto overflow-y-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table className="w-full text-sm">
                <thead className="border-b border-sidebar-border/70 text-left text-muted-foreground dark:border-sidebar-border">
                    <tr>
                        <th className="w-10 px-4 py-3" />
                        <th className="px-4 py-3 font-medium">Merchant</th>
                        <th className="px-4 py-3 font-medium">Category</th>
                        <th className="px-4 py-3 font-medium">Aliases</th>
                        <th className="px-4 py-3 text-right font-medium">Transaction Count</th>
                        <th className="px-4 py-3 text-right font-medium">Transaction Sum</th>
                    </tr>
                </thead>
                <tbody>
                    {merchants.map((merchant) => (
                        <MerchantRow
                            key={merchant.id}
                            merchant={merchant}
                            selected={selectedIds.includes(merchant.id)}
                            onSelectedChange={(selected) => onSelectedChange(merchant.id, selected)}
                            onEdit={() => onEdit(merchant.id)}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}
