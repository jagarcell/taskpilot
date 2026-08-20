<?php

use App\Enums\IssuePriority;

test('issue priority labels are human readable and stable', function () {
    expect(IssuePriority::LOW->label())->toBe('Low')
        ->and(IssuePriority::MEDIUM->label())->toBe('Medium')
        ->and(IssuePriority::HIGH->label())->toBe('High')
        ->and(IssuePriority::URGENT->label())->toBe('Urgent');
});

test('issue priority values are the canonical project domain values', function () {
    expect(IssuePriority::cases())->toHaveCount(4)
        ->and(IssuePriority::cases())->toMatchArray([
            IssuePriority::LOW,
            IssuePriority::MEDIUM,
            IssuePriority::HIGH,
            IssuePriority::URGENT,
        ]);
});
