import { router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { usePlaidLink } from 'react-plaid-link';
import type { PlaidLinkOnSuccess, PlaidLinkOnSuccessMetadata } from 'react-plaid-link';
import { Button } from '@/components/ui/button';
import { exchange, linkToken, sync } from '@/routes/plaid';

type PlaidLinkButtonProps = {
    /** When set, opens Plaid Link in update mode to re-authenticate this connection. */
    connectionId?: number;
    label?: string;
};

/**
 * Read a cookie value by name, URL-decoding it. Used to forward Laravel's
 * XSRF-TOKEN on the link-token request.
 */
function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

export default function PlaidLinkButton({ connectionId, label = 'Link a bank' }: PlaidLinkButtonProps) {
    const [token, setToken] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const onSuccess = useCallback<PlaidLinkOnSuccess>(
        (publicToken: string, metadata: PlaidLinkOnSuccessMetadata) => {
            // Update mode (re-auth) keeps the same access token, so there is no
            // public token to exchange — just re-sync to clear the error state.
            if (connectionId) {
                router.post(sync({ connection: connectionId }).url);
            } else {
                router.post(exchange().url, {
                    public_token: publicToken,
                    institution: {
                        institution_id: metadata.institution?.institution_id ?? null,
                        name: metadata.institution?.name ?? null,
                    },
                });
            }

            setToken(null);
        },
        [connectionId],
    );

    const { open, ready } = usePlaidLink({
        token: token ?? '',
        onSuccess,
        onExit: () => setToken(null),
    });

    // Open Plaid Link as soon as a freshly fetched token is ready.
    useEffect(() => {
        if (token && ready) {
            open();
        }
    }, [token, ready, open]);

    const startLink = useCallback(async () => {
        setLoading(true);

        try {
            const response = await fetch(linkToken().url, {
                method: 'post',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
                },
                body: JSON.stringify(connectionId ? { connection: connectionId } : {}),
            });

            const data: { link_token: string } = await response.json();
            setToken(data.link_token);
        } finally {
            setLoading(false);
        }
    }, [connectionId]);

    return (
        <Button onClick={startLink} disabled={loading}>
            {loading ? 'Connecting…' : label}
        </Button>
    );
}
