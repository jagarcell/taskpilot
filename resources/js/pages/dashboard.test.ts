import { describe, expect, it } from 'vitest';

import { formatProviderResultMessage, getProviderBadgeState } from './dashboard';

describe('dashboard provider badge state', () => {
    it('shows live OAuth only when the real Copilot connection is valid', () => {
        expect(getProviderBadgeState('copilot', { provider: 'copilot', status: 'ok', reauth_required: false })).toMatchObject({
            label: 'Live OAuth',
            tone: 'success',
        });

        expect(getProviderBadgeState('copilot', { provider: 'copilot', status: 'missing_credentials', reauth_required: false })).toMatchObject({
            label: 'Auth required',
            tone: 'warning',
        });

        expect(getProviderBadgeState('copilot', { provider: 'copilot', status: 'failed', reauth_required: true })).toMatchObject({
            label: 'Reauth required',
            tone: 'danger',
        });
    });

    it('keeps mock status for the OpenAI provider', () => {
        expect(getProviderBadgeState('openai', { provider: 'openai', status: 'ok', reauth_required: false })).toMatchObject({
            label: 'Mock mode',
            tone: 'warning',
        });
    });

    it('preserves the last real Copilot status when the message is closed', () => {
        const fallbackState = {
            label: 'Reauth required',
            tone: 'danger',
            className: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300',
        } as const;

        expect(getProviderBadgeState('copilot', null, fallbackState)).toMatchObject({
            label: 'Reauth required',
            tone: 'danger',
        });
    });

    it('lists the available provider models in the result message', () => {
        expect(formatProviderResultMessage({
            provider: 'copilot',
            status: 'ok',
            summary: 'Copilot access confirmed for the configured GitHub account.',
            available_models: ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'],
        })).toContain('Available models: gpt-4o, gpt-4o-mini, gpt-3.5-turbo');
    });
});
