<?php
it('defines distinct rule types', function () {
    $types = [
        ShippingRulesClass::RULE_TYPE_ADDITIONAL,
        ShippingRulesClass::RULE_TYPE_FREE,
        ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT,
        ShippingRulesClass::RULE_TYPE_DISABLE,
        ShippingRulesClass::RULE_TYPE_SET_AMOUNT,
        ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT,
        ShippingRulesClass::RULE_TYPE_REDUCTION,
    ];

    expect(array_unique($types))->toHaveCount(7);
});

it('describes the shipping_rule table', function () {
    $definition = ShippingRulesClass::$definition;

    expect($definition['table'])->toBe('shipping_rule')
        ->and($definition['primary'])->toBe('id_shipping_rule')
        ->and($definition['fields'])->toHaveKeys(['id_carrier', 'id_zone', 'id_country', 'id_group', 'id_customer', 'postcode_list', 'rule_type', 'value', 'from', 'to', 'active', 'position']);
});

it('puts a new rule at the end of the list', function (?string $maxPosition, int $expected) {
    Db::$handler = function ($method) use ($maxPosition) {
        return $method === 'getValue' ? $maxPosition : true;
    };

    $rule = new ShippingRulesClass();
    $rule->add();

    expect($rule->position)->toBe($expected);
})->with([
    'empty table' => [null, 0],
    'after position 4' => ['4', 5],
]);

it('keeps the position of a rule that already has one', function () {
    $rule = new ShippingRulesClass();
    $rule->position = 7;
    $rule->add();

    expect($rule->position)->toBe(7);
});

it('compacts the positions after a deletion', function () {
    Db::$handler = function () {
        return true;
    };

    expect((new ShippingRulesClass(1))->delete())->toBeTrue()
        ->and(implode("\n", Db::$log))->toContain('SET @i = -1')->toContain('@i:=@i+1');
});

it('moves a rule down the list', function () {
    Db::$handler = function ($method) {
        return $method === 'executeS' ? [['position' => '2', 'id_shipping_rule' => '5']] : true;
    };

    expect((new ShippingRulesClass(5))->updatePosition(true, 4))->toBeTrue();

    $sql = preg_replace('/\s+/', ' ', implode("\n", Db::$log));
    expect($sql)->toContain('`position`= `position` - 1 WHERE `position` > 2 AND `position` <= 4')
        ->and($sql)->toContain('SET `position` = 4 WHERE `id_shipping_rule`=5');
});

it('moves a rule up the list', function () {
    Db::$handler = function ($method) {
        return $method === 'executeS' ? [['position' => '4', 'id_shipping_rule' => '5']] : true;
    };

    expect((new ShippingRulesClass(5))->updatePosition(false, 1))->toBeTrue();

    expect(preg_replace('/\s+/', ' ', implode("\n", Db::$log)))
        ->toContain('`position`= `position` + 1 WHERE `position` < 4 AND `position` >= 1');
});

it('refuses to move a rule that does not exist', function () {
    Db::$handler = function () {
        return [];
    };

    expect((new ShippingRulesClass(5))->updatePosition(true, 1))->toBeFalse();
});

it('flips the status of the current shop when editing in a shop context', function () {
    Shop::$featureActive = true;
    Shop::$context = Shop::CONTEXT_SHOP;
    Shop::$contextShopId = 3;
    Db::$handler = function ($method) {
        return $method === 'getValue' ? '0' : true;
    };

    $rule = new ShippingRulesClass(1);
    $rule->active = 1; // global status, ignored: the shop one (0) is flipped
    $rule->toggleStatus();

    expect((bool) $rule->active)->toBeTrue()
        ->and(implode("\n", Db::$log))->toContain('`id_shop` = 3');
});

it('duplicates a rule as a disabled rule at the end of the list', function () {
    Db::$handler = function ($method) {
        return $method === 'getValue' ? '4' : true;
    };

    $rule = new ShippingRulesClass(7);
    $rule->active = 1;
    $rule->position = 2;
    $copy = $rule->duplicate();

    expect($copy)->toBeInstanceOf(ShippingRulesClass::class)
        ->and($copy->id)->not->toBe(7)
        ->and((bool) $copy->active)->toBeFalse()
        ->and($copy->position)->toBe(5)
        ->and($rule->position)->toBe(2);
});

it('copies the shop associations of a rule in multistore', function () {
    Shop::$featureActive = true;
    Db::$handler = function () {
        return true;
    };

    (new ShippingRulesClass(7))->duplicate();

    $sql = preg_replace('/\s+/', ' ', implode("\n", Db::$log));
    expect($sql)->toContain('INSERT IGNORE INTO `ps_shipping_rule_shop`')
        ->toContain('SELECT 100, `id_shop`, 0')
        ->toContain('WHERE `id_shipping_rule` = 7');
});

it('does not touch the shop table without multistore', function () {
    Db::$handler = function () {
        return true;
    };

    (new ShippingRulesClass(7))->duplicate();

    expect(implode("\n", Db::$log))->not->toContain('INSERT IGNORE');
});
