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
        $this->version = '1.2.4';
        $this->displayName = $this->l('Shipping Rules');
        $this->description = $this->l('Create shipping rules based on country, zone, amount, date and carrier.');
        $this->confirmUninstall = $this->l('Are you sure ?');
        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        $this->module_key = '200007ae5a749e9922e27bee45ff215c';

        parent::__construct();
    }

    public function install()
    {
        if (file_exists($this->getLocalPath() . 'sql/install.php')) {
            $sql_result = require_once $this->getLocalPath() . 'sql/install.php';
            if ($sql_result === false) {
                return false;
            }
        }

        return
            parent::install()
            && $this->installTab()
            && $this->registerHook('actionGetPackageShippingCost');
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        return
            parent::uninstall()
            && $this->uninstallTab();
    }

    private function installTab()
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
    public function uninstallTab()
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
    public function hookActionGetPackageShippingCost($params)
    {
        if ($params['shipping_cost'] === false) {
            return;
        }

        /** @var Cart $cart */
        $cart = $params['cart'];
        if ($cart->isVirtualCart()) {
            return;
        }

        $id_carrier = $params['id_carrier'];
        $use_tax = !empty($params['use_tax']);
        $product_list = !empty($params['product_list']) && is_array($params['product_list']) ? $params['product_list'] : null;
        $address_products = $product_list === null ? $cart->getProducts() : $product_list;

        // Address
        $address_id = 0;
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
            $address_id = (int) $cart->id_address_invoice;
        } elseif (count($address_products)) {
            $prod = current($address_products);
            $address_id = (int) $prod['id_address_delivery'];
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
            $id_carrier = Configuration::get('PS_CARRIER_DEFAULT');
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
            $query->where('sr.active = 1');
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
                ($shipping_rule['id_carrier'] == $carrier->id_reference || $shipping_rule['id_carrier'] == 0)
                && $cart_amount_tax >= $shipping_rule['minimum_amount']
                && ($shipping_rule['maximum_amount'] <= 0 || $cart_amount_tax <= $shipping_rule['maximum_amount'])
                && $cart_weight >= $shipping_rule['minimum_weight']
                && ($shipping_rule['maximum_weight'] <= 0 || $cart_weight <= $shipping_rule['maximum_weight'])
            ) {
                if (!empty($shipping_rule['postcode_list'])) {
                    $postcode_list = self::getCleanedPostcodeList($shipping_rule['postcode_list']);
                    if (!$address || !in_array($address->postcode, $postcode_list)) {
                        continue;
                    }
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
                    $context = Context::getContext();
                    $precision = method_exists($context, 'getComputingPrecision') ? $context->getComputingPrecision() : (int) _PS_PRICE_COMPUTE_PRECISION_;
                    $params['shipping_cost'] = (float) Tools::ps_round((float) $params['shipping_cost'], $precision);
                }

                break; // Only apply the first matching rule
            }
        }
    }

    /**
     * Converts a rule amount (shop default currency, tax excl.) to the cart currency, with the carrier taxes if required.
     *
     * @param float|string $value
     * @param Address|null $address
     */
    private function getFixedAmount($value, Cart $cart, Currency $cart_currency, Carrier $carrier, bool $use_tax, $address): float
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

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminShippingRules'));
    }

    public static function getCleanedPostcodeList($postcode_list): array
    {
        $postcode_list = explode(',', (string) $postcode_list);
        $postcode_list = array_map('trim', $postcode_list);
        $postcode_list = array_filter($postcode_list);

        return array_unique($postcode_list);
    }
}
