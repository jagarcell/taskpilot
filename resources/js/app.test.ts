import { describe, expect, it } from 'vitest';

describe('app smoke test', () => {
    it('renders a basic assertion', () => {
        expect(1 + 1).toBe(2);
    });
});
