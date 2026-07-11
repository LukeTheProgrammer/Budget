import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';

export type TransactionRow = {
    id: number;
    merchant_label: string;
    category_name: string | null;
    posted_at: string;
    amount_cents: number;
    currency: string;
};

function formatDate(isoDate: string): string {
    return new Date(isoDate).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function TransactionsTable({
    title,
    transactions,
    emptyMessage,
}: {
    title: string;
    transactions: TransactionRow[];
    emptyMessage: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {transactions.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">{emptyMessage}</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-muted-foreground">
                            <tr className="border-b">
                                <th className="py-2 text-left font-medium">Merchant</th>
                                <th className="py-2 text-left font-medium">Category</th>
                                <th className="py-2 text-left font-medium">Date</th>
                                <th className="py-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.map((row) => (
                                <tr key={row.id} className="border-b last:border-0">
                                    <td className="py-2">{row.merchant_label}</td>
                                    <td className="py-2 text-muted-foreground">
                                        {row.category_name ?? 'Uncategorized'}
                                    </td>
                                    <td className="py-2 text-muted-foreground">{formatDate(row.posted_at)}</td>
                                    <td className="py-2 text-right tabular-nums">
                                        {formatMoney(row.amount_cents, row.currency)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardContent>
        </Card>
    );
}
