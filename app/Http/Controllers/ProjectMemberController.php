<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectMemberController extends Controller
{
    /**
     * Add a user to a project as a member.
     *
     * @param  Request  $request
     * @param  Project  $project
     * @return RedirectResponse
     * Logic: allow the project owner to invite a user by email and store the membership.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->owner_id === Auth::id(), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['nullable', 'string', 'in:member,owner'],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        $project->members()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => $validated['role'] ?? 'member'],
        );

        return redirect()->route('projects.index');
    }
}
