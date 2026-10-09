<?php

namespace imarc\sitesearch\migrations;

use craft\db\Migration;
use imarc\sitesearch\services\UsageService;

class m261006_150000_add_usage extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable(UsageService::TABLE, [
            'siteUid' => $this->string(36)->notNull(),
            'month' => $this->string(7)->notNull(),
            'requests' => $this->bigInteger()->notNull()->defaultValue(0),
            'warned' => $this->boolean()->notNull()->defaultValue(false),
            'PRIMARY KEY ([[siteUid]], [[month]])',
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists(UsageService::TABLE);

        return true;
    }
}
