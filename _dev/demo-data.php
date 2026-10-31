<?php
/**
 * Demo data set for the Addons screenshots: nine shipping rules (one of them disabled) and the
 * customer group they need, on the current shop.
 *
 *   php modules/shippingrules/_dev/demo-data.php
 *
 * It empties the rules table first, so the first rule gets ID 1 and the list is always the same.
 * Run it from the shop root, or set PS_ROOT. Carriers, zones and country are found by name or ISO code
 * (demo shop of PrestaShop); a missing one makes the rule apply to all of them.
 */
const CARRIER_STANDARD = 'My carrier';
const CARRIER_PICKUP = 'Click and collect';
const ZONE_EUROPE = 'Europe';
const ZONE_NORTH_AMERICA = 'North America';
const COUNTRY_ISO = 'FR';
const GROUP_PROS = 'Professionnels';

$root = rtrim((string) (getenv('PS_ROOT') ?: getcwd()), '/');
if (!is_file($root . '/config/config.inc.php')) {
    fwrite(STDERR, "No shop found in $root (run it from the shop root or set PS_ROOT).\n");
    exit(1);
}
require $root . '/config/config.inc.php';

Module::getInstanceByName('shippingrules');
$db = Db::getInstance();
$idLang = (int) Configuration::get('PS_LANG_DEFAULT');

/** Customer group by name, created when missing. */
function demoGroupId(string $name, int $idLang): int
{
    foreach (Group::getGroups($idLang) as $group) {
        if ($group['name'] === $name) {
            return (int) $group['id_group'];
        }
    }
    $group = new Group();
    foreach (Language::getIDs(false) as $id) {
        $group->name[(int) $id] = $name;
    }
    $group->reduction = 0;
    $group->price_display_method = 1; // tax excluded, as for professionals
    $group->show_prices = 1;
    $group->add();

    return (int) $group->id;
}

/** Reference of a carrier by name (the rules follow the reference, not the id). */
function demoCarrierReference(string $name, int $idLang): int
{
    foreach (Carrier::getCarriers($idLang, true, false, false, null, Carrier::ALL_CARRIERS) as $carrier) {
        if ($carrier['name'] === $name) {
            return (int) $carrier['id_reference'];
        }
    }

    return 0;
}

function demoZoneId(string $name): int
{
    foreach (Zone::getZones(true) as $zone) {
        if ($zone['name'] === $name) {
            return (int) $zone['id_zone'];
        }
    }

    return 0;
}

$standard = demoCarrierReference(CARRIER_STANDARD, $idLang);
$pickup = demoCarrierReference(CARRIER_PICKUP, $idLang);
$europe = demoZoneId(ZONE_EUROPE);
$northAmerica = demoZoneId(ZONE_NORTH_AMERICA);
$country = (int) Country::getByIso(COUNTRY_ISO);
$pros = demoGroupId(GROUP_PROS, $idLang);
$currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');

// Created in this order: the position is the priority, the first rule matching a cart is the only one applied.
$rules = [
    [
        'label' => 'Pros : frais de port à moitié prix',
        'id_group' => $pros,
        'rule_type' => ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT,
        'value' => 50,
    ],
    [
        'label' => 'Livraison offerte dès 60 € en France',
        'id_carrier' => $standard,
        'id_zone' => $europe,
        'id_country' => $country,
        'minimum_amount' => 60,
        'minimum_amount_tax' => 1,
        'minimum_amount_currency' => $currency,
        'maximum_amount_tax' => 1,
        'maximum_amount_currency' => $currency,
        'rule_type' => ShippingRulesClass::RULE_TYPE_FREE,
    ],
    [
        'label' => 'Supplément Corse',
        'id_carrier' => $standard,
        'id_zone' => $europe,
        'id_country' => $country,
        'postcode_list' => '20000,20090,20100,20110,20112,20137,20200,20600',
        'rule_type' => ShippingRulesClass::RULE_TYPE_ADDITIONAL,
        'value' => 6,
    ],
    [
        'label' => 'Colis lourds : +12 € dès 20 kg',
        'id_carrier' => $standard,
        'minimum_weight' => 20,
        'rule_type' => ShippingRulesClass::RULE_TYPE_ADDITIONAL,
        'value' => 12,
    ],
    [
        'label' => 'Retrait impossible au-delà de 30 kg',
        'id_carrier' => $pickup,
        'minimum_weight' => 30,
        'rule_type' => ShippingRulesClass::RULE_TYPE_DISABLE,
    ],
    [
        'label' => 'Tarif unique 4,90 € dès 3 articles',
        'id_carrier' => $standard,
        'id_zone' => $europe,
        'minimum_quantity' => 3,
        'rule_type' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT,
        'value' => 4.9,
    ],
    [
        'label' => 'Vendredi 18h-20h : 3 € de moins',
        'days' => '5',
        'hour_from' => '18:00',
        'hour_to' => '20:00',
        'rule_type' => ShippingRulesClass::RULE_TYPE_REDUCTION,
        'value' => 3,
    ],
    [
        'label' => 'Export Amérique du Nord : +15 %',
        'id_zone' => $northAmerica,
        'rule_type' => ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT,
        'value' => 15,
    ],
    [
        'label' => 'Soldes d\'été : livraison offerte',
        'from' => date('Y-06-25 00:00:00', strtotime('+1 year')),
        'to' => date('Y-07-31 23:59:59', strtotime('+1 year')),
        'active' => 0,
        'rule_type' => ShippingRulesClass::RULE_TYPE_FREE,
    ],
];

$db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'shipping_rule_shop`');
$db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'shipping_rule`');

foreach ($rules as $values) {
    $label = $values['label'];
    unset($values['label']);

    $rule = new ShippingRulesClass();
    $rule->from = date('Y-m-d 00:00:00', strtotime('-1 month'));
    $rule->to = date('Y-m-d 23:59:59', strtotime('+1 year'));
    $rule->active = 1;
    foreach ($values as $property => $value) {
        $rule->$property = $value;
    }
    if (!$rule->add()) {
        fwrite(STDERR, "Rule not created: $label\n");
        exit(1);
    }
    echo '#' . $rule->id . ' ' . $label . "\n";
}
