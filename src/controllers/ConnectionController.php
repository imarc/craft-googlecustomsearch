<?php
namespace imarc\googlecustomsearch\controllers;

use Craft;
use craft\web\Controller;
use imarc\googlecustomsearch\Plugin;

/**
 *
 */
class ConnectionController extends Controller
{
    public function actionTest()
    {
        return $this->asJson(Plugin::getInstance()->search->testConnection());
    }
}
