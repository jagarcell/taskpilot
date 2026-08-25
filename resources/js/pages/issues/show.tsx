import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';

interface IssueUser {
    id: number;
    name: string;
    email?: string | null;
}

interface IssueLabel {
    id: number;
    name: string;
}

interface IssueComment {
    id: number;
    body: string;
    user_name?: string | null;
    created_at?: string | null;
}

interface IssueActivityItem {
    id: number;
    type: string;
    message: string;
    user_name?: string | null;
    context?: {
        from?: string | null;
        to?: string | null;
    } | null;
    created_at?: string | null;
}

interface IssueAgentRunMessage {
    id: number;
    role?: string | null;
    content?: string | null;
    metadata?: Record<string, unknown> | null;
    created_at?: string | null;
}

interface IssueAgentRun {
    id: number;
    status: string;
    model?: string | null;
    provider?: string | null;
    output?: Record<string, unknown> | null;
    error?: Record<string, unknown> | null;
    created_at?: string | null;
    agent?: {
        id: number;
        name: string;
        slug?: string | null;
    } | null;
    messages?: IssueAgentRunMessage[];
}

interface IssueDetailPageProps {
    project: {
        id: number;
        name: string;
    };
    issue: {
        id: number;
        issue_key: string;
        title: string;
        description?: string | null;
        type: string;
        status: string;
        priority: string;
        reporter?: IssueUser | null;
        assignee?: IssueUser | null;
        labels: IssueLabel[];
        comments: IssueComment[];
        activities: IssueActivityItem[];
        runs: IssueAgentRun[];
        agents?: Array<{
            id: number;
            name: string;
            slug?: string | null;
            model?: string | null;
            provider?: string | null;
        }>;
    };
}

const issueTypeLabel = (type: string): string => {
    if (type === 'bug') return 'Bug';
    if (type === 'task') return 'Task';
    if (type === 'story') return 'Story';
    if (type === 'epic') return 'Epic';
    return type;
};

const issuePriorityLabel = (priority: string): string => {
    if (priority === 'low') return 'Low';
    if (priority === 'medium') return 'Medium';
    if (priority === 'high') return 'High';
    if (priority === 'urgent') return 'Urgent';
    return priority;
};

const formatActivitySummary = (activity: IssueActivityItem): string => {
    if (activity.type === 'status_changed') {
        const from = activity.context?.from ? activity.context.from.replace(/_/g, ' ') : 'unknown';
        const to = activity.context?.to ? activity.context.to.replace(/_/g, ' ') : 'unknown';

        return `${from} -> ${to}`;
    }

    return activity.message;
};

const formatActivityTitle = (activity: IssueActivityItem): string => {
    if (activity.type === 'status_changed') {
        return 'Status Changed';
    }

    return activity.message;
};

const issueAnalysisSections = (output?: Record<string, unknown> | null): Array<{ key: string; label: string; value: unknown }> => {
    if (!output || typeof output !== 'object') {
        return [];
    }

    const analysis = 'analysis' in output && output.analysis && typeof output.analysis === 'object'
        ? output.analysis as Record<string, unknown>
        : output;

    const mapping: Array<{ key: string; label: string }> = [
        { key: 'likely_causes', label: 'Likely causes' },
        { key: 'missing_information', label: 'Missing information' },
        { key: 'acceptance_criteria', label: 'Acceptance criteria' },
        { key: 'suggested_priority', label: 'Suggested priority' },
        { key: 'estimated_complexity', label: 'Estimated complexity' },
        { key: 'areas_to_investigate', label: 'Areas to investigate' },
    ];

    return mapping
        .map(({ key, label }) => ({
            key,
            label,
            value: analysis[key],
        }))
        .filter(({ value }) => value !== undefined && value !== null && value !== '');
};

const issuePlanSections = (output?: Record<string, unknown> | null): Array<{ key: string; label: string; value: unknown }> => {
    if (!output || typeof output !== 'object') {
        return [];
    }

    const plan = 'plan' in output && output.plan && typeof output.plan === 'object'
        ? output.plan as Record<string, unknown>
        : output;

    const mapping: Array<{ key: string; label: string }> = [
        { key: 'technical_approach', label: 'Technical approach' },
        { key: 'files_likely_affected', label: 'Files likely affected' },
        { key: 'database_changes', label: 'Database changes' },
        { key: 'api_changes', label: 'API changes' },
        { key: 'frontend_changes', label: 'Frontend changes' },
        { key: 'testing_strategy', label: 'Testing strategy' },
        { key: 'implementation_steps', label: 'Implementation steps' },
    ];

    return mapping
        .map(({ key, label }) => ({
            key,
            label,
            value: plan[key],
        }))
        .filter(({ value }) => value !== undefined && value !== null && value !== '');
};

