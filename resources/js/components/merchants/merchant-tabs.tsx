import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { MerchantTab } from '@/types';

/**
 * Switches the list between every merchant and the review backlog, which is
 * badged with its outstanding count.
 */
export function MerchantTabs({
    tab,
    reviewCount,
    onSelect,
    onSearch,
}: {
    tab: MerchantTab;
    reviewCount: number;
    onSelect: (tab: MerchantTab) => void;
    onSearch: (search: string) => void;
}) {
    return (
        <div className="grid grid-cols-2 gap-8">
            <ToggleGroup
                type="single"
                variant="outline"
                role="tablist"
                value={tab}
                onValueChange={(value) => value && onSelect(value as MerchantTab)}
                className="self-start"
            >
                <ToggleGroupItem role="tab" value="all" aria-label="Show all merchants">
                    All
                </ToggleGroupItem>
                <ToggleGroupItem role="tab" value="review" aria-label="Show merchants that need review">
                    Needs review
                    {reviewCount > 0 && (
                        <Badge variant="secondary" className="ml-2">
                            {reviewCount}
                        </Badge>
                    )}
                </ToggleGroupItem>
            </ToggleGroup>

            <Input
                type="search"
                name="merchant-search"
                placeholder="Search"
                onChange={(event) => onSearch(event.target.value)}
                aria-label="Search merchants"
            />
        </div>
    );
}
