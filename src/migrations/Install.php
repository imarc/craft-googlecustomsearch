<?php

namespace imarc\sitesearch\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use imarc\sitesearch\models\Settings;

/**
 * Copies settings from the old googlecustomsearch plugin (v3 and earlier)
 * into this plugin's project config, if present.
 */
class Install extends Migration
{
    private const LEGACY_HANDLE = 'googlecustomsearch';

    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $legacySiteSettings = $projectConfig->get('plugins.' . self::LEGACY_HANDLE . '.siteSettings')
            ?? $projectConfig->get('plugins.' . self::LEGACY_HANDLE . '.settings.siteSettings');

        $siteSettings = [];

        if (is_array($legacySiteSettings) && $legacySiteSettings !== []) {
            foreach (ProjectConfigHelper::unpackAssociativeArrays($legacySiteSettings) as $siteKey => $values) {
                $siteSettings[$siteKey] = array_merge(
                    ['provider' => Settings::PROVIDER_GCS],
                    is_array($values) ? $values : []
                );
            }
        } else {
            $legacySettings = $projectConfig->get('plugins.' . self::LEGACY_HANDLE . '.settings');

            if (is_array($legacySettings) && (($legacySettings['apiKey'] ?? '') !== '' || ($legacySettings['searchEngineId'] ?? '') !== '')) {
                $primarySite = Craft::$app->getSites()->getPrimarySite();
                $siteSettings[$primarySite->uid] = [
                    'provider' => Settings::PROVIDER_GCS,
                    'apiKey' => (string)($legacySettings['apiKey'] ?? ''),
                    'searchEngineId' => (string)($legacySettings['searchEngineId'] ?? ''),
                ];
            }
        }

        if ($siteSettings !== []) {
            Settings::saveSiteSettings('sitesearch', $siteSettings);
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
