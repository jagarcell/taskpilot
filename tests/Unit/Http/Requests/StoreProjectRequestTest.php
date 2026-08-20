<?php

use App\Http\Requests\StoreProjectRequest;

test('project store request validates the expected fields', function () {
    $request = new StoreProjectRequest();

    expect(method_exists($request, 'rules'))->toBeTrue()
        ->and($request->rules())->toMatchArray([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
});
