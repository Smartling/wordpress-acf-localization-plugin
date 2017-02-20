<?php
/**
  * @link              https://www.smartling.com
  * @since             1.0.0
  * @package           acf-localization
  * @wordpress-plugin
  * Plugin Name:       ACF localization
  * Description:       Extend Smartling Connector functionality to support ACF options page
  * Plugin URI:        https://www.smartling.com/translation-software/wordpress-translation-plugin/
  * Author URI:        https://www.smartling.com
  * License:           GPL-3.0+
  * Network:           true
  * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
  * ConnectorRequiredMin: 4.1
  * Version: 1.0
*/

/**
 * Autoloader starts always
 */
if (!class_exists('\Smartling\Bootloader')) {
    require_once plugin_dir_path(__FILE__) . 'src/Bootloader.php';
}

/**
 * Execute ONLY for admin pages
 */
if (is_admin()){
    add_action('plugins_loaded', function () {
        add_action('smartling_before_init', function (\Symfony\Component\DependencyInjection\ContainerBuilder $di) {
            add_action('init', function () use ($di) {
                \Smartling\Bootloader::boot(__FILE__, $di);
            });
        });
    });
}
