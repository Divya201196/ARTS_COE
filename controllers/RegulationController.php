<?php

namespace app\controllers;

use Yii;
use app\models\Regulation;
use app\models\MandatoryStuMarks;
use app\models\MarkEntryMaster;
use app\models\RegulationSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * RegulationController implements the CRUD actions for Regulation model.
 */
class RegulationController extends Controller
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
     * Lists all Regulation models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new RegulationSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Regulation model.
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
     * Creates a new Regulation model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Regulation();

        if ($model->load(Yii::$app->request->post())) 
        {
           
            $check_exits = Regulation::find()->where(['coe_batch_id'=>$model->coe_batch_id,'regulation_year'=>$model->regulation_year])->all();
            $model->updated_by = Yii::$app->user->getId();
            $model->updated_at = date("Y-m-d H:i:s");
            $model->created_by = Yii::$app->user->getId();
            $model->created_at = date("Y-m-d H:i:s");
            $grade_point = $grade_point_from=$grade_point_to=$grade_name=[];
            if(!empty($check_exits))
            {
                foreach ($check_exits as $key => $value) 
                {
                    $grade_point[]=$value['grade_point'];
                    $grade_point_from[]=$value['grade_point_from'];
                    $grade_point_to[]=$value['grade_point_to'];
                    $grade_name[]=$value['grade_name'];
                }
            }
            
            if(!empty($grade_point) && !empty($grade_point_from))
            {
                if(!in_array($model->grade_point, $grade_point) && !in_array($model->grade_point_from, $grade_point_from) && !in_array($model->grade_point_to, $grade_point_to) && !in_array($model->grade_name, $grade_name))
                {

                    $model->save();
                    Yii::$app->ShowFlashMessages->setMsg('Success', $model->regulation_year.' Created  Successfully!!!');
                    return $this->redirect(['view', 'id' => $model->coe_regulation_id]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error', ' Duplicate Grades');
                    return $this->redirect(['create']);
                }
            }
            else
            {
              
                 $model->save();
                 Yii::$app->ShowFlashMessages->setMsg('Success', $model->regulation_year.' Created  Successfully!!!');
                 return $this->redirect(['view', 'id' => $model->coe_regulation_id]);
            }
            
            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Regulation Creation!!!');
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Regulation model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) 
        {
            $model->save();
            return $this->redirect(['view', 'id' => $model->coe_regulation_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Regulation model.
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
     * Finds the Regulation model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Regulation the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Regulation::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
