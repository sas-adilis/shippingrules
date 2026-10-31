<?php
// ── Rule types ──────────────────────────────────────────────────────────────

it('applies a rule type to the shipping cost', function (int $type, $value, $expected) {
    expect(runHook([makeRule(['rule_type' => $type, 'value' => $value])]))->toBe($expected);
})->with([
    'free' => [ShippingRulesClass::RULE_TYPE_FREE, 0, 0.0],
    'disable carrier' => [ShippingRulesClass::RULE_TYPE_DISABLE, 0, false],
    'new amount' => [ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 4.5, 4.5],
    'additional amount' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL, 2, 12.0],
    'reduction amount' => [ShippingRulesClass::RULE_TYPE_REDUCTION, 3, 7.0],
    'reduction larger than the cost' => [ShippingRulesClass::RULE_TYPE_REDUCTION, 25, 0.0],
    'additional percent' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT, 20, 12.0],
    'reduction percent' => [ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT, 50, 5.0],
]);

it('does not apply a percent to a free shipping', function (int $type) {
    expect(runHook([makeRule(['rule_type' => $type, 'value' => 50])], [], [], 0.0))->toBe(0.0);
})->with([
    ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT,
    ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT,
]);

it('rounds the resulting cost', function () {
    $rule = makeRule(['rule_type' => ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT, 'value' => 10]);

    expect(runHook([$rule], [], [], 3.33))->toBe(3.66);
});

// ── Early exits ─────────────────────────────────────────────────────────────

it('leaves an unavailable carrier alone', function () {
    $shipping_cost = false;
    $params = ['cart' => new Cart(), 'shipping_cost' => &$shipping_cost, 'id_carrier' => 1];
    (new ShippingRules())->hookActionGetPackageShippingCost($params);

    expect($shipping_cost)->toBeFalse()
        ->and(rulesQuery())->toBe('');
});

it('ignores virtual carts', function () {
    expect(runHook([makeRule()], [], ['virtual' => true]))->toBe(10.0);
});

it('ignores an unknown carrier', function () {
    Carrier::$missing = [99];

    expect(runHook([makeRule()], ['id_carrier' => 99]))->toBe(10.0);
});

it('does nothing without a rule', function () {
    expect(runHook([]))->toBe(10.0);
});

it('falls back on the default carrier', function () {
    Configuration::$values['PS_CARRIER_DEFAULT'] = '4';
    Carrier::$references = [4 => 40];

    expect(runHook([makeRule(['id_carrier' => 40])], ['id_carrier' => 0]))->toBe(0.0);
});

// ── Matching ────────────────────────────────────────────────────────────────

