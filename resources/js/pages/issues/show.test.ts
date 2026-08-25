import { describe, expect, it } from 'vitest';

import { getIssueAnalyzerAgent, getIssuePlannerAgent, hasLiveAgentRuns, statusBadgeClasses } from './show';

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

    it('uses distinct styling for each terminal and active status', () => {
        expect(statusBadgeClasses('pending')).toContain('amber');
        expect(statusBadgeClasses('running')).toContain('sky');
        expect(statusBadgeClasses('completed')).toContain('emerald');
        expect(statusBadgeClasses('failed')).toContain('rose');
    });
});
