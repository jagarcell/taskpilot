import { Form, Head, Link } from '@inertiajs/react';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';

interface Project {
    id: number;
    name: string;
    description?: string | null;
    created_at?: string | null;
}

interface ProjectsIndexProps {
    projects: Project[];
}

export default function ProjectsIndex({ projects }: ProjectsIndexProps) {
    return (
        <>
            <Head title="Projects" />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-[0.2em] text-sky-600">Projects</p>
                        <h1 className="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">Your projects</h1>
                    </div>
                    <Link
                        href={dashboard()}
                        className="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-sky-200 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        Back to dashboard
                    </Link>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-6">
                        <h2 className="text-sm font-medium uppercase tracking-[0.18em] text-slate-500">Create project</h2>
                        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">Create a new project to get started.</p>
                    </div>

                    <Form
                        {...ProjectController.store.form()}
                        method="post"
                        className="mb-6 space-y-4"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Project name</Label>
                                    <Input id="name" name="name" required />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">Description</Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={3}
                                        className="flex min-h-[60px] w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <Button disabled={processing}>Create project</Button>
                            </>
                        )}
                    </Form>

                    {projects.length === 0 ? (
                        <p className="text-sm text-slate-600 dark:text-slate-300">No projects yet. Create your first project to get started.</p>
                    ) : (
                        <ul className="space-y-4">
                            {projects.map((project) => (
                                <li key={project.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <Link href={`/projects/${project.id}`} className="text-lg font-semibold text-slate-900 hover:text-sky-600 dark:text-white dark:hover:text-sky-400">
                                                {project.name}
                                            </Link>
                                            {project.description ? (
                                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{project.description}</p>
                                            ) : (
                                                <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">No description provided.</p>
                                            )}
                                        </div>
                                        {project.created_at ? (
                                            <span className="text-xs uppercase tracking-[0.18em] text-slate-400">{project.created_at}</span>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Projects',
            href: projects.index(),
        },
    ],
};
