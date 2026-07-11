import { Form, router, useForm } from '@inertiajs/react';
import { Info, Plus, X } from 'lucide-react';
import { useState } from 'react';
import MerchantAliasController from '@/actions/App/Http/Controllers/Merchants/MerchantAliasController';
import MerchantController from '@/actions/App/Http/Controllers/Merchants/MerchantController';
import MerchantDefaultTagController from '@/actions/App/Http/Controllers/Merchants/MerchantDefaultTagController';
import MerchantRuleController from '@/actions/App/Http/Controllers/Merchants/MerchantRuleController';
import InputError from '@/components/input-error';
import { TagEditor } from '@/components/tags/tag-editor';
import { Badge } from '@/components/ui/badge';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { Merchant, MerchantCategory, MerchantTag } from '@/types';

function RulesSection({ merchant }: { merchant: Merchant }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        match_type: 'prefix',
        pattern: merchant.suggested_prefix ?? '',
    });

    const submitRule = () => {
        post(MerchantRuleController.store.url(merchant.id), {
            preserveScroll: true,
            onSuccess: () => reset('pattern' /* keep selected match_type */),
        });
    };

    const removeRule = (ruleId: number) => {
        router.delete(MerchantRuleController.destroy.url([merchant.id, ruleId]), {
            preserveScroll: true,
        });
    };

    return (
        <div className="grid gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
            <div className="mb-2 flex items-center justify-between">
                <Label>Matching Rules</Label>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Info className="size-4 text-muted-foreground" />
                    </TooltipTrigger>
                    <TooltipContent className="border bg-background text-foreground">
                        <p className="max-w-[10em] text-xs text-muted-foreground">
                            A prefix or pattern auto-resolves every future variant of this merchant — no more reviewing
                            new store numbers.
                        </p>
                    </TooltipContent>
                </Tooltip>
            </div>

            {merchant.rules.length > 0 && (
                <ul className="grid gap-1">
                    {merchant.rules.map((rule) => (
                        <li key={rule.id} className="flex items-center justify-between gap-2 text-sm">
                            <span className="flex items-center gap-2">
                                <Badge variant="secondary">{rule.match_type}</Badge>
                                <code className="text-xs">{rule.pattern}</code>
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 text-muted-foreground"
                                onClick={() => removeRule(rule.id)}
                                aria-label={`Remove rule ${rule.pattern}`}
                            >
                                <X className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <div className="grid gap-1">
                <div className="flex items-center gap-2">
                    <select
                        value={data.match_type}
                        onChange={(event) => setData('match_type', event.target.value)}
                        className="h-8 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs"
                        aria-label="Rule type"
                    >
                        <option value="prefix">Prefix</option>
                        <option value="regex">Regex</option>
                    </select>
                    <Input
                        value={data.pattern}
                        onChange={(event) => setData('pattern', event.target.value)}
                        placeholder={data.match_type === 'prefix' ? 'e.g. TST* BLUE SUSHI' : 'e.g. /^STARBUCKS /i'}
                        className="h-8"
                    />
                    <Button
                        type="button"
                        size="icon"
                        className="size-8 shrink-0"
                        disabled={processing || data.pattern.trim() === ''}
                        onClick={submitRule}
                        aria-label="Add rule"
                    >
                        <Plus className="size-4" />
                    </Button>
                </div>
                <InputError message={errors.pattern} />
            </div>
        </div>
    );
}

function DefaultTagsSection({ merchant, availableTags }: { merchant: Merchant; availableTags: MerchantTag[] }) {
    const addTag = (name: string) => {
        router.post(
            MerchantDefaultTagController.store.url(merchant.id),
            { tags: [name] },
            { preserveScroll: true, preserveState: true },
        );
    };

    const removeTag = (slug: string) => {
        router.delete(MerchantDefaultTagController.destroy.url([merchant.id, slug]), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <div className="grid gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
            <div className="mb-2 flex items-center justify-between">
                <Label>Default Tags</Label>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Info className="size-4 text-muted-foreground" />
                    </TooltipTrigger>
                    <TooltipContent className="border bg-background text-foreground">
                        <p className="max-w-[10em] text-xs text-muted-foreground">
                            Applied automatically to this merchant's transactions when they are imported. Changing these
                            does not affect existing transactions.
                        </p>
                    </TooltipContent>
                </Tooltip>
            </div>
            <p className="text-xs text-muted-foreground"></p>
            <TagEditor
                tags={merchant.default_tags}
                availableTags={availableTags}
                onAdd={addTag}
                onRemove={removeTag}
                addLabel="Default tag"
            />
        </div>
    );
}

/**
 * The sentinel used for the "Uncategorized" option, since a `SelectItem` cannot
 * carry an empty value. It is mapped back to an empty string on submit, which
 * the update request treats as an explicit null.
 */
const NO_CATEGORY = 'none';

function CategorySection({
    merchant,
    availableCategories,
}: {
    merchant: Merchant;
    availableCategories: MerchantCategory[];
}) {
    const [categoryId, setCategoryId] = useState(
        merchant.category_id !== null ? String(merchant.category_id) : NO_CATEGORY,
    );

    return (
        <div className="grid gap-2">
            <Label htmlFor="edit-category">Category</Label>
            <input type="hidden" name="category_id" value={categoryId === NO_CATEGORY ? '' : categoryId} />
            <Select value={categoryId} onValueChange={setCategoryId}>
                <SelectTrigger id="edit-category" className="w-full">
                    <SelectValue placeholder="Uncategorized" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NO_CATEGORY}>Uncategorized</SelectItem>
                    {availableCategories.map((category) => (
                        <SelectItem key={category.id} value={String(category.id)}>
                            {category.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export function EditMerchantDialog({
    merchant,
    availableTags,
    availableCategories,
    open,
    onOpenChange,
}: {
    merchant: Merchant;
    availableTags: MerchantTag[];
    availableCategories: MerchantCategory[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const {
        data,
        setData,
        post: addAlias,
        processing: addingAlias,
        errors: aliasErrors,
        reset: resetAlias,
    } = useForm({ name: '' });

    const submitAlias = () => {
        addAlias(MerchantAliasController.store.url(merchant.id), {
            preserveScroll: true,
            onSuccess: () => resetAlias(),
        });
    };

    const removeAlias = (aliasId: number) => {
        router.delete(MerchantAliasController.destroy.url([merchant.id, aliasId]), { preserveScroll: true });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    {...MerchantController.update.form(merchant.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Edit merchant</DialogTitle>
                                <DialogDescription>Edit {merchant.name}.</DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="edit-name">Name</Label>
                                <Input
                                    id="edit-name"
                                    name="name"
                                    defaultValue={
                                        merchant.confirmed ? merchant.name : (merchant.suggested_name ?? merchant.name)
                                    }
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <CategorySection
                                    key={merchant.id}
                                    merchant={merchant}
                                    availableCategories={availableCategories}
                                />
                                <InputError message={errors.category_id} />
                            </div>

                            <div className="grid gap-2 border-t border-sidebar-border/70 pt-4 dark:border-sidebar-border">
                                <Label>Aliases</Label>
                                {merchant.aliases.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No aliases yet.</p>
                                ) : (
                                    <ul className="grid gap-1">
                                        {merchant.aliases.map((alias) => (
                                            <li
                                                key={alias.id}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span>{alias.name}</span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7 text-muted-foreground"
                                                    onClick={() => removeAlias(alias.id)}
                                                    aria-label={`Remove alias ${alias.name}`}
                                                >
                                                    <X className="size-4" />
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <div className="grid gap-1">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            value={data.name}
                                            onChange={(event) => setData('name', event.target.value)}
                                            placeholder="New alias"
                                            className="h-8"
                                        />
                                        <Button
                                            type="button"
                                            size="icon"
                                            className="size-8 shrink-0"
                                            disabled={addingAlias}
                                            onClick={submitAlias}
                                            aria-label="Add alias"
                                        >
                                            <Plus className="size-4" />
                                        </Button>
                                    </div>
                                    <InputError message={aliasErrors.name} />
                                </div>
                            </div>

                            <RulesSection merchant={merchant} />

                            <DefaultTagsSection merchant={merchant} availableTags={availableTags} />

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
