# TaskPilot — Development Roadmap

## Development Strategy

TaskPilot will be developed incrementally.

Each phase should leave the application in a functional state.

Do not implement future phases unless explicitly requested.

Future requirements should influence architecture but should not cause premature implementation.

---

# Phase 1 — Foundation

## Objectives

Establish the application foundation.

### Backend

* Laravel application
* Authentication
* API structure
* Database configuration
* Redis
* Queue configuration
* Basic authorization
* Base domain architecture

### Frontend

* React application
* Routing
* Application layout
* Authentication UI
* Navigation
* Responsive/mobile-first foundation

### Engineering

* Docker/Sail
* GitHub Actions
* PHPUnit/Pest
* Vitest
* ESLint
* Build pipeline

### Completion Criteria

A user can authenticate and reach the application dashboard.

---

# Phase 2 — Project Management

## Objectives

Introduce projects and project membership.

### Features

* Create project
* Edit project
* View project
* Project members
* Project navigation

### Completion Criteria

A user can create a project and manage its members.

---

# Phase 3 — Issue Management

## Objectives

Introduce the core issue domain.

### Features

* Create issue
* Edit issue
* Delete issue
* Assign issue
* Issue types
* Priorities
* Labels
* Comments
* Issue detail page
* Issue activity

### Completion Criteria

A user can create and manage the complete lifecycle of an issue manually.

---

# Phase 4 — Kanban Workflow

## Objectives

Introduce workflow-based project management.

### Features

* Kanban board
* Workflow states
* Drag and drop
* Status transitions
* Optimistic UI updates
* Activity recording

### Initial Workflow

```text
Backlog
Todo
In Progress
Review
Done
```

### Completion Criteria

Users can manage project work visually through the Kanban board.

---

# Phase 5 — Agent Foundation

## Objectives

Introduce the infrastructure required for AI agents.

### Features

* Agent definitions
* Agent runs
* Agent messages
* Agent status
* Agent execution history
* Queue-based execution
* AI provider abstraction

### Important Constraint

Do not implement autonomous coding.

### Completion Criteria

An agent can be invoked asynchronously against an issue and its execution can be observed in the issue history.

---

# Phase 5.1 — Copilot Provider Integration

## Objectives

Introduce the first real external AI provider integration behind the TaskPilot agent abstraction.

### Features

* Provider abstraction for AI execution
* GitHub Copilot adapter implementation
* Secure server-side configuration for provider credentials
* Request normalization and response mapping
* Queue-based execution for asynchronous agent runs
* Provider error handling, retry policy, and status reporting
* Audit logging for model requests and outputs

### Important Constraint

Copilot is the first provider implementation, but it must remain behind a provider interface so additional models and providers can be added without redesigning the TaskPilot agent layer.

### Completion Criteria

A TaskPilot agent can run through the Copilot provider, persist the result to the issue history, and surface failures without exposing secrets or bypassing normal approval boundaries.

---

# Phase 6 — Issue Analyzer

## Objectives

Introduce the first useful AI capability.

### Agent

Issue Analyzer Agent

### Responsibilities

* Analyze issue description
* Identify likely causes
* Identify missing information
* Suggest acceptance criteria
* Suggest priority
* Estimate complexity
* Identify technical areas requiring investigation

### Completion Criteria

A user can request AI analysis from an issue and view the resulting analysis.

---

# Phase 7 — Planning Agent

## Objectives

Transform issue analysis into an implementation plan.

### Workflow

```text
Issue
  ↓
Analyzer
  ↓
Planning Agent
```

### Output

The Planning Agent should produce:

* Technical approach
* Files/components likely affected
* Database changes
* API changes
* Frontend changes
* Testing strategy
* Implementation steps

### Completion Criteria

An analyzed issue can be transformed into a structured implementation plan.

---

# Phase 8 — Agentic Workflow

## Objectives

Introduce multi-agent orchestration.

### Workflow

```text
Analyzer
   ↓
Planner
   ↓
Human Approval
   ↓
Implementation
   ↓
Testing
   ↓
Review
```

### Features

* Workflow definitions
* Workflow runs
* Agent sequencing
* Agent dependencies
* Failure handling
* Human approval
* Re-run capability
* Agent execution history

### Completion Criteria

A user can launch a development workflow and observe each stage.

---

# Phase 9 — Realtime Agent Monitoring

## Objectives

Provide live workflow visibility.

### Features

* Laravel Reverb
* Live agent status
* Live progress
* Agent completion events
* Error notifications

### Completion Criteria

Agent progress updates appear in the UI without requiring a page refresh.

---

# Phase 10 — GitHub Integration

## Objectives

Connect TaskPilot to software repositories.

### Features

* Repository connection
* Branch creation
* Repository inspection
* Commit creation
* Pull request creation
* Pull request status
* Test results

### Completion Criteria

An approved development workflow can create a GitHub branch and eventually produce a pull request.

---

# Phase 11 — Autonomous Development

## Objectives

Introduce controlled autonomous coding.

### Workflow

```text
Issue
  ↓
Analysis
  ↓
Planning
  ↓
Approval
  ↓
Implementation Agent
  ↓
Tests
  ↓
Code Review Agent
  ↓
Pull Request
  ↓
Human Approval
```

### Important Constraint

Human approval remains part of the workflow for consequential repository operations unless explicitly configured otherwise.

---

# Phase 12 — Portfolio Hardening

## Objectives

Prepare TaskPilot for public GitHub presentation.

### Features

* Comprehensive README
* Architecture diagrams
* Screenshots
* Demo data
* Seeders
* Automated tests
* CI
* Docker setup
* Security review
* Performance review
* Agent workflow examples

The repository should clearly explain the evolution from issue management to agentic software development.
