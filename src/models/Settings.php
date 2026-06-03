<?php

namespace imarc\googlecustomsearch\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\ConfigHelper;

class Settings extends Model
{
    /**
     * @var string @deprecated Use siteSettings instead. Kept for backward compatibility.
     */
    public string $apiKey = '';

    /**
     * @var string @deprecated Use siteSettings instead. Kept for backward compatibility.
     */
    public string $searchEngineId = '';

    /**
     * Per-site settings indexed by site UID (CP) or site handle (config).
     *
     * @var array<string, array{apiKey?: string, searchEngineId?: string}>
     */
    public array $siteSettings = [];

    public function init(): void
    {
        parent::init();
        $this->migrateLegacySettings();
    }

    public function getApiKey(?int $siteId = null): string
    {
        return App::parseEnv($this->getRawApiKey($siteId));
    }

    public function getSearchEngineId(?int $siteId = null): string
    {
        return App::parseEnv($this->getRawSearchEngineId($siteId));
    }

    /**
     * @return array{apiKey: string, searchEngineId: string}
     */
    public function getSettingsForSite(int $siteId): array
    {
        return [
            'apiKey' => $this->getRawApiKey($siteId),
            'searchEngineId' => $this->getRawSearchEngineId($siteId),
        ];
    }

    public function rules(): array
    {
        return [
            [['siteSettings'], 'validateSiteSettings'],
        ];
    }

    public function validateSiteSettings(): void
    {
        $siteId = $this->resolveSiteId(null);
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return;
        }

        $apiKey = $this->getRawApiKey($siteId);
        $searchEngineId = $this->getRawSearchEngineId($siteId);

        if ($apiKey === '') {
            $this->addError('siteSettings', Craft::t('googlecustomsearch', 'API Key is required for {site}.', [
                'site' => Craft::t('site', $site->name),
            ]));
        }

        if ($searchEngineId === '') {
            $this->addError('siteSettings', Craft::t('googlecustomsearch', 'Search Engine ID is required for {site}.', [
                'site' => Craft::t('site', $site->name),
            ]));
        }
    }

    private function getRawApiKey(?int $siteId): string
    {
        $value = $this->getRawSiteSetting('apiKey', $siteId);

        if ($value !== '') {
            return $value;
        }

        if ($this->apiKey !== '') {
            return (string)ConfigHelper::localizedValue($this->apiKey);
        }

        return '';
    }

    private function getRawSearchEngineId(?int $siteId): string
    {
        $value = $this->getRawSiteSetting('searchEngineId', $siteId);

        if ($value !== '') {
            return $value;
        }

        if ($this->searchEngineId !== '') {
            return (string)ConfigHelper::localizedValue($this->searchEngineId);
        }

        return '';
    }

    private function getRawSiteSetting(string $key, ?int $siteId): string
    {
        $siteId = $this->resolveSiteId($siteId);
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return '';
        }

        $uid = $site->uid;

        if (isset($this->siteSettings[$uid][$key]) && $this->siteSettings[$uid][$key] !== '') {
            return (string)$this->siteSettings[$uid][$key];
        }

        $handle = $site->handle;
        if (isset($this->siteSettings[$handle][$key]) && $this->siteSettings[$handle][$key] !== '') {
            return (string)$this->siteSettings[$handle][$key];
        }

        return '';
    }

    private function resolveSiteId(?int $siteId): int
    {
        if ($siteId !== null) {
            return $siteId;
        }

        $request = Craft::$app->getRequest();

        if ($request->getIsCpRequest()) {
            $siteHandle = $request->getParam('site');
            if ($siteHandle) {
                $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);
                if ($site) {
                    return $site->id;
                }
            }
        }

        return Craft::$app->getSites()->getCurrentSite()->id;
    }

    private function migrateLegacySettings(): void
    {
        if ($this->siteSettings !== [] || ($this->apiKey === '' && $this->searchEngineId === '')) {
            return;
        }

        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $this->siteSettings[$primarySite->uid] = [
            'apiKey' => $this->apiKey,
            'searchEngineId' => $this->searchEngineId,
        ];
    }
}
