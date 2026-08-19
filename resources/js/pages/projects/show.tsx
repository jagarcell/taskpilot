import { Form, Head } from '@inertiajs/react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
        owner: ProjectOwner;
        created_at?: string | null;
    };
    members: ProjectMember[];
}

export default function ProjectShow({ project, members }: ProjectPageProps) {
    return (
        <>
            <Head title={project.name} />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
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
                            <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Owner</h2>
                            <p className="mt-3 text-slate-700 dark:text-slate-200">{project.owner.name}</p>
                            <p className="text-sm text-slate-500 dark:text-slate-400">{project.owner.email}</p>
                        </div>
                    </div>
                </div>

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Edit project</h2>
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
                        <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Members</h2>
                    </div>

                    {members.length === 0 ? (
                        <p className="text-sm text-slate-600 dark:text-slate-300">No team members have been added yet.</p>
                    ) : (
                        <ul className="space-y-3">
                            {members.map((member) => (
                                <li key={member.id} className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/70">
                                    <div>
                                        <p className="font-medium text-slate-900 dark:text-white">{member.name || 'Unknown user'}</p>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">{member.email || 'No email available'}</p>
                                    </div>
                                    <span className="rounded-full border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-xs uppercase tracking-[0.18em] text-sky-600 dark:text-sky-300">
                                        {member.role}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}
