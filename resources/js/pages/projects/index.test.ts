import { afterEach, describe, expect, it } from 'vitest';

import { getProjectsPagePanelState, saveProjectsPagePanelState } from './index';

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
    };
}

describe('projects page panel state', () => {
    afterEach(() => {
        Object.defineProperty(globalThis, 'localStorage', {
            value: undefined,
            configurable: true,
        });
    });

    it('restores the saved section state from localStorage', () => {
        Object.defineProperty(globalThis, 'localStorage', {
            value: createMockStorage({ 'taskpilot-projects-panel-state': JSON.stringify({ showCreateProject: true, showYourProjects: false }) }),
            configurable: true,
        });

        expect(getProjectsPagePanelState()).toEqual({ showCreateProject: true, showYourProjects: false });
    });

    it('persists the current state when sections are toggled', () => {
        const storage = createMockStorage();

        Object.defineProperty(globalThis, 'localStorage', {
            value: storage,
            configurable: true,
        });

        saveProjectsPagePanelState({ showCreateProject: true, showYourProjects: true });

        expect(storage.getItem('taskpilot-projects-panel-state')).toBe(JSON.stringify({ showCreateProject: true, showYourProjects: true }));
    });
});
