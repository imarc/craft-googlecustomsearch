<?php

namespace imarc\sitesearch\services;

use Craft;
use craft\base\Component;
use imarc\sitesearch\exceptions\UsageLimitException;
use imarc\sitesearch\models\Settings;
use yii\db\Connection;
use yii\db\Expression;

/** Persistent, atomic reservations; failed provider requests are not refunded. */
class UsageService extends Component
{
    public const TABLE = '{{%sitesearch_usage}}';

    public ?Connection $db = null;

    public function reserve(string $siteUid, array $settings): void
    {
        $warning = Settings::parseRequestLimit($settings['monthlyWarningThreshold'] ?? '');
        $cap = Settings::parseRequestLimit($settings['monthlyHardCap'] ?? '');
        $db = $this->db ?? Craft::$app->getDb();
        $key = ['siteUid' => $siteUid, 'month' => $this->currentMonth()];

        // The composite primary key makes concurrent first requests safe.
        $db->createCommand()->upsert(self::TABLE, $key + ['requests' => 0, 'warned' => false], false)->execute();
        $condition = $cap > 0 ? ['and', $key, ['<', 'requests', $cap]] : $key;
        $reserved = $db->createCommand()->update(self::TABLE, [
            'requests' => new Expression('[[requests]] + 1'),
        ], $condition)->execute();

        if ($reserved === 0) {
            throw new UsageLimitException('The monthly site search request limit has been reached.');
        }

        // Claim the warning atomically so concurrent requests log it only once.
        if ($warning > 0 && $db->createCommand()->update(self::TABLE, ['warned' => true], [
            'and', $key, ['warned' => false], ['>=', 'requests', $warning],
        ])->execute() > 0) {
            $this->warn(sprintf(
                'Site Search monthly warning threshold (%d requests) reached for site %s in %s (UTC).',
                $warning, $siteUid, $key['month']
            ));
        }
    }

    protected function currentMonth(): string
    {
        return gmdate('Y-m');
    }

    protected function warn(string $message): void
    {
        Craft::warning($message, 'sitesearch');
    }
}
