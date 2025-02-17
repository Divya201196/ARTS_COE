<?php

namespace app\controllers;

use Yii;
use app\models\MandatoryStuMarks;
use app\models\MandatoryStuMarksSearch;
use app\models\MandatorySubcatSubjects;
use app\models\MandatorySubjects;
use app\models\Student;
use app\models\StudentMapping;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * MandatoryStuMarksController implements the CRUD actions for MandatoryStuMarks model.
 */
class MandatoryStuMarksController extends Controller
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
     * Lists all MandatoryStuMarks models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new MandatoryStuMarksSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single MandatoryStuMarks model.
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
     * Creates a new MandatoryStuMarks model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new MandatoryStuMarks();
        $sub_model = new MandatorySubcatSubjects();
        $mandatorySubjects = new MandatorySubjects();
        if ($model->load(Yii::$app->request->post())) 
        {
            $sn = Yii::$app->request->post('sn');
            
            $batch_id = $_POST['MandatorySubcatSubjects']['coe_batch_id'];
            $man_subject_id = $_POST['MandatorySubcatSubjects']['man_subject_id'];
            $sub_cat_code = $_POST['MandatorySubcatSubjects']['sub_cat_code'];
            $exam_year =$model->year;
            $batch_map_id =$_POST['MandatorySubcatSubjects']['batch_map_id'];
            $exam_month = $model->month;
            $mark_type = $model->mark_type;
            $term = $model->term;
            $created_by = Yii::$app->user->getId();
            $created_at = date("Y-m-d H:i:s"); 
            $catesubInfo = MandatorySubcatSubjects::findOne($sub_cat_code);
            $subInfo = MandatorySubjects::findOne($catesubInfo->man_subject_id);
           
            for ($k = 1; $k <= $sn; $k++) 
            {
                if (isset($_POST['add' . $k]) && !empty($_POST['add' . $k]) && $_POST['add' . $k]=='on') 
                {
                    
                    $stuInfo = Student::find()->where(['register_number'=>$_POST['reg_num'. $k]])->one();
                    $stuMapp = StudentMapping::find()->where(['student_rel_id'=>$stuInfo->coe_student_id])->one();

                    $marks = $_POST['actxt_' . $k];
                    $result = $_POST['acresult_' . $k];
                    $grade_name = ($result=='Pass' || $result=='pass' || $result=='PASS') ? $_POST['grade_' . $k]:'U';
                    $grade_point = $_POST['grade_point_' . $k]=='' ? 0:$_POST['grade_point_' . $k];
                    $result = $_POST['acresult_' . $k];
                    $year_of_passing = ($result=='Pass' || $result=='pass' || $result=='PASS') ? $exam_month."-".$exam_year: "";

                    $check_query = MandatoryStuMarks::find()->where(['student_map_id'=>$stuMapp->coe_student_mapping_id,'subject_map_id'=>$sub_cat_code,'year'=>$exam_year,'month'=>$exam_month,'mark_type'=>$mark_type])->one();

                    if(empty($check_query))
                    {
                        $newModel = new MandatoryStuMarks();
                        $newModel->student_map_id = $stuMapp->coe_student_mapping_id;
                        $newModel->subject_map_id = $sub_cat_code;
                        $newModel->CIA = $marks;
                        $newModel->ESE = 0;
                        $newModel->total = $marks;
                        $newModel->result = $result;
                        $newModel->grade_name = $grade_name;
                        $newModel->grade_point = $grade_point;
                        $newModel->year = $exam_year;
                        $newModel->month = $exam_month;
                        $newModel->semester = $model->semester;
                        $newModel->term = $term;
                        $newModel->mark_type = $mark_type;
                        $newModel->status_id = 0;
                        $newModel->year_of_passing = $year_of_passing;
                        $newModel->attempt = 0;
                        $newModel->created_by = $created_by;
                        $newModel->created_at = $created_at;
                        $newModel->updated_by = $created_by;
                        $newModel->updated_at = $created_at;
                        $newModel->save(false);                        
                        unset($newModel);
                    }
                }
            }
            Yii::$app->ShowFlashMessages->setMsg('Success', 'Marks Inserted Successfully for <b>'.$subInfo->subject_code.'</b> AND <b>'.$catesubInfo->sub_cat_code.'</b> !!!');
            return $this->redirect(['create',]);
        } else {
            return $this->render('create', [
                'model' => $model,
                'sub_model' =>$sub_model,
                'mandatorySubjects' =>$mandatorySubjects,
            ]);
        }
    }

    /**
     * Updates an existing MandatoryStuMarks model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_mandatory_stu_marks_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing MandatoryStuMarks model.
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
     * Finds the MandatoryStuMarks model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return MandatoryStuMarks the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = MandatoryStuMarks::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
