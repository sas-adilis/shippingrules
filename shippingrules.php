<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/ShippingRulesClass.php';

class ShippingRules extends Module
{
    public function __construct()
    {
        $this->name = 'shippingrules';
        $this->author = 'Adilis';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tab = 'shipping_logistics';
        $this->version = '1.3.1';
        $this->displayName = $this->l('Shipping Rules');
        $this->description = $this->l('Create shipping rules based on country, zone, amount, date and carrier.');
        $this->confirmUninstall = $this->l('Are you sure ?');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->module_key = '200007ae5a749e9922e27bee45ff215c';

        parent::__construct();
    }

    public function install(): bool
    {
        if (file_exists($this->getLocalPath() . 'sql/install.php')) {
            $sql_result = require_once $this->getLocalPath() . 'sql/install.php';
            if ($sql_result === false) {
                return false;
            }
        }

        return
            parent::install()
            && $this->installLegacyOverride()
            && $this->installTab()
            && $this->registerHook('actionGetPackageShippingCost')
            && $this->registerHook('actionCartGetPackageShippingCost');
    }

    /**
     * Since PrestaShop 9.2 the core fires actionCartGetPackageShippingCost itself (getPackageShippingCostValue()
     * is the refactoring that comes with it): the override of Cart, which fires the legacy hook, is not needed anymore.
     */
    public static function hasNativeShippingCostHook(): bool
    {
        return method_exists('CartCore', 'getPackageShippingCostValue');
    }

    /**
     * Before PrestaShop 9.2 the core does not fire the shipping cost hook: a small override of Cart does, and is
     * installed on these versions only.
     * The file waits outside override/, so the automatic install of overrides (which would put it on every version)
     * does not see it.
     */
    public function installLegacyOverride(): bool
    {
        return self::hasNativeShippingCostHook() || $this->withLegacyOverrideFile(function () {
            return $this->addOverride('Cart');
        });
    }

    /**
     * Removes the override whatever the version: an installation upgraded from a version that put it everywhere
     * may still hold it.
     */
    public function removeLegacyOverride(): bool
    {
        if (!is_file(_PS_OVERRIDE_DIR_ . 'classes/Cart.php')) {
            return true;
        }

        return $this->withLegacyOverrideFile(function () {
            return $this->removeOverride('Cart');
        });
    }

    /**
     * Runs an action that reads the override in override/, which only holds the file meanwhile.
     */
    private function withLegacyOverrideFile(callable $action): bool
    {
        $source = __DIR__ . '/override_legacy/classes/Cart.php';
        $directory = __DIR__ . '/override/classes';
        $target = $directory . '/Cart.php';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
            $this->_errors[] = $this->l('The override folder of the module is not writable.');

            return false;
        }
        if (!@copy($source, $target)) {
            $this->_errors[] = $this->l('The override of the shipping cost could not be prepared.');

            return false;
        }

