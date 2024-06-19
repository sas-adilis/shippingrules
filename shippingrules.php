<?php
class ShippingRules extends Module
{
    function __construct()
    {
        $this->name = 'shippingrules';
        $this->author = 'Adilis';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->displayName = $this->l('Shipping Rules');
        $this->description = $this->l('Create shipping rules based on country, zone, amount, date and carrier.');
        $this->confirmUninstall = $this->l('Are you sure ?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        parent::__construct();
    }

    public function install()
    {
        if (file_exists($this->getLocalPath().'sql/install.php')) {
            require_once($this->getLocalPath().'sql/install.php');
        }

        $hooks = [];
        return parent::install() && $this->registerHook($hooks);
    }




}