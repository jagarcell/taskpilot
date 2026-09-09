import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';

interface ProviderTestResult {
    summary?: string;
    status?: string;
    reauth_required?: boolean;
    provider?: string;
    available_models?: string[];
    errors?: { message?: string; status?: number | string } | null;
}

export function formatProviderResultMessage(result?: ProviderTestResult | null): string {
    if (!result) {
        return 'No result returned.';
    }

    const summary = result.summary ?? 'No result returned.';
    const models = Array.isArray(result.available_models) && result.available_models.length > 0
        ? result.available_models
        : [];

    if (models.length === 0) {
        return summary;
    }

    return `${summary} Available models: ${models.join(', ')}`;
}

interface ProviderBadgeState {
    label: string;
    className: string;
    tone: 'success' | 'warning' | 'danger' | 'neutral';
}

interface AgentRecord {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    provider: string;
    model: string;
    is_active: boolean;
}

interface ProviderCredentialRecord {
    provider: string;
    client_id: string;
    client_secret: string;
    redirect_uri: string;
}

export const DEFAULT_PROVIDER = 'openai';

export function getProviderModelOptions(provider: string): string[] {
    if (provider === 'copilot') {
        return ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'];
    }

    return ['gpt-4o-mini', 'gpt-4o'];
}

export function resolveProviderModelOptions(provider: string, result?: ProviderTestResult | null, fallbackOptions?: string[]): string[] {
    const models = Array.isArray(result?.available_models) && result.available_models.length > 0
        ? result.available_models
        : [];

    if (models.length > 0) {
        return models;
    }

    if (Array.isArray(fallbackOptions) && fallbackOptions.length > 0) {
        return fallbackOptions;
    }

    return getProviderModelOptions(provider);
}

export function canTriggerProviderReauth(provider: string, result?: ProviderTestResult | null): boolean {
    if (provider === 'openai') {
        return false;
    }

    return Boolean(result && (result.reauth_required || result.status === 'failed' || result.status === 'missing_credentials'));
}

