import { describe, expect, it } from 'vitest';

import { hasLiveAgentRuns, statusBadgeClasses } from './show';

describe('issue agent run status helpers', () => {
    it('flags pending or running runs as live so the page keeps polling', () => {
        expect(hasLiveAgentRuns([{ id: 1, status: 'pending' }])).toBe(true);
        expect(hasLiveAgentRuns([{ id: 2, status: 'running' }])).toBe(true);
        expect(hasLiveAgentRuns([{ id: 3, status: 'completed' }])).toBe(false);
        expect(hasLiveAgentRuns([{ id: 4, status: 'failed' }])).toBe(false);
    });

    it('uses distinct styling for each terminal and active status', () => {
        expect(statusBadgeClasses('pending')).toContain('amber');
        expect(statusBadgeClasses('running')).toContain('sky');
        expect(statusBadgeClasses('completed')).toContain('emerald');
        expect(statusBadgeClasses('failed')).toContain('rose');
    });
});
