<?php
/**
 * sitesearch plugin for Craft CMS
 *
 * A Craft plugin for integrating with Google's Custom Search (and Google's Site Search.)
 *
 * @link      https://www.imarc.com
 * @copyright Copyright (c) 2018 Jeff Turcotte
 */

namespace imarc\sitesearch;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\PluginEvent;
use craft\helpers\Html;
use craft\models\Site;
use craft\services\Plugins;
use craft\web\Controller;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use imarc\sitesearch\models\Settings;
use imarc\sitesearch\controllers\ConnectionController;
use imarc\sitesearch\services\SearchService;
use imarc\sitesearch\variables\LegacySearchVariable;
use imarc\sitesearch\variables\SearchVariable;
use yii\base\Event;

/**
 * @author    Jeff Turcotte
 * @package   Sitesearch
 * @since     2.0.0
 *
 * @property  SearchService $search
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    public bool $hasReadOnlyCpSettings = true;

    public string $schemaVersion = '1.0.0';

    public $controllerMap = [
        'connection' => ConnectionController::class,
    ];

    public function init(): void
    {
        parent::init();

        $this->setComponents([
            'search' => SearchService::class,
        ]);

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('siteSearch', SearchVariable::class);
                // Deprecated aliases for templates written against v3
                $variable->set('googlecustomsearch', LegacySearchVariable::class);
                $variable->set('googleCustomSearch', LegacySearchVariable::class);
            }
        );

        $handle = $this->handle;
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS,
            function (PluginEvent $event) use ($handle) {
                if ($event->plugin->handle !== $handle) {
                    return;
                }

                $siteSettings = $event->plugin->getSettings()->siteSettings;

                if ($siteSettings !== []) {
                    Settings::saveSiteSettings($handle, $siteSettings);
                }
            }
        );
    }

    public function getSelectedSiteForSettings(): Site
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsCpRequest()) {
            $siteHandle = $request->getParam('site');
            if ($siteHandle) {
                $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
                if ($site) {
                    return $site;
                }
            }
        }

        return Craft::$app->getSites()->getCurrentSite();
    }

    public function beforeSaveSettings(): bool
    {
        $posted = Craft::$app->getRequest()->getBodyParam('settings', []);
        $site = $this->getSelectedSiteForSettings();
        $settings = $this->getSettings();

        if (array_intersect(Settings::SITE_FIELDS, array_keys($posted)) !== []) {
            $allSiteSettings = Settings::loadSiteSettings($this->handle);

            $siteValues = [];
            foreach (Settings::SITE_FIELDS as $field) {
                $siteValues[$field] = (string)($posted[$field] ?? '');
            }
            if (!in_array($siteValues['provider'], Settings::PROVIDERS, true)) {
                $siteValues['provider'] = Settings::PROVIDER_GCS;
            }
            $allSiteSettings[$site->uid] = $siteValues;

            $settings->siteSettings = $allSiteSettings;
            Settings::saveSiteSettings($this->handle, $allSiteSettings);
        }

        return parent::beforeSaveSettings();
    }

    public function getSettingsResponse(): mixed
    {
        return $this->settingsResponse(false);
    }

    public function getReadOnlySettingsResponse(): mixed
    {
        return $this->settingsResponse(true);
    }

    private function settingsResponse(bool $readOnly): Response
    {
        /** @var View $view */
        $view = Craft::$app->getView();
        $selectedSite = $this->getSelectedSiteForSettings();

        $siteMenuHtml = $this->renderSiteMenuHtml($view, $selectedSite);
        $siteHiddenInput = '';

        if (count(Craft::$app->getSites()->getAllSites()) > 1) {
            $siteHiddenInput = (string)Html::hiddenInput('site', $selectedSite->handle);
        }

        $settingsHtml = $view->namespaceInputs(function () use ($readOnly, $selectedSite) {
            if ($readOnly) {
                return (string)Html::disableInputs(fn () => $this->settingsHtmlForSite($selectedSite));
            }

            return (string)$this->settingsHtmlForSite($selectedSite);
        }, 'settings');

        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        return $controller->renderTemplate('sitesearch/cp-settings', [
            'plugin' => $this,
            'settingsHtml' => $siteMenuHtml . $siteHiddenInput . $settingsHtml,
            'readOnly' => $readOnly,
            'selectedSite' => $selectedSite,
        ]);
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return $this->settingsHtmlForSite($this->getSelectedSiteForSettings());
    }

    private function settingsHtmlForSite(Site $site): ?string
    {
        $settings = $this->getSettings();
        $settings->siteSettings = Settings::loadSiteSettings($this->handle);
        $siteValues = $settings->getSettingsForSite($site->id);

        return Craft::$app->getView()->renderTemplate(
            'sitesearch/settings',
            [
                'site' => $site,
                'settings' => (object)$siteValues,
            ]
        );
    }

    private function renderSiteMenuHtml(View $view, Site $selectedSite): string
    {
        $params = [
            'siteIds' => Craft::$app->getSites()->getEditableSiteIds(),
            'selectedSiteId' => $selectedSite->id,
            'requestedSite' => $selectedSite,
            'urlFormat' => 'settings/plugins/' . $this->handle . '?site={handle}',
        ];

        if ($view->doesTemplateExist('_elements/sitemenu')) {
            return $view->renderTemplate('_elements/sitemenu', $params);
        }

        return $view->renderTemplate('sitesearch/_sitemenu', $params);
    }
}
