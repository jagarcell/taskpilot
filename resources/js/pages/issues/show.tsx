import { Head, Link } from '@inertiajs/react';
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

export default function IssueShowPage({ project, issue }: IssueDetailPageProps) {
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
        </>
    );
}
