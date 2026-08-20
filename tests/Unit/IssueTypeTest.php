<?php

use App\Enums\IssueType;

test('issue type labels are human readable and stable', function () {
    expect(IssueType::BUG->label())->toBe('Bug')
        ->and(IssueType::TASK->label())->toBe('Task')
        ->and(IssueType::STORY->label())->toBe('Story')
        ->and(IssueType::EPIC->label())->toBe('Epic');
});

test('issue type values are the canonical project domain values', function () {
    expect(IssueType::cases())->toHaveCount(4)
        ->and(IssueType::cases())->toMatchArray([
            IssueType::BUG,
            IssueType::TASK,
            IssueType::STORY,
            IssueType::EPIC,
        ]);
});
