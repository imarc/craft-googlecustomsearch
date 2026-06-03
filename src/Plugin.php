<?php
/**
 * googlecustomsearch plugin for Craft CMS
 *
 * A Craft plugin for integrating with Google's Custom Search (and Google's Site Search.)
 *
 * @link      https://www.imarc.com
 * @copyright Copyright (c) 2018 Jeff Turcotte
 */

namespace imarc\googlecustomsearch;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\helpers\Html;
use craft\models\Site;
use craft\web\Controller;
use craft\web\Response;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use imarc\googlecustomsearch\models\Settings;
use imarc\googlecustomsearch\controllers\ConnectionController;
use imarc\googlecustomsearch\services\SearchService;
use imarc\googlecustomsearch\variables\SearchVariable;
use yii\base\Event;

/**
 * @author    Jeff Turcotte
 * @package   Googlecustomsearch
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
                $variable->set('googlecustomsearch', SearchVariable::class);
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

        if (!empty($posted['siteSettings'])) {
            $stored = Craft::$app->getProjectConfig()->get(
                'plugins.' . $this->handle . '.settings'
            ) ?? [];

            $existingSiteSettings = $stored['siteSettings'] ?? [];
            $settings = $this->getSettings();

            foreach ($posted['siteSettings'] as $uid => $siteData) {
                $existingSiteSettings[$uid] = array_merge(
                    $existingSiteSettings[$uid] ?? [],
                    $siteData
                );
            }

            $settings->siteSettings = $existingSiteSettings;
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

        $settingsHtml = $view->namespaceInputs(function () use ($readOnly, $selectedSite) {
            if ($readOnly) {
                return (string)Html::disableInputs(fn () => $this->settingsHtmlForSite($selectedSite));
            }

            return (string)$this->settingsHtmlForSite($selectedSite);
        }, 'settings');

        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        $settingsLayout = $view->doesTemplateExist('settings/plugins/_settings')
            ? 'settings/plugins/_settings'
            : 'settings/plugins/_settings.twig';

        return $controller->renderTemplate($settingsLayout, [
            'plugin' => $this,
            'settingsHtml' => $siteMenuHtml . $settingsHtml,
            'readOnly' => $readOnly,
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
        $siteValues = $settings->getSettingsForSite($site->id);

        return Craft::$app->getView()->renderTemplate(
            'googlecustomsearch/settings',
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

        return $view->renderTemplate('googlecustomsearch/_sitemenu', $params);
    }
}
