<?php
/**
 * googlecustomsearch plugin for Craft CMS 3.x
 *
 * A Craft plugin for integrating with Google's Custom Search (and Google's Site Search.)
 *
 * @link      https://www.imarc.com
 * @copyright Copyright (c) 2018 Jeff Turcotte
 */

namespace imarc\googlecustomsearch\variables;

use imarc\googlecustomsearch\Plugin;

use Craft;

/**
 * googlecustomsearch Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.googlecustomsearch }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Jeff Turcotte
 * @package   Googlecustomsearch
 * @since     2.0.0
 */
class SearchVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Whatever you want to output to a Twig template can go into a Variable method.
     * You can have as many variable functions as you want.  From any Twig template,
     * call it like this:
     *
     *     {{ craft.googlecustomsearch.exampleVariable }}
     *
     * Or, if your variable requires parameters from Twig:
     *
     *     {{ craft.googlecustomsearch.exampleVariable(twigValue) }}
     *
     * @param null $optional
     * @return string
     */

    public function performSearch($terms, $page=1, $per_page=10, $extra=array())
    {
        return Plugin::getInstance()->search->performSearch($terms, $page, $per_page, $extra);
    }
}
