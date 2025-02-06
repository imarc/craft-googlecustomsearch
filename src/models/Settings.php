<?php
namespace imarc\googlecustomsearch\models;

use Craft;
use craft\base\Model;
use imarc\googlecustomsearch\Plugin;
use craft\helpers\App;

class Settings extends Model
{
    public $apiKey = '';
    public $searchEngineId = '';

    public function getApiKey(): string
    {
        return App::parseEnv($this->apiKey);
    }

    public function getSearchEngineId(): string
    {
        return App::parseEnv($this->searchEngineId);
    }

    public function rules(): array
    {
        return [
            [['apiKey', 'searchEngineId'], 'required'],
            [['apiKey', 'searchEngineId'], 'string'],
        ];
    }
}
