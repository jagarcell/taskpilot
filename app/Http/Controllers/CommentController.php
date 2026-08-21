<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Issue;
use App\Models\Project;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function __construct(
        protected CommentService $commentService,
    ) {}

    /**
     * Store a comment on an issue.
     *
     * @param  StoreCommentRequest  $request
     * @param  Project  $project
     * @param  Issue  $issue
     * @return RedirectResponse
     * Logic: validate the comment input and persist it only when the current user belongs to the issue's project.
     */
    public function store(StoreCommentRequest $request, Project $project, Issue $issue): RedirectResponse
    {
        $validated = $request->validated();

        $this->commentService->createComment($project, $issue, Auth::user(), $validated);

        return redirect()->route('projects.show', $project);
    }
}
