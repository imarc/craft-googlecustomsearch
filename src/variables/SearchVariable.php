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

namespace imarc\sitesearch\variables;

use imarc\sitesearch\Plugin;

/**
 * Site Search template variable, available as craft.siteSearch.
 *
 *     {% set results = craft.siteSearch.performSearch(query, page) %}
 */
class SearchVariable
{
    public function performSearch($terms, $page = 1, $per_page = 10, $extra = [])
    {
        return Plugin::getInstance()->search->performSearch($terms, $page, $per_page, $extra);
    }

    public function setThrowOnFailure(bool $throwOnFailure): bool
    {
        return Plugin::getInstance()->search->setThrowOnFailure($throwOnFailure);
    }
}
