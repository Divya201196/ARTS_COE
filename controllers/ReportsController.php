<?php

namespace app\controllers;
use Yii;
use yii\web\Controller;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

class ReportsController extends Controller
{
    public function actionIndex()
    {
    	Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('index');        
    }
    public function actionCourseGraph()
    {
        return $this->render('course-graph');        
    }

}
