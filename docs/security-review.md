# Security review

## Overview

TaskPilot is currently positioned as a workflow-oriented project management and AI-assisted engineering platform. The core security posture is therefore grounded in the usual Laravel controls: authentication, authorization, scoped project access, and approval gates before potentially destructive or externally visible actions are allowed.

## Current protections

### Authentication and session security

- The app uses Laravel authentication and Fortify-based flows for sign-in and account management.
- Session configuration is kept in standard Laravel settings rather than custom bypass logic.
- The project uses the normal Laravel authorization model for project-scoped actions and issue access.

### Authorization boundaries

- Project-level access is checked at the service layer before issue or workflow operations are performed.
- Workflow transitions are gated by the orchestration layer rather than by direct UI-only checks.
- Branch creation, commit/push, and PR creation remain behind the project GitHub integration service instead of being triggered directly from controllers or issue components.

### Secret handling

- GitHub credentials are configured on the server side through environment/configuration rather than being embedded in issue text, comments, or client-side state.
- The GitHub integration service centralizes token access and API calls behind a single abstraction so provider-specific credentials are not spread across controllers or UI layers.
- This keeps agent and GitHub operations consistent with the repository's intended server-side trust boundary.

### Approval gates and workflow integrity

The core product value is not just AI execution, but governed AI execution.

- Workflow progression requires explicit approval states before actions that create implementation work or external repository changes.
- Agent runs are recorded with status metadata, output, and failure details so the workflow remains auditable.
- The orchestration layer prevents the workflow from skipping required steps or silently advancing without the expected state transitions.

### Realtime and channel access

- Reverb channels are scoped to project and issue context rather than being broadly public.
- The app keeps event listeners aligned with the authenticated project membership model so realtime updates remain project-aware.

### Rate limiting and abuse control

- Laravel authentication and Fortify settings are already configured with rate-limiting controls for common auth flows and login abuse paths.
- Mutation routes remain behind the expected authenticated and authorized access model.

## Key risk areas

### 1. AI output as untrusted input

Agent output must never be treated as trusted code or trusted instructions. The repository intentionally keeps AI-generated output as a workflow artifact rather than automatically executing it without human review.

### 2. GitHub operations

Repository actions such as branch creation and PR creation are privileged operations. The current architecture keeps them behind the project GitHub integration service so they do not bypass normal project ownership and validation checks.

### 3. Public client exposure

The frontend should never be treated as the source of truth for privileged actions. The project’s service layer is the control point for project membership, workflow approval, and GitHub integration.

## Current risk assessment

The current implementation has a sound baseline for a portfolio-grade product:

- server-side secrets stay server-side,
- workflow execution remains approval-gated,
- project-level access remains scoped,
- external API actions are centralized behind a service boundary,
- auth and rate-limiting defaults are preserved through the standard Laravel stack.

The remaining security exposure is not a large architectural bypass; it is the normal operational risk of a product that still needs deeper hardening, documentation, and explicit review as it evolves toward broader adoption.

## Recommended follow-up

1. Add a documented secret-management checklist for local and deployment environments.
2. Review workflow approval policies for every external mutation and ensure each one is explicitly required.
3. Ensure API endpoints and Reverb channels remain project-scoped for all future workflow features.
4. Continue to keep AI-generated operational output isolated from direct execution until explicit human approval is granted.

## Summary

The current security posture is appropriate for the repository’s current stage: a controlled, human-in-the-loop workflow system with strong service-layer boundaries, auth defaults, and project-scoped enforcement. The main emphasis for the next phase is disciplined operational review rather than a complete rewrite of the existing model.
