<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the authenticated dashboard with the current agent catalog.
     *
     * @return Response
     * Logic: provide the dashboard page with the list of agent definitions so users can manage activation and metadata.
     */
    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'agents' => Agent::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Agent $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'slug' => $agent->slug,
                    'description' => $agent->description,
                    'provider' => $agent->provider,
                    'model' => $agent->model,
                    'is_active' => (bool) $agent->is_active,
                ])->all(),
        ]);
    }
}
