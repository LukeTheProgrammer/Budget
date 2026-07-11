import { ACCENT, STATUS_COLOR } from './budget-shared';

export function CategoryDetailBar({
    budgetPercent,
    pacePercent,
    isOver,
    overPercent,
}: {
    budgetPercent: number;
    pacePercent: number;
    isOver: boolean;
    overPercent: number;
}) {
    return (
        <div className="relative h-7.5">
            <div className="flex h-full overflow-hidden rounded-[9px] bg-muted ring-1 ring-border ring-inset">
                <div
                    className="h-full"
                    style={{
                        width: `${Math.min(budgetPercent, 100)}%`,
                        backgroundColor: ACCENT,
                    }}
                />
                {isOver && (
                    <div
                        className="h-full"
                        style={{
                            width: `${overPercent}%`,
                            backgroundColor: STATUS_COLOR.over.fill,
                        }}
                    />
                )}
            </div>
            <div
                className="absolute -top-1.75 -bottom-1.75 w-0.5 rounded bg-foreground"
                style={{ left: `${pacePercent}%` }}
            >
                <span className="absolute -top-0.75 -left-0.75 size-2 rounded-full bg-foreground" />
            </div>
        </div>
    );
}
