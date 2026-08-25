import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';

interface AgentRecord {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    provider: string;
    model: string;
    is_active: boolean;
}

export default function Dashboard({ agents = [] }: { agents?: AgentRecord[] }) {
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
                                        <input id="name" name="name" required className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <label htmlFor="provider" className="text-sm font-medium text-slate-700 dark:text-slate-200">Provider</label>
                                        <input id="provider" name="provider" defaultValue="openai" className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" />
                                        <InputError message={errors.provider} />
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <label htmlFor="model" className="text-sm font-medium text-slate-700 dark:text-slate-200">Model</label>
                                        <input id="model" name="model" defaultValue="gpt-4o-mini" className="flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" />
                                        <InputError message={errors.model} />
                                    </div>
                                    <div className="grid gap-2">
                                        <label htmlFor="is_active" className="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                                        <label className="flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                            <input type="checkbox" name="is_active" value="1" defaultChecked />
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

                                <div className="flex justify-end">
                                    <button type="submit" disabled={processing} className="inline-flex items-center rounded-md bg-sky-600 px-3 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                                        {processing ? 'Saving...' : 'Create agent'}
                                    </button>
                                </div>
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
