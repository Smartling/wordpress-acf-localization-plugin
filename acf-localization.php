<?php
/**
 * @link              https://www.smartling.com
 * @since             1.0.0
 * @package           smartling-acf-localization
 * @wordpress-plugin
 * Plugin Name:       ACF localization
 * Description:       Extend Smartling Connector functionality to support ACF options page
 * Plugin URI:        https://www.smartling.com/translation-software/wordpress-translation-plugin/
 * Author URI:        https://www.smartling.com
 * License:           GPL-3.0+
 * Network:           true
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Version:           1.3.1
 * ConnectorRequiredMin: 1.5.0
 */

/**
 * Autoloader starts always
 */
if (!class_exists('\Smartling\ACF\Bootloader')) {
    require_once plugin_dir_path(__FILE__) . 'src/Bootloader.php';
}

/**
 * Execute ONLY for admin pages
 */
if (is_admin() || (defined('DOING_CRON') && true === DOING_CRON)){
    add_action('plugins_loaded', function () {
        add_action('smartling_before_init', function (\Symfony\Component\DependencyInjection\ContainerBuilder $di) {
            \Smartling\ACF\Bootloader::boot(__FILE__, $di);
        });
    });
}
