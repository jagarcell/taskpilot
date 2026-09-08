import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';

interface ProviderTestResult {
    summary?: string;
    status?: string;
    reauth_required?: boolean;
    provider?: string;
    errors?: { message?: string; status?: number | string } | null;
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

export default function Dashboard({ agents = [] }: { agents?: AgentRecord[] }) {
    const providerSelectRef = useRef<HTMLSelectElement | null>(null);
    const [selectedProvider, setSelectedProvider] = useState('openai');
    const [providerTestResult, setProviderTestResult] = useState<ProviderTestResult | null>(null);
    const [lastProviderBadgeState, setLastProviderBadgeState] = useState<ProviderBadgeState | null>(null);
    const [testingProvider, setTestingProvider] = useState(false);
    const modelOptions = selectedProvider === 'copilot'
        ? ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo']
        : ['gpt-4o-mini', 'gpt-4o'];

    const providerStatus = getProviderBadgeState(selectedProvider, providerTestResult, lastProviderBadgeState);
    const canReauthenticateProvider = canTriggerProviderReauth(selectedProvider, providerTestResult);

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
            setProviderTestResult(payload);
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
            setProviderTestResult(payload);
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
                        <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">4</p>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Open issues</p>
                        <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">28</p>
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Ready for review</p>
                        <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">7</p>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Foundation status</h2>
                        <span className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300">
                            Phase 1 ready
                        </span>
                    </div>
                    <div className="space-y-4 text-sm text-slate-600 dark:text-slate-300">
                        <div className="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-slate-800/70">
                            <span>Laravel backend</span>
                            <span className="font-medium text-emerald-600 dark:text-emerald-300">Enabled</span>
                        </div>
                        <div className="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-slate-800/70">
                            <span>React frontend shell</span>
                            <span className="font-medium text-emerald-600 dark:text-emerald-300">Enabled</span>
                        </div>
                        <div className="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-slate-800/70">
                            <span>MySQL + Redis foundation</span>
                            <span className="font-medium text-emerald-600 dark:text-emerald-300">Configured</span>
                        </div>
                        <div className="flex items-center justify-between rounded-lg bg-slate-50 p-3 dark:bg-slate-800/70">
                            <span>Authentication and app shell</span>
                            <span className="font-medium text-emerald-600 dark:text-emerald-300">Active</span>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Agent definitions</h2>
                    </div>

                    <Form action="/agents" method="post" className="mb-6 space-y-4 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
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
                                                <p className="mt-1">{providerTestResult.summary ?? 'No result returned.'}</p>
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

                                    <Form action={`/agents/${agent.id}`} method="post" className="flex items-center gap-2">
                                        <input type="hidden" name="_method" value="PUT" />
                                        <input type="hidden" name="is_active" value="0" />
                                        <input type="hidden" name="name" value={agent.name} />
                                        <input type="hidden" name="provider" value={agent.provider} />
                                        <input type="hidden" name="model" value={agent.model} />
                                        <input type="hidden" name="description" value={agent.description ?? ''} />
                                        <label className="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="is_active" value="1" defaultChecked={agent.is_active} />
                                            Active
                                        </label>
                                        <button type="submit" className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300">
                                            Save
                                        </button>
                                    </Form>
                                </div>
                            ))
                        )}
                    </div>
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
