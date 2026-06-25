<!--
Sync Impact Report
==================
Version change: (template) → 1.0.0
Bump rationale: Initial ratification of the project constitution from template.

Modified principles:
  - [PRINCIPLE_1_NAME] → I. Local-Development-Only Scope
  - [PRINCIPLE_2_NAME] → II. No Automated Tests
  - [PRINCIPLE_3_NAME] → III. Framework-Idiomatic Code
  - [PRINCIPLE_4_NAME] → IV. Code Quality Gates
  - [PRINCIPLE_5_NAME] → V. Simplicity & Convention Over Configuration

Added sections:
  - Technology Constraints (was [SECTION_2_NAME])
  - Development Workflow (was [SECTION_3_NAME])

Removed sections: none

Templates requiring updates:
  - .specify/templates/plan-template.md ⚠ pending review (Constitution Check gate
    should reflect "no tests" + quality-gate principles)
  - .specify/templates/spec-template.md ✅ no mandatory changes required
  - .specify/templates/tasks-template.md ⚠ pending review (omit test-authoring task
    categories per Principle II)

Follow-up TODOs: none
-->

# budget Constitution

## Core Principles

### I. Local-Development-Only Scope

This application is built and run for local development only. There is no production
deployment target, now or in the future. Decisions MUST optimize for developer
ergonomics, fast iteration, and clarity rather than production hardening. Concerns that
exist solely for production—horizontal scaling, multi-region availability, production
secret rotation, uptime SLAs—are explicitly out of scope and MUST NOT drive design.

Rationale: Investing in production concerns for an app that will never ship wastes
effort and adds accidental complexity.

### II. No Automated Tests

Automated tests MUST NOT be written or required for changes in this project. Do not
create unit, feature, integration, browser, or end-to-end tests, and do not add
test-authoring steps to plans or task lists. Verification is performed manually by the
developer running the app locally. Any existing test-enforcement guidance from generic
tooling is superseded by this principle.

Rationale: As a throwaway local-only tool, the maintenance cost of a test suite is not
justified; manual verification is sufficient for the project's lifecycle.

### III. Framework-Idiomatic Code

Code MUST follow the conventions of the installed stack: Laravel 13, Inertia v3,
React 19 + TypeScript, Tailwind v4, and shadcn/ui. Use the framework's intended
mechanisms—Artisan generators, Eloquent, Inertia page rendering, Wayfinder-generated
route/action helpers, Fortify for auth—rather than bespoke reimplementations. Match the
structure and naming of sibling files; reuse existing UI primitives before adding new
ones. Generated files (Wayfinder routes/actions) MUST NOT be hand-edited.

Rationale: Idiomatic code is the most readable and maintainable for anyone returning to
the project, and leverages the framework's guarantees.

### IV. Code Quality Gates

Every change MUST satisfy the project's automated quality tooling before being
considered complete. PHP MUST pass Laravel Pint (`vendor/bin/pint --dirty`). Frontend
code MUST pass ESLint, Prettier, and TypeScript type-checking (`npm run lint`,
`npm run format`, `npm run types:check`). Type hints, explicit return types, and
descriptive names are required. These gates replace tests as the project's enforced
quality bar.

Rationale: With no test suite, static analysis and consistent formatting are the
primary safeguards against regressions and drift.

### V. Simplicity & Convention Over Configuration

Apply YAGNI: build only what the current feature needs. Prefer the simplest approach
that fits the existing architecture, and follow the established layering (Settings
module as the reference pattern). Do not introduce new base directories, dependencies,
or abstractions without explicit approval. Configuration MUST favor framework defaults.

Rationale: A small local tool stays maintainable only if it resists speculative
generality and unnecessary dependencies.

## Technology Constraints

- Runtime stack is fixed: Laravel 13 / PHP 8.3, Inertia v3, React 19 (TypeScript),
  Tailwind v4, MySQL 8.4 via Laravel Sail.
- The app runs through Laravel Sail (Docker); the canonical URL is `http://localhost`
  (`APP_PORT=80`).
- Dependencies MUST NOT be added, removed, or upgraded without explicit approval.
- New backend files MUST be created via Artisan generators where one exists.
- Directory structure follows the existing project layout; new top-level folders
  require approval.

## Development Workflow

- Use `./vendor/bin/sail` to run Artisan, Composer, and npm commands inside the
  container during development.
- Before finalizing any change: run Pint on modified PHP, and run ESLint/Prettier/
  TypeScript checks on modified frontend code.
- Verification is manual: run the app locally (`sail npm run dev` / `composer run dev`)
  and confirm the change in the browser. Do not write tests to verify.
- Search the version-specific documentation (Boost `search-docs`) before making
  framework-level changes.

## Governance

This constitution supersedes other generic development guidance, including any
default test-enforcement rules, where they conflict. Amendments require updating this
file, incrementing the version per the policy below, and recording the change in the
Sync Impact Report.

Versioning policy (semantic):
- MAJOR: backward-incompatible removal or redefinition of a principle.
- MINOR: a new principle/section or materially expanded guidance.
- PATCH: clarifications and non-semantic refinements.

Compliance: every change should be checked against these principles. Complexity or
deviations MUST be justified explicitly. Runtime development guidance lives in
`CLAUDE.md`; where it conflicts with this constitution (e.g., test enforcement), this
constitution governs.

**Version**: 1.0.0 | **Ratified**: 2026-06-01 | **Last Amended**: 2026-06-01
