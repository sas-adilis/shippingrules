<?php
function makeController(): AdminShippingRulesController
{
    $controller = (new ReflectionClass(AdminShippingRulesController::class))->newInstanceWithoutConstructor();
    $controller->module = (object) ['name' => 'shippingrules'];

    return $controller;
}

/**
 * @param array<string, mixed> $values posted values (override a valid "free shipping" rule)
 *
 * @return string[] validation errors
 */
function validate(array $values): array
{
    Tools::$values = array_merge([
        'rule_type' => ShippingRulesClass::RULE_TYPE_FREE,
        'minimum_amount' => '0',
        'maximum_amount' => '0',
        'minimum_amount_tax' => '0',
        'maximum_amount_tax' => '0',
        'minimum_amount_currency' => '1',
        'maximum_amount_currency' => '1',
        'minimum_weight' => '0',
        'maximum_weight' => '0',
        'from' => '2026-01-01 00:00:00',
        'to' => '2026-12-31 00:00:00',
        'id_customer' => '0',
    ], $values);

    $controller = makeController();
    $controller->_childValidation();

    return $controller->errors;
}

it('accepts a valid rule', function () {
    expect(validate([]))->toBe([]);
});

it('rejects inconsistent bounds', function (array $values, string $error) {
    expect(validate($values))->toBe([$error]);
})->with([
    'amounts' => [['minimum_amount' => '50', 'maximum_amount' => '10'], 'The minimum amount must be less than the maximum amount.'],
    'weights' => [['minimum_weight' => '5', 'maximum_weight' => '1'], 'The minimum weight must be less than the maximum weight.'],
    'dates' => [['from' => '2026-12-31 00:00:00', 'to' => '2026-01-01 00:00:00'], 'The start date must be less than the end date.'],
    'tax' => [['minimum_amount_tax' => '0', 'maximum_amount_tax' => '1'], 'The minimum amount tax must be equal to the maximum amount tax.'],
    'currency' => [['maximum_amount_currency' => '2'], 'The minimum amount currency must be equal to the maximum amount currency.'],
]);

it('accepts a maximum of 0 as no limit', function () {
    expect(validate(['minimum_amount' => '50', 'maximum_amount' => '0', 'minimum_weight' => '3', 'maximum_weight' => '0']))->toBe([]);
});

it('requires a valid value for the rule type', function (int $type, array $values, ?string $error) {
    expect(validate(array_merge(['rule_type' => $type], $values)))->toBe($error ? [$error] : []);
})->with([
    'additional amount' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL, ['impact_amount' => '2.5'], null],
    'additional amount, zero' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL, ['impact_amount' => '0'], 'The impact amount is incorrect.'],
    'reduction amount, invalid' => [ShippingRulesClass::RULE_TYPE_REDUCTION, ['impact_amount' => 'abc'], 'The impact amount is incorrect.'],
    'percent' => [ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT, ['impact_percent' => '15'], null],
    'percent over 100' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT, ['impact_percent' => '150'], 'The impact percent is incorrect.'],
    'new amount' => [ShippingRulesClass::RULE_TYPE_SET_AMOUNT, ['new_amount' => '4'], null],
    'new amount, missing' => [ShippingRulesClass::RULE_TYPE_SET_AMOUNT, [], 'The new amount is incorrect.'],
    'disable needs no value' => [ShippingRulesClass::RULE_TYPE_DISABLE, [], null],
]);

it('rejects an inconsistent quantity range', function () {
    expect(validate(['minimum_quantity' => '5', 'maximum_quantity' => '2']))->toBe(['The minimum quantity must be less than the maximum quantity.']);
    expect(validate(['minimum_quantity' => '5', 'maximum_quantity' => '0']))->toBe([]);
});

it('validates the hours', function (string $from, string $to, array $errors) {
    expect(validate(['hour_from' => $from, 'hour_to' => $to]))->toBe($errors);
})->with([
    'none' => ['', '', []],
    'valid' => ['09:00', '17:30', []],
    'overnight' => ['22:00', '06:00', []],
    'only one' => ['09:00', '', ['Both hours are required and must be in the HH:MM format.']],
    'bad format' => ['9h', '17:00', ['Both hours are required and must be in the HH:MM format.']],
    'out of range' => ['09:00', '24:00', ['Both hours are required and must be in the HH:MM format.']],
    'same hour' => ['09:00', '09:00', ['The start hour and the end hour must be different.']],
]);

it('stores the selections as clean id lists', function () {
    Tools::$values = [
        'rule_type' => ShippingRulesClass::RULE_TYPE_FREE,
        'categories' => ['3', '3', 'x', '5'],
        'manufacturers' => ['2'],
        'days' => ['1', '9', '7', '0'],
    ];
    $rule = new ShippingRulesClass();

    makeController()->copyFromPost($rule, 'shipping_rule');

    expect($rule->categories)->toBe('3,5')
        ->and($rule->manufacturers)->toBe('2')
        ->and($rule->cart_rules)->toBe('')
        ->and($rule->days)->toBe('1,7');
});

it('checks that the customer exists', function () {
    Customer::$existing = [12];

    expect(validate(['id_customer' => '12']))->toBe([]);
    expect(validate(['id_customer' => '13']))->toBe(['The selected customer does not exist.']);
});

it('validates the postcodes only for a country rule', function (array $values, array $errors) {
    expect(validate($values))->toBe($errors);
})->with([
    'valid' => [['id_zone' => '1', 'id_country' => '8', 'postcode_list' => '75001, 75002'], []],
    'invalid' => [['id_zone' => '1', 'id_country' => '8', 'postcode_list' => '75001, 75;002'], ['The postcode 75;002 is incorrect.']],
    'ignored without country' => [['id_zone' => '1', 'id_country' => '0', 'postcode_list' => '75;002'], []],
]);

it('stores the value matching the rule type', function (int $type, array $values, float $expected) {
    Tools::$values = array_merge(['rule_type' => $type, 'id_zone' => '1', 'id_country' => '8', 'postcode_list' => ''], $values);
    $rule = new ShippingRulesClass();
    $rule->id_zone = 1;
    $rule->id_country = 8;

    makeController()->copyFromPost($rule, 'shipping_rule');

    expect($rule->value)->toEqual($expected);
})->with([
    'additional' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL, ['impact_amount' => '2.5', 'impact_percent' => '9', 'new_amount' => '7'], 2.5],
    'reduction percent' => [ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT, ['impact_amount' => '2.5', 'impact_percent' => '9', 'new_amount' => '7'], 9.0],
    'new amount' => [ShippingRulesClass::RULE_TYPE_SET_AMOUNT, ['impact_amount' => '2.5', 'impact_percent' => '9', 'new_amount' => '7'], 7.0],
    'free' => [ShippingRulesClass::RULE_TYPE_FREE, ['impact_amount' => '2.5'], 0.0],
]);

it('only keeps the postcodes of a country rule', function (int $zone, int $country, string $expected) {
    Tools::$values = ['rule_type' => ShippingRulesClass::RULE_TYPE_FREE, 'postcode_list' => ' 75001,75001, 75002 '];
    $rule = new ShippingRulesClass();
    $rule->id_zone = $zone;
    $rule->id_country = $country;

    makeController()->copyFromPost($rule, 'shipping_rule');

    expect($rule->postcode_list)->toBe($expected);
})->with([
    'country' => [1, 8, '75001,75002'],
    'zone only' => [1, 0, ''],
    'all' => [0, 0, ''],
]);