export const hasLiveAgentRuns = (runs: Array<{ id?: number; status?: string | null }>): boolean =>
    runs.some((run) => ['pending', 'running'].includes(run.status ?? ''));

export const getIssueAnalyzerAgent = (agents?: Array<{ id: number; name: string; slug?: string | null; model?: string | null; provider?: string | null }>):
    | { id: number; name: string; slug?: string | null; model?: string | null; provider?: string | null }
    | undefined => agents?.find((agent) => agent.name.toLowerCase() === 'issue analyzer');

export const getIssuePlannerAgent = (agents?: Array<{ id: number; name: string; slug?: string | null; model?: string | null; provider?: string | null }>):
    | { id: number; name: string; slug?: string | null; model?: string | null; provider?: string | null }
    | undefined => agents?.find((agent) => agent.name.toLowerCase() === 'planning agent');

export const statusBadgeClasses = (status?: string | null): string => {
    const base = 'rounded-full border px-2 py-1 text-[10px] font-medium uppercase tracking-[0.12em]';

    switch (status) {
        case 'pending':
            return `${base} border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300`;
        case 'running':
            return `${base} border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300`;
        case 'completed':
            return `${base} border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300`;
        case 'failed':
            return `${base} border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300`;
        default:
            return `${base} border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-300`;
    }
};

