import { describe, expect, it } from 'vitest';

import { appendAgentRunMessage, applyAgentRunUpdate, applyWorkflowRunUpdate, buildPlanningAgentPrompt, canStartWorkflow, getDefaultAgentPrompt, getGitHubWorkflowContext, getIssueAnalyzerAgent, getIssuePlannerAgent, getPlanningContextNotice, getWorkflowCompletionSummary, getWorkflowOperatorLabel, getWorkflowStatusLabel, shouldListenForAgentRunUpdates, statusBadgeClasses, workflowStatusBadgeClasses } from './show';

describe('issue agent run status helpers', () => {
    it('subscribes for realtime updates as long as the issue is scoped to a valid project and issue', () => {
        expect(shouldListenForAgentRunUpdates(1, 12)).toBe(true);
        expect(shouldListenForAgentRunUpdates(0, 12)).toBe(false);
        expect(shouldListenForAgentRunUpdates(1, undefined)).toBe(false);
    });

    it('identifies the Issue Analyzer agent for the quick action menu', () => {
        const analyzer = getIssueAnalyzerAgent([
            { id: 1, name: 'General Assistant', provider: 'openai', model: 'gpt-4o-mini' },
            { id: 2, name: 'Issue Analyzer', provider: 'openai', model: 'gpt-4o-mini' },
        ]);

        expect(analyzer?.id).toBe(2);
        expect(analyzer?.name).toBe('Issue Analyzer');
    });

    it('identifies the Planning Agent for implementation planning tasks', () => {
        const planner = getIssuePlannerAgent([
            { id: 1, name: 'Issue Analyzer', provider: 'openai', model: 'gpt-4o-mini' },
            { id: 2, name: 'Planning Agent', provider: 'openai', model: 'gpt-4o-mini' },
        ]);

        expect(planner?.id).toBe(2);
        expect(planner?.name).toBe('Planning Agent');
    });

    it('builds a planning prompt that includes the latest analysis context', () => {
        const prompt = buildPlanningAgentPrompt({
            title: 'Checkout totals are wrong',
            description: 'Cart totals are incorrect when the user adds a second item.',
            latestAnalysis: {
                summary: 'This issue likely affects arithmetic during cart total updates.',
                analysis: {
                    likely_causes: ['subtotal recalculation bug'],
                    suggested_priority: 'high',
                    estimated_complexity: 5,
                },
            },
        });

        expect(prompt).toContain('Checkout totals are wrong');
        expect(prompt).toContain('Cart totals are incorrect when the user adds a second item.');
        expect(prompt).toContain('This issue likely affects arithmetic during cart total updates.');
        expect(prompt).toContain('subtotal recalculation bug');
        expect(prompt).toContain('high');
        expect(prompt).toContain('5');
    });

    it('prefills the planning prompt for planning agents using the latest analysis context', () => {
        const prompt = getDefaultAgentPrompt({
            agentName: 'Planning Agent',
            title: 'Checkout totals are wrong',
            description: 'Cart totals are incorrect when the user adds a second item.',
            latestAnalysis: {
                summary: 'This issue likely affects arithmetic during cart total updates.',
                analysis: { suggested_priority: 'high' },
            },
        });

        expect(prompt).toContain('Issue title: Checkout totals are wrong');
        expect(prompt).toContain('Latest analysis summary');
        expect(prompt).toContain('suggested_priority');
    });

    it('prefills the issue prompt for analyzer agents without planning context', () => {
        const prompt = getDefaultAgentPrompt({
            agentName: 'Issue Analyzer',
            title: 'Checkout totals are wrong',
            description: 'Cart totals are incorrect when the user adds a second item.',
            latestAnalysis: {
                summary: 'This issue likely affects arithmetic during cart total updates.',
                analysis: { suggested_priority: 'high' },
            },
        });

        expect(prompt).toBe('Cart totals are incorrect when the user adds a second item.');
    });

    it('renders the implementation-plan summary when a planning run returns structured plan data', () => {
        const summary = getPlanningContextNotice({
            latestAnalysis: {
                summary: 'This issue likely affects arithmetic during cart total updates.',
                analysis: { suggested_priority: 'high' },
            },
            isPlanningRun: true,
            runInputPrompt: 'Issue title: Checkout totals are wrong\n\nLatest analysis context:\nThis issue likely affects arithmetic.',
        });

        expect(summary).toContain('latest issue analysis');
    });

    it('returns a planning-context notice when the run prompt includes injected analysis context', () => {
        expect(getPlanningContextNotice({
            latestAnalysis: {
                summary: 'This issue likely affects arithmetic during cart total updates.',
                analysis: { suggested_priority: 'high' },
            },
            isPlanningRun: true,
            runInputPrompt: 'Issue title: Checkout totals are wrong\n\nLatest analysis context:\nThis issue likely affects arithmetic.',
        })).toContain('latest issue analysis');
    });

    it('does not show the notice when the prompt has no injected analysis context', () => {
        expect(getPlanningContextNotice({
            latestAnalysis: {
                summary: 'This issue likely affects arithmetic during cart total updates.',
                analysis: { suggested_priority: 'high' },
            },
            isPlanningRun: true,
            runInputPrompt: 'Generate a plan for this issue.',
        })).toBeNull();
    });

    it('uses distinct styling for each terminal and active status', () => {
        expect(statusBadgeClasses('pending')).toContain('amber');
        expect(statusBadgeClasses('running')).toContain('sky');
        expect(statusBadgeClasses('completed')).toContain('emerald');
        expect(statusBadgeClasses('failed')).toContain('rose');
    });

    it('shows the workflow start action only when no workflow run exists yet', () => {
        expect(canStartWorkflow([])).toBe(true);
        expect(canStartWorkflow([{ id: 1, status: 'running' }])).toBe(false);
        expect(canStartWorkflow([{ id: 2, status: 'failed' }])).toBe(false);
    });

    it('marks the workflow as not started when no workflow run exists yet', () => {
        expect(getWorkflowStatusLabel('not_started')).toBe('Not started');
        expect(workflowStatusBadgeClasses('not_started')).toContain('slate');
    });

    it('formats workflow status and operator labels for issue workflow actions', () => {
        expect(getWorkflowStatusLabel('waiting_for_approval')).toBe('Waiting for approval');
        expect(getWorkflowStatusLabel('failed')).toBe('Failed');
        expect(getWorkflowOperatorLabel('approve')).toBe('Approve');
        expect(getWorkflowOperatorLabel('retry')).toBe('Retry');
    });

    it('uses distinct status styling for active workflow states', () => {
        expect(workflowStatusBadgeClasses('waiting_for_approval')).toContain('amber');
        expect(workflowStatusBadgeClasses('failed')).toContain('rose');
        expect(workflowStatusBadgeClasses('completed')).toContain('emerald');
    });

    it('shows the final review completion summary when the workflow reaches the review stage', () => {
        expect(getWorkflowCompletionSummary('completed', 'review')).toContain('Review complete');
        expect(getWorkflowCompletionSummary('completed', 'implementation')).toContain('Workflow completed successfully');
        expect(getWorkflowCompletionSummary('running', 'review')).toBeNull();
    });

    it('summarizes GitHub PR health for workflow approval and retry decisions', () => {
        const failing = getGitHubWorkflowContext({
            number: 42,
            state: 'open',
            title: 'feat: add GitHub integration',
            checks: {
                total: 2,
                success: 1,
                failure: 1,
                pending: 0,
                skipped: 0,
                overall: 'failure',
            },
        });

        expect(failing.summary).toContain('PR #42');
        expect(failing.summary).toContain('1 failing check');
        expect(failing.tone).toBe('danger');

        const passing = getGitHubWorkflowContext({
            number: 43,
            state: 'open',
            title: 'fix: make metrics consistent',
            checks: {
                total: 2,
                success: 2,
                failure: 0,
                pending: 0,
                skipped: 0,
                overall: 'success',
            },
        });

        expect(passing.summary).toContain('checks are passing');
        expect(passing.tone).toBe('success');

        const none = getGitHubWorkflowContext(null);
        expect(none.summary).toContain('No open pull request');
        expect(none.tone).toBe('neutral');
    });

    it('merges a realtime agent-run event into the current listing without reloading the page', () => {
        const updated = applyAgentRunUpdate([
            { id: 1, status: 'running', output: { summary: 'initial' }, error: null },
            { id: 2, status: 'pending', output: null, error: null },
        ], {
            run_id: 1,
            status: 'completed',
            output: { summary: 'updated output' },
            previous_status: 'running',
            error: null,
        });

        expect(updated[0]?.status).toBe('completed');
        expect(updated[0]?.output).toEqual({ summary: 'updated output' });
        expect(updated[1]?.status).toBe('pending');
    });

    it('appends a realtime agent message to the active run without a full page refresh', () => {
        const updated = appendAgentRunMessage([
            { id: 1, status: 'running', messages: [{ id: 1, role: 'assistant', content: 'Starting the review.', created_at: '2026-08-31T00:00:00Z' }] },
            { id: 2, status: 'pending', messages: [] },
        ], 1, {
            id: 2,
            role: 'assistant',
            content: 'Tracing the failing request path.',
            created_at: '2026-08-31T00:00:05Z',
        });

        expect(updated[0]?.messages).toHaveLength(2);
        expect(updated[0]?.messages?.[1]?.content).toBe('Tracing the failing request path.');
        expect(updated[1]?.status).toBe('pending');
    });

    it('inserts a newly created workflow run into the list when the realtime event arrives before the page reloads', () => {
        const updated = applyWorkflowRunUpdate([], {
            workflow_run_id: 12,
            status: 'running',
            current_step: 'analysis',
            last_completed_step: null,
            operator_action: null,
            can_retry: false,
            retry_count: 0,
        });

        expect(updated).toHaveLength(1);
        expect(updated[0]).toMatchObject({
            id: 12,
            status: 'running',
            current_step: 'analysis',
            operator_action: null,
        });
    });

    it('merges a realtime workflow-run status update without reloading the page', () => {
        const updated = applyWorkflowRunUpdate([
            { id: 1, status: 'running', current_step: 'analysis', last_completed_step: null, operator_action: null },
            { id: 2, status: 'completed', current_step: 'planning', last_completed_step: 'analysis', operator_action: null },
        ], {
            workflow_run_id: 1,
            status: 'waiting_for_approval',
            current_step: 'approval',
            last_completed_step: 'analysis',
            operator_action: 'approve',
        });

        expect(updated[0]?.status).toBe('waiting_for_approval');
        expect(updated[0]?.current_step).toBe('approval');
        expect(updated[0]?.operator_action).toBe('approve');
        expect(updated[1]?.status).toBe('completed');
    });

    it('preserves failed-step and last-error details when a workflow run fails and is retried live', () => {
        const updated = applyWorkflowRunUpdate([
            { id: 4, status: 'running', current_step: 'planning', last_completed_step: 'analysis', operator_action: null, can_retry: false, retry_count: 0 },
        ], {
            workflow_run_id: 4,
            status: 'failed',
            current_step: 'planning',
            last_completed_step: 'analysis',
            operator_action: 'retry',
            can_retry: true,
            retry_count: 1,
            failed_step: 'planning',
            last_error: { message: 'Planner timed out.' },
        });

        expect(updated[0]).toMatchObject({
            status: 'failed',
            current_step: 'planning',
            operator_action: 'retry',
            can_retry: true,
            retry_count: 1,
            failed_step: 'planning',
            last_error: { message: 'Planner timed out.' },
        });
    });

    it('clears stale operator actions when the workflow broadcast intentionally sends a null value', () => {
        const updated = applyWorkflowRunUpdate([
            { id: 7, status: 'waiting_for_approval', current_step: 'approval', last_completed_step: 'analysis', operator_action: 'approve', can_retry: false, retry_count: 0 },
        ], {
            workflow_run_id: 7,
            status: 'running',
            current_step: 'planning',
            last_completed_step: 'analysis',
            operator_action: null,
            can_retry: false,
            retry_count: 0,
        });

        expect(updated[0]).toMatchObject({
            status: 'running',
            current_step: 'planning',
            operator_action: null,
        });
    });
});
