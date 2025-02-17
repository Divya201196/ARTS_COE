<?php

namespace app\controllers;

use Yii;
use yii\db\Query;
use kartik\mpdf\Pdf;
use yii\helpers\Html;
use app\models\PracticalEntry;
use app\models\MarkEntry;
use app\models\ExamTimetable;
use app\models\AbsentEntry;
use app\models\Student;
use app\models\HallAllocate;
use app\models\Categorytype;
use app\models\PracticalEntrySearch;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\StudentMapping;
use app\models\MarkEntryMaster;
use app\models\SubjectsMapping;
use app\models\Subjects;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * PracticalEntryController implements the CRUD actions for PracticalEntry model.
 */
class PracticalEntryController extends Controller
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
     * Lists all PracticalEntry models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new PracticalEntrySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single PracticalEntry model.
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
     * Displays a single PracticalEntry model.
     * @param integer $id
     * @return mixed
     */
    public function actionVerifyAndMigrate()
    {
        $model = new PracticalEntry();

        if (Yii::$app->request->post()) 
        {
            if(isset($_POST['stu_map_ids']) && !empty($_POST['stu_map_ids']) && isset($_POST['stu_marks']) && !empty($_POST['stu_marks']) )
            {
                $stuCount = count($_POST['stu_map_ids']);
                $stuMarksCount = count($_POST['stu_marks']);
                $subject_map_id = $_POST['sub_val'];
                $month = $_POST['month'];
                $year = $_POST['PracticalEntry']['year'];
               
                $mark_type = $_POST['PracticalEntry']['mark_type'];
                $created_at = date("Y-m-d H:i:s");
                $updateBy = Yii::$app->user->getId(); 
                $cia = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%CIA%' OR category_type like '%Internal%'")->queryScalar();
                $externAl = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%ESE%'")->queryScalar();
                $term = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%End%'")->queryScalar();

                $subMapping = SubjectsMapping::findOne($subject_map_id);
                $subJecs = Subjects::findOne($subMapping->subject_id);

                $check_CIA_marks = MarkEntry::find()->where(['category_type_id'=>$cia,'subject_map_id'=>$subject_map_id])->one();
                //print_r($check_CIA_marks);exit;
                $migrade_status = 0;

                if(empty($check_CIA_marks))
                {


                    for ($i=0; $i <count($_POST['stu_map_ids']) ; $i++) 
                    {

                         $cia_marks = 0;
                         $ese_marks=$_POST['stu_marks'][$i];
                        $total_marks = $ese_marks;
                        $convert_ese_marks = $ese_marks;

                        $stu_result_data = ConfigUtilities::StudentResult($_POST['stu_map_ids'][$i], $subject_map_id, $cia_marks, $_POST['stu_marks'][$i],$year,$month);

                           
                            $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month. "-" . $year : '';

                            $markEntry = new MarkEntry();
                            $markEntry->student_map_id = $_POST['stu_map_ids'][$i];
                            $markEntry->subject_map_id = $subject_map_id;
                            $markEntry->category_type_id =$externAl;
                            $markEntry->category_type_id_marks =$_POST['stu_marks'][$i];
                            $markEntry->year = $year;
                            $markEntry->month = $month;
                            $markEntry->term = $term;
                            $markEntry->mark_type = $mark_type;
                            $markEntry->created_at = $created_at;
                            $markEntry->created_by = $updateBy;
                            $markEntry->updated_at = $created_at;
                            $markEntry->updated_by = $updateBy;
                            $markEntry->save(false);


                            $markentrymaster = new MarkEntryMaster();
                            $markentrymaster->student_map_id = $_POST['stu_map_ids'][$i];
                            $markentrymaster->subject_map_id =$subject_map_id;
                            $markentrymaster->CIA =  $cia_marks;
                            $markentrymaster->ESE = $stu_result_data['ese_marks'];
                            $markentrymaster->total = $stu_result_data['total_marks'];
                            $markentrymaster->result = $stu_result_data['result'];
                            $markentrymaster->grade_point = $stu_result_data['grade_point'];
                            $markentrymaster->grade_name = $stu_result_data['grade_name'];
                            $markentrymaster->attempt = $stu_result_data['attempt'];
                            $markentrymaster->year = $year;
                            $markentrymaster->month = $month;
                            $markentrymaster->term = $term;
                            $markentrymaster->mark_type = $mark_type;
                            $markentrymaster->year_of_passing = $year_of_passing;
                            $markentrymaster->status_id = 0;
                            $markentrymaster->created_by = $updateBy;
                            $markentrymaster->created_at = $created_at;
                            $markentrymaster->updated_by = $updateBy;
                            $markentrymaster->updated_at = $created_at;
                            if($markentrymaster->save(false))
                            {
                                $connection = Yii::$app->db;
                                $update_status = $command = $connection->createCommand('UPDATE coe_practical_entry SET updated_by="'.$updateBy.'",updated_at="'.$created_at.'", approve_status="YES" WHERE subject_map_id="'.$subject_map_id.'" AND student_map_id="'.$_POST['stu_map_ids'][$i].'" and year="'.$year.'" and month="'.$month.'" ');
                                $command->execute();
                                $migrade_status++;
                            }
                            unset($markentrymaster);
                        }

                                

                                Yii::$app->ShowFlashMessages->setMsg('Success',"Data Migrated Successfully!!!!");
                        return $this->redirect(['practical-entry/verify-and-migrate']);


                    

                }

                else
                 {
                if(!empty($check_CIA_marks))
                {
                   

                    $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year])->all();



                    
                    $ab_stuMap_ids = [];
                    if(!empty($getAbsentList))
                    {
                        for ($abse=0; $abse <count($getAbsentList) ; $abse++) 
                        { 
                            $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$cia,'subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg']])->orderBy('coe_mark_entry_id desc')->one();

                           
                           $check_mark_entry = MarkEntry::find()->where(['category_type_id'=>$externAl,'subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg'],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orderBy('coe_mark_entry_id desc')->one();

                          //print_r($check_mark_entry );exit;

                           $absen_model_save = new MarkEntry();
                            $absen_model_save->student_map_id = $getAbsentList[$abse]['absent_student_reg'];
                            $absen_model_save->subject_map_id = $subject_map_id;
                            $absen_model_save->category_type_id =$externAl;
                            $absen_model_save->category_type_id_marks =0;
                            $absen_model_save->year = $year;
                            $absen_model_save->month = $month;
                            $absen_model_save->term = $term;
                            $absen_model_save->mark_type = $mark_type;
                            $absen_model_save->created_at = $created_at;
                            $absen_model_save->created_by = $updateBy;
                            $absen_model_save->updated_at = $created_at;
                            $absen_model_save->updated_by = $updateBy;

                            if(empty($check_mark_entry) && $absen_model_save->save(false))
                               // if(!empty($check_mark_entry))
                            {
                                $ab_stuMap_ids[$getAbsentList[$abse]['absent_student_reg']] = $getAbsentList[$abse]['absent_student_reg'];
                                unset($absen_model_save);
                                $ab_MarkEntryMaster = new MarkEntryMaster();
                                $ab_MarkEntryMaster->student_map_id = $getAbsentList[$abse]['absent_student_reg'];
                                $ab_MarkEntryMaster->subject_map_id =$subject_map_id;
                                $ab_MarkEntryMaster->CIA = $stuCiaMarks->category_type_id_marks;
                                $ab_MarkEntryMaster->ESE = 0;
                                $ab_MarkEntryMaster->total = $stuCiaMarks->category_type_id_marks;
                                $ab_MarkEntryMaster->result = 'Absent';
                                $ab_MarkEntryMaster->grade_point = 0;
                                $ab_MarkEntryMaster->grade_name = 'U';
                                $ab_MarkEntryMaster->attempt = 0;
                                $ab_MarkEntryMaster->year = $year;
                                $ab_MarkEntryMaster->month = $month;
                                $ab_MarkEntryMaster->term = $term;
                                $ab_MarkEntryMaster->mark_type = $mark_type;
                                $ab_MarkEntryMaster->year_of_passing = '';
                                $ab_MarkEntryMaster->status_id = 0;
                                $ab_MarkEntryMaster->created_by = $updateBy;
                                $ab_MarkEntryMaster->created_at = $created_at;
                                $ab_MarkEntryMaster->updated_by = $updateBy;
                                $ab_MarkEntryMaster->updated_at = $created_at;
                               // $ab_MarkEntryMaster->save(false);

                            if( $ab_MarkEntryMaster->save(false))
                            {
                                $connection = Yii::$app->db;
                                $update_status = $command = $connection->createCommand('UPDATE coe_practical_entry SET updated_by="'.$updateBy.'",updated_at="'.$created_at.'", approve_status="YES" WHERE subject_map_id="'.$subject_map_id.'" AND student_map_id="'.$getAbsentList[$abse]['absent_student_reg'].'" and year="'.$year.'" and month="'.$month.'" ');
                                $command->execute();
                                $migrade_status++;
                            }
                               unset($markentrymaster);
                               
                            }

                        }
                    } // Absent Entry result For Loop Ends Here 
                    for ($i=0; $i <count($_POST['stu_map_ids']) ; $i++) 
                    { 
                        $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$cia,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['stu_map_ids'][$i]])->orderBy('coe_mark_entry_id desc')->one();

                        $check_mark_entry = MarkEntry::find()->where(['category_type_id'=>$externAl,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['stu_map_ids'][$i],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orderBy('coe_mark_entry_id desc')->one();

                            $markEntry = new MarkEntry();
                            $markEntry->student_map_id = $_POST['stu_map_ids'][$i];
                            $markEntry->subject_map_id = $subject_map_id;
                            $markEntry->category_type_id =$externAl;
                            $markEntry->category_type_id_marks =$_POST['stu_marks'][$i];
                            $markEntry->year = $year;
                            $markEntry->month = $month;
                            $markEntry->term = $term;
                            $markEntry->mark_type = $mark_type;
                            $markEntry->created_at = $created_at;
                            $markEntry->created_by = $updateBy;
                            $markEntry->updated_at = $created_at;
                            $markEntry->updated_by = $updateBy;

                        if(empty($check_mark_entry) && !in_array($_POST['stu_map_ids'][$i], $ab_stuMap_ids) && $markEntry->save(false))
                        {
                            $stu_result_data = ConfigUtilities::StudentResult($_POST['stu_map_ids'][$i], $subject_map_id, $stuCiaMarks->category_type_id_marks, $_POST['stu_marks'][$i],$year,$month);

                            unset($markEntry);
                            $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month. "-" . $year : '';
                            $markentrymaster = new MarkEntryMaster();
                            $markentrymaster->student_map_id = $_POST['stu_map_ids'][$i];
                            $markentrymaster->subject_map_id =$subject_map_id;
                            $markentrymaster->CIA = $stuCiaMarks->category_type_id_marks;
                            $markentrymaster->ESE = $stu_result_data['ese_marks'];
                            $markentrymaster->total = $stu_result_data['total_marks'];
                            $markentrymaster->result = $stu_result_data['result'];
                            $markentrymaster->grade_point = $stu_result_data['grade_point'];
                            $markentrymaster->grade_name = $stu_result_data['grade_name'];
                            $markentrymaster->attempt = $stu_result_data['attempt'];
                            $markentrymaster->year = $year;
                            $markentrymaster->month = $month;
                            $markentrymaster->term = $term;
                            $markentrymaster->mark_type = $mark_type;
                            $markentrymaster->year_of_passing = $year_of_passing;
                            $markentrymaster->status_id = 0;
                            $markentrymaster->created_by = $updateBy;
                            $markentrymaster->created_at = $created_at;
                            $markentrymaster->updated_by = $updateBy;
                            $markentrymaster->updated_at = $created_at;
                            if($markentrymaster->save(false))
                            {
                                $connection = Yii::$app->db;
                                $update_status = $command = $connection->createCommand('UPDATE coe_practical_entry SET updated_by="'.$updateBy.'",updated_at="'.$created_at.'", approve_status="YES" WHERE subject_map_id="'.$subject_map_id.'" AND student_map_id="'.$_POST['stu_map_ids'][$i].'" and year="'.$year.'" and month="'.$month.'" ');
                                $command->execute();
                                $migrade_status++;
                            }
                            unset($markentrymaster);
                            
                        }
                    } // Student result For Loop Ends Here 
                    if($migrade_status>0)
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Success',"Data Migrated Successfully!!!!");
                        return $this->redirect(['practical-entry/verify-and-migrate']);
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error'," Something Wrong With ".$subJecs->subject_code." CODE");
                        return $this->redirect(['practical-entry/verify-and-migrate']);
                    }
                    
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"Kindly Enter the CIA Marks for ".$subJecs->subject_code." CODE");
                    return $this->redirect(['practical-entry/verify-and-migrate']);
                }  
            }
        }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Nothing Success!!");
                return $this->redirect(['practical-entry/verify-and-migrate']);
            }
        }
        else
        {
              Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to practical MarkEntry Migration");               
              return $this->render('verify-and-migrate', [
                    'model' => $model,
              ]);  
        }

        
    }

    /**
     * Creates a new PracticalEntry model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new PracticalEntry();
        $markEntry = new MarkEntry();
        $student = new Student();
        $MarkEntryMaster = new MarkEntryMaster();

        if ($model->load(Yii::$app->request->post())) 
        {
            if(!isset($_POST['examiner_name']) || !isset($_POST['chief_exam_name']))
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Kindly Enter the Examiner Name");
                return $this->redirect(['practical-entry/create']);
            }
            $examiner_name = $_POST['examiner_name'];
            $chief_exam_name = $_POST['chief_exam_name'];
            $mark_type = $model->mark_type;
            $reguLar = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Regular%'")->queryScalar();
            $get_cat_entry_type = Categorytype::find()->where(['description'=>'Practical Entry'])->orWhere(['category_type'=>'Practical Entry'])->one();
            $absent_entry_type = $get_cat_entry_type['coe_category_type_id'];

            if(isset($_POST['reg_number']))
            {
                $totalSuccess = '';
                $subject_map_id = $_POST['sub_val'];// as subject_id
                $year = $model->year;
                $term = $model->term;
                
                $totalSuccess = 0;
                 $month = $_POST['month'];

                
                $count_of_reg_num = count($_POST['reg_number']);
                $created_at = date("Y-m-d H:i:s");
                $updateBy = Yii::$app->user->getId(); 
                $subject_map_id_de =SubjectsMapping::findOne($subject_map_id); 
                $subjMax = Subjects::findOne($subject_map_id_de->subject_id);
                
                 if($mark_type==$reguLar)
                 {

                        for($i=0; $i < count($_POST['reg_number']) ; $i++) 
                        { 
                            if(!empty($_POST['reg_number'][$i]))
                            { 
                             //print_r($_POST['ese_marks'][$i]); 
                                $student_map_id = $_POST['reg_number'][$i];
                                $check_inserted = PracticalEntry::find()->where(['year'=>$year,'month'=>$month,'student_map_id'=>$_POST['reg_number'][$i],'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type])->one();

                                if(empty($check_inserted) && $_POST['ese_marks'][$i]=='-1')
                                {
                                    $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year,'absent_student_reg'=>$_POST['reg_number'][$i]])->all();
                                    $absentInsert = new AbsentEntry();
                                    $absentInsert->absent_student_reg = $student_map_id;
                                    $absentInsert->exam_type = $mark_type;
                                    $absentInsert->absent_term = $term;
                                    $absentInsert->exam_subject_id = $subject_map_id;
                                    $absentInsert->exam_absent_status = $absent_entry_type;
                                    $absentInsert->exam_month = $month;
                                    $absentInsert->exam_year = $year;
                                    $absentInsert->created_by = $updateBy;
                                    $absentInsert->updated_by = $updateBy;
                                    $absentInsert->created_at = $created_at;
                                    $absentInsert->updated_at = $created_at;
                                    if(empty($getAbsentList))
                                    {
                                       $absentInsert->save(false);
                                    }
                                }
                                if(empty($check_inserted))
                                {
                                    $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                    $model_save = new PracticalEntry();
                                    $model_save->student_map_id = $_POST['reg_number'][$i];
                                    $model_save->subject_map_id = $subject_map_id;
                                    $model_save->out_of_100 = $_POST['ese_marks'][$i];
                                    $converted_ese = $subjMax->ESE_max*$INSERT_ESE_MARKS/100;
                                    $model_save->ESE =$converted_ese;
                                    $model_save->year = $year;
                                    $model_save->month = $month;
                                    $model_save->term = $term;
                                    $model_save->mark_type = $mark_type;
                                    $model_save->chief_exam_name = $chief_exam_name;
                                    $model_save->examiner_name = $examiner_name;
                                    $model_save->created_at = $created_at;
                                    $model_save->created_by = $updateBy;
                                    $model_save->updated_at = $created_at;
                                    $model_save->updated_by = $updateBy;

                                    if($model_save->save(false))
                                    {
                                        $totalSuccess+=1;
                                        $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                        
                                    }
                                    else
                                    {

                                        $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                    }
                                    unset($model_save);
                                    $model_save = new PracticalEntry();
                                    Yii::$app->ShowFlashMessages->setMsg('Success','Practical Marks Inserted Successfully!!!');
                                }
                                else
                                {
                                    Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Insert the Marks');
                                }
                            }
                            

                            //Not Empty of the Register Number
                           
                        } // Regular Practical Entry Without CIA Completed
                 }

                 else
                 {
                     $transaction = Yii::$app->db->beginTransaction();
                     $subjMax = Subjects::findOne($subject_map_id_de->subject_id);
                        for($i=0; $i < count($_POST['reg_number']) ; $i++) 
                        { 
                            if(!empty($_POST['reg_number'][$i]) && !empty($_POST['ese_marks'][$i]))
                            {  
                            
                                $internLa = Categorytype::find()->where(['category_type'=>'Internal Final'])->one();
                                $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->one();

                                $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i]])->orderBy('coe_mark_entry_id desc')->one();
                                $student_map_id = $_POST['reg_number'][$i];
                                if(!empty($stuCiaMarks))
                                {

                                    $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id . '" AND student_map_id="' . $_POST['reg_number'][$i] . '" AND result not like "%pass%"')->queryScalar();

                                    $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                                    $ese_mark_entred = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                    if ($check_attempt >= $config_attempt) {
                                        $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, 0, $ese_mark_entred,$year,$month);
                                        $CIA = 0;
                                    } else {
                                        $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, $stuCiaMarks->category_type_id_marks, $ese_mark_entred,$year,$month);
                                        $CIA = $stuCiaMarks->category_type_id_marks;
                                    }
                                    
                                    $check_mark_entry = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$year,'month'=>$month])->all();

                                    if(empty($check_mark_entry) && $ese_mark_entred=='-1')
                                    {
                                        $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year,'absent_student_reg'=>$_POST['reg_number'][$i]])->all();
                                        $absentInsert = new AbsentEntry();
                                        $absentInsert->absent_student_reg = $student_map_id;
                                        $absentInsert->exam_type = $mark_type;
                                        $absentInsert->absent_term = $term;
                                        $absentInsert->exam_subject_id = $subject_map_id;
                                        $absentInsert->exam_absent_status = $absent_entry_type;
                                        $absentInsert->exam_month = $month;
                                        $absentInsert->exam_year = $year;
                                        $absentInsert->created_by = $updateBy;
                                        $absentInsert->updated_by = $updateBy;
                                        $absentInsert->created_at = $created_at;
                                        $absentInsert->updated_at = $created_at;
                                        if(empty($getAbsentList))
                                        {
                                           $absentInsert->save(false);
                                        }
                                    }

                                    $model_save = new MarkEntry();
                                    $model_save->student_map_id = $_POST['reg_number'][$i];
                                    $model_save->subject_map_id = $subject_map_id;
                                    $model_save->category_type_id =$externAl->coe_category_type_id;
                                    $model_save->category_type_id_marks =$ese_mark_entred;
                                    $model_save->year = $year;
                                    $model_save->month = $month;
                                    $model_save->term = $term;
                                    $model_save->mark_type = $mark_type;
                                    $model_save->created_at = $created_at;
                                    $model_save->created_by = $updateBy;
                                    $model_save->updated_at = $created_at;
                                    $model_save->updated_by = $updateBy;

                                    $model_save_prac = new PracticalEntry();
                                    $model_save_prac->student_map_id = $_POST['reg_number'][$i];
                                    $model_save_prac->subject_map_id = $subject_map_id;
                                    $model_save_prac->out_of_100 = $ese_mark_entred;
                                    $converted_ese = $subjMax->ESE_max*$ese_mark_entred/100;
                                    $model_save_prac->ESE =$converted_ese;
                                    $model_save_prac->year = $year;
                                    $model_save_prac->month = $month;
                                    $model_save_prac->term = $term;
                                    $model_save_prac->approve_status = 'YES';
                                    $model_save_prac->mark_type = $mark_type;
                                    $model_save_prac->chief_exam_name = $chief_exam_name;
                                    $model_save_prac->examiner_name = $examiner_name;
                                    $model_save_prac->created_at = $created_at;
                                    $model_save_prac->created_by = $updateBy;
                                    $model_save_prac->updated_at = $created_at;
                                    $model_save_prac->updated_by = $updateBy;

                                    if(empty($check_mark_entry) && $model_save_prac->save(false) && $model_save->save(false))
                                    {
                                        $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month. "-" . $year : '';

                                        $markentrymaster = new MarkEntryMaster();
                                        $markentrymaster->student_map_id = $_POST['reg_number'][$i];
                                        $markentrymaster->subject_map_id =$subject_map_id;
                                        $markentrymaster->CIA = $CIA;
                                        $markentrymaster->ESE = $stu_result_data['ese_marks'];
                                        $markentrymaster->total = $stu_result_data['total_marks'];
                                        $markentrymaster->result = $stu_result_data['result'];
                                        $markentrymaster->grade_point = $stu_result_data['grade_point'];
                                        $markentrymaster->grade_name = $stu_result_data['grade_name'];
                                        $markentrymaster->attempt = $stu_result_data['attempt'];
                                        $markentrymaster->year = $year;
                                        $markentrymaster->month = $month;
                                        $markentrymaster->term = $term;
                                        $markentrymaster->mark_type = $mark_type;
                                        $markentrymaster->year_of_passing = $year_of_passing;
                                        $markentrymaster->status_id = 0;
                                        $markentrymaster->created_by = $updateBy;
                                        $markentrymaster->created_at = $created_at;
                                        $markentrymaster->updated_by = $updateBy;
                                        $markentrymaster->updated_at = $created_at;
                                        
                                        if($markentrymaster->save())
                                        {
                                            try
                                            {
                                                $totalSuccess+=1;
                                                $transaction->commit();
                                            }
                                            catch(\Exception $e)
                                            {
                                               if($e->getCode()=='23000')
                                               {
                                                   $message = "Duplicate Entry";
                                               }
                                               else
                                               {
                                                  $transaction->rollback(); 
                                                   $message = "Something Wrong";
                                               }
                                               $dispResults[] = ['type' => 'E',  'message' => $message];
                                            }
                                            
                                            $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                        }
                                        else
                                        {
                                            $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                        }
                                           
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Marks Already Available'];
                                    }
                                    
                                    Yii::$app->ShowFlashMessages->setMsg('Success','Practical Marks Inserted Successfully!!!');
                                }
                                else
                                {
                                    Yii::$app->ShowFlashMessages->setMsg('Error','NO CIA MARKS FOUND');
                                }

                            }// For loop Ends Here 
                        }
                 }

                if($totalSuccess>0)
                {

                    $getSubsInfo = new Query();
                    $reg_numbers_print = $_POST['reg_number'];
                    if($mark_type==$reguLar)
                    {
                        $getSubsInfo->select(['A.register_number','F.subject_name','F.subject_code','out_of_100','H.degree_code','G.programme_name']);
                        $getSubsInfo->from('coe_subjects_mapping as E')                
                        ->join('JOIN', 'coe_subjects as F', 'F.coe_subjects_id=E.subject_id')
                        ->join('JOIN', 'coe_practical_entry as B', 'B.subject_map_id=E.coe_subjects_mapping_id')
                        ->join('JOIN', 'coe_student_mapping as C', 'C.coe_student_mapping_id=B.student_map_id')
                        ->join('JOIN', 'coe_student as A', 'A.coe_student_id=C.student_rel_id')
                          ->JOIN('JOIN','coe_bat_deg_reg as X','X.coe_bat_deg_reg_id=E.batch_mapping_id')
                        ->JOIN('JOIN','coe_programme as G','G.coe_programme_id=X.coe_programme_id')
                           ->JOIN('JOIN','coe_degree as H','H.coe_degree_id=X.coe_degree_id')
                        ->Where(['B.subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])
                        ->andWhere(['IN','C.coe_student_mapping_id',$reg_numbers_print])
                        ->groupBy('register_number')
                        ->orderBy('register_number');
                        if(!isset($_POST['to_reg']) && !isset($_POST['from_reg']))
                        {
                            $getSubsInfo->limit(30);    
                        }
                        
                    }
                    else
                    {
                       /* $getSubsInfo->select(['A.register_number','D.subject_name','D.subject_code','E.category_type_id_marks as out_of_100','H.degree_code','G.programme_name']);
                        $getSubsInfo->from('coe_student as A')
                        ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id')
                        ->join('JOIN', 'coe_subjects_mapping as C', 'C.batch_mapping_id=B.course_batch_mapping_id')
                        ->join('JOIN', 'coe_subjects as D', 'D.coe_subjects_id=C.subject_id')
                        ->join('JOIN', 'coe_mark_entry as E', 'E.subject_map_id=C.coe_subjects_mapping_id and E.student_map_id=B.coe_student_mapping_id')
                           ->JOIN('JOIN','coe_bat_deg_reg as X','X.coe_bat_deg_reg_id==B.course_batch_mapping_id')
                        ->JOIN('JOIN','coe_programme as G','G.coe_programme_id=X.coe_programme_id')
                           ->JOIN('JOIN','coe_degree as H','H.coe_degree_id=X.coe_degree_id')
                        ->join('JOIN', 'coe_mark_entry_master as F', 'F.subject_map_id=C.coe_subjects_mapping_id and F.student_map_id=B.coe_student_mapping_id')                        
                        ->Where(['F.subject_map_id'=>$subject_map_id,'F.year'=>$year,'F.month'=>$month,'F.term'=>$term,'F.mark_type'=>$mark_type,'E.subject_map_id'=>$subject_map_id,'E.year'=>$year,'E.month'=>$month,'E.term'=>$term,'E.mark_type'=>$mark_type])
                        ->andWhere(['IN','B.coe_student_mapping_id',$reg_numbers_print])
                        ->groupBy('register_number')
                        ->orderBy('register_number');
                        if(!isset($_POST['to_reg']) && !isset($_POST['from_reg']))
                        {
                            $getSubsInfo->limit();    
                        }*/


                       $getSubsInfo->select(['A.register_number','F.subject_name','F.subject_code','out_of_100','H.degree_code','G.programme_name']);
                        $getSubsInfo->from('coe_subjects_mapping as E')                
                        ->join('JOIN', 'coe_subjects as F', 'F.coe_subjects_id=E.subject_id')
                        ->join('JOIN', 'coe_practical_entry as B', 'B.subject_map_id=E.coe_subjects_mapping_id')
                        ->join('JOIN', 'coe_student_mapping as C', 'C.coe_student_mapping_id=B.student_map_id')
                        ->join('JOIN', 'coe_student as A', 'A.coe_student_id=C.student_rel_id')
                          ->JOIN('JOIN','coe_bat_deg_reg as X','X.coe_bat_deg_reg_id=E.batch_mapping_id')
                        ->JOIN('JOIN','coe_programme as G','G.coe_programme_id=X.coe_programme_id')
                           ->JOIN('JOIN','coe_degree as H','H.coe_degree_id=X.coe_degree_id')
                        ->Where(['B.subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])
                        ->andWhere(['IN','C.coe_student_mapping_id',$reg_numbers_print])
                        ->groupBy('register_number')
                        ->orderBy('register_number');
                        if(!isset($_POST['to_reg']) && !isset($_POST['from_reg']))
                        {
                            $getSubsInfo->limit(30);    
                        }
                        
                    }
                    
                    $getSubsInfoDet = $getSubsInfo->createCommand()->queryAll();
                    
                    if(!empty($getSubsInfoDet))
                    {
                        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                        $table='';
                        $get_month_name=Categorytype::findOne($month);
                        $header = $footer1 =$footer = $final_html = $body = $head='';
                          $header = '<table  border=1 width="100%" >
                <thead    class="thead-inverse">
                           <tr>
                        <td colspan=4>
                        <table  width="100%" align="center" border="1" >                    
                        <tr>
                                    
                                      <td> 
                                        <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                                      </td>

                                      <td colspan=2 align="center"> 
                                          <center><b>'.$org_name.'</b></center>
                                          <center> '.$org_address.'</center>
                                          
                                          <center class="tag_line"><b>'.$org_tagline.'</b></center> 
                                     </td>
                                      <td align="center">  
                                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                                      </td>
                                    </tr>
                                    
                                    
                                    <tr>
                                   </table></td></tr>
                        <tr>
                        <td align="center" colspan=4><h5> END SEMESTER  EXAMINATIONS -  <b>'.strtoupper($get_month_name['description'])."-".$year.'</b></h5>
                        </td></tr>
                         <tr>
                        <td align="center" colspan=4><h5> SCORE CARD</h5>
                        </td></tr>
                       <tr>
                         <td colspan=2 align="left"> 
                            PROGRAMME :<b>'.$getSubsInfoDet[0]['degree_code'].' '.$getSubsInfoDet[0]['programme_name'].'</b></h5>
                            
                        </td>

                         <td colspan=2 align="right"> 
                            COURSE CODE :<b>'.strtoupper( $subjMax->subject_code).'</b></h5>
                            
                        </td>
                      
                       <tr>
                         <td colspan=4 align="left"> 
                            COURSE NAME : <b>'.strtoupper($subjMax->subject_name).'</b></h5>
                            
                        </td>
                        </tr>

                         <tr>
                    

                         <td colspan=2 align="left"> 
                         DATE OF EXAMINATIONS : '.date("d/m/Y").'
                            
                        </td>
                        <td colspan=2 align="right"> 
                            
                              MAX. MARKS :<b>'.strtoupper($subjMax->ESE_max).'</b></h5>
                            
                        </td>
                        </tr>
                        <tr>                                      
                            <td align="left" colspan=2>
                                INT EXAMINER:'.$examiner_name.'
                            </td> 
                            <td align="left" colspan=2>
                                EXT EXAMINER: '.$chief_exam_name.'
                            </td> 
                        </tr>
                                    <tr class="table-danger">
                                        <th>SNO</th>  
                                        <th>REGISTER NUMBER</th>
                                        <th>'.strtoupper("Marks").'</th>
                                        <th>'.strtoupper("Marks In Words").'</th>
                                    </tr>
                                    </thead> 
                        <tbody>                  
                                    
                                    ';
                                     $footer.='</tbody></table>';
                         $footer1 .='<table border=1 width="100%" > <tbody><tr class ="alternative_border">
                    <td align="left" colspan=2><br/><br/>
                         NAME:  '.$examiner_name.'<br/><br/>
                    </td>
                    <td align="center" colspan=2><br/><br/>
                        NAME: '.$chief_exam_name.'<br /><br/>
                    </td>
                    
                </tr>
                <tr>
                    <td align="left" colspan=2><br></br><br></br>
                       Signature With Date <br /><br /><br />
                    </td>
                    <td align="center" colspan=2><br></br><br></br>
                        Signature With Date <br /><br /><br />
                    </td> 
                </tr></tbody></table>';

                          $increment = 1;
                          $Num_30_nums = 0;
                        foreach ($getSubsInfoDet as $value)
                        {
                            if(isset($value["out_of_100"]))
                            {
                                $split_number = str_split($value["out_of_100"]);
                            }
                            
                            if($value["out_of_100"]=='-1')
                            {

                                $print_text="ABSENT";
                            }
                            else
                         {
                            $print_text = $this->valueReplaceNumber($split_number);
                        }

                        if($value['out_of_100']=='-1')
                        {
                           $marks="AB";

                        }
                        else
                        {
 
                          $marks=$value["out_of_100"];
                        }
                           $body .='<tr><td>'.$increment.'</td><td>'.$value["register_number"].'</td><td>'.$marks.'</td><td>'.$print_text.'</td></tr>';
                            $increment++;
                             $head='<table width="100%" > <tbody><tr class ="alternative_border">
                                <td  class="vani"  align="left" >
                                     SCORE CARD NO :
                                </td>
                                <td align="right"><b>
                                    CE 19(01)</b><br />
                                </td>
                                
                                </tr>
                                 </tbody></table>';
                            if($increment%26==0)
                            {
                                 
                                $Num_30_nums =1;
                               $html=   $head.$header.$body.$footer;
                                $html.='<pagebreak>';
                                $final_html .=$html;
                               $html = $body = '';
                       // $page++;
                            }
                        }
                        if($Num_30_nums<=30)
                          {
                            $html=   $head.$header.$body.$footer;        
                          }                  
                         $final_html .=$html.$footer1;               
                          $content = $final_html;


                     $pdf = new Pdf([                   
                                'mode' => Pdf::MODE_CORE,                 
                                'filename' => 'PRACTICAL MARK.pdf',                
                                'format' => Pdf::FORMAT_A4,                 
                                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                                'destination' => Pdf::DEST_BROWSER,                 
                                'content' => $content,                     
                              'cssInline' => ' @media all{
                    table{border-collapse:collapse; font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; padding: 5px 5px !important; } table.no-border
                    {
                      border: none;
                    } 
                    .print_red_color{font-weight: bold: color: #FOO;}
                    .print_green_color{color: green;}
                    .vani{border:1px solid #FOO;width:50%}
                   
                    tbody{margin-top: 15px; margin-bottom: 30px; }
                    table td{padding:3px  !important;  } 
                    table tr{ line-height: 30px !important; height: 20px !important;}
                }',        
                                           
                                'options' => ['title' => strtoupper('PRACTICAL').' MARK VERIFICATION'],
                                'methods' => [ 

                                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                                    
                                ],
                                
                            ]);
                               

                            
                            $pdf->marginLeft="8";
                            $pdf->marginRight="8";
                            $pdf->marginBottom="8";
                            $pdf->marginFooter="8";
                            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                            $headers = Yii::$app->response->headers;
                            $headers->add('Content-Type', 'application/pdf');
                            return $pdf->render(); 
                    }
                    else
                    {
                         Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                    }
                }
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','Something Gone Wrong');
            }
            return $this->redirect(['practical-entry/create']);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Practical Entry');
            return $this->render('create', [
                'model' => $model,
                'student'=>$student,
                'markEntry'=>$markEntry,
                'MarkEntryMaster'=>$MarkEntryMaster,
            ]);
        }
    }

    /**
     * Updates an existing PracticalEntry model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionRePrintSheet()
    {
        $model = new PracticalEntry();
        $markEntry = new MarkEntry();
        $MarkEntryMaster = new MarkEntryMaster();

        return $this->render('re-print-sheet', [
            'model' => $model,
            'markEntry'=>$markEntry,
            'MarkEntryMaster'=>$MarkEntryMaster,
        ]);
    }

    /**
     * Updates an existing PracticalEntry model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_practical_entry_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing PracticalEntry model.
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
     * Finds the PracticalEntry model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PracticalEntry the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PracticalEntry::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    protected function valueReplaceNumber($array_data)
    {
        $array= array('0'=>'ZERO','1'=>'ONE','2'=>'TWO','3'=>'THREE','4'=>'FOUR','5'=>'FIVE','6'=>'SIX','7'=>'SEVEN','8'=>'EIGHT','9'=>'NINE','10'=>'TEN','-'=>'ABSENT');  
        $return_string='';
        for($i=0;$i<count($array_data);$i++)
        {
            $return_string .=$array[$array_data[$i]]." ";
        }
        return !empty($return_string)?$return_string:'No Data Found';
           
    }
    public function actionReprintSheetPracticalPdf()
    {
        
        if(isset($_SESSION['re_print_practical_entry']))  
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));             
            $content = $_SESSION['re_print_practical_entry'];
            $pdf = new Pdf([                   
                'mode' => Pdf::MODE_CORE,                 
                'filename' => 'PRACTICAL MARK REPRINT.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,                     
                'cssInline' => ' @media all{
                    table{border-collapse:collapse; font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; padding: 5px 5px !important; } table.no-border
                    {
                      border: none;
                    } 
                    .print_red_color{font-weight: bold: color: #FOO;}
                    .print_green_color{color: green;}
                    .vani{border:1px solid #FOO;width:50%}
                   
                    tbody{margin-top: 15px; margin-bottom: 30px; }
                    table td{padding:3px  !important;  } 
                    table tr{ line-height: 30px !important; height: 20px !important;}
                }',                       
                'options' => ['title' => strtoupper('PRACTICAL').' MARK VERIFICATION RE-PRINT'],
                'methods' => [ 
                     
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    
                ],
                
            ]);
            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
            $headers = Yii::$app->response->headers;
            $headers->add('Content-Type', 'application/pdf');
            return $pdf->render();
        }             
    }
    public function actionExcelReprintPracticalSheet()
    {

        $content = $_SESSION['re_print_practical_entry'];          
        $fileName = ' PRACTICAL MARK ENTRY RE-PRINT' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    /**
     * Creates a new PracticalEntry model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionEntryWithExamDate()
    {
        $model = new PracticalEntry();
        $markEntry = new MarkEntry();
        $hallAll = new HallAllocate();
        $student = new ExamTimetable();
        $MarkEntryMaster = new MarkEntryMaster();

        if ($model->load(Yii::$app->request->post())) 
        {
            if(!isset($_POST['examiner_name']) || !isset($_POST['chief_exam_name']))
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Kindly Enter the Examiner Name");
                return $this->redirect(['practical-entry/entry-with-exam-date']);
            }
            $examiner_name = $_POST['examiner_name'];
            $chief_exam_name = $_POST['chief_exam_name'];
            $mark_type = $model->mark_type;
            $reguLar = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Regular%'")->queryScalar();
            $get_cat_entry_type = Categorytype::find()->where(['description'=>'Practical Entry'])->orWhere(['category_type'=>'Practical Entry'])->one();
            $absent_entry_type = $get_cat_entry_type['coe_category_type_id'];
            

            if(isset($_POST['reg_number']))
            {
                $totalSuccess = '';
                $subject_map_id = $_POST['sub_val'];// as subject_id
                $year = $model->year;
                $term = $model->term;
                
                $totalSuccess = 0;
                $month = $_POST['month'];
                
                $count_of_reg_num = count($_POST['reg_number']);
                $created_at = date("Y-m-d H:i:s");
                $updateBy = Yii::$app->user->getId(); 
                $subject_map_id_de =SubjectsMapping::findOne($subject_map_id); 
                $subjMax = Subjects::findOne($subject_map_id_de->subject_id);
                
                 if($mark_type==$reguLar)
                 {
                        for($i=0; $i < count($_POST['reg_number']) ; $i++) 
                        { 
                            if(!empty($_POST['reg_number'][$i]) && !empty($_POST['ese_marks'][$i]))
                            {  
                                $student_map_id = $_POST['reg_number'][$i];
                                $check_inserted = PracticalEntry::find()->where(['year'=>$year,'month'=>$month,'student_map_id'=>$_POST['reg_number'][$i],'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type])->one();

                                if(empty($check_inserted) && $_POST['ese_marks'][$i]=='-1')
                                {
                                    $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year,'absent_student_reg'=>$_POST['reg_number'][$i]])->all();
                                    $absentInsert = new AbsentEntry();
                                    $absentInsert->absent_student_reg = $student_map_id;
                                    $absentInsert->exam_type = $mark_type;
                                    $absentInsert->absent_term = $term;
                                    $absentInsert->exam_subject_id = $subject_map_id;
                                    $absentInsert->exam_absent_status = $absent_entry_type;
                                    $absentInsert->exam_month = $month;
                                    $absentInsert->exam_year = $year;
                                    $absentInsert->created_by = $updateBy;
                                    $absentInsert->updated_by = $updateBy;
                                    $absentInsert->created_at = $created_at;
                                    $absentInsert->updated_at = $created_at;
                                    if(empty($getAbsentList))
                                    {
                                       $absentInsert->save(false);
                                    }
                                }
                                if(empty($check_inserted))
                                {
                                    $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                    $model_save = new PracticalEntry();
                                    $model_save->student_map_id = $_POST['reg_number'][$i];
                                    $model_save->subject_map_id = $subject_map_id;
                                    $model_save->out_of_100 = $INSERT_ESE_MARKS;
                                    $converted_ese = $subjMax->ESE_max*$INSERT_ESE_MARKS/100;
                                    $model_save->ESE =$converted_ese;
                                    $model_save->year = $year;
                                    $model_save->month = $month;
                                    $model_save->term = $term;
                                    $model_save->mark_type = $mark_type;
                                    $model_save->chief_exam_name = $chief_exam_name;
                                    $model_save->examiner_name = $examiner_name;
                                    $model_save->created_at = $created_at;
                                    $model_save->created_by = $updateBy;
                                    $model_save->updated_at = $created_at;
                                    $model_save->updated_by = $updateBy;

                                    if($model_save->save(false))
                                    {
                                        $totalSuccess+=1;
                                        $dispResults[] = ['type' => 'S',  'message' => 'Success'];    
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                    }
                                    unset($model_save);
                                    $model_save = new PracticalEntry();
                                    Yii::$app->ShowFlashMessages->setMsg('Success','Practical Marks Inserted Successfully!!!');
                                }
                                else
                                {
                                    Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Insert the Marks');
                                }
                            }//Not Empty of the Register Number
                           
                        } // Regular Practical Entry Without CIA Completed
                 }
                 else
                 {
                     $transaction = Yii::$app->db->beginTransaction();
                        for($i=0; $i < count($_POST['reg_number']) ; $i++) 
                        { 
                            if(!empty($_POST['reg_number'][$i]) && !empty($_POST['ese_marks'][$i]))
                            {  
                            
                                $internLa = Categorytype::find()->where(['category_type'=>'Internal Final'])->one();
                                $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->one();

                                $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i]])->orderBy('coe_mark_entry_id desc')->one();
                                $student_map_id = $_POST['reg_number'][$i];
                                if(!empty($stuCiaMarks))
                                {

                                    $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id . '" AND student_map_id="' . $_POST['reg_number'][$i] . '" AND result not like "%pass%"')->queryScalar();

                                    $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                                    $ese_mark_entred = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                    if ($check_attempt >= $config_attempt) {
                                        $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, 0, $ese_mark_entred,$year,$month);
                                        $CIA = 0;
                                    } else {
                                        $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, $stuCiaMarks->category_type_id_marks, $ese_mark_entred,$year,$month);
                                        $CIA = $stuCiaMarks->category_type_id_marks;
                                    }
                                    
                                    $check_mark_entry = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$year,'month'=>$month])->all();

                                   // if(empty($check_mark_entry) && $ese_mark_entred=='-1')
                                    if(empty($check_inserted) && $_POST['ese_marks'][$i]=='-1')

                                    {


                                        $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year,'absent_student_reg'=>$_POST['reg_number'][$i]])->all();
                                        $absentInsert = new AbsentEntry();
                                        $absentInsert->absent_student_reg = $student_map_id;
                                        $absentInsert->exam_type = $mark_type;
                                        $absentInsert->absent_term = $term;
                                        $absentInsert->exam_subject_id = $subject_map_id;
                                        $absentInsert->exam_absent_status = $absent_entry_type;
                                        $absentInsert->exam_month = $month;
                                        $absentInsert->exam_year = $year;
                                        $absentInsert->created_by = $updateBy;
                                        $absentInsert->updated_by = $updateBy;
                                        $absentInsert->created_at = $created_at;
                                        $absentInsert->updated_at = $created_at;
                                        if(empty($getAbsentList))
                                        {
                                           $absentInsert->save(false);
                                        }
                                    }

                                    $model_save = new MarkEntry();
                                    $model_save->student_map_id = $_POST['reg_number'][$i];
                                    $model_save->subject_map_id = $subject_map_id;
                                    $model_save->category_type_id =$externAl->coe_category_type_id;
                                    //$model_save->category_type_id_marks =$ese_mark_entred;
                                    $model_save->category_type_id_marks = $_POST['ese_marks'][$i];
                                    $model_save->year = $year;
                                    $model_save->month = $month;
                                    $model_save->term = $term;
                                    $model_save->mark_type = $mark_type;
                                    $model_save->created_at = $created_at;
                                    $model_save->created_by = $updateBy;
                                    $model_save->updated_at = $created_at;
                                    $model_save->updated_by = $updateBy;

                                    if(empty($check_mark_entry) && $model_save->save(false))
                                    {
                                        $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month. "-" . $year : '';


                                         $res_update = ($_POST['ese_marks'][$i]=='-1' || $_POST['ese_marks'][$i]<0 )?'Absent':$stu_result_data['result'];
                                         $grade_name = ($_POST['ese_marks'][$i]=='-1'  || $_POST['ese_marks'][$i]<0 )?'U':$stu_result_data['grade_name'];

                                        $markentrymaster = new MarkEntryMaster();
                                        $markentrymaster->student_map_id = $_POST['reg_number'][$i];
                                        $markentrymaster->subject_map_id =$subject_map_id;
                                        $markentrymaster->CIA = $CIA;
                                        $markentrymaster->ESE = $stu_result_data['ese_marks'];
                                        $markentrymaster->total = $stu_result_data['total_marks'];
                                        $markentrymaster->result = $res_update;
                                        $markentrymaster->grade_name = $grade_name;
                                        $markentrymaster->result = $stu_result_data['result'];
                                        $markentrymaster->grade_point = $stu_result_data['grade_point'];
                                        $markentrymaster->grade_name = $stu_result_data['grade_name'];
                                        $markentrymaster->attempt = $stu_result_data['attempt'];
                                        $markentrymaster->year = $year;
                                        $markentrymaster->month = $month;
                                        $markentrymaster->term = $term;
                                        $markentrymaster->mark_type = $mark_type;
                                        $markentrymaster->year_of_passing = $year_of_passing;
                                        $markentrymaster->status_id = 0;
                                        $markentrymaster->created_by = $updateBy;
                                        $markentrymaster->created_at = $created_at;
                                        $markentrymaster->updated_by = $updateBy;
                                        $markentrymaster->updated_at = $created_at;
                                        
                                        if($markentrymaster->save())
                                        {
                                            try
                                            {
                                                
                                                $transaction->commit();
                                            }
                                            catch(\Exception $e)
                                            {
                                               if($e->getCode()=='23000')
                                               {
                                                   $message = "Duplicate Entry";
                                               }
                                               else
                                               {
                                                  $transaction->rollback(); 
                                                   $message = "Something Wrong";
                                               }
                                               $dispResults[] = ['type' => 'E',  'message' => $message];
                                            }
                                            
                                            $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                        }
                                        else
                                        {
                                            $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                        }
                                           
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Marks Already Available'];
                                    }
                                    
                                    Yii::$app->ShowFlashMessages->setMsg('Success','Practical Marks Inserted Successfully!!!');
                                }
                                else
                                {
                                    Yii::$app->ShowFlashMessages->setMsg('Error','NO CIA MARKS FOUND');
                                }

                            }// For loop Ends Here 
                        }
                 }

                if($totalSuccess>0)
                {

                    $getSubsInfo = new Query();
                    $reg_numbers_print = $_POST['reg_number'];
                    if($mark_type==$reguLar)
                    {
                        $getSubsInfo->select(['A.register_number','F.subject_name','F.subject_code','out_of_100']);
                        $getSubsInfo->from('coe_subjects_mapping as E')                
                        ->join('JOIN', 'coe_subjects as F', 'F.coe_subjects_id=E.subject_id')
                        ->join('JOIN', 'coe_practical_entry as B', 'B.subject_map_id=E.coe_subjects_mapping_id')
                        ->join('JOIN', 'coe_student_mapping as C', 'C.coe_student_mapping_id=B.student_map_id')
                        ->join('JOIN', 'coe_student as A', 'A.coe_student_id=C.student_rel_id')
                        ->Where(['B.subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])
                        ->andWhere(['IN','C.coe_student_mapping_id',$reg_numbers_print])
                        ->groupBy('register_number')
                        ->orderBy('register_number')
                        ->limit(30);
                    }
                    else
                    {
                        $getSubsInfo->select(['A.register_number','D.subject_name','D.subject_code','E.category_type_id_marks as out_of_100']);
                        $getSubsInfo->from('coe_student as A')
                        ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id')
                        ->join('JOIN', 'coe_subjects_mapping as C', 'C.batch_mapping_id=B.course_batch_mapping_id')
                        ->join('JOIN', 'coe_subjects as D', 'D.coe_subjects_id=C.subject_id')
                        ->join('JOIN', 'coe_mark_entry as E', 'E.subject_map_id=C.coe_subjects_mapping_id and E.student_map_id=B.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mark_entry_master as F', 'F.subject_map_id=C.coe_subjects_mapping_id and F.student_map_id=B.coe_student_mapping_id')                        
                        ->Where(['F.subject_map_id'=>$subject_map_id,'F.year'=>$year,'F.month'=>$month,'F.term'=>$term,'F.mark_type'=>$mark_type,'E.subject_map_id'=>$subject_map_id,'E.year'=>$year,'E.month'=>$month,'E.term'=>$term,'E.mark_type'=>$mark_type])
                        ->andWhere(['IN','C.coe_student_mapping_id',$reg_numbers_print])
                        ->groupBy('register_number')
                        ->orderBy('register_number')
                        ->limit(30);
                    }
                    
                    $getSubsInfoDet = $getSubsInfo->createCommand()->queryAll();
                    
                    if(!empty($getSubsInfoDet))
                    {
                        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                        $table='';
                        $get_month_name=Categorytype::findOne($month);
                        $header = $footer = $final_html = $body = '';
                          $header = '<table width="100%" class="table table-bordered table-responsive bulk_edit_table table-hover"  >
                           
                            <tr>
                                    
                                      <td> 
                                        <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                                      </td>

                                      <td colspan=2 align="center"> 
                                          <center><b>'.$org_name.'</b></center>
                                          <center> '.$org_address.'</center>
                                          
                                          <center class="tag_line"><b>'.$org_tagline.'</b></center> 
                                     </td>
                                      <td align="center">  
                                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                                      </td>
                                    </tr>
                                    
                                    
                                    <tr>
                                    <td align="center" colspan=4><h5>PRACTICAL MARK ENTRY FOR EXAMINATIONS '.$year.' - '.strtoupper($get_month_name['description']).'</h5>
                                    </td></tr>
                                    <tr>
                                    <td align="center" colspan=4><h5>MARKS VERIFICATION AND APPROVAL FROM EXAMINER</h5></td></tr>
                                    <tr>                                        
                                        <td align="right" colspan=4>
                                            DATE OF VALUATION : '.date("d/m/Y").'
                                        </td> 
                                    </tr>
                                    <tr>
                                        <td height="15px" align="left" colspan=4> <b>
                                            '.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE").' : ('.$subjMax->subject_code.') '.$subjMax->subject_name.'</b>
                                        </td>
                                    </tr>
                                    <tr class="table-danger">
                                        <th>SNO</th>  
                                        <th>REGISTER NUMBER</th>
                                        <th>'.strtoupper("Marks Out of 100").'</th>
                                        <th>'.strtoupper("Marks In Words").'</th>
                                    </tr>               
                                    
                                    ';
                          $footer .='<tr class ="alternative_border">
                                <td align="left" colspan=2>
                                    NAME OF THE INTERNAL EXAMINER <br /><br />
                                    '.$examiner_name.' <br />
                                </td>
                                <td align="right" colspan=2>
                                    NAME OF THE EXTERNAL EXAMINER <br /><br />
                                    '.$chief_exam_name.' <br />
                                </td>
                                
                            </tr>
                            <tr>
                                <td align="left" colspan=2>
                                   Signature With Date <br /><br /><br />
                                </td>
                                <td align="right" colspan=2>
                                    Signature With Date <br /><br /><br />
                                </td> 
                            </tr></table>';

                          $increment = 1;
                          $Num_30_nums = 0;
                        foreach ($getSubsInfoDet as $value)
                        {
                            if(isset($value["out_of_100"]))
                            {
                                $split_number = str_split($value["out_of_100"]);
                            }
                            
                            $print_text = $this->valueReplaceNumber($split_number);
                           $body .='<tr><td>'.$increment.'</td><td>'.$value["register_number"].'</td><td>'.$value["out_of_100"].'</td><td>'.$print_text.'</td></tr>';
                            $increment++;
                            if($increment%31==0)
                            {
                                $Num_30_nums =1;
                                $html = $header.$body.$footer;
                                $final_html .=$html;
                                $html = $body = '';
                            }
                        }
                        if($Num_30_nums==0)
                          {
                            $html = $header.$body.$footer;     
                          }                  
                          $final_html .=$html;               
                          $content = $final_html;


                        $pdf = new Pdf([                   
                                'mode' => Pdf::MODE_CORE,                 
                                'filename' => 'PRACTICAL MARK.pdf',                
                                'format' => Pdf::FORMAT_A4,                 
                                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                                'destination' => Pdf::DEST_BROWSER,                 
                                'content' => $content,                     
                                'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: left;  font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; } 
                        
                        table td{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                        }
                        table th{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                        }
                    }   
                ',            
                                           
                                'options' => ['title' => strtoupper('PRACTICAL').' MARK VERIFICATION'],
                                'methods' => [ 
                                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                                    'SetFooter'=>[strtoupper('PRACTICAL').' MARK VERIFICATION '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                                ],
                                
                            ]);
                            
                            $pdf->marginLeft="8";
                            $pdf->marginRight="8";
                            $pdf->marginBottom="8";
                            $pdf->marginFooter="8";
                            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                            $headers = Yii::$app->response->headers;
                            $headers->add('Content-Type', 'application/pdf');
                            return $pdf->render(); 
                    }
                    else
                    {
                         Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                    }
                }
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','Something Gone Wrong');
            }
            return $this->redirect(['practical-entry/entry-with-exam-date']);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Practical Entry');
            return $this->render('entry-with-exam-date', [
                'model' => $model,
                'student'=>$student,
                'hallAll'=>$hallAll,
                'markEntry'=>$markEntry,
                'MarkEntryMaster'=>$MarkEntryMaster,
            ]);
        }
    }


      public function actionPracticalesemarklist()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $model = new MarkEntry();
        if (Yii::$app->request->post()) {
            $query = new  Query();
            $query->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,A.register_number,F.ESE,F.year,F.out_of_100,J.category_type as month,K.category_type as exam_type,E.programme_name,D.degree_name,B.coe_student_mapping_id,G.coe_subjects_mapping_id,F.month as exam_month')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_practical_entry as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_category_type as J', 'J.coe_category_type_id=F.month')
                ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.mark_type')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term'], 'A.student_status' => 'Active'])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->orderBy('A.register_number,H.subject_code');
            $ese_list = $query->createCommand()->queryAll();
            //print_r($ese_list);exit;
            $subject_get_data = new  Query();
            $subject_get_data->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_practical_entry as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $subject_get_data->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->orderBy('H.subject_code');
            $subjectsInfo = $subject_get_data->createCommand()->queryAll();
            //print_r($subjectsInfo);exit;
            $countQuery = new  Query();
            $countQuery->select('count( distinct H.subject_code) as count')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_practical_entry as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $countQuery->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $countOfSubjects = $countQuery->createCommand()->queryAll();
            if (!empty($ese_list)) {
                return $this->render('practicalesemarklist', [
                    'model' => $model,
                    'ese_list' => $ese_list,
                    'subjectsInfo' => $subjectsInfo,
                    'countOfSubjects' => $countOfSubjects,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['practical-entry/practicalesemarklist']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ESE Mark List');
            return $this->render('practicalesemarklist', [
                'model' => $model,
            ]);
        }
    }
    public function actionPracticalesemarklistPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['ese_mark_list'];
        
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "PRACTICAL ESE MARK LIST.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => "PRACTICAL ESE MARK LIST"],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ["FULL ARREAR LIST" . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionPracticalesemarklistExcel()
    {
       
        $content = $_SESSION['ese_mark_list'];
        
        $fileName = "PRACTICAL ESE MARK LIST-" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


     public function actionPracticalAttendanceSheet()
    {
        
        
        $model = new MarkEntry();
        $exam = new ExamTimetable();
        if (Yii::$app->request->post()) 
        {
            $section = $_POST['MarkEntry']['section'] != 'All' ? $_POST['MarkEntry']['section'] : '';
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

             $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            //$date_prac = DATE('Y-m-d',strtotime(Yii::$app->request->post('date_prac'))); 
            $year = Yii::$app->request->post('year');
            $month = Yii::$app->request->post('month');
            $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();
           
            $sem_valc = ConfigUtilities::SemCaluclation($_POST['mark_year'],$_POST['month'],$_POST['bat_map_val']);
            $query = new  Query();
            $query->select(' distinct (H.subject_code) as subject_code, H.subject_name,E.programme_name,D.degree_name,D.degree_code,E.programme_code,I.batch_name,G.coe_subjects_mapping_id,G.subject_type_id,G.semester,A.register_number,A.name')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                              
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'],'A.student_status' => 'Active','I.coe_batch_id'=>$_POST['bat_val'],'G.semester'=>$sem_valc,'G.coe_subjects_mapping_id'=>$_POST['subject_map_id']])
             ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
             if ($section != "")
             {
                 $query->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
            $query->groupBy('A.register_number');
            $query->orderBy('A.register_number');
            $practical = $query->createCommand()->queryAll();
            //print_r($practicalattendance);exit;
            foreach($practical  as $value)
            {
            if($value['subject_type_id']==15)
             {
            $query = new  Query();
            $query->select(' distinct (H.subject_code) as subject_code, H.subject_name,E.programme_name,D.degree_name,D.degree_code,E.programme_code,I.batch_name,G.coe_subjects_mapping_id,G.subject_type_id,G.semester,A.register_number,A.name')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')

                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
                ->join('JOIN','coe_nominal x','x.coe_subjects_id=H.coe_subjects_id and A.coe_student_id=x.coe_student_id  and B.course_batch_mapping_id=x.course_batch_mapping_id ');             ;
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'],'A.student_status' => 'Active','I.coe_batch_id'=>$_POST['bat_val'],'G.semester'=>$sem_valc,'G.coe_subjects_mapping_id'=>$_POST['subject_map_id']])
             ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
             if ($section != "")
             {
                 $query->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
            $query->groupBy('A.register_number');
            $query->orderBy('A.register_number');
            $practicalattendance = $query->createCommand()->queryAll();

             $countQuery = new  Query();
            $countQuery->select('count( distinct A.register_number) as count')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                 ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                 ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
                  ->join('JOIN','coe_nominal x','x.coe_subjects_id=H.coe_subjects_id and A.coe_student_id=x.coe_student_id  and B.course_batch_mapping_id=x.course_batch_mapping_id ');
            $countQuery->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val'],'G.coe_subjects_mapping_id'=>$_POST['subject_map_id']])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                
              if ($section != "")
             {
                 $countQuery->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
               
                  $countQuery->orderBy('A.register_number,H.subject_code');
            $countOfSubjects = $countQuery->createCommand()->queryAll();


          }
          else
         {
            $query = new  Query();
            $query->select(' distinct (H.subject_code) as subject_code, H.subject_name,E.programme_name,D.degree_name,D.degree_code,E.programme_code,I.batch_name,G.coe_subjects_mapping_id,G.subject_type_id,G.semester,A.register_number,A.name')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                              
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'],'A.student_status' => 'Active','I.coe_batch_id'=>$_POST['bat_val'],'G.semester'=>$sem_valc,'G.coe_subjects_mapping_id'=>$_POST['subject_map_id']])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
             if ($section != "")
             {
                 $query->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
            $query->groupBy('A.register_number');
            $query->orderBy('A.register_number');
            $practicalattendance = $query->createCommand()->queryAll();
            $countQuery = new  Query();
            $countQuery->select('count( distinct A.register_number) as count')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                 ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
               
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $countQuery->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val'],'G.coe_subjects_mapping_id'=>$_POST['subject_map_id']])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                
              if ($section != "")
             {
                 $countQuery->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
               
                  $countQuery->orderBy('A.register_number,H.subject_code');
            $countOfSubjects = $countQuery->createCommand()->queryAll();

           }
        



            }
           // print_r($countOfSubjects);exit;


           
            if (!empty($practicalattendance)) {
                return $this->render('practical-attendance-sheet', [
                    'model' => $model,
                    'practicalattendance' => $practicalattendance,
                    'exam'=>$exam,'countOfSubjects' => $countOfSubjects,'section'=>$section,                    
                   
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['practical-entry/practical-attendance-sheet']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Practical Attendance Sheet');
            return $this->render('practical-attendance-sheet', [
                'model' => $model,
                'exam' => $exam,
                
            ]);
        }
    }
   
     public function actionPracticalAttendanceSheetPdf()
     {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['attendance_sheet_practical'];        
        //unset($_SESSION['student_application_date']);
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => 'PRACTICAL ATTENDANCE SHEET.pdf',                
                'format' => Pdf::FORMAT_LEGAL,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse; font-family:Helvetica Neue, Helvetica, Arial, sans-serif; width:100%;   }

                        table td table{border: none !important;}
                        table td{
                            font-size: 13px !important; 
                            padding: 1px 0 !important; 
                            text-align: left;
                        }
                        table th{
                            font-size: 11px !important; 
                            text-align: left;
                            padding: 1px 0 !important; 
                           
                        }
                    }   
                ', 
                'options' => ['title' => 'Attendance Sheet'],
               
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    public function actionPracticalAttendanceSheetExcel()
    {
       
        $content = $_SESSION['attendance_sheet_practical'];
        
        $fileName = "PRACTICAL ATTENDANCE SHEET" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


}
