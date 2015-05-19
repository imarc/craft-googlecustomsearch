<?php
namespace Craft;

class GoogleCustomSearchPlugin extends BasePlugin
{
    function getName()
    {
        return 'Google Custom Search';
    }

    function getVersion()
    {
        return '1.0.0';
    }

    function getDeveloper()
    {
        return 'iMarc';
    }

    function getDeveloperUrl()
    {
        return 'http://www.imarc.net';
    }

    function defineSettings()
    {
        return [
            'searchEngineId' => [AttributeType::String, 'default' => ''],
            'apiKey' => [AttributeType::String, 'default' => '']
        ];
    }

    public function getSettingsHtml()
    {
        craft()->templates->includeJsResource('googlecustomsearch/js/settings.js');
        return craft()->templates->render('googlecustomsearch/_settings', ['settings' => $this->getSettings()]);
    }
}
