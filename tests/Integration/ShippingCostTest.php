<?php

it('keeps the cost of the carrier when there is no rule', function () {
    expect(srBase())->toBeGreaterThan(0.0)
        ->and(srCost())->toEqualWithDelta(srBase(), 0.0001);
});

it('applies each rule type to the real cost', function (int $type, float $value, $expected) {
    $base = srBase();
    srRule(['rule_type' => $type, 'value' => $value]);

    $cost = srCost();

    $expected === false ? expect($cost)->toBeFalse() : expect($cost)->toEqualWithDelta($expected($base), 0.0001);
})->with([
    'free' => [ShippingRulesClass::RULE_TYPE_FREE, 0, function () { return 0.0; }],
    'disable carrier' => [ShippingRulesClass::RULE_TYPE_DISABLE, 0, false],
    'new amount' => [ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 3.5, function () { return 3.5; }],
    'additional amount' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL, 2, function ($base) { return $base + 2; }],
    'reduction amount' => [ShippingRulesClass::RULE_TYPE_REDUCTION, 2, function ($base) { return $base - 2; }],
    'reduction larger than the cost' => [ShippingRulesClass::RULE_TYPE_REDUCTION, 500, function () { return 0.0; }],
    'additional percent' => [ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT, 20, function ($base) { return $base * 1.2; }],
    'reduction percent' => [ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT, 50, function ($base) { return $base * 0.5; }],
]);

