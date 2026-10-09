<?php

namespace imarc\sitesearch\tests;

use imarc\sitesearch\exceptions\UsageLimitException;
use imarc\sitesearch\models\Settings;
use imarc\sitesearch\services\UsageService;
use PHPUnit\Framework\TestCase;
use yii\db\Connection;

class UsageServiceTest extends TestCase
{
    private Connection $db;
    private TestUsageService $usage;

    protected function setUp(): void
    {
        $this->db = new Connection(['dsn' => 'sqlite::memory:']);
        $this->db->createCommand('CREATE TABLE sitesearch_usage (siteUid VARCHAR(36), month VARCHAR(7), requests BIGINT NOT NULL DEFAULT 0, warned BOOLEAN NOT NULL DEFAULT 0, PRIMARY KEY (siteUid, month))')->execute();
        $this->usage = new TestUsageService(['db' => $this->db]);
    }

    private function requestCount(string $site = 'site-a'): int
    {
        return (int)$this->db->createCommand('SELECT requests FROM sitesearch_usage WHERE siteUid = :site AND month = :month', [':site' => $site, ':month' => $this->usage->month])->queryScalar();
    }

    public function testCapAllowsExactlyTheConfiguredNumber(): void
    {
        $settings = ['monthlyHardCap' => '2', 'monthlyWarningThreshold' => '1'];
        $this->usage->reserve('site-a', $settings);
        $this->usage->reserve('site-a', $settings);
        $this->assertCount(1, $this->usage->warnings);
        try {
            $this->usage->reserve('site-a', $settings);
            $this->fail('Expected the third request to be blocked.');
        } catch (UsageLimitException) {
            $this->assertSame(2, $this->requestCount());
        }
        $this->usage->reserve('site-a', ['monthlyHardCap' => '3']);
        $this->assertSame(3, $this->requestCount());
    }

    public function testUnlimitedRequestsStillCountAndEnablingCapUsesExistingUsage(): void
    {
        $this->usage->reserve('site-a', []);
        $this->usage->reserve('site-a', ['monthlyHardCap' => '0']);
        $this->assertSame(2, $this->requestCount());
        $this->expectException(UsageLimitException::class);
        $this->usage->reserve('site-a', ['monthlyHardCap' => '1']);
    }

    public function testSitesAndMonthsAreIndependent(): void
    {
        $settings = ['monthlyHardCap' => '1', 'monthlyWarningThreshold' => '1'];
        $this->usage->reserve('site-a', $settings);
        $this->usage->reserve('site-b', $settings);
        $this->usage->month = '2026-11';
        $this->usage->reserve('site-a', $settings);
        $this->assertSame(1, $this->requestCount());
        $this->assertCount(3, $this->usage->warnings);
    }

    public function testSeparateServiceInstancesShareTheCap(): void
    {
        $this->usage->reserve('site-a', ['monthlyHardCap' => '1']);
        $another = new TestUsageService(['db' => $this->db]);
        $this->expectException(UsageLimitException::class);
        $another->reserve('site-a', ['monthlyHardCap' => '1']);
    }

    public function testWarningDoesNotStopRequests(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->usage->reserve('site-a', ['monthlyWarningThreshold' => '2']);
        }
        $this->assertCount(1, $this->usage->warnings);
        $this->assertSame(4, $this->requestCount());
    }

    public function testLimitParsing(): void
    {
        foreach (['' => 0, '0' => 0, '10000' => 10000] as $value => $expected) {
            $this->assertSame($expected, Settings::parseRequestLimit($value));
        }
        foreach (['-1', '1.5', 'abc', '$UNRESOLVED', '999999999999999999999999'] as $value) {
            try {
                Settings::parseRequestLimit($value);
                $this->fail('Invalid limit accepted: ' . $value);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testInvalidCapDoesNotReserveRequest(): void
    {
        try {
            $this->usage->reserve('site-a', ['monthlyHardCap' => '-1']);
            $this->fail('Expected invalid configuration to fail closed.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, $this->requestCount());
        }
    }
}

class TestUsageService extends UsageService
{
    public string $month = '2026-10';
    public array $warnings = [];

    protected function currentMonth(): string
    {
        return $this->month;
    }

    protected function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
