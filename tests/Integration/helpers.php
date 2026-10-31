<?php
/**
 * Helpers of the integration suite: a small catalog, a customer with two addresses, rules created in the
 * database and real shipping costs read through Cart::getPackageShippingCost().
 *
 * The reference carrier is the "My carrier" of the demo shop (carrier 2): 5.00 flat in Europe, tax excluded.
 * Everything created here is removed when the process ends.
 */

/** @var array<string, mixed>|null */
$GLOBALS['sr_fixture'] = null;

/** @var ShippingRulesClass[] */
$GLOBALS['sr_rules'] = [];

/** @var string[] */
$GLOBALS['sr_muted'] = [];

/** @var Cart[] */
$GLOBALS['sr_carts'] = [];

/**
 * The front container provides the services of the cart. The cache of a container that has just been built
 * warns about a file it has not written yet.
 */
function srContainer(string $name)
{
    return @\PrestaShop\PrestaShop\Adapter\ContainerBuilder::getContainer($name, defined('_PS_MODE_DEV_') && _PS_MODE_DEV_);
}

/**
 * Third-party modules that hook every object creation (the fixtures create many)
 * and crash when called from a test runner. Disabled for the run, enabled back at the end.
 */
function srMuteModules(): void
{
    foreach (['ps_eventbus'] as $name) {
        if (Module::isInstalled($name) && Module::isEnabled($name)) {
            Module::disableByName($name);
            $GLOBALS['sr_muted'][] = $name;
        }
    }
    Cache::clean('hook_module_exec_list_*');
}

/**
 * @return array<int, string>
 */
function srLangValue(string $value): array
{
    $values = [];
    foreach (Language::getIDs(false) as $idLang) {
        $values[(int) $idLang] = $value;
    }

    return $values;
}

function srCreateProduct(string $name, float $price, int $idCategory, int $idManufacturer = 0): Product
{
    $product = new Product();
    $product->name = srLangValue($name);
    $product->link_rewrite = srLangValue(Tools::str2url($name));
    $product->price = $price;
    $product->weight = 1.0;
    $product->id_manufacturer = $idManufacturer;
    $product->id_category_default = $idCategory;
    $product->active = 1;
    $product->visibility = 'both';
    $product->minimal_quantity = 1;
    if (!$product->add()) {
        throw new RuntimeException('Product ' . $name . ' not created');
    }
    $product->addToCategories([$idCategory]);
    StockAvailable::setQuantity((int) $product->id, 0, 1000);

    return $product;
}

/**
 * Catalog of the suite, created once:
 *  - p1: 20 euros, 1 kg, in the home category, no manufacturer
 *  - p2: 30 euros, 1 kg, in a test category, from a test manufacturer
 *  - a customer in the default customer group, with a delivery address in France (75001) and another one (13001).
 *
 * @return array<string, mixed>
 */