        try {
            return (bool) $action();
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();

            return false;
        } finally {
            @unlink($target);
            @rmdir($directory);
            @rmdir(__DIR__ . '/override');
        }
    }

    /**
     * Whether the override of Cart installed by an older version of the module is still in place: it fires the
     * legacy hook, so the native one must stay silent or the rules would be applied twice.
     */
    protected function isLegacyOverrideActive(): bool
    {
        static $active = null;

        if ($active === null) {
            $method = new ReflectionMethod('Cart', 'getPackageShippingCost');
            $file = $method->getFileName();
            $active = $method->getDeclaringClass()->getName() === 'Cart'
                && $file !== false
                && strpos((string) file_get_contents($file), 'actionGetPackageShippingCost') !== false;
        }

        return $active;
    }

    /**
     * Native hook of PrestaShop 9.2 and later.
     *
     * @param array<string, mixed> $params
     */
    public function hookActionCartGetPackageShippingCost(array $params): void
    {
        if ($this->isLegacyOverrideActive()) {
            return;
        }

        $params['shipping_cost'] = &$params['shippingCost'];
        $this->hookActionGetPackageShippingCost($params);
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall(): bool
    {
        return
            $this->uninstallTab()
            && $this->removeLegacyOverride()
            && parent::uninstall();
    }

    /**
     * @throws PrestaShopException
     */
    private function installTab(): bool
    {
        if (!Tab::getIdFromClassName('AdminShippingRules')) {
            $tab = new Tab();
            $tab->name = [];
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = $this->displayName;
            }
            $tab->class_name = 'AdminShippingRules';
            $tab->module = $this->name;
            $tab->id_parent = Tab::getIdFromClassName('AdminParentShipping');
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function uninstallTab(): bool
    {
        if ($id_tab = Tab::getIdFromClassName('AdminShippingRules')) {
            $tab = new Tab($id_tab);
            if (!$tab->delete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     * @throws Exception
     */
    public function hookActionGetPackageShippingCost(array $params): void
    {
        if ($params['shipping_cost'] === false) {
            return;
        }

        /** @var Cart $cart */
        $cart = $params['cart'];
        if ($cart->isVirtualCart()) {
            return;
        }

        $id_carrier = (int) $params['id_carrier'];
        $use_tax = !empty($params['use_tax']);
        $product_list = !empty($params['product_list']) && is_array($params['product_list']) ? $params['product_list'] : null;
        $address_products = $product_list === null ? $cart->getProducts() : $product_list;

        // Address
        $address_id = 0;
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
            $address_id = (int) $cart->id_address_invoice;
        } elseif (count($address_products)) {
            $prod = current($address_products);
            $address_id = (int) (isset($prod['id_address_delivery']) ? $prod['id_address_delivery'] : $cart->id_address_delivery);
        }

        $address = null;
        if ($address_id && Address::addressExists($address_id)) {
            $address = Address::initialize($address_id, true);
            $id_country = (int) $address->id_country;
            $id_zone = (int) Address::getZoneById((int) $address->id);
        } else {
            $country = $params['default_country'];
            if (!$country) {
                $country = Context::getContext()->country;
            }
            $id_country = (int) $country->id;
            $id_zone = (int) $country->id_zone;
        }

        if (!empty($params['id_zone'])) {
            $id_zone = (int) $params['id_zone'];
        }

        // Carrier
        if (empty($id_carrier)) {
            $id_carrier = (int) Configuration::get('PS_CARRIER_DEFAULT');
        }

        $carrier = new Carrier($id_carrier);
        if (!Validate::isLoadedObject($carrier)) {
            return;
        }

        $has_customer = Validate::isLoadedObject($this->context->customer);
        $id_customer = $has_customer ? (int) $this->context->customer->id : 0;
        $id_shop = (int) $this->context->shop->id;

        $groups = $has_customer ? $this->context->customer->getGroups() : [];
        $groups[] = 0;
        $groups = array_map('intval', $groups);
        sort($groups);

        $cache_id = 'ShippingRules::hookActionGetPackageShippingCost_' . $id_country . '_' . $id_zone;
        $cache_id .= '_' . $id_shop . '_' . $id_customer . '_' . implode('_', $groups);

        if (Cache::isStored($cache_id)) {
            $shipping_rules = Cache::retrieve($cache_id);
        } else {
            $query = new DbQuery();
            $query->select('sr.*');
            $query->from('shipping_rule', 'sr');
            if (Shop::isFeatureActive()) {
                $query->innerJoin('shipping_rule_shop', 'srs', 'srs.id_shipping_rule = sr.id_shipping_rule AND srs.id_shop = ' . $id_shop);
            }
            $query->where('sr.id_zone IN (' . $id_zone . ', 0)');
            $query->where('sr.id_country IN (' . $id_country . ', 0)');
            $query->where('sr.id_group IN (' . implode(',', $groups) . ')');
            $query->where('sr.id_customer IN (0, ' . $id_customer . ')');
            $query->where((Shop::isFeatureActive() ? 'srs' : 'sr') . '.active = 1');
            $query->where('sr.`from` <= NOW()');
            $query->where('sr.`to` >= NOW()');
            $query->orderBy('sr.position ASC');

            $shipping_rules = Db::getInstance()->executeS($query->build());
            Cache::store($cache_id, $shipping_rules);
        }

        if (empty($shipping_rules)) {
            return;
        }

        // Package amounts and weight (cart currency)
        $cart_currency = Currency::getCurrencyInstance((int) $cart->id_currency);
        $cart_amount_tax_excl = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $product_list);
        $cart_amount_tax_incl = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $product_list);
        $cart_weight = (float) $cart->getTotalWeight($product_list);

        foreach ($shipping_rules as $shipping_rule) {
            $cart_amount_tax = $shipping_rule['minimum_amount_tax'] ? $cart_amount_tax_incl : $cart_amount_tax_excl;
            if ((int) $shipping_rule['minimum_amount_currency']) {
                $rule_currency = Currency::getCurrencyInstance((int) $shipping_rule['minimum_amount_currency']);
                if (Validate::isLoadedObject($rule_currency)) {
                    $cart_amount_tax = Tools::convertPriceFull($cart_amount_tax, $cart_currency, $rule_currency);
                }
            }

            if (
                ((int) $shipping_rule['id_carrier'] === (int) $carrier->id_reference || (int) $shipping_rule['id_carrier'] === 0)
                && $cart_amount_tax >= $shipping_rule['minimum_amount']
                && ($shipping_rule['maximum_amount'] <= 0 || $cart_amount_tax <= $shipping_rule['maximum_amount'])
                && $cart_weight >= $shipping_rule['minimum_weight']
                && ($shipping_rule['maximum_weight'] <= 0 || $cart_weight <= $shipping_rule['maximum_weight'])
            ) {
                if (!empty($shipping_rule['postcode_list'])) {
                    $postcode_list = self::getCleanedPostcodeList($shipping_rule['postcode_list']);
                    if (!$address || !in_array(self::normalizePostcode((string) $address->postcode), array_map([self::class, 'normalizePostcode'], $postcode_list))) {
                        continue;
                    }
                }

                if (!$this->matchesCartConditions($shipping_rule, $address_products, $cart)) {
                    continue;
                }

                switch ((int) $shipping_rule['rule_type']) {
                    case ShippingRulesClass::RULE_TYPE_ADDITIONAL:
                        $params['shipping_cost'] += $this->getFixedAmount($shipping_rule['value'], $cart, $cart_currency, $carrier, $use_tax, $address);
                        break;
                    case ShippingRulesClass::RULE_TYPE_REDUCTION:
                        $params['shipping_cost'] -= $this->getFixedAmount($shipping_rule['value'], $cart, $cart_currency, $carrier, $use_tax, $address);
                        if ($params['shipping_cost'] < 0) {
                            $params['shipping_cost'] = 0;
                        }
                        break;
                    case ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT:
                        if ($params['shipping_cost'] > 0) {
                            $params['shipping_cost'] += $params['shipping_cost'] * $shipping_rule['value'] / 100;
                        }
                        break;
                    case ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT:
                        if ($params['shipping_cost'] > 0) {
                            $params['shipping_cost'] -= $params['shipping_cost'] * $shipping_rule['value'] / 100;
                        }
                        break;
                    case ShippingRulesClass::RULE_TYPE_FREE:
                        $params['shipping_cost'] = 0;
                        break;
                    case ShippingRulesClass::RULE_TYPE_DISABLE:
                        $params['shipping_cost'] = false;
                        break;
                    case ShippingRulesClass::RULE_TYPE_SET_AMOUNT:
                        $params['shipping_cost'] = $this->getFixedAmount($shipping_rule['value'], $cart, $cart_currency, $carrier, $use_tax, $address);
                        break;
                }

                if ($params['shipping_cost'] !== false) {
                    // The cost is in the currency of the cart (Currency::$precision exists since PrestaShop 1.7.6)
                    $precision = isset($cart_currency->precision) ? (int) $cart_currency->precision : (int) _PS_PRICE_COMPUTE_PRECISION_;
                    $params['shipping_cost'] = (float) Tools::ps_round((float) $params['shipping_cost'], $precision);
                }

                break; // Only apply the first matching rule
            }
        }
    }

    /**
     * Conditions on the content of the package (quantity, categories, manufacturers, cart rules) and on the moment.
     *
     * @param array<string, mixed> $shipping_rule
     * @param array<int, array<string, mixed>> $products
     */
    private function matchesCartConditions(array $shipping_rule, array $products, Cart $cart): bool
    {
        $quantity = 0;
        foreach ($products as $product) {
            $quantity += isset($product['cart_quantity']) ? (int) $product['cart_quantity'] : 0;
        }

        if ($quantity < (int) $shipping_rule['minimum_quantity']) {
            return false;
        }

        if ((int) $shipping_rule['maximum_quantity'] > 0 && $quantity > (int) $shipping_rule['maximum_quantity']) {
            return false;
        }

        $now = $this->getCurrentTimestamp();

        $days = self::getCleanedIdList($shipping_rule['days']);
        if ($days && !in_array((int) date('N', $now), $days, true)) {
            return false;
        }

        if (!empty($shipping_rule['hour_from']) && !empty($shipping_rule['hour_to'])) {
            $time = date('H:i', $now);
            $from = (string) $shipping_rule['hour_from'];
            $to = (string) $shipping_rule['hour_to'];
            $in_range = $from < $to ? ($time >= $from && $time < $to) : ($time >= $from || $time < $to);
            if (!$in_range) {
                return false;
            }
        }

        $categories = self::getCleanedIdList($shipping_rule['categories']);
        if ($categories) {
            $found = false;
            foreach ($products as $product) {
                if (array_intersect($categories, array_map('intval', Product::getProductCategories((int) $product['id_product'])))) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        $manufacturers = self::getCleanedIdList($shipping_rule['manufacturers']);
        if ($manufacturers) {
            $found = false;
            foreach ($products as $product) {
                if (isset($product['id_manufacturer']) && in_array((int) $product['id_manufacturer'], $manufacturers, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        $cart_rules = self::getCleanedIdList($shipping_rule['cart_rules']);
        if ($cart_rules) {
            // Cart::getCartRules() computes the value of each rule, which asks for the shipping cost again: read the table instead
            $applied = $cart->id ? Db::getInstance()->executeS(
                'SELECT `id_cart_rule` FROM `' . _DB_PREFIX_ . 'cart_cart_rule` WHERE `id_cart` = ' . (int) $cart->id
            ) : [];
            $applied = array_map('intval', array_column($applied ?: [], 'id_cart_rule'));
            if (!array_intersect($cart_rules, $applied)) {
                return false;
            }
        }

        return true;
    }

    protected function getCurrentTimestamp(): int
    {
        return time();
    }

    /**
     * Converts a rule amount (shop default currency, tax excl.) to the cart currency, with the carrier taxes if required.
     *
     * @param float|string $value
     */
    private function getFixedAmount($value, Cart $cart, Currency $cart_currency, Carrier $carrier, bool $use_tax, ?Address $address): float
    {
        $amount = (float) Tools::convertPriceFull((float) $value, Currency::getDefaultCurrency(), $cart_currency);

        if ($use_tax && !Tax::excludeTaxeOption()) {
            if (Configuration::get('PS_ATCP_SHIPWRAP')) {
                $amount *= 1 + $cart->getAverageProductsTaxRate();
            } else {
                $amount *= 1 + $carrier->getTaxesRate($address ?: Address::initialize(null)) / 100;
            }
        }

        return $amount;
    }

    public function getContent(): void
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminShippingRules'));
    }

    private static function normalizePostcode(string $postcode): string
    {
        return strtoupper(str_replace(' ', '', trim($postcode)));
    }

    /**
     * @return int[]
     */
    public static function getCleanedIdList(?string $list): array
    {
        $ids = array_map('intval', explode(',', (string) $list));

        return array_values(array_unique(array_filter($ids)));
    }

    public static function getCleanedPostcodeList(?string $postcode_list): array
    {
        $postcode_list = explode(',', (string) $postcode_list);
        $postcode_list = array_map('trim', $postcode_list);
        $postcode_list = array_filter($postcode_list);

        return array_unique($postcode_list);
    }
}
