<?php
/**
 * googlecustomsearch plugin for Craft CMS 3.x
 *
 * A Craft plugin for integrating with Google's Custom Search (and Google's Site Search.)
 *
 * @link      https://www.imarc.com
 * @copyright Copyright (c) 2018 Jeff Turcotte
 */

namespace imarc\googlecustomsearch;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Plugins as PluginsService;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use imarc\googlecustomsearch\models\Settings;
use imarc\googlecustomsearch\controllers\ConnectionController;
use imarc\googlecustomsearch\services\SearchService;
use imarc\googlecustomsearch\variables\SearchVariable;
use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Jeff Turcotte
 * @package   Googlecustomsearch
 * @since     2.0.0
 *
 * @property  serviceService $searchService
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public $schemaVersion = '2.0.0';

    public $controllerMap = [
        'connection' => ConnectionController::class,
    ];

    public function init()
    {
        parent::init();

        $this->setComponents([
            'search' => SearchService::class
        ]);

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('googlecustomsearch', SearchVariable::class);
            }
        );
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'googlecustomsearch/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }
}
