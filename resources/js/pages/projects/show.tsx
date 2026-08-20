import { Form, Head, Link } from '@inertiajs/react';
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
    };
    members: ProjectMember[];
}

export default function ProjectShow({ project, members }: ProjectPageProps) {
    const canManageProject = project.can_manage_project ?? false;

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

                                <div className="grid gap-4 md:grid-cols-3">
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
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>Create issue</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                {canManageProject ? (
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
