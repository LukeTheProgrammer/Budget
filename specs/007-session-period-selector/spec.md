# Feature Specification: Session Period Selector

**Feature Branch**: `007-session-period-selector`

**Created**: 2026-06-03

**Status**: Draft

**Input**: User description: "Take the period-selector from the Dashboard and create a Select component within the top nav bar that will set a time-frame for the entire user session and be applied to the data on every page in the app. The options should be the same as the period-selector with an added "custom range" where the user can input a start and end date."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Set a global time frame from the top nav (Priority: P1)

A signed-in user wants every data view in the app to reflect the same time frame without re-selecting it on each page. From the top navigation bar, the user opens a period selector and chooses a preset (This month, Last month, Last 3 months). The chosen period immediately applies to the page they are on and remains in effect as they navigate to other data pages during their session.

**Why this priority**: This is the core value of the feature — a single, persistent control that drives all time-based data across the app. Without it, nothing else in this feature matters.

**Independent Test**: Select "Last month" in the top nav on the Dashboard, confirm Dashboard data updates, navigate to the Transactions page, and confirm it is also scoped to last month without re-selecting.

**Acceptance Scenarios**:

1. **Given** a signed-in user on any data page, **When** they open the period selector in the top nav and choose a preset, **Then** the current page's data updates to reflect that period.
2. **Given** a user who selected a period on one page, **When** they navigate to another data page in the same session, **Then** that page shows data scoped to the previously selected period.
3. **Given** a returning user within the same session, **When** they load any data page, **Then** the period selector shows their last selected period rather than resetting to a default.

---

### User Story 2 - Define a custom date range (Priority: P2)

A user needs a time frame that none of the presets cover (e.g., a specific trip or pay cycle). They choose "Custom range" in the selector and enter a start date and an end date. The app then scopes all data to that explicit range for the rest of the session.

**Why this priority**: Extends the core control with flexibility beyond fixed presets. Valuable but secondary to having the global selector working at all.

**Independent Test**: Choose "Custom range," enter a start and end date, apply, and confirm data on the current and subsequent pages is limited to that range.

**Acceptance Scenarios**:

1. **Given** the period selector is open, **When** the user chooses "Custom range," **Then** they are presented with inputs for a start date and an end date.
2. **Given** a valid start and end date, **When** the user applies the custom range, **Then** all data pages scope to that inclusive range for the session.
3. **Given** an end date earlier than the start date, **When** the user attempts to apply, **Then** the app prevents application and explains the problem.
4. **Given** an applied custom range, **When** the user reopens the selector, **Then** the custom range is shown as the active selection with its dates.

---

### User Story 3 - Period persists across the session and resets predictably (Priority: P3)

A user expects the selected period to behave consistently: it stays selected while they move around the app and across page reloads within the session, and there is a clear default when they have never made a selection.

**Why this priority**: Polish and predictability. The feature is usable without explicit persistence guarantees, but defining them prevents confusing behavior.

**Independent Test**: Select a non-default period, reload the page, and confirm the selection is retained; sign out and back in, and confirm the documented default applies.

**Acceptance Scenarios**:

1. **Given** a user with no prior selection, **When** they first load a data page, **Then** the default period ("This month") is applied.
2. **Given** a selected period, **When** the user reloads any page within the same session, **Then** the selection is retained.
3. **Given** a selected period, **When** the session ends and a new session begins, **Then** the period returns to the default.

---

### Edge Cases

- What happens on pages that have no time-based data (e.g., account settings)? The selector should remain visible and inert, with no effect on those pages.
- How does the system handle a custom range with only one date filled in? Application is blocked until both dates are provided.
- How does the system handle a custom range spanning a very long period (e.g., multiple years)? The range is honored; performance expectations are covered in Success Criteria.
- What happens to a page that previously used its own local period control (the Dashboard)? The global selector becomes the single source of truth and the local control is removed.
- How are dates interpreted across time zones? Ranges are interpreted in the user's local calendar dates, inclusive of both endpoints.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The app MUST present a single period selector in the top navigation bar, visible on every authenticated page.
- **FR-002**: The selector MUST offer the same preset options currently available on the Dashboard: "This month," "Last month," and "Last 3 months."
- **FR-003**: The selector MUST offer an additional "Custom range" option that lets the user enter a start date and an end date.
- **FR-004**: The selected period MUST apply to all time-based data displayed throughout the app, replacing any page-specific period control.
- **FR-005**: The selected period MUST persist for the duration of the user's session and across page navigations and reloads within that session.
- **FR-006**: When no selection has been made, the app MUST apply a default period of "This month."
- **FR-007**: The app MUST validate a custom range so that the start date is on or before the end date, and MUST prevent application of an invalid or incomplete range with a clear explanation.
- **FR-008**: When a period is active, the selector MUST visibly reflect the current selection (preset label or the custom range dates).
- **FR-009**: Changing the period MUST update the data on the user's current page without requiring a manual refresh.
- **FR-010**: The Dashboard's existing inline period control MUST be removed in favor of the global selector so there is a single source of truth.
- **FR-011**: Custom date ranges MUST be treated as inclusive of both the start and end dates, interpreted as the user's local calendar dates.

### Key Entities *(include if feature involves data)*

- **Session Period**: The currently selected time frame for the user's session. Has a type (preset or custom). For presets, identifies which preset ("This month," "Last month," "Last 3 months"). For custom, holds a start date and an end date. Resolves to a concrete start and end date used to scope data.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A user can change the time frame for the entire app from a single control in under 5 seconds, without visiting more than one page.
- **SC-002**: After selecting a period, 100% of time-based data pages reflect that period without the user re-selecting it.
- **SC-003**: A selected period is retained across 100% of in-session page navigations and reloads.
- **SC-004**: Users can define and apply a valid custom date range in under 15 seconds.
- **SC-005**: 100% of invalid custom-range submissions (end before start, or incomplete) are rejected with an explanatory message and never applied.
- **SC-006**: Data scoped to any selected period (including multi-year custom ranges) loads within the app's standard page-load expectations.

## Assumptions

- The period selector applies only to authenticated pages; unauthenticated pages (welcome, auth) do not show it.
- "Session" means the user's active signed-in session; the period resets to the default in a new session rather than being stored as a long-term user preference. (Persisting as a saved profile preference is out of scope for v1.)
- The default period is "This month," matching the current Dashboard default.
- Preset definitions match the existing Dashboard semantics ("Last 3 months" meaning the trailing three months).
- Custom ranges are inclusive of both endpoints and interpreted using the user's local calendar dates.
- Pages without time-based data are unaffected by the selection but still display the selector for consistency.
