import { Form, Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import ProjectMemberController from '@/actions/App/Http/Controllers/ProjectMemberController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';

interface ProjectMember {
    id: number;
    user_id: number;
    name: string | null;
    email: string | null;
    role: string;
}

interface ProjectOwner {
    id: number;
    name: string;
    email: string;
}

interface IssueComment {
    id: number;
    body: string;
    user_name?: string | null;
    created_at?: string | null;
}

interface Issue {
    id: number;
    issue_key: string;
    title: string;
    description?: string | null;
    type: string;
    status: string;
    priority: string;
    assignee_id?: number | null;
    assignee_name?: string | null;
    labels?: Array<{ id: number; name: string }>;
    comments?: IssueComment[];
}

interface AssigneeOption {
    id: number;
    name: string | null;
    email: string | null;
}

interface ProjectLabel {
    id: number;
    name: string;
}

interface WorkflowState {
    value: string;
    label: string;
}

interface ProjectPageProps {
    project: {
        id: number;
        name: string;
        description?: string | null;
        settings_summary?: string | null;
        members_label?: string | null;
        owner_label?: string | null;
        owner: ProjectOwner;
        can_manage_project?: boolean;
        created_at?: string | null;
        workflow_states?: WorkflowState[];
    };
    members: ProjectMember[];
    labels: ProjectLabel[];
    issues: Issue[];
    issues_by_status?: Record<string, Issue[]>;
    assignees?: AssigneeOption[];
}

const issueTypeLabel = (type: string): string => {
    switch (type) {
        case 'bug':
            return 'Bug';
        case 'task':
            return 'Task';
        case 'story':
            return 'Story';
        case 'epic':
            return 'Epic';
        default:
            return type;
    }
};

const issuePriorityLabel = (priority: string): string => {
    switch (priority) {
        case 'low':
            return 'Low';
        case 'medium':
            return 'Medium';
        case 'high':
            return 'High';
        case 'urgent':
            return 'Urgent';
        default:
            return priority;
    }
};

const buildBoardState = (workflowStates: WorkflowState[], issuesByStatus: Record<string, Issue[]>) => {
    const nextState: Record<string, Issue[]> = {};

    workflowStates.forEach((state) => {
        nextState[state.value] = issuesByStatus[state.value] ?? [];
    });

    return nextState;
};

export default function ProjectShow({ project, members, labels, issues, issues_by_status = {}, assignees = [] }: ProjectPageProps) {
    const canManageProject = project.can_manage_project ?? false;
    const availableAssignees = assignees.length > 0 ? assignees : [{ id: project.owner.id, name: project.owner.name, email: project.owner.email }, ...members.map((member) => ({ id: member.user_id, name: member.name, email: member.email }))];
    const workflowStates = project.workflow_states ?? [
        { value: 'backlog', label: 'Backlog' },
        { value: 'todo', label: 'Todo' },
        { value: 'in_progress', label: 'In Progress' },
        { value: 'review', label: 'Review' },
        { value: 'done', label: 'Done' },
    ];
    const [boardIssues, setBoardIssues] = useState<Record<string, Issue[]>>(() => buildBoardState(workflowStates, issues_by_status));
    const [draggedIssueId, setDraggedIssueId] = useState<number | null>(null);

    useEffect(() => {
        setBoardIssues(buildBoardState(workflowStates, issues_by_status));
    }, [workflowStates, issues_by_status]);

    const moveIssueToStatus = (issueId: number, nextStatus: string) => {
        const allIssues = Object.values(boardIssues).flat();
        const issue = allIssues.find((item) => item.id === issueId);

        if (!issue || issue.status === nextStatus) {
            return;
        }

        const previousStatus = issue.status;

        setBoardIssues((currentBoard) => {
            const nextBoard = Object.fromEntries(Object.entries(currentBoard).map(([status, columnIssues]) => [status, [...columnIssues]]));

            Object.keys(nextBoard).forEach((status) => {
                nextBoard[status] = nextBoard[status].filter((columnIssue) => columnIssue.id !== issueId);
            });

            nextBoard[nextStatus] = [{ ...issue, status: nextStatus }, ...(nextBoard[nextStatus] ?? [])];

            return nextBoard;
        });

        const payload = {
            title: issue.title,
            description: issue.description ?? '',
            type: issue.type,
            status: nextStatus,
            priority: issue.priority,
            assignee_id: issue.assignee_id ?? '',
            labels: issue.labels?.map((label) => label.id) ?? [],
        };

        router.put(`/projects/${project.id}/issues/${issue.id}`, payload, {
            onError: () => {
                setBoardIssues((currentBoard) => {
                    const revertedBoard = Object.fromEntries(Object.entries(currentBoard).map(([status, columnIssues]) => [status, [...columnIssues]]));

                    Object.keys(revertedBoard).forEach((status) => {
                        revertedBoard[status] = revertedBoard[status].filter((columnIssue) => columnIssue.id !== issueId);
                    });

                    revertedBoard[previousStatus] = [{ ...issue, status: previousStatus }, ...(revertedBoard[previousStatus] ?? [])];

                    return revertedBoard;
                });
            },
            onFinish: () => {
                setDraggedIssueId(null);
            },
        });
    };

    return (
        <>
            <Head title={project.name} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={projects.index()}
                        className="inline-flex items-center text-sm font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300"
                    >
                        ← Back to projects
                    </Link>
                    <Link
                        href={dashboard()}
                        className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        Dashboard
                    </Link>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-6">
                        <p className="text-sm font-medium uppercase tracking-[0.2em] text-sky-600">Project</p>
                        <h1 className="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">{project.name}</h1>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Description</h2>
                            <p className="mt-3 text-slate-700 dark:text-slate-200">
                                {project.description || 'No description has been added yet.'}
                            </p>
                        </div>

                        <div>
                            <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">{project.owner_label ?? 'Owner'}</h2>
                            <p className="mt-3 text-slate-700 dark:text-slate-200">{project.owner.name}</p>
                            <p className="text-sm text-slate-500 dark:text-slate-400">{project.owner.email}</p>
                        </div>
                    </div>
                </div>

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4">
                        <p className="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">Issues</p>
                        <h2 className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">Create issue</h2>
                    </div>

                    <Form
                        action={`/projects/${project.id}/issues`}
                        method="post"
                        className="space-y-6"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="title">Issue title</Label>
                                    <Input id="title" name="title" placeholder="Add issue title" required />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={4}
                                        placeholder="Describe the issue context and expected outcome."
                                        className="flex min-h-[120px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-4 md:grid-cols-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="type">Type</Label>
                                        <select
                                            id="type"
                                            name="type"
                                            defaultValue="task"
                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                        >
                                            <option value="bug">bug</option>
                                            <option value="task">task</option>
                                            <option value="story">story</option>
                                            <option value="epic">epic</option>
                                        </select>
                                        <InputError message={errors.type} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="priority">Priority</Label>
                                        <select
                                            id="priority"
                                            name="priority"
                                            defaultValue="medium"
                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                        >
                                            <option value="low">low</option>
                                            <option value="medium">medium</option>
                                            <option value="high">high</option>
                                            <option value="urgent">urgent</option>
                                        </select>
                                        <InputError message={errors.priority} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <select
                                            id="status"
                                            name="status"
                                            defaultValue="backlog"
                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                        >
                                            <option value="backlog">backlog</option>
                                            <option value="todo">todo</option>
                                            <option value="in_progress">in progress</option>
                                            <option value="review">review</option>
                                            <option value="done">done</option>
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="assignee_id">Assignee</Label>
                                        <select
                                            id="assignee_id"
                                            name="assignee_id"
                                            defaultValue=""
                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                        >
                                            <option value="">Unassigned</option>
                                            {availableAssignees.map((assignee) => (
                                                <option key={assignee.id} value={assignee.id}>
                                                    {assignee.name || assignee.email || 'Unknown user'}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.assignee_id} />
                                    </div>
                                </div>

                                {labels.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="labels">Labels</Label>
                                        <select
                                            id="labels"
                                            name="labels[]"
                                            multiple
                                            className="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                        >
                                            {labels.map((label) => (
                                                <option key={label.id} value={label.id}>
                                                    {label.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.labels} />
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>Create issue</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">Workflow</p>
                            <h2 className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">Kanban board</h2>
                        </div>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-5">
                        {workflowStates.map((state) => {
                            const columnIssues = boardIssues[state.value] ?? [];

                            return (
                                <div
                                    key={state.value}
                                    className="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70"
                                    onDragOver={(event) => {
                                        event.preventDefault();
                                        event.dataTransfer.dropEffect = 'move';
                                    }}
                                    onDrop={(event) => {
                                        event.preventDefault();

                                        if (draggedIssueId !== null) {
                                            moveIssueToStatus(draggedIssueId, state.value);
                                        }
                                    }}
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="text-sm font-semibold uppercase tracking-[0.12em] text-slate-700 dark:text-slate-200">{state.label}</h3>
                                        <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                            {columnIssues.length}
                                        </span>
                                    </div>

                                    <div className="space-y-3">
                                        {columnIssues.length === 0 ? (
                                            <p className="rounded-md border border-dashed border-slate-300 p-3 text-xs text-slate-500 dark:border-slate-600 dark:text-slate-400">
                                                No issues
                                            </p>
                                        ) : columnIssues.map((issue) => (
                                            <div
                                                key={issue.id}
                                                draggable={true}
                                                onDragStart={(event) => {
                                                    event.dataTransfer.effectAllowed = 'move';
                                                    event.dataTransfer.setData('text/plain', String(issue.id));
                                                    setDraggedIssueId(issue.id);
                                                }}
                                                onDragEnd={() => setDraggedIssueId(null)}
                                                className={`cursor-grab rounded-md border border-slate-200 bg-white p-3 shadow-sm transition-opacity dark:border-slate-700 dark:bg-slate-900 ${draggedIssueId === issue.id ? 'opacity-60' : ''}`}
                                            >
                                                <div className="mb-2 flex items-center justify-between gap-2">
                                                    <span className="max-w-[60%] break-words text-[10px] font-medium uppercase tracking-[0.18em] text-sky-600 dark:text-sky-400">
                                                        {issue.issue_key}
                                                    </span>
                                                    <span className="max-w-[40%] break-words rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] uppercase tracking-[0.12em] text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                        {issue.type}
                                                    </span>
                                                </div>
                                                <p className="text-sm font-medium text-slate-900 dark:text-white">{issue.title}</p>
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] uppercase tracking-[0.12em] text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                        {issuePriorityLabel(issue.priority)}
                                                    </span>
                                                    {issue.assignee_name ? (
                                                        <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] uppercase tracking-[0.12em] text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                            {issue.assignee_name}
                                                        </span>
                                                    ) : null}
                                                </div>
                                                <div className="mt-3">
                                                    <Link
                                                        href={`/projects/${project.id}/issues/${issue.id}`}
                                                        className="inline-flex items-center text-xs font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400 dark:hover:text-sky-300"
                                                    >
                                                        Open detail
                                                    </Link>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">Issues</p>
                            <h2 className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">Project issue list</h2>
                        </div>
                    </div>

                    {issues.length === 0 ? (
                        <p className="text-sm text-slate-600 dark:text-slate-300">No issues have been created yet.</p>
                    ) : (
                        <ul className="space-y-5">
                            {issues.map((issue) => (
                                <li key={issue.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p className="text-xs font-medium uppercase tracking-[0.18em] text-sky-600 dark:text-sky-400">{issue.issue_key}</p>
                                            <h3 className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{issue.title}</h3>
                                        </div>
                                        <Link
                                            href={`/projects/${project.id}/issues/${issue.id}`}
                                            className="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-700 transition-colors hover:border-sky-300 hover:bg-sky-100 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300 dark:hover:bg-sky-500/20"
                                        >
                                            Open detail
                                        </Link>
                                        <div className="flex flex-wrap gap-2 text-xs uppercase tracking-[0.12em] text-slate-600 dark:text-slate-300">
                                            <span className="rounded-full border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-900">{issueTypeLabel(issue.type)}</span>
                                            <span className="rounded-full border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-900">{issue.status}</span>
                                            <span className="rounded-full border border-slate-200 bg-white px-2 py-1 dark:border-slate-600 dark:bg-slate-900">{issuePriorityLabel(issue.priority)}</span>
                                        </div>
                                    </div>

                                    <p className="mb-4 text-sm text-slate-700 dark:text-slate-200">
                                        {issue.description || 'No description has been added for this issue.'}
                                    </p>

                                    <Form
                                        action={`/projects/${project.id}/issues/${issue.id}`}
                                        method="put"
                                        className="space-y-4"
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor={`issue-title-${issue.id}`}>Title</Label>
                                                        <Input id={`issue-title-${issue.id}`} name="title" defaultValue={issue.title} required />
                                                        <InputError message={errors.title} />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor={`issue-status-${issue.id}`}>Status</Label>
                                                        <select
                                                            id={`issue-status-${issue.id}`}
                                                            name="status"
                                                            defaultValue={issue.status}
                                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                        >
                                                            <option value="backlog">backlog</option>
                                                            <option value="todo">todo</option>
                                                            <option value="in_progress">in progress</option>
                                                            <option value="review">review</option>
                                                            <option value="done">done</option>
                                                        </select>
                                                        <InputError message={errors.status} />
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-3">
                                                    <div className="grid gap-2">
                                                        <Label htmlFor={`issue-type-${issue.id}`}>Type</Label>
                                                        <select
                                                            id={`issue-type-${issue.id}`}
                                                            name="type"
                                                            defaultValue={issue.type}
                                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                        >
                                                            <option value="bug">bug</option>
                                                            <option value="task">task</option>
                                                            <option value="story">story</option>
                                                            <option value="epic">epic</option>
                                                        </select>
                                                        <InputError message={errors.type} />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor={`issue-priority-${issue.id}`}>Priority</Label>
                                                        <select
                                                            id={`issue-priority-${issue.id}`}
                                                            name="priority"
                                                            defaultValue={issue.priority}
                                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                        >
                                                            <option value="low">low</option>
                                                            <option value="medium">medium</option>
                                                            <option value="high">high</option>
                                                            <option value="urgent">urgent</option>
                                                        </select>
                                                        <InputError message={errors.priority} />
                                                    </div>

                                                    <div className="grid gap-2">
                                                        <Label htmlFor={`issue-assignee-${issue.id}`}>Assignee</Label>
                                                        <select
                                                            id={`issue-assignee-${issue.id}`}
                                                            name="assignee_id"
                                                            defaultValue={issue.assignee_id ?? ''}
                                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                        >
                                                            <option value="">Unassigned</option>
                                                            {members.map((member) => (
                                                                <option key={member.id} value={member.user_id}>
                                                                    {member.name || member.email}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        <InputError message={errors.assignee_id} />
                                                    </div>
                                                </div>

                                                {labels.length > 0 && (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor={`issue-labels-${issue.id}`}>Labels</Label>
                                                        <select
                                                            id={`issue-labels-${issue.id}`}
                                                            name="labels[]"
                                                            defaultValue={issue.labels?.map((label) => String(label.id)) ?? []}
                                                            multiple
                                                            className="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                        >
                                                            {labels.map((label) => (
                                                                <option key={label.id} value={label.id}>
                                                                    {label.name}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        <InputError message={errors.labels} />
                                                    </div>
                                                )}

                                                <div className="grid gap-2">
                                                    <Label htmlFor={`issue-description-${issue.id}`}>Description</Label>
                                                    <textarea
                                                        id={`issue-description-${issue.id}`}
                                                        name="description"
                                                        rows={4}
                                                        defaultValue={issue.description ?? ''}
                                                        className="flex min-h-[120px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"
                                                    />
                                                    <InputError message={errors.description} />
                                                </div>

                                                <div className="flex justify-end gap-2">
                                                    <Button type="submit" size="sm" disabled={processing}>Save issue</Button>
                                                    <Form
                                                        action={`/projects/${project.id}/issues/${issue.id}`}
                                                        method="delete"
                                                        options={{ preserveScroll: true }}
                                                    >
                                                        {({ processing: deleting }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="destructive"
                                                                disabled={deleting}
                                                            >
                                                                Delete issue
                                                            </Button>
                                                        )}
                                                    </Form>
                                                </div>

                                                <div className="mt-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                                                    <div className="mb-3">
                                                        <h4 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Comments</h4>
                                                    </div>

                                                    {(issue.comments ?? []).length === 0 ? (
                                                        <p className="text-sm text-slate-600 dark:text-slate-300">No comments yet.</p>
                                                    ) : (
                                                        <ul className="space-y-3">
                                                            {(issue.comments ?? []).map((comment) => (
                                                                <li key={comment.id} className="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800">
                                                                    <div className="mb-1 flex items-center justify-between gap-2">
                                                                        <span className="text-sm font-medium text-slate-900 dark:text-white">
                                                                            {comment.user_name ?? 'Unknown user'}
                                                                        </span>
                                                                        {comment.created_at ? (
                                                                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                                                                {new Date(comment.created_at).toLocaleString()}
                                                                            </span>
                                                                        ) : null}
                                                                    </div>
                                                                    <p className="whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-200">
                                                                        {comment.body}
                                                                    </p>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    )}

                                                    <Form
                                                        action={`/projects/${project.id}/issues/${issue.id}/comments`}
                                                        method="post"
                                                        className="mt-4 space-y-3"
                                                        options={{ preserveScroll: true }}
                                                    >
                                                        {({ processing, errors }) => (
                                                            <>
                                                                <div className="grid gap-2">
                                                                    <Label htmlFor={`comment-body-${issue.id}`}>Add comment</Label>
                                                                    <textarea
                                                                        id={`comment-body-${issue.id}`}
                                                                        name="body"
                                                                        rows={3}
                                                                        placeholder="Share an update or next step..."
                                                                        className="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"
                                                                        required
                                                                    />
                                                                    <InputError message={errors.body} />
                                                                </div>
                                                                <div className="flex justify-end">
                                                                    <Button type="submit" size="sm" disabled={processing}>Add comment</Button>
                                                                </div>
                                                            </>
                                                        )}
                                                    </Form>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {canManageProject ? (
                    <>
                        <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="mb-4">
                                <p className="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">
                                    {project.settings_summary ?? 'Project settings'}
                                </p>
                                <h2 className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">Edit project</h2>
                            </div>
                            <Form
                                {...ProjectController.update.form({ project: project.id })}
                                method="put"
                                className="mt-6 space-y-6"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Project name</Label>
                                            <Input id="name" name="name" defaultValue={project.name} required />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="description">Description</Label>
                                            <textarea
                                                id="description"
                                                name="description"
                                                defaultValue={project.description ?? ''}
                                                rows={4}
                                                className="flex min-h-[80px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"
                                            />
                                            <InputError message={errors.description} />
                                        </div>

                                        <Button disabled={processing}>Save changes</Button>
                                    </>
                                )}
                            </Form>
                        </div>

                        <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium uppercase tracking-[0.2em] text-slate-500">Labels</p>
                                    <h2 className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">Manage labels</h2>
                                </div>
                            </div>

                            <Form
                                action={`/projects/${project.id}/labels`}
                                method="post"
                                className="mb-6 space-y-4"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing, errors }) => (
                                    <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                                        <div className="grid gap-2">
                                            <Label htmlFor="label-name">Label name</Label>
                                            <Input id="label-name" name="name" placeholder="frontend" required />
                                            <InputError message={errors.name} />
                                        </div>

                                        <Button type="submit" disabled={processing}>Add label</Button>
                                    </div>
                                )}
                            </Form>

                            {labels.length === 0 ? (
                                <p className="text-sm text-slate-600 dark:text-slate-300">No labels created yet.</p>
                            ) : (
                                <ul className="space-y-3">
                                    {labels.map((label) => (
                                        <li key={label.id} className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70 sm:flex-row sm:items-center sm:justify-between">
                                            <span className="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium uppercase tracking-[0.12em] text-sky-700 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300">
                                                {label.name}
                                            </span>

                                            <div className="flex items-center gap-2">
                                                <Form
                                                    action={`/projects/${project.id}/labels/${label.id}`}
                                                    method="put"
                                                    options={{ preserveScroll: true }}
                                                    className="flex items-center gap-2"
                                                >
                                                    {({ processing, errors }) => (
                                                        <>
                                                            <label className="sr-only" htmlFor={`label-name-${label.id}`}>
                                                                Rename {label.name}
                                                            </label>
                                                            <Input
                                                                id={`label-name-${label.id}`}
                                                                name="name"
                                                                defaultValue={label.name}
                                                                className="w-36"
                                                                required
                                                            />
                                                            <Button type="submit" size="sm" variant="secondary" disabled={processing}>
                                                                Rename
                                                            </Button>
                                                            {errors.name ? <span className="text-xs text-red-500">{errors.name}</span> : null}
                                                        </>
                                                    )}
                                                </Form>

                                                <Form
                                                    action={`/projects/${project.id}/labels/${label.id}`}
                                                    method="delete"
                                                    options={{ preserveScroll: true }}
                                                >
                                                    {({ processing }) => (
                                                        <Button type="submit" size="sm" variant="outline" disabled={processing}>
                                                            Delete
                                                        </Button>
                                                    )}
                                                </Form>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </>
                ) : null}

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-xl font-semibold text-slate-900 dark:text-white">{project.members_label ?? 'Members'}</h2>
                    </div>

                    {canManageProject ? (
                        <Form
                            {...ProjectMemberController.store.form({ project: project.id })}
                            method="post"
                            className="mb-6 space-y-4"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px_auto] md:items-end">
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Member email</Label>
                                        <Input id="email" name="email" type="email" placeholder="member@example.com" required />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role</Label>
                                        <select
                                            id="role"
                                            name="role"
                                            defaultValue="member"
                                            className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                        >
                                            <option value="member">Member</option>
                                            <option value="owner">Owner</option>
                                        </select>
                                        <InputError message={errors.role} />
                                    </div>

                                    <Button type="submit" disabled={processing}>Add member</Button>
                                </div>
                            )}
                        </Form>
                    ) : null}

                    {members.length === 0 ? (
                        <p className="text-sm text-slate-600 dark:text-slate-300">No team members have been added yet.</p>
                    ) : (
                        <ul className="space-y-3">
                            {members.map((member) => (
                                <li key={member.id} className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p className="font-medium text-slate-900 dark:text-white">{member.name || 'Unknown user'}</p>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">{member.email || 'No email available'}</p>
                                    </div>

                                    {member.user_id === project.owner.id ? (
                                        <span className="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs uppercase tracking-[0.18em] text-amber-600 dark:text-amber-300">
                                            Owner
                                        </span>
                                    ) : canManageProject ? (
                                        <div className="flex items-center gap-2">
                                            <Form
                                                {...ProjectMemberController.update.form({
                                                    project: project.id,
                                                    projectMember: member.id,
                                                })}
                                                method="put"
                                                options={{ preserveScroll: true }}
                                                className="flex items-center gap-2"
                                            >
                                                {({ processing, errors }) => (
                                                    <>
                                                        <label className="sr-only" htmlFor={`role-${member.id}`}>
                                                            Role for {member.name || 'member'}
                                                        </label>
                                                        <select
                                                            id={`role-${member.id}`}
                                                            name="role"
                                                            defaultValue={member.role}
                                                            className="rounded-md border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                                        >
                                                            <option value="member">member</option>
                                                            <option value="owner">owner</option>
                                                        </select>
                                                        <Button type="submit" size="sm" variant="secondary" disabled={processing}>
                                                            Update role
                                                        </Button>
                                                        {errors.role ? <span className="text-xs text-red-500">{errors.role}</span> : null}
                                                    </>
                                                )}
                                            </Form>

                                            <Form
                                                {...ProjectMemberController.destroy.form({
                                                    project: project.id,
                                                    projectMember: member.id,
                                                })}
                                                method="delete"
                                                options={{ preserveScroll: true }}
                                            >
                                                {({ processing }) => (
                                                    <Button type="submit" size="sm" variant="outline" disabled={processing}>
                                                        Remove
                                                    </Button>
                                                )}
                                            </Form>
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

ProjectShow.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Projects',
            href: projects.index(),
        },
        {
            title: 'Project',
            href: projects.show({ project: 0 }),
        },
    ],
};
