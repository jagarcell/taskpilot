import { afterEach, describe, expect, it } from 'vitest';

import { getProjectPagePanelState, saveProjectPagePanelState } from './show';

function createMockStorage(initialEntries: Record<string, string> = {}): Storage {
    const values = new Map(Object.entries(initialEntries));

    return {
        length: values.size,
        clear: () => values.clear(),
        getItem: (key: string) => values.get(key) ?? null,
        key: (index: number) => Array.from(values.keys())[index] ?? null,
        removeItem: (key: string) => values.delete(key),
        setItem: (key: string, value: string) => {
            values.set(key, value);
        },
    } as Storage;
}

describe('project detail panel state', () => {
    afterEach(() => {
        Object.defineProperty(globalThis, 'localStorage', {
            value: undefined,
            configurable: true,
        });
    });

    it('restores the saved section state from localStorage', () => {
        Object.defineProperty(globalThis, 'localStorage', {
            value: createMockStorage({ 'taskpilot-project-panel-state:42': JSON.stringify({ showProjectOverview: true, showCreateIssue: false, showMembers: true }) }),
            configurable: true,
        });

        expect(getProjectPagePanelState(42)).toEqual({ showProjectOverview: true, showCreateIssue: false, showMembers: true });
    });

    it('persists the current state when sections are toggled', () => {
        const storage = createMockStorage();

        Object.defineProperty(globalThis, 'localStorage', {
            value: storage,
            configurable: true,
        });

        saveProjectPagePanelState(42, { showProjectOverview: true, showCreateIssue: true, showMembers: false });

        expect(storage.getItem('taskpilot-project-panel-state:42')).toBe(JSON.stringify({ showProjectOverview: true, showCreateIssue: true, showMembers: false }));
    });
});
