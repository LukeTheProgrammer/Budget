import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Pagination } from '@/types';

/**
 * Renders Laravel's paginator link collection as a numbered page nav. Renders
 * nothing when there is only a single page.
 */
export function PaginationNav({ pagination }: { pagination: Pagination }) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center justify-center gap-1">
            {pagination.links.map((link, index) => {
                const className = cn(
                    'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm',
                    link.active && 'border-primary bg-primary text-primary-foreground',
                    !link.url && 'pointer-events-none text-muted-foreground opacity-50',
                );

                if (!link.url) {
                    return <span key={index} className={className} dangerouslySetInnerHTML={{ __html: link.label }} />;
                }

                return (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        preserveState
                        className={className}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                );
            })}
        </nav>
    );
}
