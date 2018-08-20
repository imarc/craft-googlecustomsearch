<?php
namespace imarc\googlecustomsearch\models;

use Craft;
use craft\base\Model;
use imarc\googlecustomsearch\Plugin;

class Settings extends Model
{
    public $apiKey = '';
    public $searchEngineId = '';

    public function rules()
    {
        return [
            ['searchEngineId', 'string'],
            ['apiKey', 'string']
        ];
    }
}
