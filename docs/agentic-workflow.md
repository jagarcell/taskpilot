# TaskPilot — Agentic Development Workflow

## 1. Purpose

TaskPilot is designed to evolve from a task management platform into an agentic software development platform.

The agentic workflow allows an issue to become the starting point for an AI-assisted development lifecycle.

The system must maintain human visibility and control throughout the process.

---

## 2. Core Workflow

The intended long-term workflow is:

```text
Issue
  ↓
Analysis
  ↓
Planning
  ↓
Human Approval
  ↓
Implementation
  ↓
Testing
  ↓
Code Review
  ↓
Pull Request
  ↓
Human Approval
  ↓
Done
```

---

## 3. Agents

Each agent should have one clearly defined responsibility.

### Issue Analyzer

Responsibilities:

* Understand the issue
* Identify ambiguities
* Identify likely causes
* Identify affected technical areas
* Suggest acceptance criteria
* Estimate complexity

The Analyzer should not modify repository code.

---

### Planning Agent

Responsibilities:

* Convert analysis into an implementation plan
* Identify likely files
* Identify database changes
* Identify API changes
* Identify frontend changes
* Define testing requirements

The Planning Agent should not modify repository code.

---

### Implementation Agent

Future responsibility:

* Create or modify code
* Create tests
* Follow repository rules
* Work within an isolated branch
* Report files changed
* Report implementation decisions

Implementation should only occur after the required approval stage.

---

### Testing Agent

Future responsibility:

* Execute automated tests
* Analyze failures
* Determine whether failures are caused by the implementation
* Report results
* Recommend corrections

The Testing Agent should not silently modify requirements to make tests pass.

---

### Code Review Agent

Future responsibility:

* Review implementation
* Check architecture
* Check security
* Check tests
* Check coding standards
* Identify regressions
* Produce review findings

---

## 4. Agent Run

Every agent execution must produce an AgentRun.

Conceptual lifecycle:

```text
Pending
   ↓
Running
   ↓
Completed
```

or:

```text
Pending
   ↓
Running
   ↓
Failed
```

An AgentRun should retain enough information to understand what happened.

Important information includes:

* Agent
* Issue
* Model
* Provider
* Input
* Output
* Status
* Start time
* Completion time
* Error
* Token usage when available

---

## 5. Agent Messages

Agent execution records should preserve useful operational information such as:

* Actions taken
* Decisions
* Tool calls
* Results
* Errors
* Recommendations
* Final outputs

Do not persist private chain-of-thought.

Sensitive information must never be persisted.

---

## 6. Orchestration

Agents should be coordinated by an Agent Orchestrator.

The orchestrator is responsible for workflow execution.

Conceptually:

```text
Workflow Run
     │
     ▼
Analyzer
     │
     ▼
Planner
     │
     ▼
Approval
     │
     ▼
Implementation
     │
     ▼
Testing
     │
     ▼
Review
```

The orchestrator determines:

* Which agent runs
* What input it receives
* When it runs
* What happens on success
* What happens on failure
* Whether human approval is required

---

## 7. Human-in-the-Loop

Human approval is a first-class workflow concept.

Examples:

```text
Analysis
   ↓
Planning
   ↓
[Human Approval]
   ↓
Implementation
```

and:

```text
Code Review
   ↓
Pull Request
   ↓
[Human Approval]
   ↓
Merge
```

Agents must not bypass approval requirements.

---

## 8. Failure Handling

Agent failures must be observable.

Possible states:

```text
Pending
Running
Completed
Failed
Cancelled
WaitingForApproval
```

A failed agent should expose:

* Error information
* Last successful step
* Relevant output
* Retry option

Retries should create a new execution or clearly identifiable attempt rather than destroying the original execution history.

---

## 9. Agent Provider Abstraction

AI providers must remain replaceable.

Conceptual architecture:

```text
Agent
  ↓
AI Provider Interface
  ↓
Provider Adapter
  ↓
Model
```

The issue domain must not know which AI provider is being used.

The application should be able to change models without changing issue/workflow logic.

---

## 10. Repository Integration

Repository access is a future capability.

The intended workflow is:

```text
Issue
  ↓
Implementation Agent
  ↓
Repository Service
  ↓
GitHub
```

The repository service is responsible for:

* Branch creation
* Repository inspection
* File operations
* Commit operations
* Push operations
* Pull request creation

GitHub API details must remain isolated from the rest of the application.

---

## 11. Agent Permissions

Agents should operate with explicitly defined permissions.

An agent should only have access to the tools required for its current workflow step.

Examples:

```text
Analyzer
→ Read issue information

Planner
→ Read issue + analysis

Implementation
→ Repository read/write

Testing
→ Repository read + test execution

Reviewer
→ Repository read + test results
```

Credentials must remain server-side.

Agent output must be treated as untrusted input.

---

## 12. Observability

The UI should make agent activity visible.

Example:

```text
AI DEVELOPMENT

✓ Analysis
  Issue analyzed

✓ Planning
  Implementation plan generated

● Implementation
  Working on branch...

○ Testing
  Waiting

○ Review
  Waiting
```

Selecting an agent run should expose useful execution information without exposing secrets or sensitive information.

---

## 13. MVP Agentic Scope

The MVP should only implement:

```text
Issue
  ↓
Issue Analyzer
  ↓
Analysis Result
```

The next stage adds:

```text
Issue
  ↓
Analyzer
  ↓
Planner
```

Only after the foundation is stable should autonomous repository operations be introduced.

---

## 14. Guiding Principle

TaskPilot should treat AI agents as participants in the existing software development workflow, not as a separate subsystem disconnected from project management.

The Issue remains the system of record.

Agent activity becomes part of the issue's lifecycle.

Human developers remain able to understand, approve, reject, retry, and override agent actions.
