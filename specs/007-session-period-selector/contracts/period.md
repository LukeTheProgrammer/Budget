# Contract: Session Period

## Shared Inertia Prop (every authenticated page)

Provided by `HandleInertiaRequests::share()` as `period`:

```ts
type SessionPeriodType =
  | 'this_month'
  | 'last_month'
  | 'last_3_months'
  | 'custom';

type SharedPeriod = {
  type: SessionPeriodType;
  start: string | null; // 'YYYY-MM-DD', present when type === 'custom'
  end: string | null;   // 'YYYY-MM-DD', present when type === 'custom'
  label: string;        // e.g. "June 2025" or "Apr – Jun 2025" or "Jan 1, 2025 – Mar 31, 2025"
};
```

## Update Route

`POST /session-period` — name: `session-period.update`
(Wayfinder helper: import `update` from `@/actions/.../SessionPeriodController` or the
named route from `@/routes/session-period`.)

### Request body

| Field   | Type   | Required                | Rules |
|---------|--------|-------------------------|-------|
| `type`  | string | yes                     | one of the four allowed values |
| `start` | string | required if `type=custom` | `YYYY-MM-DD`, valid date |
| `end`   | string | required if `type=custom` | `YYYY-MM-DD`, valid date, `>= start` |

### Responses

- **Success**: writes selection to session, **redirects back** (`redirect()->back()`),
  re-rendering the originating page with the updated shared `period` prop and re-queried
  data. Sent as an Inertia visit with `preserveScroll` from the client.
- **Validation error** (invalid/incomplete/inverted custom range): redirect back with
  Inertia validation errors on `type` / `start` / `end`; the selector surfaces the
  message and does not apply the change (FR-007, SC-005).

## Client behavior

- The selector posts on preset change immediately, and on "Apply" for a custom range.
- It reflects the active selection from the shared `period` prop (FR-008): preset label
  or the custom range dates.
- Use Inertia router with `preserveScroll: true`; the back-redirect refreshes current
  page props so data updates without a manual refresh (FR-009).

## Controller consumption (data pages)

Data controllers resolve the window from the session-derived `SessionPeriod` instead of a
query string:

```php
[$start, $end] = $sessionPeriod->window();
```

Dashboard is wired in this feature; other data pages (e.g. Transactions) adopt the same
pattern when their data should follow the global period.
