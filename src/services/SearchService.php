<?php
/**
 * Site Search plugin for Craft CMS
 *
 * A Craft plugin for site search via Google Custom Search, Google Vertex AI
 * Search, or AddSearch.
 *
 * @link      https://www.imarc.com
 * @copyright Copyright (c) 2018 Jeff Turcotte
 */

namespace imarc\sitesearch\services;

use Craft;
use craft\base\Component;
use imarc\sitesearch\adapters\AdapterInterface;
use imarc\sitesearch\adapters\AddSearchAdapter;
use imarc\sitesearch\adapters\GoogleCustomSearchAdapter;
use imarc\sitesearch\adapters\VertexSearchAdapter;
use imarc\sitesearch\models\Settings;
use imarc\sitesearch\Plugin;

/**
 * SearchService
 *
 * Resolves the configured provider adapter for the current site and delegates
 * search requests to it.
 */
class SearchService extends Component
{
    public const ADAPTERS = [
        Settings::PROVIDER_GCS => GoogleCustomSearchAdapter::class,
        Settings::PROVIDER_VERTEX => VertexSearchAdapter::class,
        Settings::PROVIDER_ADDSEARCH => AddSearchAdapter::class,
    ];

    private $throwOnFailure = true;

    public function setThrowOnFailure(bool $throwOnFailure): bool
    {
        return $this->throwOnFailure = $throwOnFailure;
    }

    public function getThrowOnFailure(): bool
    {
        return $this->throwOnFailure;
    }

    /**
     * Perform search using the current site's configured provider
     *
     * Returns an object with the following properties:
     *
     *   page
     *   perPage
     *   start
     *   end
     *   totalResults
     *   raw
     *   results
     *     title
     *     snippet
     *     htmlSnippet
     *     link
     *     image
     *     thumbnail
     *
     * @param string terms The search terms
     * @param integer page The page to return
     * @param integer per_page How many results to display per page
     * @param array extra Extra parameters to pass to the provider
     * @return object The results of the search
     * @throws \Exception If an error is returned from the provider
     **/
    public function performSearch($terms, $page, $per_page, $extra)
    {
        $response = $this->getAdapter()->search((string)$terms, (int)$page, (int)$per_page, $extra);

        if (isset($response->error)) {
            if ($this->throwOnFailure) {
                throw new \Exception($response->error->message);
            }
            Craft::warning(
                'Search API returned error: ' . $response->error->message,
                __METHOD__
            );
        }

        return $response;
    }

    /**
     * Test connection with the current site's configured provider
     *
     * @return array The result of the connection test
     **/
    public function testConnection()
    {
        return $this->getAdapter()->testConnection();
    }

    public function getAdapter(?int $siteId = null): AdapterInterface
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        $siteId ??= Craft::$app->getSites()->getCurrentSite()->id;
        $siteSettings = $settings->getParsedSettingsForSite($siteId);

        $adapterClass = self::ADAPTERS[$siteSettings['provider']] ?? GoogleCustomSearchAdapter::class;

        return new $adapterClass($siteSettings);
    }
}
