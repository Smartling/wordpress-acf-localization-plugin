<?php

namespace Smartling\ACF;

use Smartling\Bootstrap;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class Bootloader
 * @package Smartling\Extension
 */
class Bootloader
{
    /**
     * @param string $functionName
     * @param bool   $strict
     * @param string $message
     *
     * @return bool
     * @throws \Exception
     */
    private function checkFunction($functionName, $strict = false, $message = '')
    {
        $result = function_exists($functionName);
        if (false === $result && true === $strict) {
            throw new \Exception($message);
        } else {
            return $result;
        }
    }

    /**
     * Displays error message for diagnostics
     *
     * @param string $messageText
     */
    private static function displayErrorMessage($messageText = '')
    {
        if (self::checkFunction('add_action', true, 'This code cannot run outside of wordpress.')) {
            add_action('all_admin_notices', function () use ($messageText) {
                echo vsprintf('<div class="error"><p>%s</p></div>', array($messageText));
            });
        }
    }

    private static function getPluginMeta($pluginFile, $metaName)
    {
        $pluginData = get_file_data($pluginFile, [$metaName => $metaName]);

        return $pluginData[$metaName];
    }

    private static function getPluginName($pluginFile)
    {
        return self::getPluginMeta($pluginFile, 'Plugin Name');
    }

    private static function checkConnectorVersion($pluginFile)
    {
        $requiredVersion = self::getPluginMeta($pluginFile, 'ConnectorRequiredMin');
        $realVersion = Bootstrap::$pluginVersion;

        return version_compare($realVersion, $requiredVersion, '>=');
    }

    /**
     * @param                  $pluginFile
     * @param ContainerBuilder $di
     */
    public static function boot($pluginFile, ContainerBuilder $di)
    {
        if (false === self::checkConnectorVersion($pluginFile)) {
            self::displayErrorMessage(
                vsprintf(
                    '<strong>%s</strong> extension plugin requires <strong>%s</strong> plugin version at least <strong>%s</strong>.',
                    [self::getPluginName($pluginFile), 'Smartling Connector',
                     self::getPluginMeta($pluginFile, 'ConnectorRequiredMin')]
                )
            );
        } else {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'AcfOptionEntity.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'AcfOptionHelper.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ContentTypeAcfOption.php';
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'AcfAutoSetup.php';
            (new static($di))->run();
        }
    }

    /**
     * @var ContainerBuilder
     */
    private $di;

    /**
     * @return ContainerBuilder
     */
    public function getDi()
    {
        return $this->di;
    }

    /**
     * @param ContainerBuilder $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    public function run()
    {

        AcfAutoSetup::register($this->getDi());
    }

    public function __construct(ContainerBuilder $di)
    {
        $this->setDi($di);
    }
}
