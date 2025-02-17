<?php

namespace app\controllers;

use Yii;
use app\models\CoeBatDegReg;
use app\models\Regulation;
use app\models\CoeBatDegRegSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * CoeBatDegRegController implements the CRUD actions for CoeBatDegReg model.
 */
class CoeBatDegRegController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all CoeBatDegReg models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CoeBatDegRegSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CoeBatDegReg model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new CoeBatDegReg model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new CoeBatDegReg();
        return $this->redirect(['index']);
        if ($model->load(Yii::$app->request->post())) {
            $model->save();
            return $this->redirect(['index']);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing CoeBatDegReg model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) 
        {
            $ceck_exists = Regulation::find()->where(['regulation_year'=>$model->regulation_year])->all();
            if(!empty($ceck_exists))
            {
                $model->updated_by = Yii::$app->user->getId();
                $model->updated_at = date("Y-m-d H:i:s");
                $model->save();
                Yii::$app->ShowFlashMessages->setMsg('Success', $model->regulation_year.' Updated Successfully!!!');
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', 'Unable to Update '.$model->regulation_year.' No Data Found');
            } 
            return $this->redirect(['view', 'id' => $model->coe_bat_deg_reg_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing CoeBatDegReg model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the CoeBatDegReg model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CoeBatDegReg the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CoeBatDegReg::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
