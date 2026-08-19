import { Head } from '@inertiajs/react';
import { dashboard } from '@/routes';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Active projects</p>
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
