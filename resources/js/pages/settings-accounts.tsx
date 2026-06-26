import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AccountFormDialog } from '@/components/accounts/account-form-dialog';
import { DeleteAccountDialog } from '@/components/accounts/delete-account-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import type { AccountListItem, AccountTypeOption } from '@/types/accounts';

type PageProps = {
    accounts: AccountListItem[];
    accountTypes: AccountTypeOption[];
};

function typeLabel(
    value: string | null,
    accountTypes: AccountTypeOption[],
): string | null {
    if (!value) {
        return null;
    }

    return (
        accountTypes.find((option) => option.value === value)?.label ?? value
    );
}

export default function Accounts() {
    const { accounts, accountTypes } = usePage<PageProps>().props;

    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<AccountListItem | null>(null);
    const [deleting, setDeleting] = useState<AccountListItem | null>(null);

    return (
        <>
            <Head title="Accounts" />

            <h1 className="sr-only">Accounts</h1>

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Accounts"
                        description="Create and manage the accounts you track"
                    />

                    <Button size="sm" onClick={() => setCreateOpen(true)}>
                        Add account
                    </Button>
                </div>

                {accounts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No accounts yet. Use “Add account” to create your first
                        one.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {accounts.map((account) => {
                            const label = typeLabel(account.type, accountTypes);

                            return (
                                <li
                                    key={account.id}
                                    className="flex items-center justify-between gap-4 rounded-lg border p-4"
                                >
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {account.name}
                                            </span>
                                            {account.is_linked && (
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                    {account.institution_name ??
                                                        'Linked'}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                label,
                                                account.last_four
                                                    ? `••••${account.last_four}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <span className="tabular-nums">
                                            {account.balance_cents !== null
                                                ? formatMoney(
                                                      account.balance_cents,
                                                      account.currency,
                                                  )
                                                : '—'}
                                        </span>

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setEditing(account)}
                                        >
                                            Edit
                                        </Button>

                                        {!account.is_linked && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setDeleting(account)
                                                }
                                            >
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <AccountFormDialog
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                account={null}
                accountTypes={accountTypes}
            />

            <AccountFormDialog
                open={editing !== null}
                onClose={() => setEditing(null)}
                account={editing}
                accountTypes={accountTypes}
            />

            <DeleteAccountDialog
                account={deleting}
                onClose={() => setDeleting(null)}
            />
        </>
    );
}
