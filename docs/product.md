# TaskPilot — Product Definition

## 1. Product Overview

TaskPilot is an AI-native issue tracking and software development workflow platform.

The initial product is inspired by Jira-style project and task management systems. However, TaskPilot is not intended to be a Jira clone.

Its long-term purpose is to evolve from a task and issue management platform into a system capable of orchestrating software development through specialized AI agents.

TaskPilot will be built as a Laravel/React application.

The initial MVP focuses on project management, issue tracking, Kanban workflows, activity history, and the infrastructure required to introduce AI agents.

The application must be developed incrementally. Future capabilities should influence architectural decisions, but future functionality must not be implemented prematurely.

---

## 2. Core Concept

The fundamental unit of work in TaskPilot is an Issue.

An issue can represent:

* Bug
* Task
* Story
* Epic

An issue may be worked on by:

* A human developer
* An AI agent
* A combination of humans and AI agents

The issue should maintain a complete history of its lifecycle.

The long-term development lifecycle is:

```text
Issue
  ↓
Analysis
  ↓
Planning
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

The MVP does not need to implement the complete lifecycle. It must establish the architecture required to add these capabilities incrementally.

---

## 3. MVP Goals

### Authentication

* User registration
* User authentication
* User profile
* Authorization

### Projects

* Create projects
* Edit projects
* Project members
* Project settings

### Issues

* Create issues
* Edit issues
* Delete issues
* Assign issues
* Issue types
* Priorities
* Labels
* Comments
* Parent/child relationships where appropriate

### Workflow

The initial workflow should support:

```text
Backlog
Todo
In Progress
Review
Done
```

Workflow states must be represented as domain data rather than hard-coded throughout the application.

### Kanban

Projects should provide a Kanban board where users can:

* View issues by workflow state
* Move issues between states
* Drag and drop issues
* See priority
* See assignee
* See issue type
* See relevant agent activity

### Activity

The application must maintain an activity history for important issue events.

Examples:

* Issue created
* Issue updated
* Assignee changed
* Status changed
* Comment added
* Agent started
* Agent completed
* Agent failed

---

## 4. Agent Foundation

The MVP should establish the infrastructure required for AI agents without attempting to build a fully autonomous coding system.

The initial agent model should support:

* Agent definitions
* Agent runs
* Agent messages
* Agent status
* Agent input
* Agent output
* Agent errors
* Model/provider information
* Execution timestamps

The first practical AI capability should be an Issue Analyzer Agent.

Example:

```text
User creates:

"Curriculum disappears when the iPad loses internet."

        ↓

Issue Analyzer Agent

        ↓

Analysis

Likely causes:
- Missing offline cache strategy
- API requests failing without fallback
- Cache invalidation problems

Suggested investigation:
- Service worker
- IndexedDB
- Network detection
- Synchronization

Suggested priority:
HIGH

Estimated complexity:
8 points
```

A Planning Agent may subsequently transform this analysis into an implementation plan.

---

## 5. Future Agentic Development

The long-term objective is:

```text
Issue
  ↓
Issue Analyzer
  ↓
Planning Agent
  ↓
Implementation Agent
  ↓
Testing Agent
  ↓
Code Review Agent
  ↓
GitHub Integration
  ↓
Pull Request
  ↓
Human Approval
```

AI agents must remain observable.

Users should be able to see:

* Which agent is running
* What the agent is doing
* When it started
* When it completed
* Whether it failed
* The agent's output
* Files changed when repository integration exists
* Test results
* Pull request information

---

## 6. Product Philosophy

TaskPilot is not intended to reproduce Jira feature-for-feature.

Jira-style project management is the initial foundation.

The distinguishing feature is the integration of project management with agentic software development.

TaskPilot should optimize for:

* Clear issue lifecycle
* Traceability
* Human control
* Observable automation
* Extensible workflows
* Asynchronous processing
* AI-provider independence
* GitHub integration
* Developer experience

---

## 7. MVP Non-Goals

The following should not be implemented prematurely:

* Autonomous coding agents
* Automatic GitHub branch modification
* Automatic pull request creation
* Complex multi-agent orchestration
* Multiple AI providers simultaneously
* Enterprise permissions
* Advanced reporting
* Time tracking
* Billing
* Marketplace functionality
* Full Jira compatibility

These capabilities may be added after the underlying architecture is ready.

---

## 8. Portfolio Objective

TaskPilot is intended to demonstrate professional full-stack software engineering capabilities through a realistic application.

The project should demonstrate:

* Laravel
* React
* REST API design
* Relational database design
* Authentication and authorization
* Domain/service architecture
* Events
* Queues
* Redis
* Realtime communication
* Automated testing
* CI/CD
* AI integration
* Agent orchestration
* GitHub integration

The GitHub repository should make the evolution from issue tracker to agentic development platform clear.

---

## 9. Guiding Principle

Build the smallest useful version of each capability while designing the architecture so that the next capability can be added without rewriting the previous one.
