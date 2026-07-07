<?php

namespace imarc\sitesearch\adapters;

/**
 * A search provider adapter.
 *
 * search() returns either the unified results object:
 *
 *   page, perPage, start, end, totalResults, raw
 *   results[]: title, snippet, htmlSnippet, link, image, thumbnail
 *
 * or, on failure, an object with an `error` property:
 *
 *   error: { code, message }
 */
interface AdapterInterface
{
    /**
     * @param array{apiKey?: string, searchEngineId?: string, projectId?: string, location?: string, engineId?: string, serviceAccountFile?: string, siteKey?: string, addsearchApiKey?: string} $settings
     */
    public function __construct(array $settings);

    public function search(string $terms, int $page, int $perPage, array $extra): object;

    /**
     * @return array{success: bool, error?: string}
     */
    public function testConnection(): array;
}
