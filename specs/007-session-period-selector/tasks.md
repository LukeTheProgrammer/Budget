---

description: "Task list for Session Period Selector"
---

# Tasks: Session Period Selector

**Input**: Design documents from `/specs/007-session-period-selector/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/period.md

**Tests**: OMITTED. Per Constitution Principle II (No Automated Tests), no test tasks are
generated. Verification is manual via `quickstart.md`.

**Organization**: Tasks are grouped by user story to enable independent implementation and
verification of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

## Path Conventions

Laravel + Inertia web app. Backend under `app/`, routes in `routes/`, frontend under
`resources/js/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: No new dependencies or scaffolding required (constitution: no new deps).
Confirm the existing primitives this feature reuses are present.

- [X] T001 Verify shadcn/ui `select`, `popover`, and `input` primitives exist in `resources/js/components/ui/` (they do — no install needed) and confirm Wayfinder dev/build is running so new route/action helpers regenerate.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Server-side period storage, resolution, and the shared prop that every user
story and page depends on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T002 Create the `SessionPeriod` value object at `app/Support/SessionPeriod.php` via `./vendor/bin/sail artisan make:class Support/SessionPeriod`, holding `type` (`this_month|last_month|last_3_months|custom`) and optional `start`/`end` (`CarbonImmutable`), with `window(): array{Carbon, Carbon}`, `label(): string`, `toArray(): array`, `default(): self`, and a `fromSession(array): self` factory. Port the preset window/label logic from `app/Http/Controllers/DashboardController.php` (`periodWindow`, `periodLabel`, `resolvePeriod`).
- [X] T003 [P] Create `SessionPeriodRequest` at `app/Http/Requests/SessionPeriodRequest.php` via `./vendor/bin/sail artisan make:request SessionPeriodRequest`, authorizing signed-in users and validating `type` in the allowed set; preset types ignore `start`/`end`. (Custom-range rules added in US2.)
- [X] T004 Create `SessionPeriodController` at `app/Http/Controllers/SessionPeriodController.php` via `./vendor/bin/sail artisan make:controller SessionPeriodController`, with an `update(SessionPeriodRequest $request)` action that writes the validated selection to the session under `session_period` and returns `redirect()->back()`.
- [X] T005 Register `POST /session-period` named `session-period.update` → `SessionPeriodController@update` inside the `auth, verified` group in `routes/web.php`.
- [X] T006 Share the resolved period as a global Inertia prop in `app/Http/Middleware/HandleInertiaRequests.php`: build a `SessionPeriod` from `session('session_period')` (falling back to `SessionPeriod::default()`) and expose `'period' => $sessionPeriod->toArray()`.
- [X] T007 [P] Add the shared `period` prop type (`SessionPeriodType`, `SharedPeriod` per `contracts/period.md`) to the shared Inertia props type in `resources/js/types/index.d.ts`.

**Checkpoint**: Session period can be stored/resolved server-side and is available on every page as the `period` shared prop.

---

## Phase 3: User Story 1 - Set a global time frame from the top nav (Priority: P1) 🎯 MVP

**Goal**: A single preset selector in the top nav that applies its time frame to every
authenticated page and persists across navigation within the session.

**Independent Test**: Select "Last month" in the top nav on the Dashboard; Dashboard data
and label update without manual refresh; navigate away and back (and reload) and the
selection persists.

### Implementation for User Story 1

- [X] T008 [US1] Create the selector component at `resources/js/components/period/session-period-selector.tsx` using the shadcn/ui `Select`, reading the active selection from the shared `period` prop (`usePage`) and listing the three presets (This month, Last month, Last 3 months). On change, post to `session-period.update` via the Wayfinder action/route helper with the Inertia `router` using `{ preserveScroll: true }`.
- [X] T009 [US1] Render `SessionPeriodSelector` in the app header at `resources/js/components/app-sidebar-header.tsx`, right-aligned, so it appears on every authenticated page.
- [X] T010 [US1] Refactor `app/Http/Controllers/DashboardController.php` to resolve `[$start, $end]` and the label from the shared `SessionPeriod` (from session) instead of `$request->query('period')`; remove the now-duplicated private `resolvePeriod`/`periodWindow`/`periodLabel` (moved to `SessionPeriod`) and drop the `period`/`period_label` props it passed for the inline control as appropriate.
- [X] T011 [US1] Remove the inline `PeriodSelector` usage and its props from `resources/js/pages/dashboard.tsx` (delete the import, the `<PeriodSelector />` render, and `period`/`period_label` from `DashboardProps`; keep showing the label from the shared `period` prop).
- [X] T012 [US1] Delete the obsolete component `resources/js/components/dashboard/period-selector.tsx`.

