<?php

namespace app\controllers;

use Yii;
use app\models\ElectiveWaiver;
use app\models\ElectiveWaiverSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\StudentMapping;
use app\models\Student;
use app\models\Subjects;
use app\models\MandatorySubjects;
use app\models\MandatoryStuMarks;
use app\models\MandatorySubcatSubjects;
use app\models\SubjectsMapping;
use yii\db\Query;
/**
 * ElectiveWaiverController implements the CRUD actions for ElectiveWaiver model.
 */
class ElectiveWaiverController extends Controller
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
     * Lists all ElectiveWaiver models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new ElectiveWaiverSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single ElectiveWaiver model.
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
     * Creates a new ElectiveWaiver model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new ElectiveWaiver();
        $student = new Student();
        if ($model->load(Yii::$app->request->post())) 
        {
            if(!empty($_POST['completed_subs']))
            {         
                $stu_reg_num= $_POST['stu_reg_num'];
                $completed_subs = $_POST['completed_subs'];
                $status = 0;                
                $stu_id = $_POST['stu_id'];
                $get_sinfo = 0;
                $stuInfo = StudentMapping::findOne($_POST['stu_id']);

                for ($count=0; $count <count($completed_subs) ; $count++) 
                {   
                    $completed_subs_spl = explode(',', trim($completed_subs[$count]," "));
                    $completed_subs_spl = array_filter($completed_subs_spl);
                    
                    for ($subCod=0; $subCod <count($completed_subs_spl) ; $subCod++) 
                    { 
                       $subFound= Subjects::find()->where(['subject_code'=>$completed_subs_spl[$subCod]])->all();
                       if(strpos($completed_subs_spl[$subCod], '-'))
                       {
                            $man_sub_cat_split = explode('-', trim($completed_subs_spl[$subCod]," "));
                            
                            $MansubFound_get= MandatorySubjects::find()->where(['subject_code'=>$man_sub_cat_split[0],'batch_mapping_id'=>$stuInfo->course_batch_mapping_id])->one();
                            if(!empty($MansubFound_get))
                            {
                                $MansubFound= MandatorySubcatSubjects::find()->where(['man_subject_id'=>$MansubFound_get['coe_mandatory_subjects_id'],'batch_map_id'=>$stuInfo->course_batch_mapping_id,'sub_cat_code'=>$man_sub_cat_split[1]])->one();
                            }
                            else
                            {
                                $MansubFound=0;
                            }
                       }
                       else 
                       {
                            $MansubFound= MandatorySubjects::find()->where(['subject_code'=>$completed_subs_spl[$subCod],'batch_mapping_id'=>$stuInfo->course_batch_mapping_id])->all();
                       }
                       
                        if(!empty($subFound))
                        {
                            for ($k=0; $k <count($subFound) ; $k++) 
                            { 
                                $getInfoSubs = SubjectsMapping::find()->where(['subject_id'=>$subFound[$k]['coe_subjects_id'],'batch_mapping_id'=>$stuInfo->course_batch_mapping_id])->one();
                                if(!empty($getInfoSubs))
                                {
                                    $get_sinfo=$get_sinfo+1;
                                }
                            }
                        }
                        else if(!empty($MansubFound))
                        {
                            $checkMarkEntry = MandatoryStuMarks::find()->where(['subject_map_id'=>$MansubFound['coe_mandatory_subcat_subjects_id'],'student_map_id'=>$_POST['stu_id'],'result'=>'Pass'])->one();
                            if(!empty($checkMarkEntry))
                            {
                                $get_sinfo=$get_sinfo+1;
                            }
                            
                            
                        }
                        else
                        {
                            $get_sinfo=0;
                        }
                        
                    }
                    
                }
                if($get_sinfo!=$_POST['total_waiver'][0])
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"<b>".$stu_reg_num.'</b> NOT ELIGIBLE FOR ELECTIVE WAIVER WHERE SPECIFIED SUBJECTS DOESN\'T NOT ASSIGNED FOR THIS DEGREE');
                    return $this->redirect(['create']);
                }
               
                $total_waiver = $_POST['total_waiver'];
                $elec_sel_wai = $_POST['elec_sel_wai'];
                $year = $model->year;
                $month = $model->month;
                $newModel = new ElectiveWaiver();
                for ($i=0; $i <count($elec_sel_wai) ; $i++) 
                { 
                    if(isset($_POST['reason_'.$i]) && !empty($_POST['reason_'.$i]) && isset($_POST['elect_wwa_'.$i]) && $_POST['elect_wwa_'.$i]=='on')
                    {
                        $reason = $_POST['reason_'.$i];
                        $getSubMap = SubjectsMapping::findOne($_POST['elec_sel_wai'][$i]);
                        
                        $newModel = new ElectiveWaiver();
                        $newModel->student_map_id = $stu_id;
                        $newModel->removed_sub_map_id = $_POST['elec_sel_wai'][$i];
                        $newModel->waiver_reason = $reason;
                        $newModel->total_studied = $total_waiver[0];
                        $newModel->subject_codes = $completed_subs[0];
                        $newModel->year = $year;
                        $newModel->month = $month;
                        $newModel->created_at = new \yii\db\Expression('NOW()');
                        $newModel->created_by = Yii::$app->user->getId();
                        $newModel->updated_at = new \yii\db\Expression('NOW()');
                        $newModel->updated_by = Yii::$app->user->getId();
                        
                        if(!empty($getSubMap) && $newModel->save(false))
                        {
                            $coe_student_id = $stuInfo->student_rel_id;                            
                            $query = "DELETE FROM coe_nominal WHERE coe_student_id='".$coe_student_id."' AND coe_subjects_id='".$getSubMap->subject_id."' AND semester='".$_POST['seme']."' and course_batch_mapping_id='".$getSubMap->batch_mapping_id."' and section_name='".$stuInfo->section_name."'";
                            $delNominal = Yii::$app->db->createCommand($query)->execute();
                            if($delNominal)
                            {
                                Yii::$app->ShowFlashMessages->setMsg('Success','Nominal Deleted Successfully!!!');
                            }
                            unset($newModel);
                        }
                        
                    }
                }

            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','OOPS.. Nothing Happens');
            }
            return $this->redirect(['create']);
            
        } else {
            return $this->render('create', [
                'model' => $model,
                'student' =>$student,
            ]);
        }
    }

    /**
     * Updates an existing ElectiveWaiver model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_elective_waiver_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing ElectiveWaiver model.
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
     * Finds the ElectiveWaiver model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ElectiveWaiver the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ElectiveWaiver::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
