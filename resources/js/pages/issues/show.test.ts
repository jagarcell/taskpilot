import { describe, expect, it } from 'vitest';

import { buildPlanningAgentPrompt, getDefaultAgentPrompt, getIssueAnalyzerAgent, getIssuePlannerAgent, getPlanningContextNotice, getWorkflowOperatorLabel, getWorkflowStatusLabel, hasLiveAgentRuns, statusBadgeClasses, workflowStatusBadgeClasses } from './show';

describe('issue agent run status helpers', () => {
    it('flags pending or running runs as live so the page keeps polling', () => {
        expect(hasLiveAgentRuns([{ id: 1, status: 'pending' }])).toBe(true);
        expect(hasLiveAgentRuns([{ id: 2, status: 'running' }])).toBe(true);
        expect(hasLiveAgentRuns([{ id: 3, status: 'completed' }])).toBe(false);
        expect(hasLiveAgentRuns([{ id: 4, status: 'failed' }])).toBe(false);
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
});
