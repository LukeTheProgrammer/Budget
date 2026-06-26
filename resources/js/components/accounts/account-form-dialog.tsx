import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store, update } from '@/routes/accounts';
import type { AccountListItem, AccountTypeOption } from '@/types/accounts';

/** Convert an integer cent amount to a plain decimal string for an input. */
function centsToInput(cents: number | null): string {
    return cents === null ? '' : (cents / 100).toString();
}

const NO_TYPE = '__none';

type AccountFormDialogProps = {
    open: boolean;
    onClose: () => void;
    /** The account being edited, or null when creating a new one. */
    account: AccountListItem | null;
    accountTypes: AccountTypeOption[];
};

export function AccountFormDialog({
    open,
    onClose,
    account,
    accountTypes,
}: AccountFormDialogProps) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                <AccountForm
                    key={account?.id ?? 'new'}
                    account={account}
                    accountTypes={accountTypes}
                    onSaved={onClose}
                />
            </DialogContent>
        </Dialog>
    );
}

function AccountForm({
    account,
    accountTypes,
    onSaved,
}: {
    account: AccountListItem | null;
    accountTypes: AccountTypeOption[];
    onSaved: () => void;
}) {
    const isEditing = account !== null;
    const isLinked = account?.is_linked ?? false;

    const { data, setData, post, patch, processing, errors } = useForm({
        name: account?.name ?? '',
        type: account?.type ?? '',
        currency: account?.currency ?? 'USD',
        last_four: account?.last_four ?? '',
        balance: centsToInput(account?.balance_cents ?? null),
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onSaved };

        if (isEditing) {
            patch(update({ account: account.id }).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <form onSubmit={submit} className="space-y-5">
            <DialogHeader>
                <DialogTitle>
                    {isEditing ? 'Edit account' : 'Add account'}
                </DialogTitle>
                <DialogDescription>
                    {isLinked
                        ? 'This account is linked to a bank. You can rename it; other details sync automatically.'
                        : 'Track an account manually by giving it a name and optional details.'}
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    autoFocus
                    required
                />
                <InputError message={errors.name} />
            </div>

            {!isLinked && (
                <>
                    <div className="space-y-2">
                        <Label htmlFor="type">Type</Label>
                        <Select
                            value={data.type === '' ? NO_TYPE : data.type}
                            onValueChange={(value) =>
                                setData('type', value === NO_TYPE ? '' : value)
                            }
                        >
                            <SelectTrigger id="type">
                                <SelectValue placeholder="No type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_TYPE}>No type</SelectItem>
                                {accountTypes.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="currency">Currency</Label>
                            <Input
                                id="currency"
                                value={data.currency}
                                onChange={(event) =>
                                    setData(
                                        'currency',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                maxLength={3}
                            />
                            <InputError message={errors.currency} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="last_four">Last four</Label>
                            <Input
                                id="last_four"
                                value={data.last_four}
                                onChange={(event) =>
                                    setData('last_four', event.target.value)
                                }
                                inputMode="numeric"
                                maxLength={4}
                            />
                            <InputError message={errors.last_four} />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="balance">Balance</Label>
                        <Input
                            id="balance"
                            value={data.balance}
                            onChange={(event) =>
                                setData('balance', event.target.value)
                            }
                            inputMode="decimal"
                            placeholder="0.00"
                        />
                        <InputError message={errors.balance} />
                    </div>
                </>
            )}

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onSaved}>
                    Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                    {isEditing ? 'Save changes' : 'Add account'}
                </Button>
            </DialogFooter>
        </form>
    );
}