it('only applies the first matching rule', function () {
    $rules = [
        makeRule(['id_shipping_rule' => 1, 'rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 3]),
        makeRule(['id_shipping_rule' => 2, 'rule_type' => ShippingRulesClass::RULE_TYPE_FREE]),
    ];

    expect(runHook($rules))->toBe(3.0);
});

it('skips a rule whose conditions are not met', function () {
    $rules = [
        makeRule(['minimum_amount' => 500, 'rule_type' => ShippingRulesClass::RULE_TYPE_DISABLE]),
        makeRule(['rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 6]),
    ];

    expect(runHook($rules))->toBe(6.0);
});

it('matches the carrier through its reference', function (int $ruleCarrier, bool $applies) {
    Carrier::$references = [2 => 5];

    expect(runHook([makeRule(['id_carrier' => $ruleCarrier])], ['id_carrier' => 2]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'all carriers' => [0, true],
    'same reference' => [5, true],
    'carrier id instead of the reference' => [2, false],
    'other carrier' => [7, false],
]);

it('filters on the cart amount', function (array $rule, float $amount, bool $applies) {
    expect(runHook([makeRule($rule)], [], ['totalExcl' => $amount, 'totalIncl' => $amount * 1.2]))
        ->toBe($applies ? 0.0 : 10.0);
})->with([
    'below the minimum' => [['minimum_amount' => 50], 49.99, false],
    'at the minimum' => [['minimum_amount' => 50], 50.0, true],
    'above the maximum' => [['maximum_amount' => 100], 100.01, false],
    'at the maximum' => [['maximum_amount' => 100], 100.0, true],
    'no maximum with 0' => [['maximum_amount' => 0], 99999.0, true],
    'tax included' => [['minimum_amount' => 100, 'minimum_amount_tax' => 1], 90.0, true],
    'tax excluded' => [['minimum_amount' => 100, 'minimum_amount_tax' => 0], 90.0, false],
]);

it('filters on the cart weight', function (array $rule, float $weight, bool $applies) {
    expect(runHook([makeRule($rule)], [], ['weight' => $weight]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'below the minimum' => [['minimum_weight' => 2], 1.9, false],
    'at the minimum' => [['minimum_weight' => 2], 2.0, true],
    'above the maximum' => [['maximum_weight' => 5], 5.1, false],
    'no maximum with 0' => [['maximum_weight' => 0], 500.0, true],
]);

it('converts the cart amount in the currency of the rule', function () {
    Currency::$rates = [1 => 1.0, 2 => 2.0];
    $rule = makeRule(['minimum_amount' => 80, 'minimum_amount_currency' => 2]);

    // 50 in the cart currency = 100 in the rule currency
    expect(runHook([$rule], [], ['totalExcl' => 50.0]))->toBe(0.0);
    expect(runHook([$rule], [], ['totalExcl' => 30.0]))->toBe(10.0);
});

it('filters on postcodes', function (string $list, ?string $postcode, bool $applies) {
    Address::$list = [5 => ['id_country' => 8, 'zone' => 1, 'postcode' => (string) $postcode]];
    $cart = ['products' => [['id_address_delivery' => 5]]];
    Configuration::$values['PS_TAX_ADDRESS_TYPE'] = 'id_address_delivery';

    expect(runHook([makeRule(['postcode_list' => $list])], [], $cart))->toBe($applies ? 0.0 : 10.0);
})->with([
    'listed' => ['75001,75002', '75002', true],
    'spaces and case are ignored' => ['SW1A1AA', 'sw1a 1aa', true],
    'not listed' => ['75001,75002', '75003', false],
    'empty list matches everything' => ['', '13001', true],
]);

it('never matches a postcode rule without an address', function () {
    expect(runHook([makeRule(['postcode_list' => '75001'])]))->toBe(10.0);
});

// ── Taxes and currencies ────────────────────────────────────────────────────

it('adds the carrier taxes to an amount when prices are displayed with taxes', function () {
    $rule = makeRule(['rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 5]);

    expect(runHook([$rule], ['use_tax' => true]))->toBe(6.0);
    expect(runHook([$rule], ['use_tax' => false]))->toBe(5.0);
});

it('uses the average product tax rate with the shipping wrapping option', function () {
    Configuration::$values['PS_ATCP_SHIPWRAP'] = 1;
    $rule = makeRule(['rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 10]);

    expect(runHook([$rule], ['use_tax' => true], ['averageTaxRate' => 0.1]))->toBe(11.0);
});

it('does not add taxes when the group is tax free', function () {
    Tax::$excludeTaxOption = true;
    $rule = makeRule(['rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 5]);

    expect(runHook([$rule], ['use_tax' => true]))->toBe(5.0);
});

it('converts an amount from the default currency to the cart currency', function () {
    Currency::$rates = [1 => 1.0, 2 => 2.0];
    $rule = makeRule(['rule_type' => ShippingRulesClass::RULE_TYPE_ADDITIONAL, 'value' => 5]);

    expect(runHook([$rule], [], ['id_currency' => 2]))->toBe(20.0);
});

// ── Rules query ─────────────────────────────────────────────────────────────

it('selects the rules of the zone, country, groups and customer', function () {
    Customer::$existing = [12];
    $customer = new Customer(12);
    Customer::$groups = [3, 1];
    Context::getContext()->customer = $customer;
    Address::$list = [5 => ['id_country' => 8, 'zone' => 2, 'postcode' => '75001']];
    Configuration::$values['PS_TAX_ADDRESS_TYPE'] = 'id_address_invoice';

    runHook([makeRule()], [], ['id_address_invoice' => 5]);
    $sql = rulesQuery();

    expect($sql)->toContain('sr.id_zone IN (2, 0)')
        ->toContain('sr.id_country IN (8, 0)')
        ->toContain('sr.id_group IN (0,1,3)')
        ->toContain('sr.id_customer IN (0, 12)')
        ->toContain('sr.active = 1')
        ->toContain('ORDER BY sr.position ASC')
        ->not->toContain('shipping_rule_shop');
});

it('uses the country of the context without address', function () {
    runHook([makeRule()]);

    expect(rulesQuery())->toContain('sr.id_zone IN (1, 0)')->toContain('sr.id_country IN (8, 0)')->toContain('sr.id_customer IN (0, 0)');
});

it('lets the hook zone take precedence', function () {
    runHook([makeRule()], ['id_zone' => 9]);

    expect(rulesQuery())->toContain('sr.id_zone IN (9, 0)');
});

it('reads the status of the shop in multistore', function () {
    Shop::$featureActive = true;
    Context::getContext()->shop = (object) ['id' => 2];

    runHook([makeRule()]);

    expect(rulesQuery())->toContain('INNER JOIN `ps_shipping_rule_shop` srs')
        ->toContain('srs.id_shop = 2')
        ->toContain('srs.active = 1');
});

it('queries the rules once per context', function () {
    runHook([makeRule()]);
    runHook([makeRule()]);

    $queries = array_filter(Db::$log, function ($entry) {
        return strpos($entry, 'executeS: SELECT sr.*') === 0;
    });

    expect($queries)->toHaveCount(1);
});

// ── Cart content conditions ─────────────────────────────────────────────────

it('filters on the quantity of products', function (array $rule, int $quantity, bool $applies) {
    $products = [['id_product' => 1, 'cart_quantity' => $quantity - 1], ['id_product' => 2, 'cart_quantity' => 1]];

    expect(runHook([makeRule($rule)], [], ['products' => $products]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'below the minimum' => [['minimum_quantity' => 3], 2, false],
    'at the minimum' => [['minimum_quantity' => 3], 3, true],
    'above the maximum' => [['maximum_quantity' => 4], 5, false],
    'at the maximum' => [['maximum_quantity' => 4], 4, true],
    'no maximum with 0' => [['maximum_quantity' => 0], 500, true],
]);

it('filters on the categories of the products', function (?string $list, bool $applies) {
    Product::$categories = [1 => [2, 3], 2 => [9]];
    $products = [['id_product' => 1, 'cart_quantity' => 1], ['id_product' => 2, 'cart_quantity' => 1]];

    expect(runHook([makeRule(['categories' => $list])], [], ['products' => $products]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'no restriction' => [null, true],
    'a category of the first product' => ['3', true],
    'a category of the second product' => ['7,9', true],
    'no matching category' => ['4,5', false],
]);

it('filters on the manufacturers of the products', function (?string $list, bool $applies) {
    $products = [['id_product' => 1, 'cart_quantity' => 1, 'id_manufacturer' => '6'], ['id_product' => 2, 'cart_quantity' => 1, 'id_manufacturer' => '0']];

    expect(runHook([makeRule(['manufacturers' => $list])], [], ['products' => $products]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'no restriction' => [null, true],
    'matching' => ['5,6', true],
    'no matching manufacturer' => ['7', false],
]);

it('filters on the cart rules applied to the cart', function (?string $list, array $applied, bool $applies) {
    Cart::$cartRuleIds = $applied;

    expect(runHook([makeRule(['cart_rules' => $list])]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'no restriction' => [null, [], true],
    'applied' => ['4,8', [8], true],
    'not applied' => ['4,8', [5], false],
    'no cart rule at all' => ['4', [], false],
]);

it('never reads the cart rules through the cart, which asks for the shipping cost again', function () {
    Cart::$cartRuleIds = [4];
    runHook([makeRule(['cart_rules' => '4'])]);

    expect(implode("\n", Db::$log))->toContain('FROM `ps_cart_cart_rule` WHERE `id_cart` = 1');
});

// ── Schedule conditions ─────────────────────────────────────────────────────

it('filters on the day of the week', function (?string $days, string $day, bool $applies) {
    TestableShippingRules::$now = strtotime($day . ' this week 12:00');

    expect(runHook([makeRule(['days' => $days])]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'every day' => [null, 'monday', true],
    'listed' => ['1,2', 'tuesday', true],
    'not listed' => ['1,2', 'wednesday', false],
    'sunday is 7' => ['7', 'sunday', true],
]);

it('filters on the hour', function (string $from, string $to, string $time, bool $applies) {
    TestableShippingRules::$now = strtotime('monday this week ' . $time);

    expect(runHook([makeRule(['hour_from' => $from, 'hour_to' => $to])]))->toBe($applies ? 0.0 : 10.0);
})->with([
    'inside' => ['09:00', '17:00', '12:30', true],
    'at the start' => ['09:00', '17:00', '09:00', true],
    'at the end, excluded' => ['09:00', '17:00', '17:00', false],
    'before' => ['09:00', '17:00', '08:59', false],
    'overnight, evening' => ['22:00', '06:00', '23:30', true],
    'overnight, morning' => ['22:00', '06:00', '05:59', true],
    'overnight, outside' => ['22:00', '06:00', '12:00', false],
]);

it('ignores a single hour', function () {
    TestableShippingRules::$now = strtotime('monday this week 03:00');

    expect(runHook([makeRule(['hour_from' => '09:00', 'hour_to' => null])]))->toBe(0.0);
});
