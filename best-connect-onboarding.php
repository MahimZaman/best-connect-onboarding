<?php

/**
 * Plugin Name:       Best Connect - Onboarding
 * Plugin URI:        https://example.com
 * Description:       A powerful multi-step onboarding builder for WordPress.
 * Version:           1.9.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Best Connect
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       best-connect-onboarding
 * Domain Path:       /languages
 */

defined('ABSPATH') || exit;
define('BCOB_VER', '1.9.0');
define('BCOB_PATH', plugin_dir_path(__FILE__));
define('BCOB_URL', plugin_dir_url(__FILE__));
define('BCOB_DIR', __FILE__);

if (file_exists(BCOB_PATH . '/vendor/autoload.php')) {
    require_once BCOB_PATH . '/vendor/autoload.php';
}

// Initializing Text Domain for Translating 
add_action(
    'plugins_loaded',
    function () {
        load_plugin_textdomain('best-connect-onboarding', false, BCOB_PATH . '/languages');
    }
);


require_once BCOB_PATH . 'src/functions.php';
require_once BCOB_PATH . 'src/Core/Loader.php';
BestConnectOnboarding\Core\Loader::register();
new BestConnectOnboarding\Core\Plugin();
