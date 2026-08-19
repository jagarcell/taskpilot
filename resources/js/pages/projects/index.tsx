import { Head } from '@inertiajs/react';

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
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-[0.2em] text-sky-600">Projects</p>
                        <h1 className="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">Your projects</h1>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {projects.length === 0 ? (
                        <p className="text-sm text-slate-600 dark:text-slate-300">No projects yet. Create your first project to get started.</p>
                    ) : (
                        <ul className="space-y-4">
                            {projects.map((project) => (
                                <li key={project.id} className="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">{project.name}</h2>
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
