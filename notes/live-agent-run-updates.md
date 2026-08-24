# Live Agent Run Status Updates

## Goal
Keep the issue detail page in sync when an agent run transitions from pending/running to completed or failed, without requiring a manual browser refresh.

## Why this is needed
The current issue detail page is server-rendered by Inertia and only updates when the page is reloaded or a new request is triggered. The queued agent execution job completes asynchronously, so the UI can become stale while a run is still active.

## Current status
The app currently uses a polling pattern on the issue detail page while a run is still active. This is acceptable as an incremental fix, but it is not the long-term architecture.

## Recommended future implementation
Implement a real event-driven notification layer so the frontend receives updates as soon as the agent run state changes.

### Proposed architecture
- Backend emits a domain event when an agent run changes status.
- The event is broadcast through Laravel Reverb / broadcasting channels.
- The frontend listens for the event and updates just the affected run row in place.
- The current toast pattern remains for success/error acknowledgement on user actions.

### Candidate backend flow
1. When `AgentRunRepository::updateStatus()` completes, dispatch a dedicated domain event such as `AgentRunStatusChanged`.
2. The event contains the run ID, issue ID, project ID, and current status.
3. Broadcast the event through a project-scoped or issue-scoped channel.
4. Ensure authorization checks remain enforced on the channel subscription path.

### Candidate frontend flow
1. Connect to the issue-specific channel when the issue page loads.
2. Listen for agent-run status events.
3. Update the relevant run in local component state or replace the issue payload entry without full reload.
4. Stop listening when the page unmounts.

## Implementation notes
- Do not put job-specific branching in the UI. The UI should react to server event data.
- Keep the event payload minimal: run id, issue id, status, and any output/error metadata needed for the page.
- Preserve the current Inertia flash toast behavior for user-facing confirmations.
- If polling stays temporarily in place, ensure it is limited to active runs only and is torn down once the run reaches a terminal state.

## Follow-up tasks
- Add the domain event and broadcasting setup.
- Add Reverb configuration and auth handling.
- Wire the issue-page listener.
- Add a regression test for the event-driven update contract.
- Remove the temporary polling fallback once the live event path is stable.

## Related files
- app/Repositories/AgentRunRepository.php
- app/Services/AgentExecutionService.php
- app/Models/AgentRun.php
- resources/js/pages/issues/show.tsx
- resources/js/hooks/use-flash-toast.ts
- config/broadcasting.php (if introduced)
- routes/channels.php (if introduced)
