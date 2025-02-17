<?php

namespace app\controllers;

use Yii;
use app\models\StudentCategoryDetails;
use app\models\Categorytype;
use app\models\StudentCategoryDetailsSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

/**
 * StudentCategoryDetailsController implements the CRUD actions for StudentCategoryDetails model.
 */
class StudentCategoryDetailsController extends Controller
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
     * Lists all StudentCategoryDetails models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new StudentCategoryDetailsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single StudentCategoryDetails model.
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
     * Creates a new StudentCategoryDetails model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new StudentCategoryDetails();

        if ($model->load(Yii::$app->request->post())) 
        {
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId(); 
            if(isset($_POST['StudentCategoryDetails']['subject_code']) && !empty($_POST['StudentCategoryDetails']['subject_code']))
            {
                
                $name_of_variable = $_POST['StudentCategoryDetails']['subject_code'];
                $subject_count = count($name_of_variable);
               
                for ($i=0; $i <$subject_count ; $i++) 
                { 
                    
                   $model->student_map_id = $_POST['StudentCategoryDetails']['student_map_id'];
                   $model->old_clg_reg_no = $_POST['StudentCategoryDetails']['old_clg_reg_no'];
                   $model->subject_code = $_POST['StudentCategoryDetails']['subject_code'][$i];
                   $model->subject_name = $_POST['StudentCategoryDetails']['subject_name'][$i];
                   $model->CIA = $_POST['StudentCategoryDetails']['CIA'][$i];
                   $model->ESE = $_POST['StudentCategoryDetails']['ESE'][$i];
                   $model->total = $_POST['StudentCategoryDetails']['total'][$i];
                   $model->result = $_POST['StudentCategoryDetails']['result'][$i];
                   $model->credit_point = $_POST['StudentCategoryDetails']['credit_point'][$i];
                   $model->grade_point = $_POST['StudentCategoryDetails']['grade_point'][$i];
                   $model->grade_name = $_POST['StudentCategoryDetails']['grade_name'][$i];
                   $model->semester = $_POST['StudentCategoryDetails']['semester'][$i];
                   $model->gpa = $_POST['StudentCategoryDetails']['gpa'][$i];
                   $model->year = $_POST['StudentCategoryDetails']['year'][$i];
                   $model->month = $_POST['StudentCategoryDetails']['month'][$i];
                   $model->year_of_passing = $_POST['StudentCategoryDetails']['month'][$i]."-".$_POST['StudentCategoryDetails']['year_of_passing'][$i];
                   $model->stu_status_id =$_POST['StudentCategoryDetails']['stu_status_id'];
                   $model->created_by = $updateBy;
                   $model->created_at = $created_at;
                   $model->updated_by = $updateBy;
                   $model->updated_at = $created_at;
                   $model->save();
                   $model = new StudentCategoryDetails();
                }
               
               Yii::$app->ShowFlashMessages->setMsg('Success',"All Data Updated Successfully!!!");
               
               return $this->redirect(['index']);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data updated");
                 return $this->render('create', [
                    'model' => $model,
                ]);
            }
            
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing StudentCategoryDetails model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) 
        {
            $student_map_id = $model->student_map_id;
            $old_clg_reg_no = $model->old_clg_reg_no;
            $stu_status_id = $model->stu_status_id;
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId(); 
            if(isset($_POST['StudentCategoryDetails']['subject_code']) && !empty($_POST['StudentCategoryDetails']['subject_code']))
            {

                $name_of_variable = $_POST['StudentCategoryDetails']['subject_code'];
                $subject_count = count($name_of_variable);
                
                for ($i=0; $i <$subject_count ; $i++) 
                { 
                   $model->student_map_id = $student_map_id;
                   $model->old_clg_reg_no = $old_clg_reg_no;
                   $model->subject_code = $_POST['StudentCategoryDetails']['subject_code'][$i]; 
                   $model->subject_name = $_POST['StudentCategoryDetails']['subject_name'][$i];
                   $model->CIA = $_POST['StudentCategoryDetails']['CIA'][$i];
                   $model->ESE = $_POST['StudentCategoryDetails']['ESE'][$i];
                   $model->total = $_POST['StudentCategoryDetails']['total'][$i];
                  $model->credit_point = $_POST['StudentCategoryDetails']['credit_point'][$i]; 
                   $model->result = $_POST['StudentCategoryDetails']['result'][$i];
                   $model->grade_point = $_POST['StudentCategoryDetails']['grade_point'][$i];
                   $model->grade_name = $_POST['StudentCategoryDetails']['grade_name'][$i];
                   $model->semester = $_POST['StudentCategoryDetails']['semester'][$i];
                   $model->gpa = $_POST['StudentCategoryDetails']['gpa'][$i];
                   $model->year = $_POST['StudentCategoryDetails']['year'][$i];
                   $model->month = $_POST['StudentCategoryDetails']['month'][$i];
                   $model->year_of_passing = $_POST['StudentCategoryDetails']['year_of_passing'][$i];
                   $model->stu_status_id =$stu_status_id;
                   $model->created_by = $updateBy;
                   $model->created_at = $created_at;
                   $model->updated_by = $updateBy;
                   $model->updated_at = $created_at;
                   $model->save(false);
                   
                }
               
               Yii::$app->ShowFlashMessages->setMsg('Success',"All Data Updated Successfully!!!");
               
               return $this->redirect(['view', 'id' => $model->coe_student_category_details_id]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data updated");
                 return $this->render('create', [
                    'model' => $model,
                ]);
            }
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing StudentCategoryDetails model.
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
     * Finds the StudentCategoryDetails model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return StudentCategoryDetails the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = StudentCategoryDetails::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
