<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class HealthController extends Controller
{
    /**
     * Return the application health status for API consumers.
     *
     * @return JsonResponse
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     * Logic: report the service health in a simple JSON envelope so clients can verify the API is reachable.
     */
    public function index(): JsonResponse
    {
        return Response::json(['status' => 'ok']);
    }
}
