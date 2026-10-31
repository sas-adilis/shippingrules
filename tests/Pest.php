<?php

require_once __DIR__ . '/Integration/helpers.php';
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
        'minimum_quantity' => 0,
        'maximum_quantity' => 0,
        'categories' => null,
        'manufacturers' => null,
        'cart_rules' => null,
        'days' => null,
        'hour_from' => null,
        'hour_to' => null,
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
    Db::$handler = function ($method, $sql) use ($rules) {
        if ($method !== 'executeS') {
            return false;
        }
        if (strpos($sql, 'cart_cart_rule') !== false) {
            return array_map(function ($id) {
                return ['id_cart_rule' => (string) $id];
            }, Cart::$cartRuleIds);
        }

        return $rules;
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

    (new TestableShippingRules())->hookActionGetPackageShippingCost($params);

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
    Cart::$cartRuleIds = [];
    Product::$categories = [];
    TestableShippingRules::$now = null;
    TestableShippingRules::$legacyOverride = false;
})->in('Unit');

// Integration tests need a real shop (see bootstrap.php).
pest()->beforeEach(function () {
    if (!SR_REAL_SHOP) {
        $this->markTestSkipped('Integration tests need a PrestaShop shop (run them in the Docker stack, see bin/test-module.sh).');
    }
})->in('Integration');

// PrestaShop and the other modules raise deprecations of their own (and the error handler of ps_eventbus fails on them):
// only the module's code counts.
pest()->beforeEach(function () {
    if (!SR_REAL_SHOP) {
        return;
    }

    $module = dirname(__DIR__) . '/';
    $previous = null;
    $GLOBALS['sr_error_handler'] = function ($level, $message, $file = '', $line = 0) use (&$previous, $module) {
        $isOurs = strpos((string) $file, $module) === 0 && strpos((string) $file, $module . 'vendor/') !== 0;
        if (($level & (E_DEPRECATED | E_USER_DEPRECATED)) && !$isOurs) {
            return true;
        }
        // The cache of the container of PrestaShop is empty after a reset of the shop
        if (($level & E_WARNING) && !$isOurs && strpos((string) $message, 'filemtime(') === 0) {
            return true;
        }

        return $previous ? $previous($level, $message, $file, $line) : false;
    };
    $previous = set_error_handler($GLOBALS['sr_error_handler']);

    $GLOBALS['sr_exception_handler'] = set_exception_handler(function () {
    });
    restore_exception_handler();
})->in('Integration');

pest()->afterEach(function () {
    if (!SR_REAL_SHOP) {
        return;
    }

    srCleanRules();

    // PrestaShop may have stacked its own handler (Symfony) on ours: remove everything down to ours.
    for ($i = 0; $i < 10; ++$i) {
        $current = set_error_handler(function () {
            return false;
        });
        restore_error_handler();
        restore_error_handler();
        if ($current === $GLOBALS['sr_error_handler']) {
            break;
        }
    }
    // Same for the exception handler PrestaShop sets when it builds its first cart.
    for ($i = 0; $i < 10; ++$i) {
        $current = set_exception_handler(function () {
        });
        restore_exception_handler();
        if ($current === $GLOBALS['sr_exception_handler']) {
            break;
        }
        restore_exception_handler();
    }
})->in('Integration');
