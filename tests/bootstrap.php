<?php
/**
 * Test bootstrap: constants, PrestaShop stand-ins, then the module classes.
 */
define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'ps_');

require __DIR__ . '/stubs.php';
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/shippingrules.php';
require_once dirname(__DIR__) . '/controllers/admin/AdminShippingRulesController.php';
