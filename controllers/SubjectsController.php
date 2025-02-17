<?php

namespace app\controllers;
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');
use Yii;
use app\models\Subjects;
use app\models\SubjectsSearch;
use app\models\SubjectsMapping;
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
 * SubjectsController implements the CRUD actions for Subjects model.
 */
class SubjectsController extends Controller
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
     * Lists all Subjects models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new SubjectsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        
        $this->redirect(['subjects-mapping/index']);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Subjects model.
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
     * Creates a new Subjects model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
      $model = new Subjects();
      $subjects = new SubjectsMapping();
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
        $sub=$_POST['Subjects']['subject_code'];
        $rows = Subjects::find()->where(['subject_code' => $sub])->one();
        $semester_c= $_POST['SubjectsMapping']['semester'];
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
            $sub_id=$rows->coe_subjects_id;
        }
        else if(!empty($_POST['bat_map_val']) && !empty($_POST['bat_val']))
        {
          
            $model->created_at = $created_at;
            $model->created_by = $created_by;
            $model->updated_at = $updated_at;
            $model->updated_by = $updated_by;
            $model->save();
            $sub_id=$model->coe_subjects_id;
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
        
        $rows1 = SubjectsMapping::find()->where(['batch_mapping_id' => $batch_map_id , 'subject_id' => $sub_id,'semester'=>$semester_c])->one();

        if(count($rows1)>0)
        {
            Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' already created for this '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
            unset($model);
            unset($subjects);
            $model = new Subjects();
            $subjects = new SubjectsMapping();
            return $this->render('create', [
              'model' => $model,
              'subjects' => $subjects,
              'batchmapping' => $batchmapping,
            ]);
          
        }
        else
        {
  	      $subjects->batch_mapping_id=$batch_map_id;
  	      $subjects->subject_id=$sub_id;
          $subjects->paper_no=$_POST['SubjectsMapping']['paper_no'];
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
          $insert_id = $subjects->coe_subjects_mapping_id;
          unset($subjects);
          Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' created Successfully!! ');
  	       return $this->redirect(['subjects-mapping/view', 'id' => $insert_id]);
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
     * Updates an existing Subjects model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
      $model = $this->findModel($id); 
      $subjects = SubjectsMapping::findOne(['subject_id'=>$model->coe_subjects_id]); 
      //$subjects = SubjectsMapping::findOne($model->coe_subjects_id);


      if ($model->load(Yii::$app->request->post()) && $model->validate()) 
      {
        $bat_val=$_POST['bat_val'];
        $batch_map_id=$_POST['bat_map_val'];
        $sub_type_val=$_POST['sub_type_val'];
        $prgm_type_val=$_POST['prgm_type_val'];
        $paper_type_val=$_POST['paper_type_val'];

        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();

        if($model->save())
        {
          $subjects->batch_mapping_id=$batch_map_id;
          $subjects->subject_id=$model->coe_subjects_id;

          $subjects->paper_type_id=$paper_type_val;
          $subjects->subject_type_id=$sub_type_val;
          $subjects->course_type_id=$prgm_type_val;

          $subjects->updated_at = $updated_at;
          $subjects->updated_by = $updated_by;

          $subjects->save();
        }
        else
        {
          return $this->render('update', [
                'model' => $model,
                'subjects' => $subjects,
            ]);
        }
        return $this->redirect(['view', 'id' => $model->coe_subjects_id]);
      }
      else
      {
        return $this->render('update', [
                'model' => $model,
                'subjects' => $subjects,
            ]);
      }
        
    }

    /**
     * Deletes an existing Subjects model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);  
        $subject_code = $model->coe_subjects_id;
        $stuMapping = SubjectsMapping::deleteAll(['subject_id' => $id]);
        if($model->delete())
        {
            Yii::$app->ShowFlashMessages->setMsg('Success',$subject_code.' Deleted Successfully!! ');
            return $this->redirect(['index']);
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Perform the action for ".$subject_code.' <br /> Technical Problem');
            return $this->redirect(['index']);
        }
   

        
    }

    /**
     * Finds the Subjects model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Subjects the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Subjects::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionMigrate()
    {
      $model = new Subjects();
      $subjects = new SubjectsMapping();
      $batchmapping   =   new CoeBatDegReg(); 

      if(Yii::$app->request->post())
      {

      $sn = Yii::$app->request->post('sn');
      $batch_map_id = Yii::$app->request->post('bat_map_val');
      $sem = Yii::$app->request->post('sem');

      //Getting deg_id and Pgm_id
      $bat_map_val = CoeBatDegReg::find()->where(['coe_bat_deg_reg_id'=>$batch_map_id])->one();
      $deg_id = $bat_map_val['coe_degree_id'];
      $pgm_id = $bat_map_val['coe_programme_id'];

      //Getting bat_map_id
      $mig_batch = Yii::$app->request->post('mig_bat_val');
      $bat_map_val1 = CoeBatDegReg::find()->where(['coe_degree_id'=>$deg_id , 'coe_programme_id'=>$pgm_id , 'coe_batch_id'=>$mig_batch])->one();

        if(empty($bat_map_val1))
        {
            Yii::$app->ShowFlashMessages->setMsg('Error','No '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)." Was Assigned..");
            return $this->redirect(['migrate']);
        }
        for($k=1;$k<=$sn;$k++)
        {
          if(isset($_POST['mig'.$k]))
          {
            $status = $_POST["mig$k"];
            $sub_code = $_POST["sub_code$k"];
            
            //Getting sub_id 
            $sub_val = Subjects::find()->where(['subject_code' => $sub_code])->one();

            $created_at = new \yii\db\Expression('NOW()');
            $created_by = Yii::$app->user->getId();
            $updated_at = new \yii\db\Expression('NOW()');
            $updated_by = Yii::$app->user->getId();

            //Getting all sub_id with NO condition
            $sub_map_val = SubjectsMapping::find()->where(['batch_mapping_id' => $batch_map_id , 'subject_id' => $sub_val->coe_subjects_id , 'migration_status' => "NO"])->one();
            
            $sub_map_val1 = SubjectsMapping::find()->where(['batch_mapping_id' => $bat_map_val1->coe_bat_deg_reg_id , 'subject_id' => $sub_val->coe_subjects_id ])->one();


            if(count($sub_map_val1)>0)
            {
              Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' already created for this '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
              //return $this->render('migrate', ['model' => $model,'subjects' => $subjects,'batchmapping' => $batchmapping]);
            }
            else
            {
              $subjects->batch_mapping_id=$bat_map_val1->coe_bat_deg_reg_id;
              $subjects->subject_id=$sub_val->coe_subjects_id;
              $subjects->paper_type_id=$sub_map_val->paper_type_id;
              $subjects->subject_type_id=$sub_map_val->subject_type_id;
              $subjects->course_type_id=$sub_map_val->course_type_id;
              $subjects->semester=$sem;
              $subjects->migration_status=$status;
              $subjects->created_at = $created_at;
              $subjects->created_by = $created_by;
              $subjects->updated_at = $updated_at;
              $subjects->updated_by = $updated_by;

              $subjects->save();
              unset($subjects);
              $subjects = new SubjectsMapping();
             
            }
          }
        } // Loop Ends Here 

        Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' Migrated Successfully for '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
            return $this->redirect(['migrate']);
     
      }
      else
      {
          Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MIGRATE_STATUS). ' '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));
        return $this->render('migrate', [
            'model' => $model,
            'subjects' => $subjects,
            'batchmapping' => $batchmapping,
          ]);
      }

    }
    public function actionExcelTopperslist()
    {        
        $content = $_SESSION['singlAttemprPass'];            
        $fileName = "Toppers List" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionToppersList()
    {
      $model = new Subjects();
      $subjects = new SubjectsMapping();
      $batchmapping   =   new CoeBatDegReg(); 
       
      if ($model->load(Yii::$app->request->post()) ) 
      {
        
        
      } 
      else 
      {
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Toppers List');
        return $this->render('toppers-list', [
          'model' => $model,
          'subjects' => $subjects,
          'batchmapping' => $batchmapping,
        ]);
      }
    }


}
