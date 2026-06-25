import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update as updateSessionPeriod } from '@/routes/session-period';
import type { SessionPeriodType, SharedPeriod } from '@/types';

const PRESET_OPTIONS: { value: SessionPeriodType; label: string }[] = [
    { value: 'this_month', label: 'This month' },
    { value: 'last_month', label: 'Last month' },
    { value: 'last_3_months', label: 'Last 3 months' },
    { value: 'last_6_months', label: 'Last 6 months' },
    { value: 'last_12_months', label: 'Last 12 months' },
    { value: 'ytd', label: 'YTD' },
];

export function SessionPeriodSelector() {
    const period = usePage().props.period as SharedPeriod;
    const [customOpen, setCustomOpen] = useState(false);

    const form = useForm({
        type: 'custom' as const,
        start: period.start ?? '',
        end: period.end ?? '',
    });

    function handleChange(value: string) {
        if (value === 'custom') {
            openCustom();

            return;
        }

        if (value === period.type) {
            return;
        }

        router.post(
            updateSessionPeriod.url(),
            { type: value },
            { preserveScroll: true, preserveState: true },
        );
    }

    function openCustom() {
        // Defer until the Select has finished closing, otherwise its
        // closing focus/pointer events reach the Popover and immediately
        // dismiss it.
        setTimeout(() => setCustomOpen(true), 0);
    }

    function applyCustomRange() {
        // End date is optional and defaults to today when left empty.
        const end = form.data.end || new Date().toLocaleDateString('en-CA');

        form.transform((data) => ({ ...data, end }));
        form.post(updateSessionPeriod.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setCustomOpen(false),
        });
    }

    return (
        <Popover open={customOpen} onOpenChange={setCustomOpen}>
            <Select value={period.type} onValueChange={handleChange}>
                <PopoverAnchor asChild>
                    <SelectTrigger className="w-[200px]" size="sm">
                        {period.type === 'custom' ? (
                            <span className="line-clamp-1">{period.label}</span>
                        ) : (
                            <SelectValue placeholder="Select period" />
                        )}
                    </SelectTrigger>
                </PopoverAnchor>
                <SelectContent align="end">
                    {PRESET_OPTIONS.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                    <SelectItem
                        value="custom"
                        onClick={() => {
                            if (period.type === 'custom') {
                                openCustom();
                            }
                        }}
                    >
                        Custom range
                    </SelectItem>
                </SelectContent>
            </Select>
            <PopoverContent
                align="end"
                className="w-72 space-y-3"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onFocusOutside={(event) => event.preventDefault()}
            >
                <div className="space-y-1">
                    <Label htmlFor="period-start">Start date</Label>
                    <Input
                        id="period-start"
                        type="date"
                        value={form.data.start}
                        max={form.data.end || undefined}
                        onChange={(event) =>
                            form.setData('start', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.start)}
                    />
                    {form.errors.start && (
                        <p className="text-sm text-destructive">
                            {form.errors.start}
                        </p>
                    )}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="period-end">End date</Label>
                    <Input
                        id="period-end"
                        type="date"
                        value={form.data.end}
                        min={form.data.start || undefined}
                        onChange={(event) =>
                            form.setData('end', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.end)}
                    />
                    {form.errors.end && (
                        <p className="text-sm text-destructive">
                            {form.errors.end}
                        </p>
                    )}
                </div>
                <Button
                    type="button"
                    size="sm"
                    className="w-full"
                    disabled={form.processing || !form.data.start}
                    onClick={applyCustomRange}
                >
                    Apply
                </Button>
            </PopoverContent>
        </Popover>
    );
}
