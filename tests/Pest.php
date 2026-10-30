<?php
/**
 * A rule row (shipping_rule) with the defaults of a plain "free shipping" rule.
 *
 * @param array<string, mixed> $overrides
 *
 * @return array<string, mixed>
 */
function makeRule(array $overrides = []): array
{
    return array_merge([
        'id_shipping_rule' => 1,
        'id_carrier' => 0,
        'rule_type' => ShippingRulesClass::RULE_TYPE_FREE,
        'id_country' => 0,
        'id_zone' => 0,
        'id_group' => 0,
        'id_customer' => 0,
        'postcode_list' => '',
        'minimum_amount' => 0,
        'minimum_amount_tax' => 0,
        'minimum_amount_currency' => 0,
        'maximum_amount' => 0,
        'maximum_amount_tax' => 0,
        'maximum_amount_currency' => 0,
        'minimum_weight' => 0,
        'maximum_weight' => 0,
        'value' => 0,
        'position' => 0,
    ], $overrides);
}

/**
 * Runs the shipping cost hook with the given rules and returns the resulting cost.
 *
 * @param array<int, array<string, mixed>> $rules rows returned by the database
 * @param array<string, mixed> $params hook parameters (override the defaults)
 * @param array<string, mixed> $cart properties of the cart
 *
 * @return float|false
 */
function runHook(array $rules, array $params = [], array $cart = [], float $cost = 10.0)
{
    Db::$handler = function ($method) use ($rules) {
        return $method === 'executeS' ? $rules : false;
    };

    $theCart = new Cart();
    foreach ($cart as $property => $value) {
        $theCart->$property = $value;
    }

    $shipping_cost = $cost;
    $params = array_merge([
        'cart' => $theCart,
        'shipping_cost' => &$shipping_cost,
        'id_carrier' => 1,
        'use_tax' => false,
        'default_country' => (object) ['id' => 8, 'id_zone' => 1],
        'product_list' => null,
        'id_zone' => null,
    ], $params);

    (new ShippingRules())->hookActionGetPackageShippingCost($params);

    return $shipping_cost;
}

/**
 * The SQL of the rules query sent by the last hook call.
 */
function rulesQuery(): string
{
    foreach (Db::$log as $entry) {
        if (strpos($entry, 'executeS: SELECT sr.*') === 0) {
            return $entry;
        }
    }

    return '';
}

pest()->beforeEach(function () {
    Db::$handler = null;
    Db::$log = [];
    Cache::$store = [];
    Configuration::$values = [];
    Context::reset();
    Tools::$values = [];
    Shop::$featureActive = false;
    Shop::$context = Shop::CONTEXT_ALL;
    Tax::$excludeTaxOption = false;
    Customer::$existing = [];
    Customer::$groups = [3];
    Currency::$rates = [1 => 1.0];
    Currency::$default = 1;
    Carrier::$references = [];
    Carrier::$missing = [];
    Carrier::$taxRate = 20.0;
    Address::$list = [];
})->in('Unit');