**Checkpoint**: Presets work globally from the top nav; Dashboard follows the session period; old inline control is gone (FR-001, FR-002, FR-004, FR-005, FR-009, FR-010).

---

## Phase 4: User Story 2 - Define a custom date range (Priority: P2)

**Goal**: Allow a "Custom range" option with start/end date inputs that scopes all data to
that inclusive range.

**Independent Test**: Choose "Custom range", enter valid start/end, Apply; data scopes to
that inclusive range and the selector shows the range. Invalid/incomplete ranges are
rejected with a message.

### Implementation for User Story 2

- [X] T013 [US2] Extend `app/Support/SessionPeriod.php` to support `type = custom`: store `start`/`end`, return an inclusive `start->startOfDay()`…`end->endOfDay()` window, and a custom label (e.g. `M j, Y – M j, Y`).
- [X] T014 [US2] Extend `app/Http/Requests/SessionPeriodRequest.php` so that when `type = custom`, `start` and `end` are `required`, valid dates, with `end` `after_or_equal` `start` (FR-007, FR-011); add a clear validation message.
- [X] T015 [US2] Add a "Custom range" option to `resources/js/components/period/session-period-selector.tsx` that opens a `Popover` with two `<input type="date">` fields (start/end) and an Apply button; on Apply, post `type=custom` with the dates and surface Inertia validation errors inline.
- [X] T016 [US2] Reflect an active custom range in the selector trigger (show the range dates from the shared `period` prop) per FR-008.

**Checkpoint**: Custom ranges apply globally and validate correctly; presets (US1) still work.

---

## Phase 5: User Story 3 - Period persists across the session and resets predictably (Priority: P3)

**Goal**: Predictable default and persistence: default "This month" when unset, retained
across reloads, reset on new session.

**Independent Test**: Fresh session shows "This month"; selecting a period survives reload;
sign out/in returns to "This month".

### Implementation for User Story 3

- [X] T017 [US3] Confirm/handle the default in `app/Http/Middleware/HandleInertiaRequests.php` and `SessionPeriod::default()` so an unset session resolves to `this_month` (FR-006); ensure the selector reflects this default on first load.
- [X] T018 [US3] Ensure the selector trigger always reflects the active selection from the shared `period` prop after reloads (no client-only state that drifts from the server value) in `resources/js/components/period/session-period-selector.tsx`.

**Checkpoint**: Default, persistence, and session-reset behavior are correct (FR-005, FR-006).

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Quality gates and manual verification.

- [X] T019 [P] Run `./vendor/bin/sail composer run lint` (Pint) and fix any PHP formatting in the new/edited files.
- [X] T020 [P] Run `npm run lint && npm run format && npm run types:check` and fix any frontend lint/type issues.
- [ ] T021 Walk through all steps in `specs/007-session-period-selector/quickstart.md` in the running app to verify FR-001…FR-011.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3–5)**: All depend on Foundational. US1 is the MVP; US2 and US3
  build on US1's selector/controller but are independently verifiable.
- **Polish (Phase 6)**: After the desired stories are complete.

### User Story Dependencies

- **US1 (P1)**: Depends only on Foundational.
- **US2 (P2)**: Extends the value object/request/selector from Foundational+US1.
- **US3 (P3)**: Verifies/locks default & persistence behavior established in Foundational.

### Within Each User Story

- Backend window/validation before frontend wiring.
- Selector component before header wiring and Dashboard refactor.

### Parallel Opportunities

- T003 and T002 differ but T003 is independent of T002 → marked [P].
- T007 (frontend types) parallel to backend T002–T006.
- Polish T019 and T020 run in parallel.

---

## Parallel Example: Foundational

```bash
# After T002 is underway, these touch different files:
Task: "Create SessionPeriodRequest in app/Http/Requests/SessionPeriodRequest.php"   # T003
Task: "Add shared period prop type in resources/js/types/index.d.ts"                 # T007
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup
2. Phase 2: Foundational (CRITICAL — blocks all stories)
3. Phase 3: User Story 1 (global preset selector + Dashboard refactor)
4. **STOP and VALIDATE**: presets apply everywhere and persist.

### Incremental Delivery

1. Setup + Foundational → period stored & shared.
2. US1 → global presets (MVP).
3. US2 → custom range.
4. US3 → default/persistence polish.

---

## Notes

- No automated tests per constitution; verify via quickstart.md.
- [P] = different files, no dependency on incomplete tasks.
- Do not hand-edit Wayfinder-generated `@/routes`/`@/actions` files; they regenerate.
- Run Artisan via `./vendor/bin/sail`.
- Commit after each logical group.
