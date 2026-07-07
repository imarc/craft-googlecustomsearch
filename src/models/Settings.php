<?php

namespace imarc\sitesearch\models;

use Craft;
use craft\base\Model;
use craft\helpers\App;
use craft\helpers\ConfigHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use imarc\sitesearch\Plugin;

class Settings extends Model
{
    public const PROVIDER_GCS = 'gcs';
    public const PROVIDER_VERTEX = 'vertex';
    public const PROVIDER_ADDSEARCH = 'addsearch';

    public const PROVIDERS = [
        self::PROVIDER_GCS,
        self::PROVIDER_VERTEX,
        self::PROVIDER_ADDSEARCH,
    ];

    /**
     * Per-site setting keys.
     */
    public const SITE_FIELDS = [
        'provider',
        'apiKey',
        'searchEngineId',
        'projectId',
        'location',
        'engineId',
        'serviceAccountFile',
        'siteKey',
        'addsearchApiKey',
    ];

    /**
     * @var string The current site's provider (used when saving CP settings)
     */
    public string $provider = self::PROVIDER_GCS;

    /**
     * @var string @deprecated Use siteSettings instead. Kept for backward compatibility.
     */
    public string $apiKey = '';

    /**
     * @var string @deprecated Use siteSettings instead. Kept for backward compatibility.
     */
    public string $searchEngineId = '';

    public string $projectId = '';
    public string $location = '';
    public string $engineId = '';
    public string $serviceAccountFile = '';
    public string $siteKey = '';
    public string $addsearchApiKey = '';

    /**
     * Per-site settings indexed by site UID (CP) or site handle (config).
     *
     * @var array<string, array<string, string>>
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
     * @return array<string, array<string, string>>
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
     * @param array<string, array<string, string>> $siteSettings
     */
    public static function saveSiteSettings(string $handle, array $siteSettings): void
    {
        Craft::$app->getProjectConfig()->set(
            self::getSiteSettingsPath($handle),
            ProjectConfigHelper::packAssociativeArrays($siteSettings),
            'Update Site Search site settings'
        );
    }

    /**
     * Raw (unparsed) settings for a site, for CP form display.
     *
     * @return array<string, string>
     */
    public function getSettingsForSite(int $siteId): array
    {
        $defaults = array_fill_keys(self::SITE_FIELDS, '');
        $defaults['provider'] = self::PROVIDER_GCS;

        $site = Craft::$app->getSites()->getSiteById($siteId);

        if ($site === null) {
            return $defaults;
        }

        if ($this->hasStoredSiteEntry($site->uid, $site->handle)) {
            $siteData = $this->getSiteSettingsArray($site->uid, $site->handle);

            $values = array_merge($defaults, array_intersect_key($siteData, $defaults));

            if (!in_array($values['provider'], self::PROVIDERS, true)) {
                $values['provider'] = self::PROVIDER_GCS;
            }

            return $values;
        }

        // Fall back to legacy top-level settings (always Google Custom Search)
        $defaults['apiKey'] = $this->getLegacySetting('apiKey');
        $defaults['searchEngineId'] = $this->getLegacySetting('searchEngineId');

        return $defaults;
    }

    /**
     * Env-parsed settings for a site, for adapter use.
     *
     * @return array<string, string>
     */
    public function getParsedSettingsForSite(int $siteId): array
    {
        $values = $this->getSettingsForSite($siteId);

        foreach ($values as $key => $value) {
            if ($key !== 'provider') {
                $values[$key] = (string)App::parseEnv($value);
            }
        }

        return $values;
    }

    /**
     * @deprecated Use getParsedSettingsForSite() instead.
     */
    public function getApiKey(?int $siteId = null): string
    {
        return $this->getParsedSettingsForSite($this->resolveSiteId($siteId))['apiKey'];
    }

    /**
     * @deprecated Use getParsedSettingsForSite() instead.
     */
    public function getSearchEngineId(?int $siteId = null): string
    {
        return $this->getParsedSettingsForSite($this->resolveSiteId($siteId))['searchEngineId'];
    }

    public function rules(): array
    {
        return [
            ['provider', 'in', 'range' => self::PROVIDERS],
            [['provider'], 'validateCurrentSiteSettings'],
        ];
    }

    public function validateCurrentSiteSettings(): void
    {
        $site = Craft::$app->getSites()->getSiteById($this->resolveSiteId(null));

        if ($site === null) {
            return;
        }

        $required = match ($this->provider) {
            self::PROVIDER_VERTEX => [
                'projectId' => 'Project ID',
                'engineId' => 'App / Engine ID',
                // location defaults to "global"; serviceAccountFile may be blank (ADC)
            ],
            self::PROVIDER_ADDSEARCH => [
                'siteKey' => 'Site Key',
            ],
            default => [
                'apiKey' => 'API Key',
                'searchEngineId' => 'Search Engine ID',
            ],
        };

        foreach ($required as $attribute => $label) {
            if ((string)$this->$attribute === '') {
                $this->addError($attribute, Craft::t('sitesearch', '{label} is required for {site}.', [
                    'label' => Craft::t('sitesearch', $label),
                    'site' => Craft::t('site', $site->name),
                ]));
            }
        }
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
            'provider' => self::PROVIDER_GCS,
            'apiKey' => $this->apiKey,
            'searchEngineId' => $this->searchEngineId,
        ];
    }

    private function getPluginHandle(): string
    {
        $plugin = Plugin::getInstance();

        return $plugin?->handle ?? 'sitesearch';
    }
}
