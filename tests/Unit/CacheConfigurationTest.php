<?php

test('application cache configuration uses redis by default', function () {
    expect(config('cache.default'))->toBe('redis');
    expect(config('queue.default'))->toBe('redis');
});
