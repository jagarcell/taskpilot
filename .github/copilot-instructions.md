# TaskPilot — Copilot Repository Instructions

## Repository Governance

Always treat `AGENTS.md` as the base operating rules for this repository.

Always read and follow `LOCAL_DEV.md` as the project-specific local development extension.

If rules conflict:

* `LOCAL_DEV.md` overrides `AGENTS.md` for local/project-specific behavior.
* `AGENTS.md` remains the general fallback.

Before making any code, configuration, migration, test, or asset change:

1. Read `AGENTS.md`.
2. Read `LOCAL_DEV.md`.
3. Read the relevant TaskPilot documentation under `docs/`.
4. Follow the repository workflow and approval requirements.

---

## Product Context

This repository contains TaskPilot.

TaskPilot is an AI-native issue tracking and software development workflow platform built with Laravel and React.

The initial application provides Jira-style project and issue management.

The long-term objective is to evolve TaskPilot into an agentic software development platform where issues can drive:

```text
Analysis
→ Planning
→ Implementation
→ Testing
→ Code Review
→ Pull Request
→ Human Approval
```

The Issue is the fundamental unit of work.

---

## Documentation Sources of Truth

### Product Requirements

Read:

```text
docs/product.md
```

This defines what TaskPilot is and what functionality belongs in each stage.

### Architecture

Read:

```text
docs/architecture.md
```

This defines the intended technical architecture and domain boundaries.

### Development Roadmap

Read:

```text
docs/roadmap.md
```

This defines the planned development phases.

Do not implement future roadmap phases unless explicitly requested.

### Agentic Workflow

Read:

```text
docs/agentic-workflow.md
```

This defines the intended AI-agent architecture and future development workflow.

---

## Implementation Principles

Build incrementally.

Do not implement functionality merely because it is described as a future capability.

Future requirements should influence architecture but should not cause premature implementation.

Prefer small, focused changes.

Respect existing repository architecture and coding rules.

Do not introduce dependencies without a clear architectural reason.

Business logic belongs in domain/service layers.

Database access belongs in repositories.

Controllers must remain thin.

AI provider-specific logic must remain behind an abstraction.

Agent execution must be asynchronous where appropriate.

Agents must not bypass normal authorization or application domain rules.

---

## TaskPilot Agentic Principle

TaskPilot itself is intended to become a platform capable of orchestrating software development agents.

When implementing foundational functionality, preserve the ability for future agents to interact with the same domain through well-defined services, workflows, events, and APIs.

Do not create special-case code paths solely for future AI functionality when an extensible domain abstraction is more appropriate.

The Issue remains the system of record for development work.

---

## Roadmap Discipline

Before implementing a feature:

1. Determine which TaskPilot roadmap phase the feature belongs to.
2. Read the relevant product and architecture documentation.
3. Identify existing domain boundaries affected by the change.
4. Do not implement functionality belonging to later roadmap phases unless explicitly requested.
5. Preserve backward compatibility with the current phase whenever practical.

---

## Before Coding

Before implementing a requested feature:

1. Read `AGENTS.md`.
2. Read `LOCAL_DEV.md`.
3. Read the relevant documentation in `docs/`.
4. Determine the roadmap phase.
5. Identify affected domain boundaries.
6. Identify all files that will be created or modified.
7. Produce the implementation plan required by `AGENTS.md`.
8. Wait for the required user approval before making changes.

---

## Agentic Development Safety

AI functionality must not bypass:

* Authentication
* Authorization
* Project membership
* Domain services
* Validation
* Audit logging
* Human approval requirements

Agent output must be treated as untrusted input.

AI provider credentials and GitHub credentials must remain server-side.

Do not store secrets in:

* Issues
* Comments
* Agent messages
* Logs
* Git history
* Frontend state

---

## Completion

Follow all completion, testing, build, branch, commit, and pull-request requirements defined by `AGENTS.md` and `LOCAL_DEV.md`.

Do not override those requirements based on the TaskPilot documentation.
