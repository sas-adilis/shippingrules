<?php
/**
 * Test bootstrap.
 *
 * Unit suite: constants, PrestaShop stand-ins, then the module classes.
 * Integration suite: a real PrestaShop with the module installed, found in
 * <shop>/modules/<module> or through PS_ROOT. Without a shop, the integration tests are skipped.
 */
$psRoot = getenv('PS_ROOT') ?: dirname(__DIR__, 3);
define('SR_REAL_SHOP', is_file(rtrim((string) $psRoot, '/') . '/config/config.inc.php'));

if (SR_REAL_SHOP) {
    require rtrim((string) $psRoot, '/') . '/config/config.inc.php';
    // The datasets of the tests use the constants of the module before any module is loaded
    require_once dirname(__DIR__) . '/shippingrules.php';

    return;
}

define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'ps_');

require __DIR__ . '/stubs.php';
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/shippingrules.php';
require_once dirname(__DIR__) . '/controllers/admin/AdminShippingRulesController.php';

/**
 * The module with a controllable clock.
 */
class TestableShippingRules extends ShippingRules
{
    /** @var int|null */
    public static $now;

    /** @var bool */
    public static $legacyOverride = false;

    protected function isLegacyOverrideActive(): bool
    {
        return self::$legacyOverride;
    }

    protected function getCurrentTimestamp(): int
    {
        return self::$now ?? time();
    }
}