export default function IssueShowPage({ project, issue }: IssueDetailPageProps) {
    const liveRuns = hasLiveAgentRuns(issue.runs);
    const issueAnalyzerAgent = getIssueAnalyzerAgent(issue.agents);
    const issuePlannerAgent = getIssuePlannerAgent(issue.agents);

    useEffect(() => {
        if (!liveRuns) {
            return undefined;
        }

        const intervalId = window.setInterval(() => {
            router.reload({ only: ['issue'] });
        }, 2000);

        return () => window.clearInterval(intervalId);
    }, [liveRuns, issue.id]);

    return (
        <>
            <Head title={`${issue.issue_key} · ${issue.title}`} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={projects.show(project.id)}
                        className="inline-flex items-center text-sm font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300"
                    >
                        ← Back to project
                    </Link>
                    <Link
                        href={dashboard()}
                        className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        Dashboard
                    </Link>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-xs font-medium uppercase tracking-[0.2em] text-sky-600 dark:text-sky-400">{issue.issue_key}</p>
                            <h1 className="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">{issue.title}</h1>
                        </div>
                        <div className="flex flex-wrap gap-2 text-xs uppercase tracking-[0.12em] text-slate-600 dark:text-slate-300">
                            <span className="rounded-full border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-950">{issueTypeLabel(issue.type)}</span>
                            <span className="rounded-full border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-950">{issue.status}</span>
                            <span className="rounded-full border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-950">{issuePriorityLabel(issue.priority)}</span>
                        </div>
                    </div>

                    <div className="grid gap-6 md:grid-cols-3">
                        <div className="md:col-span-2">
                            <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Description</h2>
                            <p className="mt-3 whitespace-pre-wrap text-slate-700 dark:text-slate-200">
                                {issue.description || 'No description has been added for this issue.'}
                            </p>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Reporter</h2>
                                <p className="mt-2 text-slate-700 dark:text-slate-200">{issue.reporter?.name ?? 'Unknown user'}</p>
                            </div>
                            <div>
                                <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Assignee</h2>
                                <p className="mt-2 text-slate-700 dark:text-slate-200">{issue.assignee?.name ?? 'Unassigned'}</p>
                            </div>
                            <div>
                                <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Labels</h2>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {issue.labels.length > 0 ? issue.labels.map((label) => (
                                        <span key={label.id} className="rounded-full border border-sky-200 bg-sky-50 px-2 py-1 text-xs font-medium text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300">
                                            {label.name}
                                        </span>
                                    )) : <span className="text-sm text-slate-600 dark:text-slate-300">No labels</span>}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Comments</h2>
                        <div className="mt-4 space-y-4">
                            {issue.comments.length === 0 ? (
                                <p className="text-sm text-slate-600 dark:text-slate-300">No comments on this issue yet.</p>
                            ) : issue.comments.map((comment) => (
                                <div key={comment.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-slate-900 dark:text-white">{comment.user_name ?? 'Unknown user'}</span>
                                        {comment.created_at ? (
                                            <span className="text-xs text-slate-500 dark:text-slate-400">{new Date(comment.created_at).toLocaleString()}</span>
                                        ) : null}
                                    </div>
                                    <p className="whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-200">{comment.body}</p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4">
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                Back to project
                            </Button>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Run Agent</h2>
                                <div className="flex flex-wrap gap-2">
                                    {issueAnalyzerAgent ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.post(`/projects/${project.id}/issues/${issue.id}/agent-runs`, {
                                                agent_id: issueAnalyzerAgent.id,
                                                model: issueAnalyzerAgent.model ?? 'gpt-4o-mini',
                                                provider: issueAnalyzerAgent.provider ?? 'openai',
                                                'input[prompt]': issue.description || issue.title,
                                            }, { preserveScroll: true })}
                                        >
                                            Analyze issue
                                        </Button>
                                    ) : null}
                                    {issuePlannerAgent ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.post(`/projects/${project.id}/issues/${issue.id}/agent-runs`, {
                                                agent_id: issuePlannerAgent.id,
                                                model: issuePlannerAgent.model ?? 'gpt-4o-mini',
                                                provider: issuePlannerAgent.provider ?? 'openai',
                                                'input[prompt]': `${issue.title}\n\n${issue.description || ''}\n\nGenerate an implementation plan with technical approach, likely files, database changes, API changes, frontend changes, testing strategy, and implementation steps.`,
                                            }, { preserveScroll: true })}
                                        >
                                            Plan implementation
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                            <Form
                                action={`/projects/${project.id}/issues/${issue.id}/agent-runs`}
                                method="post"
                                className="mt-4 space-y-4"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <label htmlFor="agent_id" className="text-sm font-medium text-slate-700 dark:text-slate-200">Agent</label>
                                            <select
                                                id="agent_id"
                                                name="agent_id"
                                                defaultValue={issue.agents?.[0]?.id ?? ''}
                                                className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                            >
                                                {issue.agents && issue.agents.length > 0 ? (
                                                    issue.agents.map((agent) => (
                                                        <option key={agent.id} value={agent.id}>{agent.name}</option>
                                                    ))
                                                ) : (
                                                    <option value="">No active agents available</option>
                                                )}
                                            </select>
                                            <InputError message={errors.agent_id} />
                                        </div>

                                        <div className="grid gap-2">
                                            <label htmlFor="model" className="text-sm font-medium text-slate-700 dark:text-slate-200">Model</label>
                                            <input
                                                id="model"
                                                name="model"
                                                defaultValue={issue.agents?.[0]?.model ?? 'gpt-4o-mini'}
                                                className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                            />
                                            <InputError message={errors.model} />
                                        </div>

                                        <div className="grid gap-2">
                                            <label htmlFor="provider" className="text-sm font-medium text-slate-700 dark:text-slate-200">Provider</label>
                                            <input
                                                id="provider"
                                                name="provider"
                                                defaultValue={issue.agents?.[0]?.provider ?? 'openai'}
                                                className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                            />
                                            <InputError message={errors.provider} />
                                        </div>

                                        <div className="grid gap-2">
                                            <label htmlFor="prompt" className="text-sm font-medium text-slate-700 dark:text-slate-200">Prompt</label>
                                            <textarea
                                                id="prompt"
                                                name="input[prompt]"
                                                rows={4}
                                                placeholder="Describe what you want the agent to review."
                                                className="flex min-h-[120px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"
                                            />
                                            <InputError message={errors['input.prompt']} />
                                        </div>

                                        <div className="flex justify-end">
                                            <Button type="submit" disabled={processing || !issue.agents || issue.agents.length === 0}>
                                                {processing ? 'Running...' : 'Run Agent'}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Agent Runs</h2>
                            <div className="mt-4 space-y-4">
                                {issue.runs.length === 0 ? (
                                    <p className="text-sm text-slate-600 dark:text-slate-300">No agent runs recorded yet.</p>
                                ) : issue.runs.map((run) => (
                                    <div key={run.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                                        <div className="mb-1 flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium text-slate-900 dark:text-white">{run.agent?.name ?? 'Agent'}</span>
                                            <span className={statusBadgeClasses(run.status)}>{run.status}</span>
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-slate-300">
                                            {run.provider ?? 'unknown'} · {run.model ?? 'default model'}
                                        </p>
                                        {run.created_at ? (
                                            <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{new Date(run.created_at).toLocaleString()}</p>
                                        ) : null}

                                        {run.output ? (() => {
                                            const analysisSections = issueAnalysisSections(run.output);
                                            const planSections = issuePlanSections(run.output);
                                            const summary = typeof run.output.summary === 'string' ? run.output.summary : null;
                                            const sections = planSections.length > 0 ? planSections : analysisSections;
                                            const heading = planSections.length > 0 ? 'Implementation plan' : 'Analysis';

                                            if (sections.length > 0) {
                                                return (
                                                    <div className="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                                                        <p className="mb-2 font-medium uppercase tracking-[0.12em] text-emerald-700 dark:text-emerald-300">{heading}</p>
                                                        {summary ? <p className="mb-3 whitespace-pre-wrap text-sm">{summary}</p> : null}
                                                        <div className="space-y-3">
                                                            {sections.map((section) => (
                                                                <div key={section.key}>
                                                                    <p className="mb-1 font-medium text-emerald-700 dark:text-emerald-300">{section.label}</p>
                                                                    {Array.isArray(section.value) ? (
                                                                        <ul className="list-disc space-y-1 pl-5">
                                                                            {section.value.map((item, index) => (
                                                                                <li key={`${section.key}-${index}`}>{String(item)}</li>
                                                                            ))}
                                                                        </ul>
                                                                    ) : (
                                                                        <p className="whitespace-pre-wrap">{String(section.value)}</p>
                                                                    )}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                );
                                            }

                                            return (
                                                <div className="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">
                                                    <p className="mb-1 font-medium uppercase tracking-[0.12em] text-emerald-700 dark:text-emerald-300">Output</p>
                                                    <pre className="whitespace-pre-wrap font-sans">{JSON.stringify(run.output, null, 2)}</pre>
                                                </div>
                                            );
                                        })() : null}

                                        {run.error ? (
                                            <div className="mt-3 rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
                                                <p className="mb-1 font-medium uppercase tracking-[0.12em] text-rose-700 dark:text-rose-300">Error</p>
                                                <pre className="whitespace-pre-wrap font-sans">{JSON.stringify(run.error, null, 2)}</pre>
                                            </div>
                                        ) : null}

                                        {run.messages && run.messages.length > 0 ? (
                                            <div className="mt-3 space-y-2">
                                                {run.messages.map((message) => (
                                                    <div key={message.id} className="rounded-md border border-slate-200 bg-white p-2 text-xs text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                                                        <div className="mb-1 flex items-center justify-between gap-2">
                                                            <span className="font-medium uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{message.role ?? 'message'}</span>
                                                            {message.created_at ? (
                                                                <span className="text-[10px] text-slate-500 dark:text-slate-400">{new Date(message.created_at).toLocaleString()}</span>
                                                            ) : null}
                                                        </div>
                                                        <p className="whitespace-pre-wrap">{message.content ?? 'No content available.'}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : null}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Activity</h2>
                            <div className="mt-4 space-y-4">
                                {issue.activities.length === 0 ? (
                                    <p className="text-sm text-slate-600 dark:text-slate-300">No activity has been recorded yet.</p>
                                ) : issue.activities.map((activity) => (
                                    <div key={activity.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                                        <div className="mb-1 flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium text-slate-900 dark:text-white">{formatActivityTitle(activity)}</span>
                                            {activity.created_at ? (
                                                <span className="text-xs text-slate-500 dark:text-slate-400">{new Date(activity.created_at).toLocaleString()}</span>
                                            ) : null}
                                        </div>
                                        <p className="text-xs text-slate-600 dark:text-slate-300">
                                            {activity.user_name ?? 'System'} · {formatActivitySummary(activity)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
