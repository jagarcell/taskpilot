# Project Repository Context Plan

## Objective

Allow each project to bind to a repository that serves as the default execution context for issue work, agent planning, implementation, validation, and review workflows.

## Phase 13 Milestones

### Milestone 1: Repository binding schema

**Goal**
Persist a normalized, project-scoped repository binding record so the application has one canonical source of truth for repository context.

**Scope**
- Create a `project_repository_bindings` table.
- Store provider, binding type, remote/local repository metadata, default branch, active flag, and verification timestamp.
- Add `ProjectRepositoryBinding` model and repository/service layer.
- Add a has-one relation from `Project` to its repository binding.
- Add regression tests covering store/retrieve behavior.

**Acceptance criteria**
- A project can persist exactly one active repository binding record.
- The binding stores both local and remote repository metadata without forcing GitHub-specific assumptions.
- The record is available through a service boundary and project relation.

### Milestone 2: Remote repository validation

**Goal**
Verify that the configured remote repository is valid, accessible, and consistent with the project’s intended context before it is used by workflows.

**Scope**
- Add validation logic for remote providers such as GitHub.
- Check that the owner/repo exists and is accessible.
- Normalize repository metadata, including default branch and remote URL.
- Persist verification metadata and expose clear error states.
- Add unit tests for success and failure cases.

**Acceptance criteria**
- Invalid or inaccessible remote repositories fail with a clear error.
- Valid repositories record verification metadata and remain usable as execution context.
- The binding is not silently left in a broken state.

### Milestone 3: Local repository context support

**Goal**
Support project-local repository path linking alongside remote provider-backed repositories.

**Scope**
- Allow binding to an absolute local repository path.
- Validate the local path exists and is a git repository when applicable.
- Keep local and remote bindings mutually exclusive or clearly typed.
- Expose the selected context in project state and API payloads.

**Acceptance criteria**
- A project can resolve to either a local or remote repository context.
- The chosen context is explicitly typed and validated.
- Agent or workflow code can resolve the active repository context consistently.

### Milestone 4: Project repository context API and UI

**Goal**
Surface the repository context in the application for project owners and members.

**Scope**
- Add API endpoints to create, update, and inspect the binding.
- Show repository status, provider, branch, and validation state on the project detail page.
- Add a clear “connect repository” flow and a disconnected/error state.
- Ensure project member authorizations remain enforced.

**Acceptance criteria**
- Users can connect and validate a repository from the project UI.
- The app clearly communicates whether the repository is valid, disconnected, or inaccessible.
- Ownership and authorization rules remain enforced.

### Milestone 5: Repo-aware workflow context

**Goal**
Make the repository binding the default execution input for issue and agent workflows.

**Scope**
- Pass the bound repository context into agent planning and implementation steps.
- Use the repository metadata when creating branches or checking repository health.
- Ensure human approval gates remain active and are not bypassed.

**Acceptance criteria**
- Repository context informs automation without bypassing authorization.
- Project workflows can resolve a standard code context from the binding record.
- Approval-aware flow remains intact.

## Recommended implementation order

1. Repository binding schema
2. Remote repository validation
3. Local repository context support
4. API + project UI integration
5. Repo-aware workflow execution context

## Engineering constraints

- Keep provider-specific logic behind service abstractions.
- Treat repository context as execution input, not authorization bypass.
- Keep controllers thin and domain rules in service/repository layers.
- Validate repository connectivity before allowing workflows to depend on it.
- Preserve multi-tenant and project-scoped access boundaries.

## Initial backend contract

### Model

- `ProjectRepositoryBinding`
  - `project_id`
  - `provider` (`github` as first provider)
  - `binding_type` (`local` or `remote`)
  - `remote_owner`
  - `remote_repo`
  - `remote_url`
  - `local_path`
  - `default_branch`
  - `is_active`
  - `verified_at`

### Repository/service layer

- `ProjectRepositoryBindingRepository`
  - `bind(Project $project, array $attributes)`
  - `findForProject(Project $project)`

- `ProjectRepositoryBindingService`
  - `bind(...)`
  - `getForProject(...)`
  - `validate(...)` (future milestone)

## Validation flow for the first GitHub-backed version

1. User opens project settings or repository panel.
2. User selects a provider: GitHub.
3. User enters owner/repository or a local path.
4. System validates remote metadata against the provider API.
5. System stores binding and last verification timestamp.
6. Project detail page shows repository status and default branch.
7. Later agent/issue actions can resolve the project context from this binding.

## Key design note

The repository binding should be a generic project context primitive. GitHub is only the first concrete provider implementation, and all provider-specific code should remain behind the abstraction rather than being sprinkled into project logic.
