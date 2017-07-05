=== Smartling ACF localization ===

Contributors: smartling
Tags: automation, international, internationalisation, internationalization, localisation, localization, multilingual, smartling, translate, translation, acf, advanced, custom, custom field
Requires at least: 4.6
Tested up to: 4.8
Stable tag: 1.3.1
License: GPL-3.0 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

Extend Smartling Connector functionality by adding localization for ACF plugin

== Description ==

[The Smartling Connector](https://wordpress.org/plugins/smartling-connector) extends the WordPress interface for seamless management of the translation process. This plugin allows to localize [ACF Options Page](https://www.advancedcustomfields.com/add-ons/options-page/)

Integration Features

* Automatic change detection for content updates
* Robust custom workflow engine configurable per language
* Automatic download of completed translations to WordPress
* Translation Memory integration
* No tie-ins to translation agencies or vendors
* Reporting for translation velocity, efficiency

== Installation ==

= Minimum Requirements =
* WordPress 4.6 or higher
* [Smartling Connector](https://wordpress.org/plugins/smartling-connector) 1.5 or higher

1. Upload the plugin files to the `/wp-content/plugins/smartling-acf-localization` directory, or install the plugin through the WordPress plugins screen directly.
1. Go to the Plugins screen and **Network Activate** the ACF localization plugin.
1. Go to the **ACF Tools page** `/wp-admin/edit.php?post_type=acf-field-group&page=acf-settings-tools` and select all used field groups.
1. Click **Generate export code** and add generated PHP code to your wordpress installation as part of your theme or a separate plugin.
* Note, last steps should be done every time ACF filed configuration is changed (fields added, updated or removed).


== Frequently Asked Questions ==

Additional information on the Smartling Connector for WordPress can be found [there](http://help.smartling.com/knowledge-base/sections/wordpress-connector/).

== Screenshots ==

1. Sample page is built with ACF Options.
2. The Bulk Submit page allows submission of standard WordPress assets including ACF options text for all configured locales from a single interface.
3. Track translation status within WordPress from the Submissions Board. View overall progress of submitted translation requests as well as resend updated content.

== Changelog ==
= 1.3.1 =
* Fixed minor issues.

= 1.3.0 =
* Added full automatic handling for standard (built-in) ACF fields (translation works without configuration regeneration).
* Added automatic check for Database and PHP definitions to detect if PHP version is outdated.

= 1.2.2 =
* Tested with 4.8 Wordpress release.

= 1.2.1 =
* minor improvements

= 1.2 =
* Added fully automatic configuration for ACF-pro list of fields. Requires fields and groups to be registered via php code (`Custom Fields`->`Tools`->`Generate export code`), `init` hook should be used to add fields. The `user` field is excluded from translation.

= 1.1 =
* Changed option name where plugin stores map for options from `acf_option_key_map` to `smartling_acf_option_key_map`
* Minor bugfixes

= 1.0 =
The initial release. It allows translate only content of ACF Options Pages
