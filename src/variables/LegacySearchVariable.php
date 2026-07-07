<?php

namespace imarc\sitesearch\variables;

use Craft;

/**
 * Deprecated alias for templates written against v3's
 * craft.googlecustomsearch variable. Use craft.siteSearch instead.
 */
class LegacySearchVariable extends SearchVariable
{
    public function performSearch($terms, $page = 1, $per_page = 10, $extra = [])
    {
        $this->logDeprecation();

        return parent::performSearch($terms, $page, $per_page, $extra);
    }

    public function setThrowOnFailure(bool $throwOnFailure): bool
    {
        $this->logDeprecation();

        return parent::setThrowOnFailure($throwOnFailure);
    }

    private function logDeprecation(): void
    {
        Craft::$app->getDeprecator()->log(
            'craft.googlecustomsearch',
            '`craft.googlecustomsearch` has been deprecated. Use `craft.siteSearch` instead.'
        );
    }
}
