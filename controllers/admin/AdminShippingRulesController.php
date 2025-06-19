<?php
/**
 * @author    Adilis <support@adilis.fr>
 * @copyright 2024 SAS Adilis
 * @license   http://www.adilis.fr
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminShippingRulesController extends ModuleAdminController
{

    protected $position_identifier = 'id_shipping_rule';

    public function __construct()
    {
        require_once __DIR__ . '/../../classes/ShippingRulesClass.php';

        $this->className = 'ShippingRulesClass';
        $this->table = 'shipping_rule';
        $this->primary_key = 'id_shipping_rule';
        $this->list_id = 'shipping_rule';
        $this->bootstrap = true;
        $this->_defaultOrderBy = 'position';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        $this->_select = 'co_l.name country_name, ca.name carrier_name, z.name zone_name';
        $this->_join = '
            LEFT JOIN ' . _DB_PREFIX_ . 'carrier ca ON (ca.id_reference = a.id_carrier AND deleted=0)
            LEFT JOIN ' . _DB_PREFIX_ . 'country_lang co_l ON (co_l.id_country = a.id_country AND co_l.id_lang=' . (int) Context::getContext()->cookie->id_lang . ')
            LEFT JOIN ' . _DB_PREFIX_ . 'zone z ON z.id_zone = a.id_zone
		';
        $this->_use_found_rows = false;

        parent::__construct();

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Delete selected', [], 'Admin.Actions'),
                'confirm' => $this->trans('Delete selected items?', [], 'Admin.Notifications.Warning'),
                'icon' => 'icon-trash',
            ],
        ];

        $this->fields_list = [
            'id_shipping_rule' => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'carrier_name' => [
                'title' => $this->l('Carrier'),
                'align' => 'center',
                'filter_key' => 'ca!name',
                'type' => 'select',
                'list' => array_reduce(
                    Carrier::getCarriers(Context::getContext()->cookie->id_lang, true, 0, false, null, Carrier::ALL_CARRIERS),
                    function ($carry, $item) {
                        $carry[$item['id_carrier']] = $item['name'];

                        return $carry;
                    },
                    []
                ),
                'callback' => 'getCarrierName',
            ],
            'zone_name' => [
                'title' => $this->l('Zone'),
                'align' => 'center',
                'filter_key' => 'z!id_zone',
                'type' => 'select',
                'list' => array_reduce(
                    Zone::getZones(Context::getContext()->cookie->id_lang),
                    function ($carry, $item) {
                        $carry[$item['id_zone']] = $item['name'];

                        return $carry;
                    },
                    []
                ),
                'callback' => 'getZoneName',
            ],
            'country_name' => [
                'title' => $this->l('Country'),
                'align' => 'center',
                'type' => 'select',
                'filter_key' => 'co_l!id_country',
                'list' => array_reduce(
                    Country::getCountries(Context::getContext()->cookie->id_lang),
                    function ($carry, $item) {
                        $carry[$item['id_country']] = $item['name'];

                        return $carry;
                    },
                    []
                ),
                'callback' => 'getCountryName',
            ],
            'rule_type' => [
                'type' => 'select',
                'title' => $this->l('Rule type'),
                'list' => [
                    ShippingRulesClass::RULE_TYPE_FREE => $this->l('Free shipping'),
                    ShippingRulesClass::RULE_TYPE_ADDITIONAL => $this->l('Additional cost (amount)'),
                    ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT => $this->l('Additional cost (percent)'),
                    ShippingRulesClass::RULE_TYPE_DISABLE => $this->l('Disable carrier'),
                ],
                'filter_key' => 'a!rule_type',
                'callback' => 'getRuleTypeName',
            ],
            'from' => [
                'title' => $this->l('Beginning'),
                'align' => 'right',
                'type' => 'datetime',
            ],
            'to' => [
                'title' => $this->l('End'),
                'align' => 'right',
                'type' => 'datetime',
            ],
            'position' => [
                'title' => $this->l('Priority'),
                'filter_key' => 'a!position',
                'align' => 'center',
                'class' => 'fixed-width-xs',
                'position' => 'position',
            ],
            'active' => [
                'title' => $this->l('Active'),
                'active' => 'status',
                'type' => 'bool',
                'class' => 'fixed-width-xs',
                'align' => 'center',
                'ajax' => true,
                'orderby' => false,
            ],
        ];
    }

    public function getCarrierName($carrier, $tr)
    {
        if ((int) $tr['id_carrier'] == 0) {
            return $this->l('All carriers');
        }

        return $carrier;
    }

    public function getCountryName($country, $tr)
    {
        if ((int) $tr['id_country'] == 0) {
            return $this->l('All countries');
        }

        return $country;
    }

    public function getZoneName($zone, $tr)
    {
        if ((int) $tr['id_zone'] == 0) {
            return $this->l('All zones');
        }

        return $zone;
    }

    public function getRuleTypeName($rule_type, $tr)
    {
        switch ($rule_type) {
            case ShippingRulesClass::RULE_TYPE_FREE:
                return $this->l('Free shipping');
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL:
                return $this->l('Additional cost (amount)');
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT:
                return $this->l('Additional cost (percent)');
            case ShippingRulesClass::RULE_TYPE_DISABLE:
                return $this->l('Disable carrier');
            case ShippingRulesClass::RULE_TYPE_SET_AMOUNT:
                return $this->l('New amount');
        }
    }

    public function renderForm()
    {
        $carriers = Carrier::getCarriers($this->context->cookie->id_lang, true, 0, false, null, Carrier::ALL_CARRIERS);
        $countries = Country::getCountries($this->context->cookie->id_lang, false);
        $zones = Zone::getZones($this->context->cookie->id_lang, false);
        $currencies = Currency::getCurrencies();

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Shipping rule'),
                'icon' => 'icon-group',
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
            'input' => [
                [
                    'type' => 'separator',
                    'col' => 12,
                    'name' => '',
                    'content' => $this->l('Carrier and location'),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Carrier'),
                    'name' => 'id_carrier',
                    'options' => [
                        'default' => ['value' => 0, 'label' => $this->l('All carriers')],
                        'query' => $carriers,
                        'id' => 'id_reference',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Zone'),
                    'name' => 'id_zone',
                    'options' => [
                        'default' => ['value' => 0, 'label' => $this->l('All zones')],
                        'query' => $zones,
                        'id' => 'id_zone',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Country'),
                    'name' => 'id_country',
                    'options' => [
                        'default' => ['value' => 0, 'label' => $this->l('All countries')],
                        'query' => $countries,
                        'id' => 'id_country',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'textarea',
                    'name' => 'postcode_list',
                    'label' => $this->l('Postcode list'),
                    'desc' => $this->l('List of postcodes separated by commas. Leave empty to apply to all postcodes.'),
                ],
                [
                    'type' => 'separator',
                    'col' => 12,
                    'name' => '',
                    'content' => $this->l('Action'),
                ],
                [
                    'type' => 'select',
                    'name' => 'rule_type',
                    'label' => $this->l('Rule type'),
                    'required' => true,
                    'options' => [
                        'query' => [
                            ['id' => ShippingRulesClass::RULE_TYPE_FREE, 'name' => $this->l('Free shipping')],
                            ['id' => ShippingRulesClass::RULE_TYPE_SET_AMOUNT, 'name' => $this->l('New amount')],
                            ['id' => ShippingRulesClass::RULE_TYPE_ADDITIONAL, 'name' => $this->l('Additional cost (amount)')],
                            ['id' => ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT, 'name' => $this->l('Additional cost (percent)')],
                            ['id' => ShippingRulesClass::RULE_TYPE_REDUCTION, 'name' => $this->l('Cost reduction (amount)')],
                            ['id' => ShippingRulesClass::RULE_TYPE_REDUCTION_PERCENT, 'name' => $this->l('Cost reduction (percent)')],
                            ['id' => ShippingRulesClass::RULE_TYPE_DISABLE, 'name' => $this->l('Disable carrier')],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('New amount'),
                    'name' => 'new_amount',
                    'maxlength' => 10,
                    'suffix' => $this->context->currency->getSign('right') . ' ' . $this->l('(tax excl.)'),
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Impact amount'),
                    'name' => 'impact_amount',
                    'maxlength' => 10,
                    'suffix' => $this->context->currency->getSign('right') . ' ' . $this->l('(tax excl.)'),
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Impact percent'),
                    'name' => 'impact_percent',
                    'maxlength' => 10,
                    'suffix' => '%',
                    'required' => true,
                ],
                [
                    'type' => 'separator',
                    'col' => 12,
                    'name' => '',
                    'content' => $this->l('Conditions'),
                ],
                [
                    'type' => 'select',
                    'name' => 'id_group',
                    'id' => 'id_group',
                    'label' => $this->l('Customer group'),
                    'required' => true,
                    'options' => [
                        'default' => ['value' => 0, 'label' => $this->l('All groups')],
                        'query' => Group::getGroups($this->context->cookie->id_lang, true),
                        'id' => 'id_group',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'hidden',
                    'name' => 'id_customer',
                ],
                [
                    'type' => 'text',
                    'name' => 'customer_filter',
                    'label' => $this->l('Limit to one customer'),
                    'required' => true,
                ],
                [
                    'type' => 'amount_taxes',
                    'label' => $this->l('From price'),
                    'name' => 'minimum_amount',
                    'maxlength' => 10,
                    'suffix' => $this->context->currency->getSign('right'),
                    'required' => true,
                    'currencies' => $currencies,
                ],
                [
                    'type' => 'amount_taxes',
                    'label' => $this->l('To price'),
                    'name' => 'maximum_amount',
                    'maxlength' => 10,
                    'suffix' => $this->context->currency->getSign('right'),
                    'required' => true,
                    'currencies' => $currencies,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('From weight'),
                    'name' => 'minimum_weight',
                    'class' => 'fixed-width-md',
                    'maxlength' => 10,
                    'suffix' => Configuration::get('PS_WEIGHT_UNIT'),
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('To weight'),
                    'name' => 'maximum_weight',
                    'class' => 'fixed-width-md',
                    'maxlength' => 10,
                    'suffix' => Configuration::get('PS_WEIGHT_UNIT'),
                    'required' => true,
                ],
                [
                    'type' => 'datetime',
                    'label' => $this->l('From'),
                    'required' => true,
                    'name' => 'from',
                ],
                [
                    'type' => 'datetime',
                    'label' => $this->l('To'),
                    'required' => true,
                    'name' => 'to',
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Active', [], 'Admin.Global'),
                    'name' => 'active',
                    'required' => false,
                    'class' => 't',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', [], 'Admin.Global'),
                        ],
                        [
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', [], 'Admin.Global'),
                        ],
                    ],
                ],
            ],
        ];

        $this->fields_value['minimum_amount_tax'] = $this->object->minimum_amount_tax;
        $this->fields_value['minimum_amount_currency'] = $this->object->minimum_amount_currency;
        $this->fields_value['maximum_amount_tax'] = $this->object->maximum_amount_tax;
        $this->fields_value['maximum_amount_currency'] = $this->object->maximum_amount_currency;

        $this->fields_value['impact_amount'] = $this->fields_value['impact_percent'] = $this->fields_value['new_amount'] = 0;
        switch ($this->object->rule_type) {
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL:
                $this->fields_value['impact_amount'] = $this->object->value;
                break;
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT:
                $this->fields_value['impact_percent'] = $this->object->value;
                break;
            case ShippingRulesClass::RULE_TYPE_SET_AMOUNT:
                $this->fields_value['new_amount'] = $this->object->value;
                break;
        }

        $this->fields_value['customer_filter'] = '';
        if (Validate::isUnsignedId($this->object->id_customer)
            && Validate::isLoadedObject($customer = new Customer($this->object->id_customer))
        ) {
            $this->fields_value['customer_filter'] = $customer->firstname . ' ' . $customer->lastname . ' (' . $customer->email . ')';
        }

        if (Shop::isFeatureActive()) {
            $this->fields_form['input'][] = [
                'type' => 'shop',
                'label' => $this->trans('Shop association', [], 'Admin.Global'),
                'name' => 'checkBoxShopAsso',
            ];
        }

        return parent::renderForm();
    }

    public function _childValidation()
    {
        if (Tools::getValue('minimum_amount') > Tools::getValue('maximum_amount')) {
            $this->errors[] = $this->l('The minimum amount must be less than the maximum amount.');
        }

        if (Tools::getValue('minimum_weight') > Tools::getValue('maximum_weight')) {
            $this->errors[] = $this->l('The minimum weight must be less than the maximum weight.');
        }

        if (Tools::getValue('from') >= Tools::getValue('to')) {
            $this->errors[] = $this->l('The start date must be less than the end date.');
        }

        if (Tools::getValue('minimum_amount_tax') != Tools::getValue('maximum_amount_tax')) {
            $this->errors[] = $this->l('The minimum amount tax must be equal to the maximum amount tax.');
        }

        if (Tools::getValue('minimum_amount_currency') != Tools::getValue('maximum_amount_currency')) {
            $this->errors[] = $this->l('The minimum amount currency must be equal to the maximum amount currency.');
        }

        switch (Tools::getValue('rule_type')) {
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL:
                if (Tools::getValue('impact_amount') <= 0 || !Validate::isPrice(Tools::getValue('impact_amount'))) {
                    $this->errors[] = $this->l('The impact amount is incorrect.');
                }
                break;
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT:
                if (Tools::getValue('impact_percent') <= 0 || !Validate::isPercentage(Tools::getValue('impact_percent'))) {
                    $this->errors[] = $this->l('The impact percent is incorrect.');
                }
                break;
            case ShippingRulesClass::RULE_TYPE_SET_AMOUNT:
                if (Tools::getValue('new_amount') <= 0 || !Validate::isPrice(Tools::getValue('new_amount'))) {
                    $this->errors[] = $this->l('The new amount is incorrect.');
                }
                break;
        }

        if ((int) Tools::getValue('id_zone') && (int) Tools::getValue('id_country')) {
            $postcode_list = ShippingRules::getCleanedPostcodeList(Tools::getValue('postcode_list'));
            foreach ($postcode_list as $postcode) {
                if (!Validate::isPostCode($postcode)) {
                    $this->errors[] = sprintf(
                        $this->l('The postcode %s is incorrect.'),
                        $postcode
                    );
                    break;
                }
            }
        }
    }

    public function copyFromPost(&$object, $table)
    {
        parent::copyFromPost($object, $table);
        $object->value = 0;
        switch (Tools::getValue('rule_type')) {
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL:
                $object->value = (float) Tools::getValue('impact_amount');
                break;
            case ShippingRulesClass::RULE_TYPE_ADDITIONAL_PERCENT:
                $object->value = (float) Tools::getValue('impact_percent');
                break;
            case ShippingRulesClass::RULE_TYPE_SET_AMOUNT:
                $object->value = (float) Tools::getValue('new_amount');
                break;
        }

        if ((int) $object->id_zone && (int) $object->id_country) {
            $object->postcode_list = implode(',', ShippingRules::getCleanedPostcodeList(Tools::getValue('postcode_list')));
        } else {
            $object->postcode_list = '';
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJS(__DIR__ . '/../../views/js/admin.js');
        $this->addCSS(__DIR__ . '/../../views/css/admin.css');
        $this->addJqueryPlugin('autocomplete');
        Media::addJsDef([
            'adminCartRulesToken' => Tools::getAdminTokenLite('AdminCartRules'),
        ]);
    }

    public function ajaxProcessUpdatePositions()
    {
        if ($this->access('edit')) {
            $way = (bool) Tools::getValue('way');
            $id_shipping_rule = (int) Tools::getValue('id');
            $positions = Tools::getValue('shipping_rule');

            $new_positions = [];
            foreach ($positions as $v) {
                if (!empty($v)) {
                    $new_positions[] = $v;
                }
            }

            foreach ($new_positions as $position => $value) {
                $pos = explode('_', $value);

                if (isset($pos[2]) && (int) $pos[2] === $id_shipping_rule) {
                    $shipping_rule = new ShippingRulesClass((int) $pos[2]);
                    if (Validate::isLoadedObject($shipping_rule)) {
                        if ($shipping_rule->updatePosition($way, $position, $id_shipping_rule)) {
                            echo 'ok position ' . (int) $position . ' for feature ' . (int) $pos[1] . '\r\n';
                        } else {
                            echo '{"hasError" : true, "errors" : "Can not update feature ' . (int) $id_shipping_rule . ' to position ' . (int) $position . ' "}';
                        }
                    } else {
                        echo '{"hasError" : true, "errors" : "This feature (' . (int) $id_shipping_rule . ') can t be loaded"}';
                    }

                    break;
                }
            }
        }
    }
}
