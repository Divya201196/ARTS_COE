<?php

namespace app\controllers;

use Yii;
use app\models\CoeValueSubjects;
use app\models\Sub;
use app\models\CoeValueSubjectsSearch;
use app\models\SubSearch;
use app\models\Degree;
use app\models\Programme;
use app\models\Batch;
use app\models\CoeBatDegReg;
use app\models\Regulation;
use app\models\Categorytype;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\widgets\ActiveForm;
// Configuaration Information
use app\components\ConfigConstants;
use app\components\ConfigUtilities;


/**
 * CoeValueSubjectsController implements the CRUD actions for CoeValueSubjects model.
 */
class CoeValueSubjectsController extends Controller
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
     * Lists all CoeValueSubjects models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CoeValueSubjectsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CoeValueSubjects model.
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
     * Creates a new CoeValueSubjects model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    /*public function actionCreate()
    {
        $model = new CoeValueSubjects();
        $subjects = new Sub();
        $batchmapping   =   new CoeBatDegReg(); 

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_val_sub_id]);
        } else {
            return $this->render('create', [
                'model' => $model,'subjects'=>$subjects,'batchmapping' => $batchmapping,
            ]);
        }
    }*/

    public function actionCreate()
    {
      $model = new CoeValueSubjects();
        $subjects = new Sub();
      $batchmapping   =   new CoeBatDegReg(); 
       
      if (Yii::$app->request->isAjax) 
      {
        if($model->load(Yii::$app->request->post())) 
        {
          \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
          return ActiveForm::validate($model);
        }
      }
      
      if ($model->load(Yii::$app->request->post()) ) 
      {
        $sub=$_POST['CoeValueSubjects']['subject_code'];
        $rows = CoeValueSubjects::find()->where(['subject_code' => $sub])->one();
        $semester_c= $_POST['Sub']['semester'];
        $batch_map_id=$_POST['bat_map_val'];
        $sub_type_val=$_POST['sub_type_val'];
        $prgm_type_val=$_POST['prgm_type_val'];
        $paper_type_val=$_POST['paper_type_val'];
        $created_at = new \yii\db\Expression('NOW()');
        $created_by = Yii::$app->user->getId();
        $updated_at = new \yii\db\Expression('NOW()');
        $updated_by = Yii::$app->user->getId();
       
        if(count($rows)>0)
        {
            $sub_id=$rows->coe_val_sub_id;
        }
        else if(!empty($_POST['bat_map_val']) && !empty($_POST['bat_val']))
        {
          
            $model->created_at = $created_at;
            $model->created_by = $created_by;
            $model->updated_at = $updated_at;
            $model->updated_by = $updated_by;
            $model->save();
            $sub_id=$model->coe_val_sub_id;
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Error',' Select The Required Fields ');
            return $this->render('create', [
              'model' => $model,
              'subjects' => $subjects,
              'batchmapping' => $batchmapping,
            ]);
        }
        
        $rows1 = Sub::find()->where(['batch_mapping_id' => $batch_map_id , 'val_subject_id' => $sub_id,'semester'=>$semester_c])->one();

        if(count($rows1)>0)
        {
            Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' already created for this '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
            unset($model);
            unset($subjects);
            $model = new CoeValueSubjects();
            $subjects = new Sub();
            return $this->render('create', [
              'model' => $model,
              'subjects' => $subjects,
              'batchmapping' => $batchmapping,
            ]);
          
        }
        else
        {
          $subjects->batch_mapping_id=$batch_map_id;
          $subjects->val_subject_id=$sub_id;
          $subjects->paper_no=$_POST['Sub']['paper_no'];
          $subjects->paper_type_id=$paper_type_val;
          $subjects->subject_type_id=$sub_type_val;
          $subjects->course_type_id=$prgm_type_val;
          $subjects->migration_status="NO";
          $subjects->semester=$semester_c;
          $subjects->created_at = $created_at;
          $subjects->created_by = $created_by;
          $subjects->updated_at = $updated_at;
          $subjects->updated_by = $updated_by;          
          $subjects->save();
          $insert_id = $subjects->coe_sub_mapping_id;
          unset($subjects);
          Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' created Successfully!! ');
           return $this->redirect(['sub/view', 'id' => $insert_id]);
        }
        $model = new Subjects();
        $subjects = new SubjectsMapping();
          return $this->redirect(['index']);
        
      } 
      else 
      {
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));
        return $this->render('create', [
          'model' => $model,
          'subjects' => $subjects,
          'batchmapping' => $batchmapping,
        ]);
      }
    }


    /**
     * Updates an existing CoeValueSubjects model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_val_sub_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing CoeValueSubjects model.
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
     * Finds the CoeValueSubjects model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CoeValueSubjects the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CoeValueSubjects::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
