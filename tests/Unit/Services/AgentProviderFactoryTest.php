<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Services\AgentProviderFactory;
use App\Services\Providers\CopilotAgentProvider;
use App\Services\Providers\OpenAiAgentProvider;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

it('supports the openai provider contract and normalizes it case-insensitively', function () {
    expect(AgentProviderFactory::supportedProviders())->toContain('openai');
    expect(AgentProviderFactory::normalizeProvider('OpenAI'))->toBe('openai');
    expect(AgentProviderFactory::normalizeProvider(null))->toBe('openai');

    $factory = new AgentProviderFactory();
    expect($factory->resolve('OPENAI'))->toBeInstanceOf(OpenAiAgentProvider::class);
});

it('supports the copilot provider contract and normalizes it case-insensitively', function () {
    expect(AgentProviderFactory::supportedProviders())->toContain('copilot');
    expect(AgentProviderFactory::normalizeProvider('Copilot'))->toBe('copilot');

    $factory = new AgentProviderFactory();
    expect($factory->resolve('COPILOT'))->toBeInstanceOf(CopilotAgentProvider::class);
});

it('exposes copilot credentials and provider settings only through the server-side config contract', function () {
    config()->set('services.copilot', [
        'token' => 'server-side-token',
        'base_uri' => 'https://api.githubcopilot.com',
        'model' => 'gpt-4o-mini',
        'timeout' => 30,
    ]);

    expect(config('services.copilot.token'))->toBe('server-side-token');
    expect(config('services.copilot.base_uri'))->toBe('https://api.githubcopilot.com');
    expect(config('services.copilot.model'))->toBe('gpt-4o-mini');
    expect(config('services.copilot.timeout'))->toBe(30);
});

it('prefers the agent run model over the global Copilot default', function () {
    config()->set('services.copilot.model', 'gpt-4o');

    $agent = Agent::factory()->create(['name' => 'Issue Analyzer', 'provider' => 'copilot', 'model' => 'gpt-4o-mini']);
    $issue = \App\Models\Issue::factory()->create();
    $user = \App\Models\User::factory()->create();

    Http::fake([
        'https://api.githubcopilot.com/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode(['summary' => '### Issue Analysis'])],
            ]],
        ]),
    ]);

    $agentRun = AgentRun::factory()->create([
        'agent_id' => $agent->id,
        'issue_id' => $issue->id,
        'user_id' => $user->id,
        'provider' => 'copilot',
        'model' => 'gpt-4o-mini',
        'input' => ['prompt' => 'Analyze this issue.'],
    ]);

    $provider = (new AgentProviderFactory())->resolve('copilot');
    $result = $provider->execute($agentRun);

    expect($result['model'])->toBe('gpt-4o-mini');
    expect($result['summary'])->toContain('Issue Analysis');
});

it('generates issue-aware analysis and planning output instead of stale placeholder text', function () {
    $provider = new OpenAiAgentProvider();
    $issuePrompt = 'Checkout totals are wrong when a second item is added to the cart.';

    $analysisAgent = Agent::factory()->create(['name' => 'Issue Analyzer']);
    $analysisRun = AgentRun::factory()->create([
        'agent_id' => $analysisAgent->id,
        'input' => ['prompt' => $issuePrompt],
    ]);

    $analysis = $provider->execute($analysisRun);

    expect($analysis['summary'])->toContain('Checkout totals are wrong')
        ->and($analysis['summary'])->not->toContain('The issue appears to involve')
        ->and($analysis['analysis']['likely_causes'])->toContain('subtotal recalculation bug');

    $planningAgent = Agent::factory()->create(['name' => 'Planning Agent']);
    $planningRun = AgentRun::factory()->create([
        'agent_id' => $planningAgent->id,
        'input' => ['prompt' => $issuePrompt],
    ]);

    $plan = $provider->execute($planningRun);

    expect($plan['summary'])->toContain('Checkout totals are wrong')
        ->and($plan['summary'])->not->toContain('The main work should focus on the relevant calculation or workflow path')
        ->and($plan['plan']['files_likely_affected'])->toContain('checkout totals component');
});

it('throws for unsupported providers', function () {
    $factory = new AgentProviderFactory();

    $factory->resolve('anthropic');
})->throws(InvalidArgumentException::class, 'Unsupported agent provider: anthropic.');
