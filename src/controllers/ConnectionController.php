<?php

namespace imarc\sitesearch\controllers;

use craft\web\Controller;
use imarc\sitesearch\Plugin;

class ConnectionController extends Controller
{
    /**
     * @var array|bool|int
     */
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireAdmin();

        return true;
    }

    public function actionTest(): \yii\web\Response
    {
        return $this->asJson(Plugin::getInstance()->search->testConnection());
    }
}