export function getProviderBadgeState(provider: string, result?: ProviderTestResult | null, fallbackState?: ProviderBadgeState | null): ProviderBadgeState {
    if (provider === 'openai') {
        return {
            label: 'Mock mode',
            tone: 'warning',
            className: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300',
        };
    }

    if (!result) {
        if (fallbackState) {
            return fallbackState;
        }

        return {
            label: 'Checking...',
            tone: 'neutral',
            className: 'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    if (result.status === 'ok') {
        return {
            label: 'Live OAuth',
            tone: 'success',
            className: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300',
        };
    }

    if (result.status === 'missing_credentials') {
        return {
            label: 'Auth required',
            tone: 'warning',
            className: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300',
        };
    }

    if (result.reauth_required || result.status === 'failed') {
        return {
            label: 'Reauth required',
            tone: 'danger',
            className: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300',
        };
    }

    return {
        label: 'OAuth issue',
        tone: 'warning',
        className: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300',
    };
}

export default function Dashboard({ agents = [], active_projects_count = 0, open_issues_count = 0, open_issues = [], ready_for_review_count = 0, ready_for_review_issues = [], provider_oauth_credentials = {} }: { agents?: AgentRecord[]; active_projects_count?: number; open_issues_count?: number; open_issues?: Array<{ id: number; project_id: number; issue_key: string; title: string; description?: string | null; type?: string; status?: string; priority?: string; project_name?: string | null; reporter_name?: string | null; assignee_name?: string | null; detail_url?: string | null }>; ready_for_review_count?: number; ready_for_review_issues?: Array<{ id: number; project_id: number; issue_key: string; title: string; description?: string | null; type?: string; status?: string; priority?: string; project_name?: string | null; reporter_name?: string | null; assignee_name?: string | null; detail_url?: string | null }>; provider_oauth_credentials?: Record<string, ProviderCredentialRecord> }) {
    const providerSelectRef = useRef<HTMLSelectElement | null>(null);
    const dashboardPanelStorageKey = 'taskpilot-dashboard-panel-state';

    const getStoredDashboardPanelState = () => {
        if (typeof window === 'undefined') {
            return null;
        }

        try {
            const storedValue = window.localStorage.getItem(dashboardPanelStorageKey);
            if (!storedValue) {
                return null;
            }

            return JSON.parse(storedValue) as {
                showOpenIssues?: boolean;
                showReadyForReviewIssues?: boolean;
                showAgentOAuthCredentials?: boolean;
                showAgentDefinitions?: boolean;
                showAvailableAgents?: boolean;
            };
        } catch {
            return null;
        }
    };

    const storedPanelState = getStoredDashboardPanelState();
    const [showOpenIssues, setShowOpenIssues] = useState(storedPanelState?.showOpenIssues ?? false);
    const [showReadyForReviewIssues, setShowReadyForReviewIssues] = useState(storedPanelState?.showReadyForReviewIssues ?? false);
    const [showAgentOAuthCredentials, setShowAgentOAuthCredentials] = useState(storedPanelState?.showAgentOAuthCredentials ?? false);
    const [showAgentDefinitions, setShowAgentDefinitions] = useState(storedPanelState?.showAgentDefinitions ?? false);
    const [showAvailableAgents, setShowAvailableAgents] = useState(storedPanelState?.showAvailableAgents ?? false);
    const [selectedProvider, setSelectedProvider] = useState(DEFAULT_PROVIDER);
    const [editingAgentId, setEditingAgentId] = useState<number | null>(null);
    const [editingProviderResult, setEditingProviderResult] = useState<ProviderTestResult | null>(null);
    const [editingModelOptions, setEditingModelOptions] = useState<string[]>(getProviderModelOptions(DEFAULT_PROVIDER));
    const [editingAgent, setEditingAgent] = useState({
        name: 'Issue Analyzer',
        provider: DEFAULT_PROVIDER,
        model: getProviderModelOptions(DEFAULT_PROVIDER)[0],
        description: '',
        is_active: true,
    });
    const [providerTestResult, setProviderTestResult] = useState<ProviderTestResult | null>(null);
    const [lastProviderBadgeState, setLastProviderBadgeState] = useState<ProviderBadgeState | null>(null);
    const [testingProvider, setTestingProvider] = useState(false);
    const [credentialForm, setCredentialForm] = useState({
        provider: 'github',
        client_id: '',
        client_secret: '',
        redirect_uri: '',
    });
    const [savingCredentials, setSavingCredentials] = useState(false);
    const modelOptions = resolveProviderModelOptions(selectedProvider, providerTestResult, getProviderModelOptions(selectedProvider));

    useEffect(() => {
        const record = provider_oauth_credentials?.[credentialForm.provider];
        if (record) {
            setCredentialForm((current) => ({
                ...current,
                client_id: record.client_id ?? current.client_id,
                client_secret: record.client_secret ?? current.client_secret,
                redirect_uri: record.redirect_uri ?? current.redirect_uri,
            }));
        }
    }, [credentialForm.provider, provider_oauth_credentials]);

    const providerStatus = getProviderBadgeState(selectedProvider, providerTestResult, lastProviderBadgeState);
    const canReauthenticateProvider = canTriggerProviderReauth(selectedProvider, providerTestResult);

    useEffect(() => {
        try {
            window.localStorage.setItem(dashboardPanelStorageKey, JSON.stringify({
                showOpenIssues,
                showReadyForReviewIssues,
                showAgentOAuthCredentials,
                showAgentDefinitions,
                showAvailableAgents,
            }));
        } catch {
            // Ignore storage failures and keep the dashboard functional.
        }
    }, [showOpenIssues, showReadyForReviewIssues, showAgentOAuthCredentials, showAgentDefinitions, showAvailableAgents]);

    useEffect(() => {
        if (providerTestResult) {
            setLastProviderBadgeState(getProviderBadgeState(selectedProvider, providerTestResult));
        }
    }, [providerTestResult, selectedProvider]);

    const refreshProviderStatus = async (provider: string) => {
        if (provider === 'openai') {
            setProviderTestResult({
                provider,
                status: 'ok',
                summary: 'OpenAI mock connection test succeeded. This is a simulated provider check for the current app setup.',
                reauth_required: false,
            });
            return;
        }

        try {
            const response = await fetch('/dashboard/provider/test', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                },
                body: JSON.stringify({ provider }),
            });

            const payload = await response.json();
            const availableModels = provider === 'copilot'
                ? (Array.isArray(payload?.available_models) && payload.available_models.length > 0
                    ? payload.available_models
                    : ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'])
                : (Array.isArray(payload?.available_models) && payload.available_models.length > 0
                    ? payload.available_models
                    : ['gpt-4o-mini', 'gpt-4o']);

            setProviderTestResult({
                ...payload,
                available_models: availableModels,
            });
        } catch (error) {
            setProviderTestResult({
                provider,
                summary: 'Provider connection test failed unexpectedly.',
                status: 'failed',
                reauth_required: false,
                errors: { message: error instanceof Error ? error.message : 'Unknown error', status: 500 },
            });
        }
    };

    useEffect(() => {
        void refreshProviderStatus(selectedProvider);
    }, []);

    const handleProviderChange = async (provider: string) => {
        setSelectedProvider(provider);
        setProviderTestResult(null);
        await refreshProviderStatus(provider);
    };

    const triggerProviderReauth = async () => {
        if (!canReauthenticateProvider) {
            return;
        }

        try {
            const response = await fetch('/dashboard/provider/reauth', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                },
                body: JSON.stringify({ provider: selectedProvider }),
            });

            const payload = await response.json();

            if (payload?.redirect_to) {
                window.location.href = payload.redirect_to;
                return;
            }

            if (payload?.summary) {
                setProviderTestResult({
                    provider: selectedProvider,
                    status: payload.status ?? 'failed',
                    summary: payload.summary,
                    reauth_required: payload.reauth_required ?? true,
                });
            }
        } catch (error) {
            setProviderTestResult({
                provider: selectedProvider,
                summary: 'Provider reauthentication failed unexpectedly.',
                status: 'failed',
                reauth_required: true,
                errors: { message: error instanceof Error ? error.message : 'Unknown error', status: 500 },
            });
        }
    };

    const runProviderTest = async () => {
        setTestingProvider(true);
        setProviderTestResult(null);

        try {
            if (selectedProvider === 'openai') {
                const mockStatus = Math.random() > 0.5 ? 'ok' : 'failed';
                const mockSummary = mockStatus === 'ok'
                    ? 'OpenAI mock connection test succeeded. This is a simulated provider check for the current app setup.'
                    : 'OpenAI mock connection test failed. This simulated failure demonstrates the failure state for provider validation UI.';

                setProviderTestResult({
                    provider: selectedProvider,
                    status: mockStatus,
                    summary: mockSummary,
                    reauth_required: false,
                });
                return;
            }

            const response = await fetch('/dashboard/provider/test', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                },
                body: JSON.stringify({ provider: selectedProvider }),
            });

            const payload = await response.json();
            const availableModels = selectedProvider === 'copilot'
                ? (Array.isArray(payload?.available_models) && payload.available_models.length > 0
                    ? payload.available_models
                    : ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'])
                : (Array.isArray(payload?.available_models) && payload.available_models.length > 0
                    ? payload.available_models
                    : ['gpt-4o-mini', 'gpt-4o']);

            setProviderTestResult({
                ...payload,
                available_models: availableModels,
            });
            if (payload?.reauth_required) {
                window.location.href = '/auth/github';
            }
        } catch (error) {
            setProviderTestResult({
                provider: selectedProvider,
                summary: 'Provider connection test failed unexpectedly.',
                status: 'failed',
                reauth_required: false,
                errors: { message: error instanceof Error ? error.message : 'Unknown error', status: 500 },
            });
        } finally {
            setTestingProvider(false);
        }
    };

    const saveProviderCredentials = async () => {
        setSavingCredentials(true);

        try {
            const response = await fetch('/dashboard/provider/oauth-credentials', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                },
                body: JSON.stringify(credentialForm),
            });

            if (!response.ok) {
                throw new Error('Unable to save OAuth credentials.');
            }

            window.location.reload();
        } catch (error) {
            console.error(error);
        } finally {
            setSavingCredentials(false);
        }
    };

    const openAgentEditor = async (agent: AgentRecord) => {
        const fallbackOptions = getProviderModelOptions(agent.provider);

        setEditingAgentId(agent.id);
        setEditingProviderResult(null);
        setEditingAgent({
            name: agent.name,
            provider: agent.provider,
            model: agent.model,
            description: agent.description ?? '',
            is_active: agent.is_active,
        });
        setEditingModelOptions(fallbackOptions);

        await refreshEditingAgentProvider(agent.provider);
    };

    const closeAgentEditor = () => {
        setEditingAgentId(null);
        setEditingProviderResult(null);
    };

    const refreshEditingAgentProvider = async (provider: string) => {
        const fallbackOptions = getProviderModelOptions(provider);

        if (provider === 'openai') {
            const mockResult: ProviderTestResult = {
                provider,
                status: 'ok',
                summary: 'OpenAI mock connection test succeeded. This is a simulated provider check for the current app setup.',
                reauth_required: false,
                available_models: fallbackOptions,
            };

            setEditingProviderResult(mockResult);
            setEditingModelOptions(fallbackOptions);
            setEditingAgent((current) => ({
                ...current,
                provider,
                model: fallbackOptions[0] ?? current.model,
            }));
            return;
        }

        try {
            const response = await fetch('/dashboard/provider/test', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                },
                body: JSON.stringify({ provider }),
            });

            const payload = await response.json();
            const availableModels = resolveProviderModelOptions(provider, payload, fallbackOptions);
            const result = {
                ...payload,
                provider,
                available_models: availableModels,
            } as ProviderTestResult;

            setEditingProviderResult(result);
            setEditingModelOptions(availableModels);
            setEditingAgent((current) => ({
                ...current,
                provider,
                model: availableModels[0] ?? current.model,
            }));
        } catch (error) {
            console.error('Unable to refresh editing provider models.', error);
            const failedResult: ProviderTestResult = {
                provider,
                summary: 'Provider connection test failed unexpectedly.',
                status: 'failed',
                reauth_required: false,
                errors: { message: error instanceof Error ? error.message : 'Unknown error', status: 500 },
                available_models: fallbackOptions,
            };

            setEditingProviderResult(failedResult);
            setEditingModelOptions(fallbackOptions);
            setEditingAgent((current) => ({
                ...current,
                provider,
                model: fallbackOptions[0] ?? current.model,
            }));
        }
    };

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Active projects</p>
                            <Link href={projects.index()} className="text-xs font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300">
                                View all
                            </Link>
                        </div>
                        <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{active_projects_count}</p>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Open issues</p>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowOpenIssues((current) => !current);
                                    if (showReadyForReviewIssues) {
                                        setShowReadyForReviewIssues(false);
                                    }
                                }}
                                className="text-xs font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300"
                            >
                                {showOpenIssues ? 'Hide all' : 'View all'}
                            </button>
                        </div>
                        <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{open_issues_count}</p>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Ready for review</p>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowReadyForReviewIssues((current) => !current);
                                    if (showOpenIssues) {
                                        setShowOpenIssues(false);
                                    }
                                }}
                                className="text-xs font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300"
                            >
                                {showReadyForReviewIssues ? 'Hide all' : 'View all'}
                            </button>
                        </div>
                        <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{ready_for_review_count}</p>
                    </div>
                </div>

                {showOpenIssues && open_issues.length > 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Open issues</h2>
                            <span className="text-sm text-slate-500 dark:text-slate-400">{open_issues.length} total</span>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {open_issues.map((issue) => (
                                <div key={issue.id} className="flex h-full flex-col justify-between rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                                    <div className="space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="text-xs font-semibold uppercase tracking-[0.14em] text-sky-600 dark:text-sky-400">{issue.issue_key}</span>
                                            <span className="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200">{issue.status}</span>
                                        </div>
                                        <h3 className="text-base font-semibold text-slate-900 dark:text-white">{issue.title}</h3>
                                        <p className="text-sm text-slate-600 dark:text-slate-300">{issue.description || 'No issue description provided.'}</p>
                                    </div>

                                    <div className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                        <p><span className="font-medium text-slate-700 dark:text-slate-200">Project:</span> {issue.project_name || 'Unknown project'}</p>
                                        <p><span className="font-medium text-slate-700 dark:text-slate-200">Priority:</span> {issue.priority}</p>
                                        <p><span className="font-medium text-slate-700 dark:text-slate-200">Assignee:</span> {issue.assignee_name || 'Unassigned'}</p>
                                    </div>

                                    <div className="mt-4 flex justify-end">
                                        <Link
                                            href={issue.detail_url ?? `/projects/${issue.project_id}/issues/${issue.id}`}
                                            className="inline-flex items-center rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500"
                                        >
                                            Open issue
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {showReadyForReviewIssues && ready_for_review_issues.length > 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Ready for review</h2>
                            <span className="text-sm text-slate-500 dark:text-slate-400">{ready_for_review_issues.length} total</span>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {ready_for_review_issues.map((issue) => (
                                <div key={issue.id} className="flex h-full flex-col justify-between rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                                    <div className="space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="text-xs font-semibold uppercase tracking-[0.14em] text-sky-600 dark:text-sky-400">{issue.issue_key}</span>
                                            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{issue.status}</span>
                                        </div>
                                        <h3 className="text-base font-semibold text-slate-900 dark:text-white">{issue.title}</h3>
                                        <p className="text-sm text-slate-600 dark:text-slate-300">{issue.description || 'No issue description provided.'}</p>
                                    </div>

                                    <div className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                        <p><span className="font-medium text-slate-700 dark:text-slate-200">Project:</span> {issue.project_name || 'Unknown project'}</p>
                                        <p><span className="font-medium text-slate-700 dark:text-slate-200">Priority:</span> {issue.priority}</p>
                                        <p><span className="font-medium text-slate-700 dark:text-slate-200">Assignee:</span> {issue.assignee_name || 'Unassigned'}</p>
                                    </div>

                                    <div className="mt-4 flex justify-end">
                                        <Link
                                            href={issue.detail_url ?? `/projects/${issue.project_id}/issues/${issue.id}`}
                                            className="inline-flex items-center rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500"
                                        >
                                            Open issue
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Agent OAuth credentials</h2>
                        <button
                            type="button"
                            aria-label={showAgentOAuthCredentials ? 'Collapse agent OAuth credentials' : 'Expand agent OAuth credentials'}
                            onClick={() => setShowAgentOAuthCredentials((current) => !current)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                        >
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className={`h-4 w-4 transition-transform ${showAgentOAuthCredentials ? 'rotate-180' : ''}`}
                                aria-hidden="true"
                            >
                                <path d="M5 7.5L10 12.5L15 7.5" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>
                    </div>

                    {showAgentOAuthCredentials ? (
                        <div className="mb-6 space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <label htmlFor="oauth-provider" className="text-sm font-medium text-slate-700 dark:text-slate-200">Provider</label>
                                    <select
                                        id="oauth-provider"
                                        value={credentialForm.provider}
                                        onChange={(event) => setCredentialForm((current) => ({ ...current, provider: event.target.value }))}
                                        className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                    >
                                        <option value="github">github</option>
                                        <option value="openai">openai</option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <label htmlFor="oauth-client-id" className="text-sm font-medium text-slate-700 dark:text-slate-200">Client ID</label>
                                    <input
                                        id="oauth-client-id"
                                        type="text"
                                        value={credentialForm.client_id}
                                        onChange={(event) => setCredentialForm((current) => ({ ...current, client_id: event.target.value }))}
                                        className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <label htmlFor="oauth-redirect-uri" className="text-sm font-medium text-slate-700 dark:text-slate-200">Redirect URI</label>
                                    <input
                                        id="oauth-redirect-uri"
                                        type="text"
                                        value={credentialForm.redirect_uri}
                                        onChange={(event) => setCredentialForm((current) => ({ ...current, redirect_uri: event.target.value }))}
                                        className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <label htmlFor="oauth-client-secret" className="text-sm font-medium text-slate-700 dark:text-slate-200">Client secret</label>
                                <input
                                    id="oauth-client-secret"
                                    type="text"
                                    value={credentialForm.client_secret}
                                    onChange={(event) => setCredentialForm((current) => ({ ...current, client_secret: event.target.value }))}
                                    placeholder={provider_oauth_credentials?.[credentialForm.provider]?.client_secret ? provider_oauth_credentials[credentialForm.provider].client_secret : 'Enter client secret'}
                                    className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                />
                            </div>

                            <div className="flex justify-end">
                                <button
                                    type="button"
                                    onClick={saveProviderCredentials}
                                    disabled={savingCredentials}
                                    className="inline-flex items-center rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60"
                                >
                                    {savingCredentials ? 'Saving...' : 'Save'}
                                </button>
                            </div>
                        </div>
                    ) : null}
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Agent definitions</h2>
                        <button
                            type="button"
                            aria-label={showAgentDefinitions ? 'Collapse agent definitions' : 'Expand agent definitions'}
                            onClick={() => setShowAgentDefinitions((current) => !current)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                        >
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className={`h-4 w-4 transition-transform ${showAgentDefinitions ? 'rotate-180' : ''}`}
                                aria-hidden="true"
                            >
                                <path d="M5 7.5L10 12.5L15 7.5" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>
                    </div>

                    {showAgentDefinitions ? (
                        <Form
                            action="/agents"
                            method="post"
                            className="mb-6 space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
                            options={{ preserveScroll: true, preserveState: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <label htmlFor="name" className="text-sm font-medium text-slate-700 dark:text-slate-200">Agent name</label>
                                            <select id="name" name="name" required defaultValue="Issue Analyzer" className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                <option value="Issue Analyzer">Issue Analyzer</option>
                                                <option value="Planning Agent">Planning Agent</option>
                                                <option value="Approval Agent">Approval Agent</option>
                                                <option value="Implementation Agent">Implementation Agent</option>
                                                <option value="QA Agent">QA Agent</option>
                                                <option value="Review Agent">Review Agent</option>
                                            </select>
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <div className="flex items-center justify-between gap-2">
                                                <label htmlFor="provider" className="text-sm font-medium text-slate-700 dark:text-slate-200">Provider</label>
                                                <span
                                                    role={canReauthenticateProvider ? 'button' : undefined}
                                                    tabIndex={canReauthenticateProvider ? 0 : -1}
                                                    onMouseDown={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        providerSelectRef.current?.blur();
                                                    }}
                                                    onPointerDown={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        providerSelectRef.current?.blur();
                                                    }}
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        event.stopPropagation();

                                                        if (canReauthenticateProvider) {
                                                            void triggerProviderReauth();
                                                        }
                                                    }}
                                                    onKeyDown={(event) => {
                                                        if (!canReauthenticateProvider) {
                                                            return;
                                                        }

                                                        if (event.key === 'Enter' || event.key === ' ') {
                                                            event.preventDefault();
                                                            event.stopPropagation();
                                                            void triggerProviderReauth();
                                                        }
                                                    }}
                                                    className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] transition ${providerStatus.className} ${canReauthenticateProvider ? 'cursor-pointer hover:opacity-90' : 'cursor-default opacity-100'}`}
                                                >
                                                    {providerStatus.label}
                                                </span>
                                            </div>
                                            <select
                                                ref={providerSelectRef}
                                                id="provider"
                                                name="provider"
                                                value={selectedProvider}
                                                onChange={(event) => {
                                                    void handleProviderChange(event.target.value);
                                                }}
                                                className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                            >
                                                <option value="openai">openai</option>
                                                <option value="copilot">copilot</option>
                                            </select>
                                            <InputError message={errors.provider} />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <label htmlFor="model" className="text-sm font-medium text-slate-700 dark:text-slate-200">Model</label>
                                            <select
                                                id="model"
                                                name="model"
                                                defaultValue={modelOptions[0]}
                                                className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                            >
                                                {modelOptions.map((model) => (
                                                    <option key={model} value={model}>{model}</option>
                                                ))}
                                            </select>
                                            <InputError message={errors.model} />
                                        </div>
                                        <div className="grid gap-2">
                                            <label htmlFor="is_active" className="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                                            <label className="flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                <input type="checkbox" id="is_active" name="is_active" value="1" defaultChecked />
                                                Active
                                            </label>
                                            <InputError message={errors.is_active} />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <label htmlFor="description" className="text-sm font-medium text-slate-700 dark:text-slate-200">Description</label>
                                        <textarea id="description" name="description" rows={3} className="flex min-h-[100px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" />
                                        <InputError message={errors.description} />
                                    </div>

                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={runProviderTest}
                                            disabled={testingProvider}
                                            className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-200 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                                        >
                                            {testingProvider ? `Testing ${selectedProvider}...` : `Test ${selectedProvider}`}
                                        </button>
                                        <button type="submit" disabled={processing} className="inline-flex items-center rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                                            {processing ? 'Saving...' : 'Create agent'}
                                        </button>
                                    </div>

                                    {providerTestResult ? (
                                        <div className={`mt-3 rounded-lg border p-3 text-sm ${providerTestResult.status === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300'}`}>
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="font-medium">{providerTestResult.provider ? `${providerTestResult.provider} connection test` : 'Provider connection test'}</p>
                                                    <p className="mt-1">{formatProviderResultMessage(providerTestResult)}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setProviderTestResult(null);
                                                        setLastProviderBadgeState((currentState) => currentState ?? getProviderBadgeState(selectedProvider, providerTestResult));
                                                    }}
                                                    className="rounded-md border border-current/20 px-2 py-1 text-xs font-medium hover:bg-black/5 dark:hover:bg-white/5"
                                                >
                                                    Close
                                                </button>
                                            </div>
                                        </div>
                                    ) : null}
                                </>
                            )}
                        </Form>
                    ) : null}
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Available Agents</h2>
                        <button
                            type="button"
                            aria-label={showAvailableAgents ? 'Collapse available agents' : 'Expand available agents'}
                            onClick={() => setShowAvailableAgents((current) => !current)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                        >
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                className={`h-4 w-4 transition-transform ${showAvailableAgents ? 'rotate-180' : ''}`}
                                aria-hidden="true"
                            >
                                <path d="M5 7.5L10 12.5L15 7.5" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>
                    </div>

                    {showAvailableAgents ? (
                        <div className="space-y-3">
                            {agents.length === 0 ? (
                                <p className="text-sm text-slate-600 dark:text-slate-300">No agent definitions have been created yet.</p>
                            ) : (
                                agents.map((agent) => (
                                    <div key={agent.id} className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium text-slate-900 dark:text-white">{agent.name}</span>
                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium ${agent.is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300' : 'bg-slate-500/10 text-slate-600 dark:text-slate-300'}`}>
                                                    {agent.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{agent.description || 'No description yet.'}</p>
                                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{agent.provider} · {agent.model}</p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Form action={`/agents/${agent.id}`} method="post" className="flex items-center gap-2">
                                                <input type="hidden" name="_method" value="PUT" />
                                                <input type="hidden" name="is_active" value="0" />
                                                <input type="hidden" name="name" value={agent.name} />
                                                <input type="hidden" name="provider" value={agent.provider} />
                                                <input type="hidden" name="model" value={agent.model} />
                                                <input type="hidden" name="description" value={agent.description ?? ''} />
                                                <label className="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                    <input
                                                        type="checkbox"
                                                        name="is_active"
                                                        value="1"
                                                        defaultChecked={agent.is_active}
                                                        onChange={(event) => {
                                                            const form = event.currentTarget.form;
                                                            if (!form) {
                                                                return;
                                                            }

                                                            const payload = Object.fromEntries(new FormData(form).entries());
                                                            router.put(form.action, payload, {
                                                                preserveScroll: true,
                                                                preserveState: true,
                                                            });
                                                        }}
                                                    />
                                                    Active
                                                </label>
                                            </Form>
                                            <button
                                                type="button"
                                                onClick={() => openAgentEditor(agent)}
                                                className="inline-flex items-center rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                                            >
                                                Edit
                                            </button>
                                        </div>

                                        {editingAgentId === agent.id ? (
                                            <Dialog open={editingAgentId === agent.id} onOpenChange={(open) => !open && closeAgentEditor()}>
                                                <DialogContent className="sm:max-w-xl">
                                                    <DialogHeader>
                                                        <DialogTitle>Edit agent</DialogTitle>
                                                        <DialogDescription>
                                                            Update the agent name, provider, model, description, and active state.
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <Form
                                                        action={`/agents/${agent.id}`}
                                                        method="post"
                                                        className="space-y-4"
                                                        options={{ preserveScroll: true, preserveState: true }}
                                                        onSuccess={closeAgentEditor}
                                                    >
                                                        <input type="hidden" name="_method" value="PUT" />
                                                        <div className="grid gap-4 md:grid-cols-2">
                                                            <div className="grid gap-2">
                                                                <label htmlFor={`edit-agent-name-${agent.id}`} className="text-sm font-medium text-slate-700 dark:text-slate-200">Agent name</label>
                                                                <select
                                                                    id={`edit-agent-name-${agent.id}`}
                                                                    name="name"
                                                                    value={editingAgent.name}
                                                                    onChange={(event) => setEditingAgent((current) => ({ ...current, name: event.target.value }))}
                                                                    className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                                >
                                                                    <option value="Issue Analyzer">Issue Analyzer</option>
                                                                    <option value="Planning Agent">Planning Agent</option>
                                                                    <option value="Approval Agent">Approval Agent</option>
                                                                    <option value="Implementation Agent">Implementation Agent</option>
                                                                    <option value="QA Agent">QA Agent</option>
                                                                    <option value="Review Agent">Review Agent</option>
                                                                </select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <label htmlFor={`edit-agent-provider-${agent.id}`} className="text-sm font-medium text-slate-700 dark:text-slate-200">Provider</label>
                                                                <select
                                                                    id={`edit-agent-provider-${agent.id}`}
                                                                    name="provider"
                                                                    value={editingAgent.provider}
                                                                    onChange={(event) => {
                                                                        const provider = event.target.value;
                                                                        void refreshEditingAgentProvider(provider);
                                                                    }}
                                                                    className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                                >
                                                                    <option value="openai">openai</option>
                                                                    <option value="copilot">copilot</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div className="grid gap-4 md:grid-cols-2">
                                                            <div className="grid gap-2">
                                                                <label htmlFor={`edit-agent-model-${agent.id}`} className="text-sm font-medium text-slate-700 dark:text-slate-200">Model</label>
                                                                <select
                                                                    id={`edit-agent-model-${agent.id}`}
                                                                    name="model"
                                                                    value={editingAgent.model}
                                                                    onChange={(event) => setEditingAgent((current) => ({ ...current, model: event.target.value }))}
                                                                    className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                                >
                                                                    {editingModelOptions.map((model) => (
                                                                        <option key={model} value={model}>{model}</option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                            <div className="grid gap-2">
                                                                <label htmlFor={`edit-agent-active-${agent.id}`} className="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                                                                <label className="flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                                                    <input
                                                                        id={`edit-agent-active-${agent.id}`}
                                                                        type="checkbox"
                                                                        name="is_active"
                                                                        value="1"
                                                                        checked={editingAgent.is_active}
                                                                        onChange={(event) => setEditingAgent((current) => ({ ...current, is_active: event.target.checked }))}
                                                                    />
                                                                    Active
                                                                </label>
                                                            </div>
                                                        </div>

                                                        {editingProviderResult ? (
                                                            <div className={`rounded-lg border p-3 text-sm ${editingProviderResult.status === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300'}`}>
                                                                <p className="font-medium">{editingProviderResult.provider ? `${editingProviderResult.provider} connection test` : 'Provider connection test'}</p>
                                                                <p className="mt-1">{formatProviderResultMessage(editingProviderResult)}</p>
                                                            </div>
                                                        ) : null}

                                                        <div className="grid gap-2">
                                                            <label htmlFor={`edit-agent-description-${agent.id}`} className="text-sm font-medium text-slate-700 dark:text-slate-200">Description</label>
                                                            <textarea
                                                                id={`edit-agent-description-${agent.id}`}
                                                                name="description"
                                                                rows={3}
                                                                value={editingAgent.description}
                                                                onChange={(event) => setEditingAgent((current) => ({ ...current, description: event.target.value }))}
                                                                className="flex min-h-[100px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                            />
                                                        </div>

                                                        <DialogFooter className="gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    router.delete(`/agents/${agent.id}`, {
                                                                        preserveScroll: true,
                                                                        preserveState: true,
                                                                        onSuccess: () => closeAgentEditor(),
                                                                    });
                                                                }}
                                                                className="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:border-rose-300 hover:bg-rose-100 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300"
                                                            >
                                                                Delete
                                                            </button>
                                                            <button type="button" onClick={closeAgentEditor} className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Cancel</button>
                                                            <button type="submit" className="inline-flex items-center rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500">Save changes</button>
                                                        </DialogFooter>
                                                    </Form>
                                                </DialogContent>
                                            </Dialog>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
