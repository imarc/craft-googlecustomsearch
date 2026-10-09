<?php

namespace imarc\sitesearch\tests;

use imarc\sitesearch\services\UsageNotificationService;
use PHPUnit\Framework\TestCase;

class UsageNotificationServiceTest extends TestCase
{
    public function testEachUniqueAdminReceivesSiteAndLimitDetails(): void
    {
        $service = new TestNotificationService();
        $service->emails = ['one@example.com', 'two@example.com', 'one@example.com', ''];
        $service->sendWarning('site-uid', '2026-10', 8000, 10000);
        $this->assertCount(2, $service->sent);
        $this->assertSame('one@example.com', $service->sent[0]['email']);
        $this->assertSame('two@example.com', $service->sent[1]['email']);
        $body = $service->sent[0]['body'];
        foreach (['Example Site', 'site-uid', '2026-10', '8000', '10000', 'UTC'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertSame([], $service->warnings);
    }

    public function testFailuresDoNotPreventOtherRecipients(): void
    {
        $service = new TestNotificationService();
        $service->emails = ['throw@example.com', 'false@example.com', 'ok@example.com'];
        $service->sendWarning('site-uid', '2026-10', 8000, 0);
        $this->assertCount(3, $service->sent);
        $this->assertCount(2, $service->warnings);
        $this->assertStringContainsString('No monthly hard cap', $service->sent[2]['body']);
    }

    public function testNoRecipientsIsLogged(): void
    {
        $service = new TestNotificationService();
        $service->sendWarning('site-uid', '2026-10', 8000, 10000);
        $this->assertSame([], $service->sent);
        $this->assertCount(1, $service->warnings);
    }

    public function testRecipientLookupFailureIsNonFatal(): void
    {
        $service = new TestNotificationService();
        $service->failLookup = true;
        $service->sendWarning('site-uid', '2026-10', 8000, 10000);
        $this->assertSame([], $service->sent);
        $this->assertCount(1, $service->warnings);
    }
}

class TestNotificationService extends UsageNotificationService
{
    public array $emails = [];
    public array $sent = [];
    public array $warnings = [];
    public bool $failLookup = false;

    protected function getAdminEmails(): array
    {
        if ($this->failLookup) {
            throw new \RuntimeException('Lookup failed');
        }
        return $this->emails;
    }

    protected function getSiteName(string $siteUid): string
    {
        return 'Example Site';
    }

    protected function sendEmail(string $email, string $subject, string $body): bool
    {
        $this->sent[] = compact('email', 'subject', 'body');
        if ($email === 'throw@example.com') {
            throw new \RuntimeException('Transport failed');
        }
        return $email !== 'false@example.com';
    }

    protected function warn(string $message): void
    {
        $this->warnings[] = $message;
    }
}