it('adds the carrier taxes to an amount when prices are displayed with taxes', function () {
    srRule(['rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 3]);
    $f = srFixture();
    $rate = $f['carrier']->getTaxesRate($f['paris']);

    expect(srCost(null, true))->toEqualWithDelta(3 * (1 + $rate / 100), 0.0001);
});

it('only applies the first rule, in the order of the priorities', function () {
    srRule(['rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 3]);
    srRule(['rule_type' => ShippingRulesClass::RULE_TYPE_FREE]);

    expect(srCost())->toEqualWithDelta(3.0, 0.0001);
});

it('ignores a rule that is disabled, expired or not started', function (array $values) {
    srRule($values);

    expect(srCost())->toEqualWithDelta(srBase(), 0.0001);
})->with([
    'disabled' => [['active' => 0]],
    'expired' => [['from' => '2000-01-01 00:00:00', 'to' => '2000-12-31 00:00:00']],
    'not started' => [['from' => '2999-01-01 00:00:00', 'to' => '2999-12-31 00:00:00']],
]);

it('matches the carrier through its reference', function (int $idCarrierRule, bool $untouched) {
    srRule(['id_carrier' => $idCarrierRule]);

    expect(srCost())->toEqualWithDelta($untouched ? srBase() : 0.0, 0.0001);
})->with([
    'the carrier' => [2, false],
    'another carrier' => [1, true],
]);

it('filters on the zone and the country', function (string $property, string $target, bool $applies) {
    $f = srFixture();
    $values = [
        'zone' => $f['id_zone'],
        'other zone' => $f['id_zone'] + 1,
        'france' => $f['id_france'],
        'other country' => (int) Country::getByIso('US'),
    ];
    srRule([$property => $values[$target]]);

    expect(srCost())->toEqualWithDelta($applies ? 0.0 : srBase(), 0.0001);
})->with([
    'zone of the address' => ['id_zone', 'zone', true],
    'other zone' => ['id_zone', 'other zone', false],
    'country of the address' => ['id_country', 'france', true],
    'other country' => ['id_country', 'other country', false],
]);

it('filters on the customer and its groups', function (string $property, bool $matches) {
    $f = srFixture();
    $values = [
        'id_customer' => $matches ? (int) $f['customer']->id : (int) $f['customer']->id + 1000,
        'id_group' => $matches ? (int) $f['customer']->id_default_group : 999,
    ];
    srRule([$property => $values[$property]]);

    expect(srCost())->toEqualWithDelta($matches ? 0.0 : srBase(), 0.0001);
})->with([
    'the customer' => ['id_customer', true],
    'another customer' => ['id_customer', false],
    'a group of the customer' => ['id_group', true],
    'another group' => ['id_group', false],
]);

it('filters on the amount of the cart', function (array $values, bool $applies) {
    srRule($values);

    // two units of p1: 40 euros
    expect(srCost(srCart(['p1' => 2])))->toEqualWithDelta($applies ? 0.0 : srBase(), 0.0001);
})->with([
    'below the minimum' => [['minimum_amount' => 50], false],
    'above the minimum' => [['minimum_amount' => 30], true],
    'above the maximum' => [['maximum_amount' => 30], false],
    'under the maximum' => [['maximum_amount' => 50], true],
]);

it('filters on the weight of the cart', function (array $values, bool $applies) {
    srRule($values);

    // two units of p1: 2 kg
    expect(srCost(srCart(['p1' => 2])))->toEqualWithDelta($applies ? 0.0 : srBase(), 0.0001);
})->with([
    'below the minimum' => [['minimum_weight' => 3], false],
    'above the minimum' => [['minimum_weight' => 1], true],
    'above the maximum' => [['maximum_weight' => 1], false],
    'under the maximum' => [['maximum_weight' => 3], true],
]);

it('filters on the postcode of the address', function (string $list, string $address, bool $applies) {
    srRule(['postcode_list' => $list]);

    expect(srCost(srCart(['p1' => 1], $address)))->toEqualWithDelta($applies ? 0.0 : srBase(), 0.0001);
})->with([
    'listed' => ['75001, 75002', 'paris', true],
    'not listed' => ['75001, 75002', 'marseille', false],
]);

it('filters on the quantity of products', function (array $values, bool $applies) {
    srRule($values);

    // three units
    expect(srCost(srCart(['p1' => 2, 'p2' => 1])))->toEqualWithDelta($applies ? 0.0 : srBase(), 0.0001);
})->with([
    'below the minimum' => [['minimum_quantity' => 4], false],
    'at the minimum' => [['minimum_quantity' => 3], true],
    'above the maximum' => [['maximum_quantity' => 2], false],
    'at the maximum' => [['maximum_quantity' => 3], true],
]);

it('filters on the categories and the manufacturers of the products', function (string $property, bool $matchesP2, bool $inCart, bool $applies) {
    $f = srFixture();
    $ids = ['categories' => (int) $f['category']->id, 'manufacturers' => (int) $f['manufacturer']->id];
    srRule([$property => $matchesP2 ? (string) $ids[$property] : '999999']);

    expect(srCost(srCart($inCart ? ['p1' => 1, 'p2' => 1] : ['p1' => 1])))->toEqualWithDelta($applies ? 0.0 : srBase(), 0.0001);
})->with([
    'category in the cart' => ['categories', true, true, true],
    'category not in the cart' => ['categories', true, false, false],
    'other category' => ['categories', false, true, false],
    'manufacturer in the cart' => ['manufacturers', true, true, true],
    'manufacturer not in the cart' => ['manufacturers', true, false, false],
    'other manufacturer' => ['manufacturers', false, true, false],
]);

it('filters on the cart rules applied to the cart, even when one gives a free shipping', function (bool $applied, bool $freeShipping) {
    $cartRule = new CartRule();
    $cartRule->name = srLangValue('SR free shipping');
    $cartRule->code = 'SRTEST' . strtoupper(substr(uniqid(), -6));
    $cartRule->date_from = date('Y-m-d H:i:s', strtotime('-1 day'));
    $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 day'));
    $cartRule->quantity = 100;
    $cartRule->quantity_per_user = 100;
    $cartRule->free_shipping = $freeShipping;
    $cartRule->reduction_percent = $freeShipping ? 0 : 10;
    $cartRule->active = 1;
    $cartRule->add();
    srFixture()['objects'][] = $cartRule;

    srRule(['cart_rules' => (string) $cartRule->id, 'rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'value' => 3]);

    $cart = srCart();
    if ($applied) {
        $cart->addCartRule((int) $cartRule->id);
    }

    // a free-shipping rule asks for the shipping cost while the cart rules are computed: it must not loop
    $cost = srCost($cart);
    $cartRule->delete();

    expect($cost)->toEqualWithDelta($applied ? 3.0 : srBase(), 0.0001);
})->with([
    'applied' => [true, false],
    'not applied' => [false, false],
    'applied with a free shipping' => [true, true],
]);

it('filters on the day and the hour', function (string $property, bool $matches) {
    $now = time();
    $values = [
        'days' => $matches ? date('N', $now) : (string) (date('N', $now) % 7 + 1),
        'hours' => null,
    ];
    if ($property === 'hours') {
        $values = $matches
            ? ['hour_from' => date('H:i', $now - 3600), 'hour_to' => date('H:i', $now + 3600)]
            : ['hour_from' => date('H:i', $now + 3600), 'hour_to' => date('H:i', $now + 7200)];
    } else {
        $values = ['days' => $values['days']];
    }
    srRule($values);

    expect(srCost())->toEqualWithDelta($matches ? 0.0 : srBase(), 0.0001);
})->with([
    'today' => ['days', true],
    'another day' => ['days', false],
    'now' => ['hours', true],
    'later' => ['hours', false],
]);

it('hooks the shipping cost the way the version of PrestaShop allows', function () {
    $declaring = (new ReflectionMethod('Cart', 'getPackageShippingCost'))->getDeclaringClass()->getName();

    // From PrestaShop 9.2 the core has its own hook and the module installs no override
    expect($declaring)->toBe(ShippingRules::hasNativeShippingCostHook() ? 'CartCore' : 'Cart');
});
