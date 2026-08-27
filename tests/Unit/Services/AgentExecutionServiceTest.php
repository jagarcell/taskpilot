<?php

namespace Tests\Unit\Services;

use App\Services\AgentExecutionService;

it('ignores the QA workflow failure toggle while running automated tests', function () {
    putenv('WORKFLOW_FORCE_FAILURE=true');
    $_ENV['WORKFLOW_FORCE_FAILURE'] = 'true';
    $_SERVER['WORKFLOW_FORCE_FAILURE'] = 'true';

    $service = app(AgentExecutionService::class);

    expect(app()->environment())->toBe('testing')
        ->and($service->shouldForceWorkflowFailure())->toBeFalse();

    putenv('WORKFLOW_FORCE_FAILURE=false');
    $_ENV['WORKFLOW_FORCE_FAILURE'] = 'false';
    $_SERVER['WORKFLOW_FORCE_FAILURE'] = 'false';
});