function srFixture(): array
{
    if ($GLOBALS['sr_fixture'] !== null) {
        return $GLOBALS['sr_fixture'];
    }
    if (!Module::isInstalled('shippingrules')) {
        throw new RuntimeException('The module is not installed in this shop: its hooks are what these tests check (bin/test-module.sh installs it).');
    }
    Module::getInstanceByName('shippingrules');
    // What a front request has and the CLI has not
    Context::getContext()->currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
    Context::getContext()->country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'));
    Context::getContext()->cart = new Cart();
    // The cart totals are computed by services of the container, which a front controller provides
    Context::getContext()->container = srContainer('front');
    srMuteModules();

    $f = ['objects' => []];
    $track = function ($object) use (&$f) {
        $f['objects'][] = $object;

        return $object;
    };

    $category = new Category();
    $category->name = srLangValue('SR Test');
    $category->link_rewrite = srLangValue('sr-test');
    $category->id_parent = (int) Configuration::get('PS_HOME_CATEGORY');
    $category->active = 1;
    $category->add();
    $f['category'] = $track($category);

    $manufacturer = new Manufacturer();
    $manufacturer->name = 'SR Test brand';
    $manufacturer->active = 1;
    $manufacturer->add();
    $f['manufacturer'] = $track($manufacturer);

    $f['p1'] = $track(srCreateProduct('SR Product 1', 20.0, (int) Configuration::get('PS_HOME_CATEGORY')));
    $f['p2'] = $track(srCreateProduct('SR Product 2', 30.0, (int) $category->id, (int) $manufacturer->id));

    $customer = new Customer();
    $customer->firstname = 'Shipping';
    $customer->lastname = 'Tester';
    $customer->email = 'shippingrules-test-' . uniqid() . '@example.com';
    $customer->passwd = md5('shippingrules');
    $customer->active = 1;
    if (!$customer->add()) {
        throw new RuntimeException('Customer not created');
    }
    $f['customer'] = $track($customer);

    $idFrance = (int) Country::getByIso('FR');
    foreach (['paris' => '75001', 'marseille' => '13001'] as $key => $postcode) {
        $address = new Address();
        $address->id_customer = (int) $customer->id;
        $address->id_country = $idFrance;
        $address->alias = 'SR ' . $key;
        $address->firstname = 'Shipping';
        $address->lastname = 'Tester';
        $address->address1 = '1 rue du Test';
        $address->postcode = $postcode;
        $address->city = ucfirst($key);
        if (!$address->add()) {
            throw new RuntimeException('Address ' . $key . ' not created');
        }
        $f[$key] = $track($address);
    }

    $f['carrier'] = new Carrier(2);
    $f['id_france'] = $idFrance;
    $f['id_zone'] = (int) Country::getIdZone($idFrance);

    register_shutdown_function(function () use ($f) {
        srCleanRules();
        foreach (array_reverse($f['objects']) as $object) {
            try {
                $object->delete();
            } catch (Throwable $e) {
                // the shop is reset after the run anyway
            }
        }
        // Without a container the modules management skips the services it cannot reach
        Context::getContext()->container = null;
        foreach ($GLOBALS['sr_muted'] as $name) {
            Module::enableByName($name);
        }
    });

    return $GLOBALS['sr_fixture'] = $f;
}

/**
 * A rule valid now. Defaults: every carrier, zone, country, group and customer; no condition.
 *
 * @param array<string, mixed> $values properties of the rule
 */
function srRule(array $values = []): ShippingRulesClass
{
    srFixture();

    $rule = new ShippingRulesClass();
    $rule->rule_type = ShippingRulesClass::RULE_TYPE_FREE;
    $rule->from = date('Y-m-d H:i:s', strtotime('-1 year'));
    $rule->to = date('Y-m-d H:i:s', strtotime('+1 year'));
    $rule->active = 1;
    foreach ($values as $property => $value) {
        $rule->$property = $value;
    }
    if (!$rule->add()) {
        throw new RuntimeException('Rule not created');
    }

    return $GLOBALS['sr_rules'][] = $rule;
}

function srCleanRules(): void
{
    foreach ($GLOBALS['sr_rules'] as $rule) {
        $rule->delete();
    }
    $GLOBALS['sr_rules'] = [];
}

/**
 * A cart of the fixture customer, delivered in Paris unless told otherwise.
 *
 * @param array<string, int> $quantities fixture key (p1, p2) => quantity
 */
function srCart(array $quantities = ['p1' => 1], string $address = 'paris'): Cart
{
    $f = srFixture();

    $cart = new Cart();
    $cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
    $cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    $cart->id_customer = (int) $f['customer']->id;
    $cart->id_address_delivery = (int) $f[$address]->id;
    $cart->id_address_invoice = (int) $f[$address]->id;
    $cart->id_carrier = 2;
    $cart->add();
    foreach ($quantities as $key => $quantity) {
        $cart->updateQty($quantity, (int) $f[$key]->id);
    }

    Context::getContext()->cart = $cart;
    Context::getContext()->customer = $f['customer'];

    return $GLOBALS['sr_carts'][] = $cart;
}

/**
 * Shipping cost of the carrier 2 for the cart, tax excluded unless asked, as the checkout computes it.
 *
 * @return float|false
 */
function srCost(?Cart $cart = null, bool $useTax = false, int $idCarrier = 2)
{
    // The rules of a context are kept in memory for the length of a request
    Cache::clean('ShippingRules::hookActionGetPackageShippingCost_*');

    return ($cart ?? srCart())->getPackageShippingCost($idCarrier, $useTax);
}

/**
 * Cost of the carrier 2 for one unit of p1 without any rule: the flat rate plus the handling charges of the shop.
 */
function srBase(): float
{
    static $base = null;

    if ($base === null) {
        srCleanRules();
        $base = (float) srCost();
    }

    return $base;
}
