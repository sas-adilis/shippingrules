<?php
it('cleans a postcode list', function (?string $input, array $expected) {
    expect(array_values(ShippingRules::getCleanedPostcodeList($input)))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty' => ['', []],
    'trims and drops empties' => [' 75001 , ,75002,', ['75001', '75002']],
    'removes duplicates' => ['75001,75001,75002', ['75001', '75002']],
    'keeps alphanumeric codes' => ['SW1A 1AA, EC1A 1BB', ['SW1A 1AA', 'EC1A 1BB']],
]);

it('normalizes a postcode for comparison', function (string $input, string $expected) {
    $method = new ReflectionMethod(ShippingRules::class, 'normalizePostcode');
    $method->setAccessible(true);

    expect($method->invoke(null, $input))->toBe($expected);
})->with([
    'spaces' => ['75 001', '75001'],
    'case' => ['sw1a 1aa', 'SW1A1AA'],
    'surrounding blanks' => ['  75001 ', '75001'],
]);
