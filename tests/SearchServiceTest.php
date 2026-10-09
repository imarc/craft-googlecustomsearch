<?php

namespace imarc\sitesearch\tests;

use imarc\sitesearch\adapters\AdapterInterface;
use imarc\sitesearch\exceptions\UsageLimitException;
use imarc\sitesearch\services\SearchService;
use PHPUnit\Framework\TestCase;

class SearchServiceTest extends TestCase
{
    public function testCapBlocksAdapterAndThrowsByDefault(): void
    {
        $service = $this->blockedService();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Monthly cap reached');
        $service->performSearch('test', 1, 10, []);
    }

    public function testCapReturnsEmptyErrorWhenThrowingIsDisabled(): void
    {
        $service = $this->blockedService();
        $service->setThrowOnFailure(false);
        $response = $service->performSearch('test', 2, 10, []);
        $this->assertSame(429, $response->error->code);
        $this->assertSame([], $response->results);
        $this->assertSame(0, $response->totalResults);
        $this->assertSame(2, $response->page);
    }

    public function testConnectionCannotBypassCap(): void
    {
        $this->assertSame(['success' => false, 'error' => 'Monthly cap reached'], $this->blockedService()->testConnection());
    }

    public function testSearchReservesBeforeCallingProvider(): void
    {
        $reserved = false;
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('search')->willReturnCallback(function () use (&$reserved) {
            $this->assertTrue($reserved);
            return (object)['results' => []];
        });
        $service = $this->getMockBuilder(SearchService::class)->onlyMethods(['reserveRequest', 'getAdapter'])->getMock();
        $service->expects($this->once())->method('reserveRequest')->willReturnCallback(function () use (&$reserved) {
            $reserved = true;
        });
        $service->method('getAdapter')->willReturn($adapter);
        $this->assertSame([], $service->performSearch('test', 1, 10, [])->results);
    }

    public function testStorageFailureStopsProviderCalls(): void
    {
        $service = $this->getMockBuilder(SearchService::class)->onlyMethods(['reserveRequest', 'getAdapter'])->getMock();
        $service->method('reserveRequest')->willThrowException(new \RuntimeException('Storage unavailable'));
        $service->expects($this->never())->method('getAdapter');
        $this->expectException(\RuntimeException::class);
        $service->performSearch('test', 1, 10, []);
    }

    private function blockedService(): SearchService
    {
        $service = $this->getMockBuilder(SearchService::class)->onlyMethods(['reserveRequest', 'getAdapter'])->getMock();
        $service->expects($this->once())->method('reserveRequest')->willThrowException(new UsageLimitException('Monthly cap reached'));
        $service->expects($this->never())->method('getAdapter');
        return $service;
    }
}
