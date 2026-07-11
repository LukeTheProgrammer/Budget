import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import PlaidLinkButton from '@/components/plaid-link-button';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { sync } from '@/routes/plaid';
import { destroy as destroyConnection } from '@/routes/plaid/connections';

type LinkedAccount = {
    id: number;
    name: string;
    type: string | null;
    last_four: string | null;
    balance_cents: number | null;
    currency: string;
};

type Connection = {
    id: number;
    institution_name: string | null;
    status: 'active' | 'reauth_required' | 'error';
    last_synced_at: string | null;
    accounts: LinkedAccount[];
};

type PageProps = {
    connections: Connection[];
    status?: string;
};

const statusLabels: Record<Connection['status'], string> = {
    active: 'Connected',
    reauth_required: 'Needs attention',
    error: 'Error',
};

function formatLastSynced(value: string | null): string {
    if (!value) {
        return 'Never synced';
    }

    return `Last synced ${new Date(value).toLocaleString()}`;
}

export default function Connections() {
    const { connections, status } = usePage<PageProps>().props;
    const [syncingId, setSyncingId] = useState<number | null>(null);

    const triggerSync = (connectionId: number) => {
        setSyncingId(connectionId);

        router.post(
            sync({ connection: connectionId }).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncingId(null),
            },
        );
    };

    const disconnect = (connection: Connection) => {
        const name = connection.institution_name ?? 'this bank';

        if (!window.confirm(`Disconnect ${name}? It will stop syncing. Imported transactions are kept.`)) {
            return;
        }

        router.delete(destroyConnection({ connection: connection.id }).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Bank connections" />

            <h1 className="sr-only">Bank connections</h1>

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Bank connections"
                        description="Link your bank to import transactions automatically"
                    />

                    <PlaidLinkButton />
                </div>

                {status && <p className="text-sm text-muted-foreground">{status}</p>}

                {connections.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No banks linked yet. Use “Link a bank” to connect your first account.
                    </p>
                ) : (
                    <ul className="space-y-4">
                        {connections.map((connection) => (
                            <li key={connection.id} className="rounded-lg border p-4">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <span className="font-medium">
                                            {connection.institution_name ?? 'Linked bank'}
                                        </span>
                                        <p className="text-xs text-muted-foreground">
                                            {statusLabels[connection.status]} ·{' '}
                                            {formatLastSynced(connection.last_synced_at)}
                                        </p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {connection.status === 'reauth_required' && (
                                            <PlaidLinkButton connectionId={connection.id} label="Re-authenticate" />
                                        )}

                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => triggerSync(connection.id)}
                                            disabled={syncingId === connection.id}
                                        >
                                            {syncingId === connection.id ? 'Syncing…' : 'Sync'}
                                        </Button>

                                        <Button size="sm" variant="ghost" onClick={() => disconnect(connection)}>
                                            Disconnect
                                        </Button>
                                    </div>
                                </div>

                                <ul className="mt-3 space-y-1">
                                    {connection.accounts.map((account) => (
                                        <li key={account.id} className="flex items-center justify-between text-sm">
                                            <span>
                                                {account.name}
                                                {account.last_four && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        ••••{account.last_four}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="tabular-nums">
                                                {account.balance_cents !== null
                                                    ? formatMoney(account.balance_cents, account.currency)
                                                    : '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
