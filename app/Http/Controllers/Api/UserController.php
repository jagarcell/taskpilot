<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class UserController extends Controller
{
    /**
     * Return the authenticated user's profile information for API consumers.
     *
     * @return JsonResponse
     * Logic: expose the current user profile in a stable payload for authenticated API clients.
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();

        return Response::json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
