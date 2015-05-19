<?php
namespace Craft;

class GoogleCustomSearch_ConnectionController extends BaseController
{
    public function actionTest()
    {
        $this->returnJson(craft()->googleCustomSearch_search->testConnection());
    }
}