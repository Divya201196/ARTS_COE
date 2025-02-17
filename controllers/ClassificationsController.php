<?php

namespace app\controllers;

use Yii;
use app\models\Classifications;
use app\models\ClassificationsSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\widgets\ActiveForm;
/**
 * ClassificationsController implements the CRUD actions for Classifications model.
 */
class ClassificationsController extends Controller
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
     * Lists all Classifications models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ClassificationsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Classifications model.
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
     * Creates a new Classifications model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Classifications();

        if (Yii::$app->request->isAjax) 
          {
            if($model->load(Yii::$app->request->post())) 
            {
              \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
              return ActiveForm::validate($model);
            }
          }
        if ($model->load(Yii::$app->request->post()))
        {
            $created_at = new \yii\db\Expression('NOW()');
            $created_by = Yii::$app->user->getId();
            $model->created_at = $created_at;
            $model->created_by = $created_by;
            $model->updated_at = $created_at;
            $model->updated_by = $created_by;
            $check_exists = Classifications::find()->where(['regulation_year'=>$model->regulation_year,'grade_name'=>$model->grade_name])->one();
            $check_exists_1 = Classifications::find()->where(['regulation_year'=>$model->regulation_year,'percentage_from'=>$model->percentage_from])->one();
            
            if(!empty($check_exists) || !empty($check_exists_1))
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','OOPS Duplicate Values Submitted');
                return $this->redirect(['create']);
            }
            else
            {
                if($model->save())
                {
                    Yii::$app->ShowFlashMessages->setMsg('Success','Record Inserted Successfully!!!');
                    return $this->redirect(['index']);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','Something Wrong with Submission');
                    return $this->redirect(['create']);
                }
            }
            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Classifications Print');
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Classifications model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) 
        {
            $created_at = new \yii\db\Expression('NOW()');
            $created_by = Yii::$app->user->getId();           
            $model->updated_at = $created_at;
            $model->updated_by = $created_by;

            if($model->save())
            {
                Yii::$app->ShowFlashMessages->setMsg('Success','Record Updated Successfully!!!');
                return $this->redirect(['view', 'id' => $model->coe_classifications_id]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','Something Wrong with Submission');
                return $this->redirect(['index']);
            }
            
            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','YOU ARE UPDATING '.$model->regulation_year."-".$model->classification_text.' !!!');
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Classifications model.
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
     * Finds the Classifications model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Classifications the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Classifications::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
