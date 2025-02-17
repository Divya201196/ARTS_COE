<?php

namespace app\controllers;

use Yii;
use app\models\CoeBatDegReg;
use app\models\SubjectsMapping;
use app\models\StudentMapping;
use app\models\Programme;
use app\models\Regulation;
use app\models\ProgrammeSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use kartik\widgets\Growl;

/**
 * ProgrammeController implements the CRUD actions for Programme model.
 */
class ProgrammeController extends Controller
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
     * Lists all Programme models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ProgrammeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).' ');
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Programme model.
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
     * Creates a new Programme model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Programme();
        
        if ($model->load(Yii::$app->request->post()))
        {
            $model->created_at = new \yii\db\Expression('NOW()');
            $model->created_by = Yii::$app->user->getId();
            $model->updated_at = new \yii\db\Expression('NOW()');
            $model->updated_by = Yii::$app->user->getId();
            $model->attributes;
            //$model->save(); 
             
                if($model->save())
                {  
                    unset($model);
                    $model = new Programme();
                    Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).' Created Successfully!!!! ');
                    return $this->render('create', ['model' => $model,]);
                } 
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).' Creation Error!!!! ');
                    return $this->render('create', ['model' => $model,]);
                }
        }       
        else 
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome to ',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Programme model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) ) {

            $batch_mapping = Programme::find()->where(['programme_name'=>$model->programme_name])->one();
            $name_of_degree = $model->programme_name;
            
            if(empty($batch_mapping))
            {
                $model->save();
                Yii::$app->ShowFlashMessages->setMsg('Success',"<b>".$name_of_degree.'</b> Has Updated Successfully!! ');
                return $this->redirect(['update', 'model' => $model,'id' => $model->coe_programme_id]);                
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',' You can not Update <b>'.$name_of_degree.'</b> Because Already Same <b>'.$name_of_degree.'</b> Available');
                return $this->redirect(['update',  'model' => $model,'id' => $model->coe_programme_id]);
                
                
            }


            //return $this->redirect(['view', 'id' => $model->coe_programme_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Programme model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $batch_mapping = CoeBatDegReg::find()->where(['coe_programme_id'=>$id])->one();
        $degree_name = Programme::findOne($id);
        $name_of_degree = $degree_name->programme_code;
        if(empty($batch_mapping))
        {
            $this->findModel($id)->delete();
            Yii::$app->ShowFlashMessages->setMsg('Success',"<b>".$name_of_degree.'</b> Has Deleted Successfully!! ');
            return $this->redirect(['index']);
            
        }
        else
        {
            $SubjectsMapping = SubjectsMapping::findOne(['batch_mapping_id'=>$batch_mapping->coe_bat_deg_reg_id]); 
            $StudentMapping = StudentMapping::findOne(['course_batch_mapping_id'=>$batch_mapping->coe_bat_deg_reg_id]);
            if(empty($SubjectsMapping) && empty($StudentMapping) && empty($batch_mapping))
            {      
                
                $this->findModel($id)->delete();
                Yii::$app->ShowFlashMessages->setMsg('Success'," <b>".$name_of_degree.'</b> Has Deleted Successfully!! ');
                
                return $this->redirect(['index']);                    
            }
            else
            {
               
                Yii::$app->ShowFlashMessages->setMsg('Error',' You can not delete this <b>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).'</b> Because already <b>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)."</b> are Assigned OR <b>".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)."</b> are Available");
                return $this->redirect(['index']);
            }
            
        }

    }

    /**
     * Finds the Programme model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Programme the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Programme::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
