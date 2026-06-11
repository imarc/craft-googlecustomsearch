<?php

namespace imarc\googlecustomsearch\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use imarc\googlecustomsearch\Plugin;

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
        $this->siteSettings = self::loadSiteSettings($this->getPluginHandle());
        $this->normalizeSiteSettings();
        $this->migrateLegacySettings();
    }

    public static function getSiteSettingsPath(string $handle): string
    {
        return 'plugins.' . $handle . '.siteSettings';
    }

    /**
     * @return array<string, array{apiKey?: string, searchEngineId?: string}>
     */
    public static function loadSiteSettings(string $handle): array
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $stored = $projectConfig->get(self::getSiteSettingsPath($handle));

        if (!is_array($stored) || $stored === []) {
            $stored = $projectConfig->get('plugins.' . $handle . '.settings.siteSettings');
        }

        if (!is_array($stored) || $stored === []) {
            return [];
        }

        return ProjectConfigHelper::unpackAssociativeArrays($stored);
    }

    /**
     * @param array<string, array{apiKey?: string, searchEngineId?: string}> $siteSettings
     */
    public static function saveSiteSettings(string $handle, array $siteSettings): void
    {
        Craft::$app->getProjectConfig()->set(
            self::getSiteSettingsPath($handle),
            ProjectConfigHelper::packAssociativeArrays($siteSettings),
            'Update Google Custom Search site settings'
        );
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
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return [
                'apiKey' => '',
                'searchEngineId' => '',
            ];
        }

        if ($this->hasStoredSiteEntry($site->uid, $site->handle)) {
            $siteData = $this->getSiteSettingsArray($site->uid, $site->handle);

            return [
                'apiKey' => (string)($siteData['apiKey'] ?? ''),
                'searchEngineId' => (string)($siteData['searchEngineId'] ?? ''),
            ];
        }

        return [
            'apiKey' => $this->getLegacySetting('apiKey'),
            'searchEngineId' => $this->getLegacySetting('searchEngineId'),
        ];
    }

    public function rules(): array
    {
        return [
            [['apiKey', 'searchEngineId'], 'validateCurrentSiteSettings'],
        ];
    }

    public function validateCurrentSiteSettings(): void
    {
        $siteId = $this->resolveSiteId(null);
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return;
        }

        $apiKey = (string)$this->apiKey;
        $searchEngineId = (string)$this->searchEngineId;

        if ($apiKey === '') {
            $this->addError('apiKey', Craft::t('googlecustomsearch', 'API Key is required for {site}.', [
                'site' => Craft::t('site', $site->name),
            ]));
        }

        if ($searchEngineId === '') {
            $this->addError('searchEngineId', Craft::t('googlecustomsearch', 'Search Engine ID is required for {site}.', [
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

        return $this->getLegacySetting('apiKey');
    }

    private function getRawSearchEngineId(?int $siteId): string
    {
        $value = $this->getRawSiteSetting('searchEngineId', $siteId);

        if ($value !== '') {
            return $value;
        }

        return $this->getLegacySetting('searchEngineId');
    }

    private function getRawSiteSetting(string $key, ?int $siteId): string
    {
        $siteId = $this->resolveSiteId($siteId);
        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return '';
        }

        $siteData = $this->getSiteSettingsArray($site->uid, $site->handle);

        if (array_key_exists($key, $siteData) && $siteData[$key] !== '') {
            return (string)$siteData[$key];
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function getSiteSettingsArray(string $uid, string $handle): array
    {
        $this->normalizeSiteSettings();

        if (isset($this->siteSettings[$uid]) && is_array($this->siteSettings[$uid])) {
            return ProjectConfigHelper::unpackAssociativeArrays($this->siteSettings[$uid]);
        }

        if (isset($this->siteSettings[$handle]) && is_array($this->siteSettings[$handle])) {
            return ProjectConfigHelper::unpackAssociativeArrays($this->siteSettings[$handle]);
        }

        return [];
    }

    private function hasStoredSiteEntry(string $uid, string $handle): bool
    {
        $this->normalizeSiteSettings();

        return isset($this->siteSettings[$uid]) || isset($this->siteSettings[$handle]);
    }

    private function getLegacySetting(string $key): string
    {
        $value = $this->$key ?? '';

        if ($value === '') {
            return '';
        }

        return (string)ConfigHelper::localizedValue($value);
    }

    private function normalizeSiteSettings(): void
    {
        if ($this->siteSettings === []) {
            return;
        }

        $this->siteSettings = ProjectConfigHelper::unpackAssociativeArrays($this->siteSettings);
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

    private function getPluginHandle(): string
    {
        $plugin = Plugin::getInstance();

        return $plugin?->handle ?? 'googlecustomsearch';
    }
}
