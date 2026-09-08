<?php

namespace App\Http\Controllers;

use App\Services\ProviderConnectionTesterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderConnectionTestController extends Controller
{
    /**
     * Run a provider connectivity test using the selected API backend.
     *
     * @param  Request  $request
     * @return JsonResponse
     * Logic: accept the active provider name, resolve the provider-specific tester behind the abstraction, and return a normalized result payload for the dashboard UI.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $provider = (string) $request->input('provider', 'openai');
        $tester = (new ProviderConnectionTesterFactory())->resolve($provider);

        return response()->json($tester->testConnection());
    }

    /**
     * Trigger the selected provider's reauthentication flow.
     *
     * @param  Request  $request
     * @return JsonResponse
     * Logic: ask the provider-specific connection tester for its reconnect action so the dashboard can remain provider-agnostic and future integrations can plug in a new OAuth flow without UI changes.
     */
    public function reauth(Request $request): JsonResponse
    {
        $provider = (string) $request->input('provider', 'openai');
        $tester = (new ProviderConnectionTesterFactory())->resolve($provider);

        return response()->json($tester->reauthAction());
    }
}
