<?php
/**
 * @copyright 2016 Imarc LLC
 * @license Apache (see LICENSE file)
 */
namespace Craft;

/**
 * GoogleCustomSearchPlugin is a Craft plugin for using Google Custom Search
 * (and Google Site Search) on Craft sites.
 */
class GoogleCustomSearchPlugin extends BasePlugin
{
    public function getName()
    {
        return 'Google Custom Search';
    }

    public function getVersion()
    {
        return '1.0.0';
    }

    public function getDeveloper()
    {
        return 'Imarc';
    }

    public function getDeveloperUrl()
    {
        return 'https://www.imarc.com';
    }

    public function defineSettings()
    {
        return array(
            'searchEngineId' => array(AttributeType::String, 'default' => ''),
            'apiKey' => array(AttributeType::String, 'default' => ''),
        );
    }

    public function getSettingsHtml()
    {
        craft()->templates->includeJsResource('googlecustomsearch/js/settings.js');
        return craft()->templates->render('googlecustomsearch/_settings', array('settings' => $this->getSettings()));
    }
}
