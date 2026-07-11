import { router } from '@inertiajs/react';
import TagController from '@/actions/App/Http/Controllers/Tags/TagController';
import TransactionTagController from '@/actions/App/Http/Controllers/Transactions/TransactionTagController';
import { TagEditor } from '@/components/tags/tag-editor';
import type { Tag } from '@/components/tags/tag-editor';

export type { Tag };

type TransactionTagsProps = {
    transactionId: number;
    tags: Tag[];
    availableTags: Tag[];
};

/**
 * Tag editor for a single transaction, persisting changes through the
 * transaction tag endpoints.
 */
export function TransactionTags({ transactionId, tags, availableTags }: TransactionTagsProps) {
    const addTag = (name: string) => {
        router.post(
            TransactionTagController.store.url(transactionId),
            { tags: [name] },
            { preserveScroll: true, preserveState: true },
        );
    };

    const removeTag = (slug: string) => {
        router.delete(TransactionTagController.destroy.url([transactionId, slug]), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const deleteTag = (slug: string) => {
        router.delete(TagController.destroy.url(slug), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <TagEditor tags={tags} availableTags={availableTags} onAdd={addTag} onRemove={removeTag} onDelete={deleteTag} />
    );
}
