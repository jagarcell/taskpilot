<?php

namespace App\Services\Providers;

use App\Contracts\AgentProvider;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Http;

class CopilotAgentProvider implements AgentProvider
{
    /**
     * Execute the Copilot provider for a given agent run and return a normalized output payload.
     *
     * @param  AgentRun  $agentRun
     * @return array<string, mixed>
     * Logic: translate a TaskPilot agent run into a Copilot-compatible request, then normalize the vendor response into the TaskPilot contract so the workflow layer stays provider-agnostic.
     */
    public function execute(AgentRun $agentRun): array
    {
        $token = config('services.copilot.token');
        $model = (string) config('services.copilot.model', 'gpt-4o');

        if (blank($token)) {
            return $this->fallbackToOpenAi($agentRun, $model);
        }

        $prompt = is_array($agentRun->input) ? ($agentRun->input['prompt'] ?? 'No prompt provided.') : 'No prompt provided.';
        $endpoint = rtrim((string) config('services.copilot.base_uri', 'https://api.githubcopilot.com'), '/');

        logger()->info('Copilot provider request', [
            'provider' => 'copilot',
            'model' => $model,
            'prompt' => $prompt,
            'endpoint' => $endpoint . '/chat/completions',
            'temperature' => 0.2,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.copilot.timeout', 30))
            ->post($endpoint . '/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an assistant for TaskPilot issue analysis and implementation planning. Return concise, structured output that matches the issue workflow.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.2,
            ]);

        if ($response->failed()) {
            return [
                'provider' => 'copilot',
                'model' => $model,
                'status' => 'failed',
                'summary' => 'Copilot request failed during execution.',
                'errors' => [
                    'message' => $response->json('message', 'Request failed.'),
                    'status' => $response->status(),
                ],
            ];
        }

        $content = $response->json('choices.0.message.content', 'No response provided by Copilot.');
        $normalized = $this->normalizeResponse($content);

        logger()->info('Copilot provider response', [
            'provider' => 'copilot',
            'model' => $model,
            'status' => $normalized['status'] ?? 'completed',
            'summary' => $normalized['summary'] ?? $this->extractSummary($content),
            'content' => $this->sanitizeContent($normalized['content'] ?? $content),
        ]);

        return [
            'provider' => 'copilot',
            'model' => $model,
            'status' => $normalized['status'] ?? 'completed',
            'summary' => $normalized['summary'] ?? $this->extractSummary($content),
            'content' => $normalized['content'] ?? $this->sanitizeContent($content),
        ];
    }

    /**
     * Fall back to the existing OpenAI-backed implementation when Copilot credentials are missing.
     *
     * @param  AgentRun  $agentRun
     * @param  string  $model
     * @return array<string, mixed>
     * Logic: keep local and unconfigured environments functional while preserving the Copilot-first provider contract when secrets are absent.
     */
    protected function fallbackToOpenAi(AgentRun $agentRun, string $model): array
    {
        $provider = new OpenAiAgentProvider();
        $payload = $provider->execute($agentRun);

        return [
            'provider' => 'copilot',
            'model' => $model,
            'summary' => $payload['summary'] ?? 'Copilot provider is not configured; fallback analysis was used.',
            'analysis' => $payload['analysis'] ?? null,
            'plan' => $payload['plan'] ?? null,
            'implementation' => $payload['implementation'] ?? null,
            'testing' => $payload['testing'] ?? null,
            'review' => $payload['review'] ?? null,
        ];
    }

    /**
     * Normalize a raw Copilot payload into the TaskPilot run contract.
     *
     * @param  mixed  $content
     * @return array<string, mixed>
     * Logic: decode the provider payload into a stable task result so the execution service can persist a predictable summary and structured content without leaking raw provider details.
     */
    protected function normalizeResponse(mixed $content): array
    {
        $decoded = $this->decodeContent($content);

        if (! is_array($decoded)) {
            return [
                'status' => 'completed',
                'summary' => $this->extractSummary($decoded),
                'content' => $this->sanitizeContent($decoded),
            ];
        }

        $status = is_string($decoded['status'] ?? null) ? strtolower((string) $decoded['status']) : 'completed';
        $summary = is_string($decoded['summary'] ?? null) ? $decoded['summary'] : $this->extractSummary($decoded);
        $sanitized = $this->sanitizeContent($decoded);

        return [
            'status' => $status,
            'summary' => $summary,
            'content' => $sanitized,
        ];
    }

    /**
     * Decode raw provider content into a structured array when the vendor returns JSON.
     *
     * @param  mixed  $content
     * @return mixed
     * Logic: accept both plain strings and structured JSON so the provider can support both generic and structured vendor payloads.
     */
    protected function decodeContent(mixed $content): mixed
    {
        if (is_array($content)) {
            return $content;
        }

        $decoded = json_decode((string) $content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
    }

    /**
     * Remove provider-internal keys before returning content to the TaskPilot workflow.
     *
     * @param  mixed  $content
     * @return array<string, mixed>|string
     * Logic: strip internal provider metadata while preserving the user-facing domain payload that must survive in issue history.
     */
    protected function sanitizeContent(mixed $content): array|string
    {
        if (! is_array($content)) {
            return $this->extractSummary($content);
        }

        $sanitized = $content;
        unset($sanitized['token'], $sanitized['api_key'], $sanitized['secret'], $sanitized['raw_response']);

        return $sanitized;
    }

    /**
     * Normalize a provider response into a compact summary string.
     *
     * @param  mixed  $content
     * @return string
     * Logic: avoid exposing verbose provider output in the TaskPilot run history while preserving enough context for status reporting.
     */
    protected function extractSummary(mixed $content): string
    {
        if (is_array($content)) {
            $encoded = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $content = $encoded === false ? '' : $encoded;
        }

        $normalized = trim((string) $content);

        if ($normalized === '') {
            return 'Copilot completed without returning a summary.';
        }

        return preg_replace('/\s+/', ' ', $normalized) ?: $normalized;
    }
}
