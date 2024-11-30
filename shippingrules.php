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
        $this->version = '1.1.5';
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
            require_once $this->getLocalPath() . 'sql/install.php';
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
        $id_carrier = $params['id_carrier'];
        $product_list = $cart->getProducts();

        // Address
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
            $address_id = (int) $cart->id_address_invoice;
        } elseif (count($product_list)) {
            $prod = current($product_list);
            $address_id = (int) $prod['id_address_delivery'];
        } else {
            $address_id = null;
        }

        if (!Address::addressExists($address_id)) {
            $country = $params['default_country'];
            if (!$country) {
                $country = Context::getContext()->country;
            }
            $id_country = $country->id;
            $id_zone = $country->id_zone;
        } else {
            $address = Address::initialize($address_id, true);
            $id_country = $address->id_country;
            $id_zone = Address::getZoneById((int) $address->id);
        }

        // Carrier
        if (empty($id_carrier)) {
            $id_carrier = Configuration::get('PS_CARRIER_DEFAULT');
        }

        $carrier = new Carrier($id_carrier);
        if (!Validate::isLoadedObject($carrier)) {
            return;
        }


        $groups = Validate::isLoadedObject($this->context->customer) ? $this->context->customer->getGroups() : [];
        $groups[] = 0;
        sort($groups);

        $cache_id = 'ShippingRules::hookActionGetPackageShippingCost_' . (int) $id_country . '_' . (int) $id_zone;
        $cache_id .= '_' . implode('_', $groups);

        if (Cache::isStored($cache_id)) {
            $shipping_rules = Cache::retrieve($cache_id);
        } else {
            $query = new DbQuery();
            $query->select('*');
            $query->from('shipping_rule');
            $query->where('id_zone IN (' . (int) $id_zone . ', 0)');
            $query->where('id_country IN (' . (int) $id_country . ', 0)');
            $query->where('id_group IN (' . implode(',', array_map('intval', $groups)) . ')');
            if (Validate::isLoadedObject($this->context->customer)) {
                $query->where('id_customer IN (0, ' . (int) $this->context->customer->id . ')');
            } else {
                $query->where('id_customer = 0');
            }
            $query->where('active = 1');
            $query->where('`from` <= NOW()');
            $query->where('`to` >= NOW()');
            $shipping_rules = Db::getInstance()->executeS($query->build());
            Cache::store($cache_id, $shipping_rules);
        }

        $cart_amount_tax_excl = $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS);
        $cart_amount_tax_incl = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        $cart_weight = $cart->getTotalWeight();

        foreach ($shipping_rules as $shipping_rule) {
            $cart_amount_tax = $shipping_rule['minimum_amount_tax'] ? $cart_amount_tax_incl : $cart_amount_tax_excl;
            $cart_amount_tax = Tools::convertPrice($cart_amount_tax, $shipping_rule['minimum_amount_currency']);

            if (
                ($shipping_rule['id_carrier'] == $carrier->id_reference || $shipping_rule['id_carrier'] == 0)
                && $cart_amount_tax >= $shipping_rule['minimum_amount']
                && $cart_amount_tax <= $shipping_rule['maximum_amount']
                && $cart_weight >= $shipping_rule['minimum_weight']
                && $cart_weight <= $shipping_rule['maximum_weight']
            ) {
                if (!empty($shipping_rule['postcode_list'])) {
                    $postcode_list = self::getCleanedPostcodeList($shipping_rule['postcode_list']);
                    if (!isset($address) || !Validate::isLoadedObject($address)) {
                        $address = new Address($address_id);
                    }
                    if (!in_array($address->postcode, $postcode_list)) {
                        continue;
                    }
                }

                switch ($shipping_rule['rule_type']) {
                    case ShippingRulesClass::RULE_TYPE_ADDITIONAL:
                        $params['shipping_cost'] += $shipping_rule['value'];
                        break;
                    case ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT:
                        if ($params['shipping_cost'] > 0) {
                            $params['shipping_cost'] += $params['shipping_cost'] * $shipping_rule['value'] / 100;
                        }
                        break;
                    case ShippingRulesClass::RULE_TYPE_FREE:
                        $params['shipping_cost'] = 0;
                        break;
                    case ShippingRulesClass::RULE_TYPE_DISABLE:
                        $params['shipping_cost'] = false;
                        break;
                    case ShippingRulesClass::RULE_TYPE_SET_AMOUNT:
                        $params['shipping_cost'] = $shipping_rule['value'];
                        break;
                }
            }
        }
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminShippingRules'));
    }

    public static function getCleanedPostcodeList($postcode_list): array
    {
        $postcode_list = explode(',', $postcode_list);
        $postcode_list = array_map('trim', $postcode_list);
        $postcode_list = array_filter($postcode_list);

        return array_unique($postcode_list);
    }
}
