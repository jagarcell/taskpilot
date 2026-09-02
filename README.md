# TaskPilot

TaskPilot is an AI-native project and issue tracking platform built with Laravel and React. It combines classic issue-management workflows with agent-driven analysis, planning, review, and GitHub-aware coordination so an issue can move from triage to implementation and pull-request visibility without losing human oversight.

## Why TaskPilot exists

The product started as a Jira-style project tracker, but it is intentionally designed to evolve into a workflow system for software development teams that want AI assistance to be visible, reviewable, and governed by human approval.

The core idea is simple:

- issues are the source of work,
- agents analyze and plan against that context,
- workflow stages create approval gates,
- repository integration keeps the work connected to real code changes,
- humans stay in control of consequential actions.

## Current capabilities

TaskPilot already includes:

- project creation and project membership
- issue creation, management, labels, comments, and assignment
- workflow status tracking and an issue lifecycle model
- Kanban-style project flow
- AI agent definitions and execution history
- issue analysis and planning prompts grounded in current issue context
- approval-gated workflow progression
- GitHub repository linking, branch creation, and pull-request status summaries
- realtime workflow updates through Laravel Reverb

## Workflow overview

```text
Issue
  ↓
Analysis
  ↓
Planning
  ↓
Approval
  ↓
Implementation
  ↓
Testing
  ↓
Review
  ↓
Pull Request
  ↓
Human Approval
```

The system keeps the workflow observable and auditable at each stage instead of hiding AI work behind opaque automation.

## Tech stack

### Backend

- Laravel 12 / PHP 8.5
- MySQL
- Redis
- Laravel Queues
- Laravel Reverb
- GitHub API integration layer

### Frontend

- React
- TypeScript
- Vite
- Tailwind CSS
- Inertia.js-style server-driven pages

### Quality and validation

- PHPUnit / Pest
- Vitest
- GitHub Actions CI

## Repository structure

```text
app/                 Laravel application code
bootstrap/           framework bootstrap
config/              app configuration
database/            migrations, factories, seeders
docs/                product, architecture, and roadmap docs
public/              public web assets
resources/           frontend, CSS, and view templates
routes/              HTTP routes
tests/               PHP and frontend test coverage
```

## Local development

### Prerequisites

- Docker Desktop or Docker Engine
- Node.js 22+
- Composer

### Start the app

```bash
cp .env.example .env
composer install
npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

### Run the frontend in development mode

```bash
npm run dev
```

### Run tests

```bash
./vendor/bin/sail test
npx vitest run
```

### Build assets

```bash
npm run build
```

### Realtime services

The app uses Laravel Reverb and a queue worker for realtime workflow updates:

```bash
./vendor/bin/sail restart queue reverb
```

## GitHub integration

TaskPilot can be connected to a GitHub repository at the project level. The integration is intentionally isolated behind a service boundary so the rest of the app does not depend directly on GitHub client calls.

This supports:

- repository connection metadata
- branch creation from the configured default branch
- issue-to-branch mapping
- pull request status summaries
- review and approval context for the current workflow

## Roadmap status

This repository is currently at the Phase 12 hardening handoff stage:

- project and issue management are established,
- AI analysis and planning flow is working,
- workflow approval and implementation transitions are in place,
- GitHub-aware branch and PR status are integrated,
- the remaining work is product polish, onboarding clarity, and public presentation.

## License

This project is currently distributed as a repository-first portfolio application and is intended for local development and showcasing the product direction.

## Contributing

This repository is intended to demonstrate an evolving engineering workflow, so contribution and iteration are welcome as long as the existing architecture and phase boundaries are respected.
