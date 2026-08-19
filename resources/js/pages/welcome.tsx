import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="TaskPilot" />
            <div className="flex min-h-screen flex-col items-center bg-slate-950 p-6 text-slate-100 lg:justify-center lg:p-8">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-slate-600 px-5 py-1.5 text-sm leading-normal text-white hover:border-sky-400"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-slate-200 hover:border-slate-500"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-block rounded-sm border border-sky-500 bg-sky-500 px-5 py-1.5 text-sm leading-normal text-white hover:border-sky-400 hover:bg-sky-400"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <div className="flex w-full items-center justify-center lg:grow">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-5xl lg:flex-row">
                        <div className="flex-1 rounded-br-lg rounded-bl-lg bg-slate-900 p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(148,163,184,0.28)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20">
                            <div className="mb-4 inline-flex rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-[11px] font-medium uppercase tracking-[0.18em] text-sky-300">
                                Phase 1 foundation
                            </div>
                            <h1 className="mb-3 text-3xl font-semibold tracking-tight text-white">
                                Ship work with clarity.
                            </h1>
                            <p className="mb-6 max-w-xl text-slate-300">
                                TaskPilot brings project work, issue tracking, and future agent workflows into one observable system for product and engineering teams.
                            </p>
                            <ul className="mb-6 space-y-4 text-slate-200">
                                <li className="flex items-start gap-3">
                                    <span className="mt-1 inline-block h-2.5 w-2.5 rounded-full bg-sky-400" />
                                    <span>Authenticated access with a clean React + Laravel foundation.</span>
                                </li>
                                <li className="flex items-start gap-3">
                                    <span className="mt-1 inline-block h-2.5 w-2.5 rounded-full bg-violet-400" />
                                    <span>MySQL and Redis defaults ready for project workflows and queue-backed automation.</span>
                                </li>
                                <li className="flex items-start gap-3">
                                    <span className="mt-1 inline-block h-2.5 w-2.5 rounded-full bg-emerald-400" />
                                    <span>Responsive app shell built to scale into issue tracking, Kanban, and agent execution.</span>
                                </li>
                            </ul>
                            <div className="flex gap-3 text-sm leading-normal">
                                <Link
                                    href={register()}
                                    className="inline-block rounded-sm border border-sky-500 bg-sky-500 px-5 py-2 text-sm leading-normal text-white hover:border-sky-400 hover:bg-sky-400"
                                >
                                    Create account
                                </Link>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-sm border border-slate-600 bg-slate-800 px-5 py-2 text-sm leading-normal text-slate-100 hover:border-slate-500"
                                >
                                    Sign in
                                </Link>
                            </div>
                        </div>

                        <div className="relative -mb-px aspect-[335/364] w-full shrink-0 overflow-hidden rounded-t-lg bg-slate-900/80 p-6 lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[500px] lg:rounded-t-none lg:rounded-r-lg">
                            <div className="flex h-full flex-col justify-center rounded-xl border border-slate-700 bg-slate-950/70 p-6 shadow-2xl shadow-sky-950/30">
                                <div className="mb-6 flex items-center justify-between">
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.22em] text-slate-400">Overview</p>
                                        <h2 className="mt-2 text-2xl font-semibold text-white">TaskPilot</h2>
                                    </div>
                                    <span className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300">
                                        Live
                                    </span>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="rounded-xl border border-slate-800 bg-slate-900 p-4">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Projects</p>
                                        <p className="mt-3 text-3xl font-semibold text-white">12</p>
                                    </div>
                                    <div className="rounded-xl border border-slate-800 bg-slate-900 p-4">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Issues</p>
                                        <p className="mt-3 text-3xl font-semibold text-white">184</p>
                                    </div>
                                    <div className="rounded-xl border border-slate-800 bg-slate-900 p-4 sm:col-span-2">
                                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Workflow health</p>
                                        <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-800">
                                            <div className="h-full w-[78%] rounded-full bg-gradient-to-r from-sky-400 to-violet-400" />
                                        </div>
                                        <p className="mt-3 text-sm text-slate-300">78% on-track delivery</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
