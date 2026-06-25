import { PlusIcon, Trash2Icon, XIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

export type Tag = {
    slug: string;
    name: string;
};

type TagEditorProps = {
    tags: Tag[];
    availableTags: Tag[];
    onAdd: (name: string) => void;
    onRemove: (slug: string) => void;
    /**
     * When provided, each suggestion shows a control to delete the tag globally
     * (removing it from all transactions and merchant defaults).
     */
    onDelete?: (slug: string) => void;
    /** Label shown on the add control (e.g. "Tag" or "Default tag"). */
    addLabel?: string;
};

/**
 * Reusable inline tag editor: applied tags render as removable badges, and an
 * add control suggests existing tags (autocomplete) while allowing free-form
 * entry to create a tag on the fly. The caller owns persistence via onAdd /
 * onRemove.
 */
export function TagEditor({
    tags,
    availableTags,
    onAdd,
    onRemove,
    onDelete,
    addLabel = 'Tag',
}: TagEditorProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const appliedSlugs = new Set(tags.map((tag) => tag.slug));
    const suggestions = availableTags.filter(
        (tag) => !appliedSlugs.has(tag.slug),
    );

    const addTag = (value: string) => {
        const name = value.trim();

        if (name === '') {
            return;
        }

        onAdd(name);
        setQuery('');
        setOpen(false);
    };

    return (
        <div className="flex flex-wrap items-center gap-1">
            {tags.map((tag) => (
                <Badge key={tag.slug} variant="secondary" className="gap-1">
                    {tag.name}
                    <button
                        type="button"
                        aria-label={`Remove ${tag.name}`}
                        onClick={() => onRemove(tag.slug)}
                        className="text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <XIcon className="size-3" />
                    </button>
                </Badge>
            ))}

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        aria-label={`Add ${addLabel.toLowerCase()}`}
                        className="inline-flex h-6 items-center gap-1 rounded-md border border-dashed px-2 text-xs text-muted-foreground transition-colors hover:text-foreground"
                    >
                        <PlusIcon className="size-3" />
                        {addLabel}
                    </button>
                </PopoverTrigger>
                <PopoverContent align="start" className="w-56 p-0">
                    <Command>
                        <CommandInput
                            placeholder="Add or create a tag…"
                            value={query}
                            onValueChange={setQuery}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    addTag(query);
                                }
                            }}
                        />
                        <CommandList>
                            <CommandEmpty>
                                {query.trim() === ''
                                    ? 'Type to create a tag.'
                                    : `Press Enter to create “${query.trim()}”.`}
                            </CommandEmpty>
                            {suggestions.length > 0 && (
                                <CommandGroup>
                                    {suggestions.map((tag) => (
                                        <CommandItem
                                            key={tag.slug}
                                            value={tag.name}
                                            onSelect={() => addTag(tag.name)}
                                            className="group"
                                        >
                                            <span className="flex-1">
                                                {tag.name}
                                            </span>
                                            {onDelete && (
                                                <button
                                                    type="button"
                                                    aria-label={`Delete ${tag.name} everywhere`}
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        onDelete(tag.slug);
                                                    }}
                                                    className="text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive"
                                                >
                                                    <Trash2Icon className="size-3.5" />
                                                </button>
                                            )}
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        </div>
    );
}
