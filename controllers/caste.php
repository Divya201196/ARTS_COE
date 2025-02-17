<?php

namespace app\controllers;

use Yii;
use yii\db\Query;
use kartik\mpdf\Pdf;
use app\models\MarkEntryMaster;
use app\models\MarkEntry;
use app\models\DummyNumbers;
use app\models\HallAllocate;
use app\models\Regulation;
use app\models\AbsentEntry;
use app\models\Batch;
use app\models\Import;
use app\models\CoeBatDegReg;
use app\models\Degree;
use app\models\FeesPaid;
use app\models\Programme;
use app\models\Student;
use app\models\StuInfo;
use app\models\SubInfo;
use app\models\Categorytype;
use app\models\StudentMapping;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\ExamTimetable;
use app\models\StudentCategoryDetails;
use app\models\UpdateTracker;
use app\models\MarkEntryMasterSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use yii\helpers\Html;
/* Import Excel */
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Style_Protection;
use PHPExcel_Cell;
use PHPExcel_Shared_Date;
use PHPExcel_Cell_DataValidation;
use PHPExcel_Style_Alignment;
use kartik\widgets\Growl;
use yii\i18n\Formatter;
/* Excel Properties */
/**
 * MarkEntryMasterController implements the CRUD actions for MarkEntryMaster model.
 */
class MarkEntryMasterController extends Controller
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
     * Lists all MarkEntryMaster models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new MarkEntryMasterSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single MarkEntryMaster model.
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
     * Creates a new MarkEntryMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */

    public function getExcelproperties($fileName)
    {
        
        $objReader = PHPExcel_IOFactory::createReaderForFile($fileName);
        $objReader->setLoadSheetsOnly(array(0));
        $objReader->setReadDataOnly(true);
        $objPHPExcel = $objReader->load($fileName);
        $highestColumm = $objPHPExcel->setActiveSheetIndex(0)->getHighestDataColumn();
        $highestRow = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        $getData = $objPHPExcel->setActiveSheetIndex(0)->toArray();

        //unset($sheetData[1]); // Removing the headers         
        return ['sheetData'=>$sheetData,'highestRow'=>$highestRow,'highestColumm'=>$highestColumm];         
    }
    public function actionStudentArrearExport()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        if ($model->load(Yii::$app->request->post())) 
        {
            $register_number = $_POST['MarkEntry']['register_number'];
            $stu_map_id = Student::findOne(['register_number'=>$register_number]);

            if(!empty($stu_map_id))
            {
                $student_map_id = StudentMapping::findOne(['student_rel_id'=>$stu_map_id->coe_student_id]);
                
                $fetched_data = Yii::$app->db->createCommand("SELECT batch_name,degree_name,subject_code,subject_name,register_number,A.result  FROM coe_mark_entry_master as A JOIN coe_student_mapping as B ON B.coe_student_mapping_id=A.student_map_id JOIN coe_subjects_mapping as C ON C.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student as D ON D.coe_student_id=B.student_rel_id JOIN coe_subjects as E ON E.coe_subjects_id=C.subject_id JOIN coe_bat_deg_reg as F ON F.coe_bat_deg_reg_id=B.course_batch_mapping_id and F.coe_bat_deg_reg_id=C.batch_mapping_id JOIN coe_degree as G ON G.coe_degree_id=F.coe_degree_id JOIN coe_programme as H ON H.coe_programme_id=F.coe_programme_id JOIN coe_batch as I ON I.coe_batch_id=F.coe_batch_id where student_map_id='".$student_map_id->coe_student_mapping_id."' and subject_map_id NOT IN (select subject_map_id FROM coe_mark_entry_master where student_map_id='".$student_map_id->coe_student_mapping_id."' and student_map_id=A.student_map_id and subject_map_id=A.subject_map_id and result like '%Pass%') group by subject_code")->queryAll();
                if(!empty($fetched_data))
                {
                    return $this->render('student-arrear-export', [
                    'markentrymaster' => $markentrymaster,'model' => $model,'fetched_data'=>$fetched_data,
                       ]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','No Arrears Found');
                    return $this->redirect(['mark-entry-master/student-arrear-export']);
                }
            }
            else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','No Student Information Found');
                    return $this->redirect(['mark-entry-master/student-arrear-export']);
                }
            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Sudent Arrear Report');
            return $this->render('student-arrear-export', [
            'markentrymaster' => $markentrymaster,'model' => $model,
               ]);
        }
        
    }
    public function actionNoticeboard()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reval_status_entry = Categorytype::find()->where(['category_type'=>'Revaluation'])->orWhere(['description'=>'Revaluation'])->one();
        if (isset($_POST['noticeboardbutton'])) 
        {
            $reval_status = isset($_POST['MarkEntry']['mark_type'][0])?$_POST['MarkEntry']['mark_type'][0]:'';
            $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['bat_val'] . "'")->queryScalar();
            $degree_name = Yii::$app->db->createCommand("select concat(degree_name,' -  ',programme_name) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['bat_map_val'] . "'")->queryScalar();
            $year = $_POST['year'];
            $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();

            $whereCondition = [                        
                        'b.course_batch_mapping_id' => $_POST['bat_map_val'], 'c.year' => $year, 'c.month' => $_POST['month']
                    ];
            $query_n = new Query();
            $query_n->select('UPPER(a.register_number) as register_number,d.semester,UPPER(e.subject_code) as subject_code, UPPER(e.subject_name) as subject_name, c.CIA,c.ESE,e.CIA_max,e.ESE_max,c.total,UPPER(c.result) as result,UPPER(c.grade_name) as grade_name,c.withheld, c.grade_point,c.subject_map_id, c.student_map_id,c.mark_type,paper_no')
                ->from('coe_student a')
                ->join('JOIN', 'coe_student_mapping b', 'a.coe_student_id=b.student_rel_id')
                ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_student_mapping_id=c.student_map_id')
                ->join('JOIN', 'coe_subjects_mapping d', 'c.subject_map_id=d.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_subjects e', 'd.subject_id=e.coe_subjects_id')
                ->join('JOIN','coe_category_type xyz','xyz.coe_category_type_id = status_category_type_id');
            if(!empty($reval_status) && $reval_status=='yes')
                {
                    $query_n->join('JOIN', 'coe_mark_entry f', 'f.student_map_id=c.student_map_id and f.subject_map_id=c.subject_map_id and f.year=c.year and f.mark_type=c.mark_type and f.term=c.term and f.month=c.month');

                    $whereCondition_12 = [                        
                            'f.category_type_id'=>$reval_status_entry['coe_category_type_id'],'f.year'=>$_POST['year'],'f.month'  => $_POST['month'],'c.term'=>'35'
                        ];
                    $whereCondition = array_merge($whereCondition,$whereCondition_12);
                }
                $query_n->where($whereCondition)
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->groupBy('a.register_number,e.subject_code')
                ->orderBy('a.register_number,d.semester,paper_no');
            $noticeboard_copy = $query_n->createCommand()->queryAll();
            
            if (count($noticeboard_copy) > 0) {
                return $this->render('noticeboard', [
                    'model' => $model,
                    'noticeboard_copy' => $noticeboard_copy,
                    'year' => $year, 'month' => $month, 'batch_name' => $batch_name, 'degree_name' => $degree_name,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Notice Board Copy");
                return $this->render('noticeboard', [
                    'model' => $model,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Noticeboard Copy');
            return $this->render('noticeboard', [
                'model' => $model,
            ]);
        }
    }
    public function actionNoticeboardCopyPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['noticeboard_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Noticeboard Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => 'Noticeboard Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['NOTICE BOARD COPY ' . 'PRINTED ON : {DATE d-m-Y H:i:s:A} PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelNoticeboardcopy()
    {
        
            $content = $_SESSION['noticeboard_print'];
            
        $fileName = "Noticeboard Data " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionStudentArrearReportsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
          $content = $_SESSION['student_arrear_report'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => 'STUDENT ARREAR REPORTS.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                
                'options' => ['title' => 'STUDENT ARREAR REPORTS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['STUDENT ARREAR REPORTS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }

    public function actionStudentArrearReportsExcel()
    {        
        
        $content = $_SESSION['student_arrear_report'];
        
        $fileName ='STUDENT ARREAR REPORT.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionStudentResultExport()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Sudent Result Export');
        return $this->render('student-result-export', [
        'model' => $model,'galley' => $galley,
           ]);
    }
    public function actionViewExternalMarkentryArts()
    {
        $markEntry = new MarkEntry();
        $model = new MarkEntryMaster();
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to View External Marks');
        return $this->render('view-external-markentry-arts', [
        'model' => $model,'markEntry' => $markEntry,
           ]);

    }

    public function actionExternalMarkentryArts()
    {
        $markEntry = new MarkEntry();
        $model = new MarkEntryMaster();
        
        if ($model->load(Yii::$app->request->post())) 
        {
            if(isset($_POST['reg_number']))
            {
                $totalSuccess = '';
                $year =  $_POST['MarkEntryMaster']['year'];
                $term =  $_POST['MarkEntryMaster']['term'];
                $mark_type = $_POST['MarkEntryMaster']['mark_type'];
                $subject_map_id = $_POST['sub_val'];
                $totalSuccess = 0;
                $month = $_POST['month'];
                $internLa = Categorytype::find()->where(['category_type'=>'Internal Final'])->orWhere(['category_type'=>'CIA'])->one();
                $get_cat_entry_type = Categorytype::find()->where(['description'=>'Exam Datewise'])->one();
                $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->one();
                $count_of_reg_num = count($_POST['reg_number']);
                $created_at = date("Y-m-d H:i:s");
                $updateBy = Yii::$app->user->getId(); 
                $subject_map_id_de =SubjectsMapping::findOne($subject_map_id); 
                $subjMax = Subjects::findOne($subject_map_id_de->subject_id);
                
                $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year])->all();
               
                if(!empty($getAbsentList))
                {
                    for ($abse=0; $abse <count($getAbsentList) ; $abse++) 
                    { 
                        $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg']])->orderBy('coe_mark_entry_id desc')->one();
                       
                       $check_mark_entry = MarkEntry::find()->where(['category_type_id'=>$externAl->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg'],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orderBy('coe_mark_entry_id desc')->one();

                        $absen_model_save = new MarkEntry();
                        $absen_model_save->student_map_id = $getAbsentList[$abse]['absent_student_reg'];
                        $absen_model_save->subject_map_id = $subject_map_id;
                        $absen_model_save->category_type_id =$externAl->coe_category_type_id;
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
                        {
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
                            $ab_MarkEntryMaster->save(false);
                            unset($ab_MarkEntryMaster);
                        }

                    }
                }

                $transaction = Yii::$app->db->beginTransaction();
                for($i=0; $i < count($_POST['reg_number']) ; $i++) 
                { 
                    if(!empty($_POST['reg_number'][$i]) && $_POST['ese_marks'][$i]!='' )
                    {  
                        $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i]])->orderBy('coe_mark_entry_id desc')->one();

                        if(!empty($stuCiaMarks))
                        {
                            $INSERT_ESE_MARKS = ($_POST['ese_marks'][$i]=='-1' || $_POST['ese_marks'][$i]<0 )?0:$_POST['ese_marks'][$i];
                            $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, $stuCiaMarks->category_type_id_marks, $INSERT_ESE_MARKS,$year,$month);
                            $CIA = $stuCiaMarks->category_type_id_marks;

                            if($_POST['ese_marks'][$i]=='-1' || $_POST['ese_marks'][$i]<0)
                            {
                                $check_data = "SELECT * FROM coe_absent_entry WHERE absent_student_reg='".$_POST['reg_number'][$i]."' AND exam_type='".$mark_type."' AND absent_term='".$term."' and exam_month='".$month."' and exam_year='".$year."' AND exam_subject_id='".$subject_map_id." '";
                                $available_data = Yii::$app->db->createCommand($check_data)->queryAll();
                                if(count($available_data)>0)
                                {
                                    
                                } 
                                else{
                                        $query_insert = 'INSERT INTO coe_absent_entry (`absent_student_reg`,`exam_type`,`absent_term`,`exam_subject_id`,`exam_absent_status`,`exam_month`,`exam_year`,`created_by`,`created_at`,`updated_by`,`updated_at`) VALUES ("'.$_POST['reg_number'][$i].'","'.$mark_type.'","'.$term.'","'.$subject_map_id.'","'.$get_cat_entry_type['coe_category_type_id'].'","'.$month.'","'.$year.'","'.$updateBy.'","'.$created_at.'","'.$updateBy.'","'.$created_at.'")';
                                        $Insert_absent = Yii::$app->db->createCommand($query_insert)->execute();
                                    }
                            }

                            $model_save = new MarkEntry();
                            $model_save->student_map_id = $_POST['reg_number'][$i];
                            $model_save->subject_map_id = $subject_map_id;
                            $model_save->category_type_id =$externAl->coe_category_type_id;
                            $model_save->category_type_id_marks =$INSERT_ESE_MARKS;
                            $model_save->year = $year;
                            $model_save->month = $month;
                            $model_save->term = $term;
                            $model_save->mark_type = $mark_type;
                            $model_save->created_at = $created_at;
                            $model_save->created_by = $updateBy;
                            $model_save->updated_at = $created_at;
                            $model_save->updated_by = $updateBy;
                            
                            $check_mark_entry = MarkEntry::find()->where(['category_type_id'=>$externAl->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orderBy('coe_mark_entry_id desc')->one();

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
                                $markentrymaster->grade_point = $stu_result_data['grade_point'];
                                $markentrymaster->grade_name = $grade_name;
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
                                
                                $check_mark_entry_mas = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->one();

                                if(empty($check_mark_entry_mas) && $markentrymaster->save())
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
                                $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                            }
                            
                            Yii::$app->ShowFlashMessages->setMsg('Success','External Marks Inserted Successfully!!!');
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','NO CIA MARKS FOUND');
                        }
                    }
                }// For loop Ends Here 
                        

                if($totalSuccess>0)
                {
                    $getEse = Categorytype::find()->where(['description'=>'ESE'])->orWhere(['description'=>'External'])->one();
                    $getSubsInfo = new Query();
                    
                        $getSubsInfo->select(['A.register_number','D.subject_name','D.subject_code','E.category_type_id_marks as out_of_100','F.result']);
                        $getSubsInfo->from('coe_student as A')
                        ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id')
                        ->join('JOIN', 'coe_subjects_mapping as C', 'C.batch_mapping_id=B.course_batch_mapping_id')
                        ->join('JOIN', 'coe_subjects as D', 'D.coe_subjects_id=C.subject_id')
                        ->join('JOIN', 'coe_mark_entry as E', 'E.subject_map_id=C.coe_subjects_mapping_id and E.student_map_id=B.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mark_entry_master as F', 'F.subject_map_id=C.coe_subjects_mapping_id and F.student_map_id=B.coe_student_mapping_id')                        
                        ->Where(['F.subject_map_id'=>$subject_map_id,'F.year'=>$year,'F.month'=>$month,'F.term'=>$term,'F.mark_type'=>$mark_type,'E.subject_map_id'=>$subject_map_id,'E.year'=>$year,'E.month'=>$month,'E.term'=>$term,'E.mark_type'=>$mark_type,'category_type_id'=>$getEse['coe_category_type_id']])
                        ->groupBy('register_number')
                        ->orderBy('register_number');
                    $getSubsInfoDet = $getSubsInfo->createCommand()->queryAll();
                    
                    if(!empty($getSubsInfoDet))
                    {
                        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                        $table='';
                        $get_month_name=Categorytype::findOne($month);
                        $header = $footer = $final_html = $body = '';
                          $header = '<table width="100%" >
                            <thead class="thead-inverse">
                           
                                    <tr>
                                    <td align="center" colspan=4><h5>EXTERNAL MARK ENTRY FOR EXAMINATIONS '.$year.' - '.$get_month_name['description'].'</h5>
                                    </td></tr>
                                    <tr>
                                    <td align="center" colspan=4><h5>MARKS VERIFICATION AND APPROVAL FROM EXAMINER</h5></td></tr>
                                    <tr>                                        
                                        <td align="right" colspan=4>
                                            DATE OF VALUATION : '.date("d/m/Y").'
                                        </td> 
                                    </tr>
                                    <tr>
                                        <td align="left" colspan=4> 
                                            '.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE").' : ('.$subjMax->subject_code.') '.$subjMax->subject_name.'
                                        </td>
                                    </tr>
                                    <tr class="table-danger">
                                        <th>SNO</th>  
                                        <th>REGISTER NUMBER</th>
                                        <th>'.strtoupper("Marks Out of ".$subjMax->ESE_max).'</th>
                                        <th>'.strtoupper("Marks In Words").'</th>
                                    </tr>               
                                    </thead> 
                                    <tbody>     

                                    ';
                          $footer .='<tr class ="alternative_border">
                                <td align="left" colspan=2>
                                    Name of the INTERNAL Examiner <br /><br />
                                    &nbsp; <br />
                                </td>
                                <td align="right" colspan=2>
                                    Name of the EXTERNAL Examiner <br /><br />
                                    &nbsp; <br />
                                </td>
                                
                            </tr>
                            <tr>
                                <td align="left" colspan=2>
                                   Signature With Date <br /><br /><br />
                                </td>
                                <td align="right" colspan=2>
                                    Signature With Date <br /><br /><br />
                                </td> 
                            </tr></tbody></table>';

                          $increment = 1;
                        foreach ($getSubsInfoDet as $value)
                        {
                            if(isset($value["out_of_100"]))
                            {
                                $split_number = str_split($value["out_of_100"]);
                            }
                            
                            $print_text =( $value["result"]=='Absent' || $value["result"]=='AB' ) ?' <span style="color: #F00;" > ABSENT</span>': $this->valueReplaceNumber($split_number);

                           $body .='<tr height="15px"><td>'.$increment.'</td><td>'.$value["register_number"].'</td><td>'.$value["out_of_100"].'</td><td>'.$print_text.'</td></tr>';
                            $increment++;
                            if($increment%31==0)
                            {
                                $html = $header.$body.$footer;
                                $final_html .=$html;
                                $html = $body = '';
                            }
                        }
                        $html = $header.$body.$footer;   
                        $final_html .=$html;               
                        $content = $final_html; 

                        $pdf = new Pdf([                   
                                'mode' => Pdf::MODE_CORE,                 
                                'filename' => 'EXTERNAL MARK VERIFICATION.pdf',                
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
                                    table tr{border:1px solid #ccc;}
                                   
                                    tbody{margin-top: 15px; margin-bottom: 30px; }
                                    table td{padding:3px  !important;  } 
                                    table tr{ line-height: 20px !important; height: 10px !important;}
                                }',                       
                                'options' => ['title' => strtoupper('EXTERNAL').' MARK VERIFICATION'],
                                'methods' => [ 
                                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                                    'SetFooter'=>[strtoupper('EXTERNAL').' MARK VERIFICATION '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                                ],
                                
                            ]);
                          
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
            return $this->redirect(['mark-entry-master/external-markentry-arts']);
        }  else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to External Mark Entry');
            return $this->render('external-markentry-arts', [
                'markEntry' => $markEntry,                    
                'model' => $model,
           ]);
        }
        
    }

    public function actionExternalMarkentryEngg()
    {
        $markEntry = new MarkEntry();
        $model = new MarkEntryMaster();
        
        if ($model->load(Yii::$app->request->post())) 
        {
            if(isset($_POST['reg_number']))
            {
                $totalSuccess = '';
                $year =  $_POST['MarkEntryMaster']['year'];
                $term =  $_POST['MarkEntryMaster']['term'];
                $mark_type = $_POST['MarkEntryMaster']['mark_type'];
                $subject_map_id = $_POST['sub_val'];
                $totalSuccess = 0;
                $month = $_POST['month'];
                $internLa = Categorytype::find()->where(['category_type'=>'Internal Final'])->orWhere(['category_type'=>'CIA'])->one();
                $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->one();
                $count_of_reg_num = count($_POST['reg_number']);
                $created_at = date("Y-m-d H:i:s");
                $updateBy = Yii::$app->user->getId(); 
                $subject_map_id_de =SubjectsMapping::findOne($subject_map_id); 
                $subjMax = Subjects::findOne($subject_map_id_de->subject_id);
                
                $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year])->all();
               
                if(!empty($getAbsentList))
                {
                    for ($abse=0; $abse <count($getAbsentList) ; $abse++) 
                    { 
                        $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg']])->orderBy('coe_mark_entry_id desc')->one();
                        $stuMasterCiaMarks = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg']])->orderBy('coe_mark_entry_master_id desc')->one();
                       
                        $check_mark_entry = MarkEntry::find()->where(['category_type_id'=>$externAl->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg'],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->one();

                        $absen_model_save = new MarkEntry();
                        $absen_model_save->student_map_id = $getAbsentList[$abse]['absent_student_reg'];
                        $absen_model_save->subject_map_id = $subject_map_id;
                        $absen_model_save->category_type_id =$externAl->coe_category_type_id;
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
                        {
                            unset($absen_model_save);

                            $check_mark_master_entry = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$getAbsentList[$abse]['absent_student_reg'],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->one();
                            if(empty($check_mark_master_entry))
                            {
                                $CIA_Marks_dis = !empty($stuCiaMarks)?$stuCiaMarks->category_type_id_marks:(!empty($stuMasterCiaMarks)?$stuMasterCiaMarks['CIA']:0);
                                $ab_MarkEntryMaster = new MarkEntryMaster();
                                $ab_MarkEntryMaster->student_map_id = $getAbsentList[$abse]['absent_student_reg'];
                                $ab_MarkEntryMaster->subject_map_id =$subject_map_id;
                                $ab_MarkEntryMaster->CIA = $CIA_Marks_dis;
                                $ab_MarkEntryMaster->ESE = 0;
                                $ab_MarkEntryMaster->total = $CIA_Marks_dis;
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
                                $ab_MarkEntryMaster->save(false);
                            }
                            
                            unset($ab_MarkEntryMaster);
                        }

                    }
                }

                $transaction = Yii::$app->db->beginTransaction();
                 $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                for($i=0; $i < count($_POST['reg_number']) ; $i++) 
                { 
                    if(!empty($_POST['reg_number'][$i]) && !empty($_POST['ese_marks'][$i]))
                    {  
                        $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i]])->orderBy('coe_mark_entry_id desc')->one();

                        $stuMasterCiaMarks = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i]])->orderBy('coe_mark_entry_master_id desc')->one();

                        $condition=0;
                        $CIA='SKIT';
                        if(!empty($stuCiaMarks))
                        {
                            $condition=1;
                            $CIA = $stuCiaMarks->category_type_id_marks;
                        }
                        else if(!empty($stuMasterCiaMarks))
                        {
                            $condition=1;
                            $CIA = $stuMasterCiaMarks['CIA'];
                        }
                        else{
                            $condition=0;
                        }

                        if($condition==1 && $CIA!='SKIT' )
                        {      
                            $ese_mark_entred = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];                      
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
                            
                            $check_mark_entry = MarkEntry::find()->where(['category_type_id'=>$externAl->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orderBy('coe_mark_entry_id desc')->one();

                            if(empty($check_mark_entry) && $model_save->save(false))
                            {                               
                                $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id . '" AND student_map_id="' . $_POST['reg_number'][$i] . '" AND result not like "%pass%"')->queryScalar();
                                if ($check_attempt >= $config_attempt) {
                                    $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, 0, $ese_mark_entred,$year,$month);
                                    $CIA = 0;
                                    $ESE = $ese_mark_entred;
                                    $TOTAL = $ese_mark_entred;
                                } else {
                                    $stu_result_data = ConfigUtilities::StudentResult($_POST['reg_number'][$i], $subject_map_id, $CIA, $ese_mark_entred,$year,$month);
                                    $ESE = $stu_result_data['ese_marks'];
                                    $TOTAL = $stu_result_data['total_marks'];
                                }
                                $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month. "-" . $year : '';

                                $stu_result = $_POST['ese_marks'][$i]=='-1'?'Absent':$stu_result_data['result'];
                                $stu_grade_name = $_POST['ese_marks'][$i]=='-1'?'U':$stu_result_data['grade_name'];

                                $markentrymaster = new MarkEntryMaster();
                                $markentrymaster->student_map_id = $_POST['reg_number'][$i];
                                $markentrymaster->subject_map_id =$subject_map_id;
                                $markentrymaster->CIA = $CIA;
                                $markentrymaster->ESE = $ESE;
                                $markentrymaster->total = $TOTAL;
                                $markentrymaster->result = $stu_result;
                                $markentrymaster->grade_point = $stu_result_data['grade_point'];
                                $markentrymaster->grade_name = $stu_grade_name;
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
                                
                                $check_mark_entry_mas = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$_POST['reg_number'][$i],'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->one();

                                if(empty($check_mark_entry_mas) && $markentrymaster->save())
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
                                $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                            }
                            
                            Yii::$app->ShowFlashMessages->setMsg('Success','External Marks Inserted Successfully!!!');
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','NO CIA MARKS FOUND');
                        }
                    }
                }// For loop Ends Here 
                        

                if($totalSuccess>0)
                {
                    $getSubsInfo = new Query();
                    
                        $getSubsInfo->select(['A.register_number','D.subject_name','D.subject_code','E.category_type_id_marks as out_of_100']);
                        $getSubsInfo->from('coe_student as A')
                        ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id')
                        ->join('JOIN', 'coe_subjects_mapping as C', 'C.batch_mapping_id=B.course_batch_mapping_id')
                        ->join('JOIN', 'coe_subjects as D', 'D.coe_subjects_id=C.subject_id')
                        ->join('JOIN', 'coe_mark_entry as E', 'E.subject_map_id=C.coe_subjects_mapping_id and E.student_map_id=B.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mark_entry_master as F', 'F.subject_map_id=C.coe_subjects_mapping_id and F.student_map_id=B.coe_student_mapping_id')                        
                        ->Where(['F.subject_map_id'=>$subject_map_id,'F.year'=>$year,'F.month'=>$month,'F.term'=>$term,'F.mark_type'=>$mark_type,'E.subject_map_id'=>$subject_map_id,'E.year'=>$year,'E.month'=>$month,'E.term'=>$term,'E.mark_type'=>$mark_type,'E.category_type_id'=>$externAl->coe_category_type_id])
                        ->andWhere(['NOT LIKE','F.result','Absent'])
                        ->groupBy('register_number')
                        ->orderBy('register_number');
                    $getSubsInfoDet = $getSubsInfo->createCommand()->queryAll();
                    
                    if(!empty($getSubsInfoDet))
                    {
                        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                        $table='';
                        $get_month_name=Categorytype::findOne($month);
                        $header = $footer = $final_html = $body = '';
                          $header = '<table width="100%" >
                            <thead class="thead-inverse">
                                    <tr>
                                      <td> 
                                        <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                                      </td>

                                      <td colspan=2 align="center"> 
                                          <center><b><font size="6px">' . $org_name . '</font></b></center>
                                          <center> <font size="3px">' . $org_address . '</font></center>
                                          
                                          <center class="tag_line"><b>' . $org_tagline . '</b></center> 
                                     </td>
                                      <td align="center">  
                                        <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                                      </td>
                                    </tr>
                                    <tr>
                                    <td align="center" colspan=4><h5>EXTERNAL MARK ENTRY FOR EXAMINATIONS '.$year.' - '.$get_month_name['description'].'</h5>
                                    </td></tr>
                                    <tr>
                                    <td align="center" colspan=4><h5>MARKS VERIFICATION AND APPROVAL FROM EXAMINER</h5></td></tr>
                                    <tr>                                        
                                        <td align="right" colspan=4>
                                            DATE OF VALUATION : '.date("d/m/Y").'
                                        </td> 
                                    </tr>
                                    <tr>
                                        <td align="left" colspan=4> 
                                            '.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE").' : ('.$subjMax->subject_code.') '.$subjMax->subject_name.'
                                        </td>
                                    </tr>
                                    <tr class="table-danger">
                                        <th>SNO</th>  
                                        <th>REGISTER NUMBER</th>
                                        <th>'.strtoupper("Marks Out of ".$subjMax->ESE_max).'</th>
                                        <th>'.strtoupper("Marks In Words").'</th>
                                    </tr>               
                                    </thead> 
                                    <tbody>     

                                    ';
                          $footer .='<tr class ="alternative_border">
                                <td align="left" colspan=2>
                                    Name of the INTERNAL Examiner <br /><br />
                                    &nbsp; <br />
                                </td>
                                <td align="right" colspan=2>
                                    Name of the EXTERNAL Examiner <br /><br />
                                    &nbsp; <br />
                                </td>
                                
                            </tr>
                            <tr>
                                <td align="left" colspan=2>
                                   Signature With Date <br /><br /><br />
                                </td>
                                <td align="right" colspan=2>
                                    Signature With Date <br /><br /><br />
                                </td> 
                            </tr></tbody></table>';

                          $increment = 1;
                        foreach ($getSubsInfoDet as $value)
                        {
                            if(isset($value["out_of_100"]))
                            {
                                $split_number = str_split($value["out_of_100"]);
                            }
                            
                            $print_text = $this->valueReplaceNumber($split_number);
                           $body .='<tr height="15px"><td>'.$increment.'</td><td>'.$value["register_number"].'</td><td>'.$value["out_of_100"].'</td><td>'.$print_text.'</td></tr>';
                            $increment++;
                            if($increment%31==0)
                            {
                                $html = $header.$body.$footer;
                                $final_html .=$html;
                                $html = $body = '';
                            }
                        }
                        $html = $header.$body.$footer;   
                        $final_html .=$html;               
                        $content = $final_html; 

                        $pdf = new Pdf([                   
                                'mode' => Pdf::MODE_CORE,                 
                                'filename' => 'EXTERNAL MARK VERIFICATION.pdf',                
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
                                    table tr{border:1px solid #ccc;}
                                   
                                    tbody{margin-top: 15px; margin-bottom: 30px; }
                                    table td{padding:3px  !important;  } 
                                    table tr{ line-height: 20px !important; height: 10px !important;}
                                }',                       
                                'options' => ['title' => strtoupper('EXTERNAL').' MARK VERIFICATION'],
                                'methods' => [ 
                                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                                    'SetFooter'=>[strtoupper('EXTERNAL').' MARK VERIFICATION '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                                ],
                                
                            ]);
                          
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
            return $this->redirect(['mark-entry-master/external-markentry-engg']);
        }  else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to External Mark Entry');
            return $this->render('external-markentry-engg', [
                'markEntry' => $markEntry,                    
                'model' => $model,
           ]);
        }
        
    }

    public function actionStudentGradeInfoExport()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Sudent Grade Info Export');
        return $this->render('student-grade-info-export', [
        'model' => $model,'galley' => $galley,
           ]);
    }
    public function actionExcelExportStudentGradeResult()
    {
        
        $content = $_SESSION['student_grade_res_export'];
        $fileName =ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' RESULT '.date('y-M').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
           
    }
    public function actionInternalModeMarkEntry()
    {
        $markEntry = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
        if (Yii::$app->request->post()) 
        {
            $stu_map_ids = $_POST['reg_number'];
            $sub_map_id = $_POST['sub_val'];
            $sub_type = $_POST['sub_type'];
            $semester = $_POST['exam_semester'];
            $month = $_POST['month'];
            $year = $_POST['MarkEntry']['year'];
            $term = $_POST['MarkEntry']['term'];
            $mark_type = $_POST['MarkEntry']['mark_type'];
            $category_type_id = Categorytype::find()->where(['description'=>'ESE'])->one();
            $category_type_cia_id = Categorytype::find()->where(['description'=>'CIA'])->one();
            $bat_map_val = $_POST['bat_map_val'];
            $attendance_percentage = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT_STATUS);
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId();
           
            for ($i=0; $i <count($stu_map_ids) ; $i++) 
            { 
                $MarkEntry = new MarkEntry();
                $MarkEntryMaster = new MarkEntryMaster();
                if(isset($_POST['reg_number'][$i]))
                {
                    $pas_status = $_POST['status_'.$_POST['reg_number'][$i]];

                    $MarkEntry->student_map_id = $_POST['reg_number'][$i];
                    $MarkEntry->subject_map_id = $sub_map_id;
                    $MarkEntry->category_type_id = $category_type_cia_id['coe_category_type_id'];
                    $MarkEntry->category_type_id_marks = 0;
                    $MarkEntry->year = $year;
                    $MarkEntry->month = $month;
                    $MarkEntry->term = $term;
                    $MarkEntry->mark_type = $mark_type;
                    $MarkEntry->status_id = 0;
                    $MarkEntry->attendance_percentage = $attendance_percentage;
                    $MarkEntry->attendance_remarks = 'Allowed';
                    $MarkEntry->created_at = $created_at;
                    $MarkEntry->created_by = $updateBy;
                    $MarkEntry->updated_at = $created_at;
                    $MarkEntry->updated_by = $updateBy;
                    $MarkEntry->save(false);
                    unset($MarkEntry);

                    $MarkEntry = new MarkEntry();
                    $MarkEntry->student_map_id = $_POST['reg_number'][$i];
                    $MarkEntry->subject_map_id = $sub_map_id;
                    $MarkEntry->category_type_id = $category_type_id['coe_category_type_id'];
                    $MarkEntry->category_type_id_marks = 0;
                    $MarkEntry->year = $year;
                    $MarkEntry->month = $month;
                    $MarkEntry->term = $term;
                    $MarkEntry->mark_type = $mark_type;
                    $MarkEntry->status_id = 0;
                    $MarkEntry->attendance_percentage = $attendance_percentage;
                    $MarkEntry->attendance_remarks = 'Allowed';
                    $MarkEntry->created_at = $created_at;
                    $MarkEntry->created_by = $updateBy;
                    $MarkEntry->updated_at = $created_at;
                    $MarkEntry->updated_by = $updateBy;
                    $MarkEntry->save(false);
                    unset($MarkEntry);

                    if($sub_type==1)
                    {
                        $result = $pas_status=='YES' ? 'COMPLETED' : ($pas_status=='AB'?'Absent':'Not Completed');
                        $year_of_passing = $pas_status=='YES' ? $month.'-'.$year : '';
                        
                        $grade_name = $pas_status=='YES' ? '' :'U'; 
                    }
                     else if($sub_type==2)
                    {
                        $result =ConfigUtilities::getPartFiveDetails($pas_status);
                        $year_of_passing =$month.'-'.$year;
                        $grade_name =ConfigUtilities::getPartFiveDetails($pas_status); 
                    }
                    else
                    {
                        $result = $pas_status=='YES' ? 'PASS' : ($pas_status=='AB'?'Absent':'Fail');
                        $year_of_passing = $pas_status=='YES' ? $month.'-'.$year : '';
                        $grade_name = $pas_status=='YES' ? '' :'U'; 
                    }

                    $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master as A  WHERE A.subject_map_id="'.$sub_map_id.'" AND A.student_map_id="'.$_POST['reg_number'][$i].'"')->queryScalar();
                                     
                    $change_cia_val = isset($check_attempt) && !empty($check_attempt) ? $check_attempt +1:1;

                    $MarkEntryMaster->student_map_id = $_POST['reg_number'][$i];
                    $MarkEntryMaster->subject_map_id = $sub_map_id;
                    $MarkEntryMaster->CIA = 0;
                    $MarkEntryMaster->ESE = 0;
                    $MarkEntryMaster->total = 0;
                    $MarkEntryMaster->result = $result;
                    $MarkEntryMaster->grade_point = 0;
                    $MarkEntryMaster->grade_name = $grade_name;
                    $MarkEntryMaster->year = $year;
                    $MarkEntryMaster->month = $month;
                    $MarkEntryMaster->term = $term;
                    $MarkEntryMaster->mark_type = $mark_type;
                    $MarkEntryMaster->status_id = 0;
                    $MarkEntryMaster->year_of_passing = $year_of_passing;
                    $MarkEntryMaster->attempt = $change_cia_val;
                    $MarkEntryMaster->created_by = $updateBy;
                    $MarkEntryMaster->created_at = $created_at;
                    $MarkEntryMaster->updated_by = $updateBy;
                    $MarkEntryMaster->updated_at = $created_at;
                    $MarkEntryMaster->save(false);
                    unset($MarkEntryMaster);
                }
            }
             Yii::$app->ShowFlashMessages->setMsg('Success','Successfully Updated');
            return $this->redirect(['internal-mode-mark-entry']);

        }

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Internal Mode Mark Entry');
        return $this->render('internal-mode-mark-entry', [
                'markEntry' => $markEntry,                    
                'markentrymaster' => $markentrymaster,
           ]);
    }
    public function actionViewInternalModeMarkEntry()
    {
        $markEntry = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to View Internal Mode Mark Entry');
        return $this->render('view-internal-mode-mark-entry', [
                'markEntry' => $markEntry,                    
                'markentrymaster' => $markentrymaster,
           ]);
           
    }
    public function actionExcelExportStudentResult()
    {
        
        $content = $_SESSION['student_res_export'];         
        $fileName =ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' RESULT '.date('y-M').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
           
    }
    
    public function actionImportCiaMarks()
    {
        $model = new MarkEntryMaster();
        $markEntry = new MarkEntry();
        $student = new Student();
        $importModel = new Import();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (Yii::$app->request->post() && isset($_FILES['uploaded_file'])) 
        {
            $exam_year = $_POST['MarkEntry']['year'];
            $batch = $_POST['bat_val'];
            $bat_map_val = $_POST['bat_map_val'];
            $month = $_POST['month'];

            if(empty($exam_year) || empty($bat_map_val) || empty($month) )
            {
                Yii::$app->ShowFlashMessages->setMsg('ERROR','NO DATA FOUND  ');
                return $this->redirect(['import-cia-marks']);
            }

            $batch = Batch::findOne($batch);
            $batch_mapping = CoeBatDegReg::findOne($bat_map_val);
            $stu_map_get = StudentMapping::findOne(['course_batch_mapping_id'=>$bat_map_val]);
            $degree = Degree::findOne($batch_mapping->coe_degree_id);
            $programme = Programme::findOne($batch_mapping->coe_programme_id);
            $elective_id_get = Categorytype::find()->where(['description'=>'Elective'])->orWhere(['category_type'=>'Elective'])->one();
            $term = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE description LIKE "%end%"')->queryScalar();
                                                        
            $mark_type = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE description LIKE "%regular%"')->queryScalar();

            $sem_calc = ConfigUtilities::SemCaluclation($exam_year,$month,$bat_map_val);

            $subject_query1 = new Query();
            $subject_query1->select('a.*')
            ->from('coe_subjects_mapping a')
            ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
            ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$sem_calc])
            ->andWhere(['NOT IN','a.subject_type_id',$elective_id_get['coe_category_type_id']])
            ->andWhere(['<>','CIA_max',0])
            ->andWhere(['<>','ESE_max',0])
            ->groupBy('b.subject_code')
            ->orderBy('b.subject_code');
            $subjects_datache = $subject_query1->createCommand()->queryAll();
            $adfdd_subs = array_filter(['']);
            foreach ($subjects_datache as $key => $checkAdd) {
                $adfdd_subs[$checkAdd['coe_subjects_mapping_id']] = $checkAdd['coe_subjects_mapping_id'];
            }

          $check_date = new Query();
            $check_date->select('*')
            ->from('coe_mark_entry a')
            ->where(["a.year"=>$exam_year,"a.month"=>$month,'student_map_id'=>$stu_map_get->coe_student_mapping_id]);
            $get_result = $check_date->createCommand()->queryAll();

            if(!empty($get_result) && count($get_result)>0)
            {
                Yii::$app->ShowFlashMessages->setMsg('error','CIA MARKS ALREADY IMPORTED FOR '.$degree->degree_code." ".$programme->programme_code.' ');
                return $this->redirect(['import-cia-marks']);
            }

            $uploaded_file = $_FILES["uploaded_file"]["name"];
            $save_folder = Yii::getAlias('@webroot').'/resources/uploaded/marks/';
            $saving_file_name = date('d-m-Y-H-i-s')."-".str_replace(" ", "-", $uploaded_file);
            $save_in_folder = $save_folder.$saving_file_name;
            if(move_uploaded_file($_FILES["uploaded_file"]["tmp_name"], $save_in_folder))
            {

                if(!empty($save_in_folder))
                {
                    $sheet_properties = $this->getExcelproperties($save_in_folder);                   
                    $sheetData = $sheet_properties['sheetData'];
                    $highestRow = $sheet_properties['highestRow'];
                    $highestColumm = $sheet_properties['highestColumm'];
                    $dispResults = []; 
                    $totalSuccess = 0;
                    $importResults = [];
                    $created_by = $updated_by = Yii::$app->user->getId();
                    $created_at = $updated_at = date("Y-m-d H:i:s");
                    $get_cat_id = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE (category_type like "%Internal Final%" OR category_type like "%CIA%") ')->queryScalar();
                    
                    $data = 0;
                    $add_count = count($sheetData[3])<26 ? count($sheetData[3]) : 26;
                    for ($i=65; $i <(65+$add_count) ; $i++) 
                    { 
                       if(strstr($sheetData[3][chr($i)],"SEMESTER"))
                       {
                         $data = $sheetData[3][chr($i)];
                       }
                    }
                    $sem_verify = ConfigUtilities::SemCaluclation($exam_year,$month,$bat_map_val);
                    $semester_number = preg_replace("/[^0-9]/", '', $data);  

                    if(!empty($sheetData) && $semester_number!=0 && $sem_verify==$semester_number)
                    {
                        $subject_query = new Query();
                        $subject_query->select('a.*')
                        ->from('coe_subjects_mapping a')
                        ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
                        ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$semester_number])
                        ->andWhere(['<>','a.subject_type_id',$elective_id_get['coe_category_type_id']])
                        ->andWhere(['<>','CIA_max',0])
                        ->andWhere(['<>','ESE_max',0])
                        ->groupBy('b.subject_code')
                        ->orderBy('b.subject_code');
                        $subjects_data_com = $subject_query->createCommand()->queryAll();
                        
                        $get_sub_details_no = new Query();
                        $get_sub_details_no->select('b.*')
                        ->from('coe_subjects a')
                        ->join('JOIN','coe_subjects_mapping b','b.subject_id=a.coe_subjects_id')
                        ->join('JOIN','coe_nominal c','c.coe_subjects_id=a.coe_subjects_id')
                        ->where(["b.batch_mapping_id"=>$bat_map_val,"b.semester"=>$semester_number,'c.course_batch_mapping_id'=>$bat_map_val,'c.semester'=>$semester_number])
                        ->andWhere(['IN','b.subject_type_id',$elective_id_get['coe_category_type_id']])
                        ->andWhere(['<>','CIA_max',0])
                        ->andWhere(['<>','ESE_max',0])
                        ->groupBy('coe_subjects_mapping_id');
                        $get_sub_result_no = $get_sub_details_no->createCommand()->queryAll(); 


                        $subjects_data = !empty($get_sub_result_no) ? array_merge($get_sub_result_no,$subjects_data_com) : $subjects_data_com;   
                        $sub_codes_count = count($subjects_data)+4; 
                        
                        // Count of Subject Codes + College Name & Titles
                        // $i=1 Excel Starting Array is 1
                        for ($i=1; $i <=$sub_codes_count ; $i++) { 
                           unset($sheetData[$i]);
                        }
                        $lastRow = $highestRow-$sub_codes_count;
                        $sheetData = array_values($sheetData); // Reset the Array Index After unsetting the index
                        $omit_looping = ['A','B','C',$highestColumm];

                        $count = count($subjects_data)+4;
                        $lastColumn = $highestColumm;
                        $getSplit = str_split($lastColumn);
                        
                        
                        $last_comp = count($getSplit)>1? ord($getSplit[1]):ord($getSplit[0]); 

                        $finalDff = $last_comp-65; 

                        $repeat_col = $finalDff<3 ? 'Z': (count($getSplit)>1?$getSplit[1]:$getSplit[0]) ;

                        $a1_value = chr( (ord($last_comp)-1) );
                        
                        $last_column_before = count($getSplit)>1? $getSplit[0].$a1_value:$getSplit[0];
                        
                        if(count($getSplit)>1)
                        {

                        }
                        else
                        {
                            /*for ($char=65; $char <= ord($last_column_before) ; $char++) 
                            { 
                                if(!in_array(chr($char), $omit_looping))
                                {
                                    $subject_column[] = chr($char);
                                    $excel_subjects[] = $sheetData[0][chr($char)];
                                }
                            }*/
                        }

                        for ($char=65; $char <= ord($lastColumn) ; $char++) 
                        { 
                            if(!in_array(chr($char), $omit_looping))
                            {
                                $subject_column[] = chr($char);
                                $excel_subjects[] = $sheetData[0][chr($char)];
                            }
                        }
                        
                        unset($sheetData[0]); //Remove Header Now
                        for ($sub=0; $sub <count($excel_subjects) ; $sub++) 
                        { 
                            for ($stu=1; $stu <= count($sheetData) ; $stu++) 
                            { 
                                $subject_code = isset($excel_subjects[$sub])?$excel_subjects[$sub]:'';

                                 $sub_marks = !empty($sheetData[$stu][$subject_column[$sub]])? round($sheetData[$stu][$subject_column[$sub]]):'0'; 
                                $sub_marks = $sub_marks=='-'?'':$sub_marks;
                                $stu_reg_num = $sheetData[$stu]['B'];

                                $attendance_value = isset($sheetData[$stu][$highestColumm])?$sheetData[$stu][$highestColumm]:ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT_STATUS);
                                
                                $attendance_remarks = $attendance_value>=ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT_STATUS)?"Allowed":'Not Allowed';

                                $masterModel = new MarkEntryMaster();
                                $markEntryModel = new MarkEntry();

                                $stu_map_query = new Query();
                                $stu_map_query->select('a.coe_student_mapping_id')
                                ->from('coe_student_mapping a')
                                ->join('JOIN','coe_student b','b.coe_student_id=a.student_rel_id')
                                ->where(['b.register_number'=>$stu_reg_num,'course_batch_mapping_id'=>$bat_map_val])
                                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                                $stu_map_id = $stu_map_query->createCommand()->queryScalar();

                                $sub_map_query = new Query();
                                $sub_map_query->select(['a.coe_subjects_mapping_id','b.ESE_max','b.CIA_max'])
                                ->from('coe_subjects_mapping a')
                                ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
                                ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$semester_number,'b.subject_code'=>$subject_code])
                                ->groupBy('b.subject_code')
                                ->orderBy('b.subject_code');
                                $sub_map_id = $sub_map_query->createCommand()->queryOne();

                               $check_mark_query = new Query();
                                $check_mark_query->select('*')
                                ->from('coe_mark_entry')                                
                                ->where(["student_map_id"=>$stu_map_id,'subject_map_id'=>$sub_map_id['coe_subjects_mapping_id'],'category_type_id'=>$get_cat_id]);
                                $check_mark_entry = $check_mark_query->createCommand()->queryAll();
                                if(!empty($stu_map_id) && !empty($sub_map_id))
                                {
                                    $subs_id = SubjectsMapping::findOne($sub_map_id['coe_subjects_mapping_id']);
                                    $subject_max_marks = Subjects::findOne($subs_id->subject_id);
                                    $sub_cat_type = Categorytype::findOne($subs_id->subject_type_id);
                                    
                                    $stu_tab_id = StudentMapping::findOne($stu_map_id);

                                    if(($sub_cat_type->description || $sub_cat_type->category_type) =="Elective" )
                                    {
                                        
                                        $check_nominal_query = new Query();
                                        $check_nominal_query->select('*')
                                        ->from('coe_nominal')                                
                                        ->where(["coe_student_id"=>$stu_tab_id->student_rel_id,'coe_subjects_id'=>$subs_id->subject_id,'semester'=>$semester_number]);
                                        $check_nominal = $check_nominal_query->createCommand()->queryAll();
                                    }
                                    if(empty($check_nominal) && empty($sub_marks) && $sub_marks!=0)
                                    {
                                         
                                    }
                                    else
                                    {
                                        $stage = 0;   
                                        $inserted_status = 1; 
                                        if(empty($check_mark_entry))
                                        {    
                                            if( $sub_cat_type->description == "Elective"  || $sub_cat_type->category_type  == "Elective" )
                                            {   
                                                $check_nominal1_query = new Query();
                                                $check_nominal1_query->select('*')
                                                ->from('coe_nominal')                                
                                                ->where(["coe_student_id"=>$stu_tab_id->student_rel_id,'coe_subjects_id'=>$subs_id->subject_id,'semester'=>$semester_number]);
                                                $check_nominal_ava = $check_nominal1_query->createCommand()->queryAll();
                                                $inserted_status = isset($check_nominal_ava) && !empty($check_nominal_ava) ? 2 : 1;                    
                                            }
                                            else
                                            {                                         
                                                $inserted_status = 2;
                                            }
                                            if($inserted_status==2)
                                            {
                                                $sub_marks = $sub_marks <= 0 ? 0 : $sub_marks;

                                                $markEntry = new MarkEntry();
                                                $markEntry->student_map_id = $stu_map_id;
                                                $markEntry->subject_map_id = $sub_map_id['coe_subjects_mapping_id'];
                                                $markEntry->category_type_id = $get_cat_id;
                                                $markEntry->category_type_id_marks = $sub_marks;
                                                $markEntry->year = $exam_year;
                                                $markEntry->month = $month;
                                                $markEntry->term = $term;
                                                $markEntry->mark_type = $mark_type;
                                                $markEntry->attendance_percentage = $attendance_value;
                                                $markEntry->attendance_remarks = $attendance_remarks;
                                                $markEntry->created_at = $created_at;
                                                $markEntry->created_by = $created_by;
                                                $markEntry->updated_at = $created_at;
                                                $markEntry->updated_by = $created_by;
                                            }
                                            if($subject_max_marks->ESE_min==0 && $subject_max_marks->ESE_max==0 && $subject_max_marks->CIA_max!=0)
                                            {
                                                $stage = 1;
                                            }
                                            else if($subject_max_marks->ESE_min==0 && $subject_max_marks->ESE_max==0 && $subject_max_marks->CIA_max==0 && $subject_max_marks->CIA_min==0)
                                            {
                                                $stage = 1;
                                            }

                                            if($sub_marks>$subject_max_marks->CIA_max)
                                            {
                                                $dispResults[] = ['type' => 'E',  'message' => 'Marks Exceeds Maximum','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code,'marks'=>$sub_marks];
                                            }
                                            else
                                            {
                                                if($inserted_status == 2 && $markEntry->save(false))
                                                {
                                                    if($stage==1)
                                                    {
                                                        $sub_marks = $sub_marks < 0 ? 0 : $sub_marks;
                                                        $result_calc = ConfigUtilities::StudentResult($stu_map_id, $sub_map_id['coe_subjects_mapping_id'],$sub_marks,0,$exam_year,$month);

                                                        $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                                                        $cia_marks = $result_calc['attempt']>$config_attempt ? '0':$sub_marks; 

                                                        $MarkEntryMasterModel = new MarkEntryMaster();
                                                        $MarkEntryMasterModel->student_map_id=$stu_map_id;
                                                        $MarkEntryMasterModel->subject_map_id=$sub_map_id['coe_subjects_mapping_id'];
                                                        $MarkEntryMasterModel->CIA=$sub_marks;
                                                        $MarkEntryMasterModel->ESE=$result_calc["ese_marks"];
                                                        $MarkEntryMasterModel->total=($result_calc['ese_marks']+$sub_marks);
                                                        $MarkEntryMasterModel->result=$result_calc["result"];
                                                        $MarkEntryMasterModel->grade_point=$result_calc['grade_point'];
                                                        $MarkEntryMasterModel->grade_name=$result_calc['grade_name'];
                                                        $MarkEntryMasterModel->year = $exam_year;
                                                        $MarkEntryMasterModel->month = $month;

                                                        $MarkEntryMasterModel->term = $term;
                                                        $MarkEntryMasterModel->mark_type = $mark_type;

                                                        $year_of_passing = $result_calc["year_of_passing"]=='' && $result_calc["result"]=='Pass' ? $month."-".$exam_year : $result_calc["year_of_passing"];

                                                        $MarkEntryMasterModel->year_of_passing = $year_of_passing;
                                                        $MarkEntryMasterModel->attempt=$result_calc["attempt"];
                                                        $MarkEntryMasterModel->status_id=0;
                                                        $MarkEntryMasterModel->created_by = $created_by;
                                                        $MarkEntryMasterModel->created_at = $created_at;
                                                        $MarkEntryMasterModel->updated_by = $created_by;
                                                        $MarkEntryMasterModel->updated_at = $created_at;
                                                        $MarkEntryMasterModel->save(false);
                                                    }

                                                    $dispResults[] = ['type' => 'S',  'message' => 'Success','reg_num'=>$stu_reg_num,'marks'=>$sub_marks,'sub_code'=>$subject_code];
                                                    $totalSuccess+=1;

                                                    $stage = 0; 
                                                }
                                                else
                                                {
                                                    $dispResults[] = ['type' => 'P',  'message' => 'No Nominal Found','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code,'marks'=>$sub_marks];
                                                }
                                            }                                            
                                        }
                                        else
                                        {

                                            $dispResults[] = ['type' => 'E',  'message' => 'Already Imported','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code,'marks'=>'Not Imported'];
                                        }
                                    }
                                    
                                }
                                else
                                {

                                    $dispResults[] = ['type' => 'E',  'message' => 'No Data Found','reg_num'=>$stu_reg_num,'marks'=>'No Data Found','sub_code'=>$subject_code];
                                }
                            } // For Loop 2
                        } // For Loop 1 Closed Here 


                        // Moved till here

                        $importResults = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess];

                        return $this->render('import-cia-marks', [
                            'model' => $model,
                            'markEntry' => $markEntry,
                            'student' => $student,
                            'importModel' => $importModel,
                            'importResults' => $importResults,
                        ]);
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error',"File is Empty OR File was Modified. ");

                        unlink($save_in_folder);
                        return $this->render('import-cia-marks', [
                            'model' => $model,
                            'markEntry' => $markEntry,
                            'student' => $student,
                            'importModel' => $importModel,
                        ]);
                    }
                }
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Import the File");
                return $this->render('import-cia-marks', [
                    'model' => $model,
                    'markEntry' => $markEntry,
                    'student' => $student,
                    'importModel' => $importModel,
                ]); 
            }
        }
        else
        {
           Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to Marks Import Section");
           return $this->render('import-cia-marks', [
                'model' => $model,
                'markEntry' => $markEntry,
                'student' => $student,
                'importModel' => $importModel,
            ]); 
        }
    }

    public function actionImportEseMarks()
    {
        $model = new MarkEntryMaster();
        $markEntry = new MarkEntry();
        $student = new Student();
        $importModel = new Import();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (Yii::$app->request->post() && isset($_FILES['uploaded_file'])) 
        {
            $exam_year = $_POST['MarkEntry']['year'];
            $batch = $_POST['bat_val'];
            $bat_map_val = $_POST['bat_map_val'];
            $month = $_POST['month'];
            $term = $_POST['term'];
            $mark_type = $_POST['mark_type'];
            $get_cat_id_reg = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE category_type like "%Regular%"  ')->queryScalar();
           
            if($get_cat_id_reg != $mark_type)
            {
                Yii::$app->ShowFlashMessages->setMsg('error','ESE MARKS CAN BE IMPORTED ONLY FOR REGULAR CONTACT ADMIN FOR MORE DETAILS ');
                return $this->redirect(['import-ese-marks']);
            }

            if(empty($exam_year) || empty($batch) || empty($bat_map_val) || empty($month) || empty($term) || empty($mark_type))
            {
                Yii::$app->ShowFlashMessages->setMsg('error','KINDLY SELECT ALL REQUIRED FIELDS');
                return $this->redirect(['import-ese-marks']);
            }

            $batch = Batch::findOne($batch);
            $batch_mapping = CoeBatDegReg::findOne($bat_map_val);
            $degree = Degree::findOne($batch_mapping->coe_degree_id);
            $programme = Programme::findOne($batch_mapping->coe_programme_id);
            $elective_id_get = Categorytype::find()->where(['description'=>'Elective'])->orWhere(['category_type'=>'Elective'])->one();

            $check_date = new Query();
            $check_date->select('*')
            ->from('coe_mark_entry a')
            ->where(["a.year"=>$exam_year,"a.month"=>$month,'mark_type'=>$mark_type]);
            $get_result = $check_date->createCommand()->queryAll();
            if(!empty($get_result) && count($get_result)>0)
            {
                Yii::$app->ShowFlashMessages->setMsg('error','ESE MARKS ALREADY IMPORTED FOR '.$degree->degree_code." ".$programme->programme_code.' ');
                return $this->redirect(['import-ese-marks']);
            }
            $uploaded_file = $_FILES["uploaded_file"]["name"];
            $save_folder = Yii::getAlias('@webroot').'/resources/uploaded/marks/';
            $saving_file_name = date('d-m-Y-H-i-s')."-".str_replace(" ", "-", $uploaded_file);
            $save_in_folder = $save_folder.$saving_file_name;
            if(move_uploaded_file($_FILES["uploaded_file"]["tmp_name"], $save_in_folder))
            {
                if(!empty($save_in_folder))
                {
                    $sheet_properties = $this->getExcelproperties($save_in_folder);                   
                    $sheetData = $sheet_properties['sheetData'];
                    $highestRow = $sheet_properties['highestRow'];
                    $highestColumm = $sheet_properties['highestColumm'];
                    $dispResults = []; 
                    $totalSuccess = 0;
                    $importResults = [];
                    $created_by = $updated_by = Yii::$app->user->getId();
                    $created_at = $updated_at = date("Y-m-d H:i:s");
                    $get_cat_id = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE category_type like "%ESE%"  ')->queryScalar();
                    $get_cia_cat_id = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE (category_type like "%Internal Final%" OR category_type like "%CIA%")')->queryScalar();

                    $data = 0;
                    for ($i=65; $i <(65+count($sheetData[3])) ; $i++) 
                    { 
                       if(strstr($sheetData[3][chr($i)],"SEMESTER"))
                       {
                         $data = $sheetData[3][chr($i)];
                       }
                    }
                    $semester_number = preg_replace("/[^0-9]/", '', $data);  
                    
                    if(!empty($sheetData) && $semester_number!=0)
                    {
                        $subject_query = new Query();
                        $subject_query->select('a.*')
                        ->from('coe_subjects_mapping a')
                        ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
                        ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$semester_number])
                        ->andWhere(['NOT IN','a.subject_type_id',$elective_id_get['coe_category_type_id']])
                        ->andWhere(['<>','ESE_min','0'])
                        ->andWhere(['<>','ESE_max','0'])
                        ->groupBy('b.subject_code')
                        ->orderBy('b.subject_code');
                        $subjects_data_comm = $subject_query->createCommand()->queryAll();

                        $get_sub_details_no = new Query();
                        $get_sub_details_no->select('b.*')
                        ->from('coe_subjects a')
                        ->join('JOIN','coe_subjects_mapping b','b.subject_id=a.coe_subjects_id')
                        ->join('JOIN','coe_nominal c','c.coe_subjects_id=a.coe_subjects_id')
                        ->where(["b.batch_mapping_id"=>$bat_map_val,"b.semester"=>$semester_number,'c.course_batch_mapping_id'=>$bat_map_val,'c.semester'=>$semester_number])
                        ->andWhere(['IN','b.subject_type_id',$elective_id_get['coe_category_type_id']])
                        ->groupBy('coe_subjects_mapping_id');
                        $get_sub_result_no = $get_sub_details_no->createCommand()->queryAll();           
                        $subjects_data = !empty($get_sub_result_no) ? array_merge($get_sub_result_no,$subjects_data_comm) : $get_sub_result_co;   
                        
                        $sub_codes_count = count($subjects_data)+4; 
                        // Count of Subject Codes + College Name & Titles
                        // $i=1 Excel Starting Array is 1
                        for ($i=1; $i <=$sub_codes_count ; $i++) { 
                           unset($sheetData[$i]);
                        }
                        $lastRow = $highestRow-$sub_codes_count;
                        $sheetData = array_values($sheetData); // Reset the Array Index After unsetting the index
                        $omit_looping = ['A','B','C',$highestColumm];

                        for ($char=65; $char <= ord($highestColumm) ; $char++) 
                        { 
                            if(!in_array(chr($char), $omit_looping))
                            {
                                $subject_column[] = chr($char);
                                $excel_subjects[] = $sheetData[0][chr($char)];
                            }
                        }
                        unset($sheetData[0]); //Remove Header Now
                       
                        for ($sub=0; $sub <count($excel_subjects) ; $sub++) 
                        { 
                            for ($stu=1; $stu <= count($sheetData) ; $stu++) 
                            { 
                                $subject_code = isset($excel_subjects[$sub])?$excel_subjects[$sub]:'';
                                $sub_marks = isset($sheetData[$stu][$subject_column[$sub]])? round($sheetData[$stu][$subject_column[$sub]]):0;
                                $stu_reg_num = $sheetData[$stu]['B'];

                                $masterModel = new MarkEntryMaster();
                                $markEntryModel = new MarkEntry();

                                $stu_map_query = new Query();
                                $stu_map_query->select('a.coe_student_mapping_id')
                                ->from('coe_student_mapping a')
                                ->join('JOIN','coe_student b','b.coe_student_id=a.student_rel_id')
                                ->where(['b.register_number'=>$stu_reg_num,'course_batch_mapping_id'=>$bat_map_val])
                                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                                $stu_map_id = $stu_map_query->createCommand()->queryScalar();

                                $sub_map_query = new Query();
                                $sub_map_query->select(['a.coe_subjects_mapping_id','b.ESE_max','b.ESE_max'])
                                ->from('coe_subjects_mapping a')
                                ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
                                ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$semester_number,'b.subject_code'=>$subject_code])
                                ->groupBy('b.subject_code')
                                ->orderBy('b.subject_code');
                                $sub_map_id = $sub_map_query->createCommand()->queryOne();
                                
                               $check_mark_query = new Query();
                                $check_mark_query->select('*')
                                ->from('coe_mark_entry')                                
                                ->where(["student_map_id"=>$stu_map_id,'subject_map_id'=>$sub_map_id['coe_subjects_mapping_id'],'category_type_id'=>$get_cat_id]);
                                $check_mark_entry = $check_mark_query->createCommand()->queryAll();
                                /* @var $stu_map_id type */
                                if(!empty($sub_map_id))
                                {                                    
                                    $subs_id = SubjectsMapping::findOne($sub_map_id['coe_subjects_mapping_id']);
                                    $sub_cat_type = Categorytype::findOne($subs_id->subject_type_id);
                                    $subject_max_marks = Subjects::findOne($subs_id->subject_id);
                                    if(($sub_cat_type->description || $sub_cat_type->category_type) =="Elective" )
                                    {
                                        $stu_tab_id = StudentMapping::findOne($stu_map_id);
                                        $check_nominal_query = new Query();
                                        $check_nominal_query->select('*')
                                        ->from('coe_nominal')                                
                                        ->where(["coe_student_id"=>$stu_tab_id->student_rel_id,'coe_subjects_id'=>$subs_id->subject_id,'semester'=>$semester_number]);
                                        $check_nominal = $check_nominal_query->createCommand()->queryAll();
                                    }

                                    if(empty($sub_marks) && empty($check_nominal))
                                    {
                                        
                                    }
                                    else
                                    {
                                        if(empty($check_mark_entry))
                                        {   
                                            
                                            $get_cia_marks = MarkEntry::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$sub_map_id['coe_subjects_mapping_id'],'category_type_id'=>$get_cia_cat_id])->one();
                                            if(empty($get_cia_marks))
                                            {
                                                $dispResults[] = ['type' => 'E',  'message' => 'No CIA Marks','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                                
                                            }   
                                            else{
                                                $markEntry = new MarkEntry();
                                                $markEntry->student_map_id = $stu_map_id;
                                                $markEntry->subject_map_id = $sub_map_id['coe_subjects_mapping_id'];
                                                $markEntry->category_type_id = $get_cat_id;
                                                $markEntry->category_type_id_marks = $sub_marks;
                                                $markEntry->year = $exam_year;
                                                $markEntry->month = $month;
                                                $markEntry->term = $term;
                                                $markEntry->mark_type = $mark_type;
                                                $markEntry->created_at = $created_at;
                                                $markEntry->created_by = $created_by;
                                                $markEntry->updated_at = $created_at;
                                                $markEntry->updated_by = $created_by;
                                                
                                                $result_calc = ConfigUtilities::StudentResult($stu_map_id,$sub_map_id['coe_subjects_mapping_id'],$get_cia_marks->category_type_id_marks,$sub_marks,$exam_year,$month);

                                                $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);

                                                $cia_marks = $result_calc['attempt']>$config_attempt ? 0:$get_cia_marks->category_type_id_marks; 
                                                

                                                $year_of_passing_up = $result_calc["result"]=='Pass' && $result_calc["year_of_passing"]=='' ? $month.'-'.$exam_year : $result_calc["year_of_passing"];


                                                $MarkEntryMasterModel = new MarkEntryMaster();
                                                $MarkEntryMasterModel->student_map_id=$stu_map_id;
                                                $MarkEntryMasterModel->subject_map_id=$sub_map_id['coe_subjects_mapping_id'];
                                                $MarkEntryMasterModel->CIA=$cia_marks;
                                                $MarkEntryMasterModel->ESE=$result_calc["ese_marks"];
                                                $MarkEntryMasterModel->total=($result_calc['ese_marks']+$cia_marks);
                                                $MarkEntryMasterModel->result=$result_calc["result"];
                                                $MarkEntryMasterModel->grade_point=$result_calc['grade_point'];
                                                $MarkEntryMasterModel->grade_name=$result_calc['grade_name'];
                                                $MarkEntryMasterModel->year = $exam_year;
                                                $MarkEntryMasterModel->month = $month;
                                                $MarkEntryMasterModel->term = $term;
                                                $MarkEntryMasterModel->mark_type = $mark_type;
                                                $MarkEntryMasterModel->year_of_passing = $year_of_passing_up;
                                                $MarkEntryMasterModel->attempt=$result_calc["attempt"];
                                                $MarkEntryMasterModel->status_id=0;
                                                $MarkEntryMasterModel->created_by = $created_by;
                                                $MarkEntryMasterModel->created_at = $created_at;
                                                $MarkEntryMasterModel->updated_by = $created_by;
                                                $MarkEntryMasterModel->updated_at = $created_at;
                                                
                                                if($sub_marks >100)
                                                {
                                                    $dispResults[] = ['type' => 'E',  'message' => 'Marks Exceeds Maximum','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                                }
                                                else
                                                {

                                                    $check_stu_mark_entry = MarkEntry::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$sub_map_id['coe_subjects_mapping_id'],'year'=>$exam_year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type,'category_type_id'=>$get_cat_id])->all();

                                                    $check_stu_mark_entry_master = MarkEntryMaster::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$sub_map_id['coe_subjects_mapping_id'],'year'=>$exam_year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->all();
                                                    $execution = 0;
                                                    if(!empty($check_stu_mark_entry) && count($check_stu_mark_entry)>0)
                                                    {
                                                        $updated_at = date("Y-m-d H:i:s");
                                                        $updated_by = Yii::$app->user->getId();
                                                        $update_marks = "UPDATE coe_mark_entry SET category_type_id_marks='".$sub_marks."',updated_by='".$updated_by."',updated_at='".$updated_at."' WHERE student_map_id='".$stu_map_id."' AND subject_map_id='".$sub_map_id['coe_subjects_mapping_id']."' AND category_type_id='".$get_cat_id."' AND year='".$exam_year."' AND month='".$month."' and term='".$term."' AND mark_type='".$mark_type."'";

                                                        $update_result = Yii::$app->db->createCommand($update_marks)->execute();
                                                        $execution = 1;
                                                        unset($markEntry);
                                                        $markEntry = new MarkEntry();
                                                    }

                                                    if(!empty($check_stu_mark_entry_master) && count($check_stu_mark_entry_master)>0)
                                                    {
                                                        $updated_at = date("Y-m-d H:i:s");
                                                        $updated_by = Yii::$app->user->getId();
                                                        $update_master_marks = "UPDATE coe_mark_entry_master SET CIA='".$cia_marks."',ESE='".$result_calc["ese_marks"]."',total='".($result_calc['ese_marks']+$cia_marks)."',result='".$result_calc["result"]."',grade_point='".$result_calc['grade_point']."',grade_name='".$result_calc['grade_name']."',year_of_passing='".$result_calc["year_of_passing"]."',attempt='".$result_calc["attempt"]."',updated_by='".$updated_by."',updated_at='".$updated_at."' WHERE student_map_id='".$stu_map_id."' AND subject_map_id='".$sub_map_id['coe_subjects_mapping_id']."' AND year='".$exam_year."' AND month='".$month."' and term='".$term."' AND mark_type='".$mark_type."'";

                                                        $update_master_result = Yii::$app->db->createCommand($update_master_marks)->execute();
                                                        $execution = 2;

                                                        $dispResults[] = ['type' => 'S',  'message' => 'Updated The Marks','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                                        unset($MarkEntryMasterModel);
                                                        $MarkEntryMasterModel = new MarkEntryMaster();

                                                    }
                                                    if($execution==0)
                                                    {
                                                        $transaction = Yii::$app->db->beginTransaction();
                                                        if($markEntry->save(false) && $MarkEntryMasterModel->save(false))
                                                        {
                                                            $transaction->commit();
                                                            $dispResults[] = ['type' => 'S',  'message' => 'Success','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                                            $totalSuccess+=1;
                                                        }
                                                        else
                                                        {
                                                            $transaction->rollback(); 
                                                            $dispResults[] = ['type' => 'E',  'message' => 'Unknow Error','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                                        }
                                                    }
                                                    
                                                    
                                                }
                                                
                                            }                                  
                                            
                                            
                                        }
                                        else
                                        {

                                            $dispResults[] = ['type' => 'E',  'message' => 'Already Imported','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                        }
                                    }
                                    
                                }
                                else
                                {

                                    $dispResults[] = ['type' => 'E',  'message' => 'No Data Found','reg_num'=>$stu_reg_num,'sub_code'=>$subject_code];
                                }
                                

                            } // For Loop 2
                        } // For Loop 1 Closed Here 
                        $importResults = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess];

                        return $this->render('import-ese-marks', [
                            'model' => $model,
                            'markEntry' => $markEntry,
                            'student' => $student,
                            'importModel' => $importModel,
                            'importResults' => $importResults,
                        ]);
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error',"You Are trying to import Empty File");

                        unlink($save_in_folder);
                        return $this->render('import-ese-marks', [
                            'model' => $model,
                            'markEntry' => $markEntry,
                            'student' => $student,
                            'importModel' => $importModel,
                        ]);
                    }
                }
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Import the File");
                return $this->render('import-ese-marks', [
                    'model' => $model,
                    'markEntry' => $markEntry,
                    'student' => $student,
                    'importModel' => $importModel,
                ]); 
            }
        }
        else
        {
           Yii::$app->ShowFlashMessages->setMsg('Welcome',"Welcome to ESE Marks Import Section");
           return $this->render('import-ese-marks', [
                'model' => $model,
                'markEntry' => $markEntry,
                'student' => $student,
                'importModel' => $importModel,
            ]); 
        }
    }

    public function actionVerifyMarksArts()
    {
        $markEntry = new MarkEntry();
        Yii::$app->ShowFlashMessages->setMsg('Welcome',"Welcome to Marks Verification");
        return $this->render('verify-marks-arts', [
                'markEntry' => $markEntry,           
            ]);
    }
    public function actionVerifyMarksArtsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
        $content = $_SESSION['verify_marks_data'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 
                'filename' => 'MARKS VERIFICATION.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;   width:100%; font-size: 10px; } 
                        table tr{
                            border: 1px solid #CCC;
                        }
                        table td{
                            border: 1px solid #CCC;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            height: 20px;
                        }
                        table th{
                           border: 1px solid #CCC;
                             white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                        }
                    }   
                ', 
                'options' => ['title' => 'MARKS VERIFICATION'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['Marks Verification PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
                
            ]);
       
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render();
    }

    public function actionVerifyMarks()
    {
        $model = new MarkEntryMaster();
        Yii::$app->ShowFlashMessages->setMsg('Welcome',"Welcome to Marks Verification");
        return $this->render('verify-marks', [
                'model' => $model,           
            ]);
    }
    public function actionVerifyMarksPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
          $content = $_SESSION['verify_marks_data'];
         
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 
                'filename' => 'MARKS VERIFICATION.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;   width:100%; font-size: 10px; } 
                        table tr{
                            border: 1px solid #CCC;

                        }
                        table td{
                            border: 1px solid #CCC;
                            overflow: hidden;
                            text-align: center;
                            height: 20px;
                        }
                        table th{
                           border: 1px solid #CCC;
                            overflow: hidden;
                            text-align: center;
                           
                        }
                    }   
                ', 
                'options' => ['title' => 'MARKS VERIFICATION'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['Marks Verification PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
                
            ]);
       
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render();
    }
    public function actionVerification()
    {
        $exam_year = Yii::$app->request->post('exam_year');
        $exam_month = Yii::$app->request->post('exam_month');
        $get_id_details=['exam_year'=>$exam_year,'exam_month'=>$exam_month];
        $sub_id = Yii::$app->request->post('sub_code');
        $mark_type = Yii::$app->request->post('mark_type');
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $sub_map_ids = ConfigUtilities::getSubjectMappingIds($sub_id,$exam_year,$exam_month);
        $sub_details = Subjects::findOne($sub_id);
       
        $check_date = new Query();
        $check_date->select(['subject_code','subject_name','register_number','name','CIA','ESE','C.ESE as val','total','result'])
        ->from('coe_subjects a')
        ->join('JOIN','coe_subjects_mapping b','a.coe_subjects_id=b.subject_id')
        ->join('JOIN','coe_mark_entry_master c','c.subject_map_id=b.coe_subjects_mapping_id')
        
        ->join('JOIN','coe_mark_entry f','f.subject_map_id=c.subject_map_id AND f.student_map_id=c.student_map_id AND f.year=c.year AND f.month=c.month AND f.mark_type=c.mark_type and f.term=c.term')
        ->join('JOIN','coe_student_mapping d','d.coe_student_mapping_id=c.student_map_id')
        ->join('JOIN','coe_student e','e.coe_student_id=d.student_rel_id')
        ->where(["c.year"=>$exam_year,"c.month"=>$exam_month,'c.mark_type'=>$_POST['mark_type']])
        ->andWhere(['IN','c.subject_map_id',$sub_map_ids])
        ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
        ->groupBy('c.student_map_id')->orderBy('register_number');
        $get_subjects = $check_date->createCommand()->queryAll();
        if(!empty($get_subjects))
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $header  ='<table width="100%"><tbody align="center">';                 
            $header.='<tr>
                        <td colspan=11 align="center"> 
                            <center> <h3>'.$org_name.'</h3></center>
                            <center><h5>'.$org_address.'</h5></center>
                            <center><h5>'.$org_tagline.'</h5></center> 
                        </td>                       
                    </tr>
                    ';
                    $header.='<tr><td colspan="11" align="center"><h5>EXAMINATION HELD ON '.$exam_year.' '.ConfigUtilities::getCateName($exam_month).'</h5></td></tr>';
                    $header.='<tr><td colspan="11" align="left"><h5><b> '.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' CODE : '.$sub_details->subject_code).'</b></h5></td></tr>';
                    $header.='<tr><td colspan="11" align="left"><h5> <b>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' NAME : '.$sub_details->subject_name).'</b></h5></td></tr>';

                    $header.='<tr>
                                <th align="left">S.No</th>  
                                
                                <th align="left" colspan=3>Register Number</th>                                
                                <th align="left" colspan=3>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Name</th>                                
                                <th align="left">CIA</th>
                                <th align="left">ESE</th>
                                <th align="left">TOTAL</th>
                                <th align="left">RESULT</th>
                            </tr>';
                    
                   
                    $sn=1;
                    $full_table = $data = '';
                    foreach($get_subjects as $revaluation1)
                    {

                        if($sn%42==0)
                        {
                            $full_table .= $header.$data."</tbody></table><pagebreak />";
                            $data = '';
                        }

                        $data.='<tr>';
                        $data.='<td align="left" >'.$sn.'</td>';
                        
                        $data.='<td colspan=3 align="left">'.$revaluation1['register_number'].'</td>';
                        $data.='<td colspan=3 align="left">'.$revaluation1['name'].'</td>';

                        $data.='<td align="left">'.$revaluation1['CIA'].'</td>';
                        $data.='<td align="left">'.$revaluation1['ESE'].'</td>
                                <td align="left" >'.$revaluation1['total'].'</td>
                                <td align="left" >'.$revaluation1['result'].'</td>
                        </tr>';
                        $sn++;
                    }
                    $table_close = $full_table.$header.$data."</tbody></table>";

            if(isset($_SESSION['verify_marks_data'])){ unset($_SESSION['verify_marks_data']);}
            $_SESSION['verify_marks_data'] = $table_close;
            
            $data1 = '<div class="box box-primary">
                        <div class="box-body">
                            <div class="row" >';
            $data1 .= '<div class="col-xs-12">';
            $data1 .= Html::a('<i class="fa fa-file-pdf-o"></i> ' . "PDF",array('verify-marks-pdf','exportPDF'=>'PDF'),array('title'=>'Export to PDF','target'=>'_blank','class'=>'pull-right btn btn-warning', 'style'=>'color:#fff'));
            
            $data1 .= '<div class="col-xs-12" >'.$table_close.'</div>
                            </div>
                        </div>
                      </div>';

            return $data1;
        }
        else
        {            
            return 0;
        }
       
    }

	public function actionVerificationarts()
    {
        $exam_year = Yii::$app->request->post('year');
        $exam_month = Yii::$app->request->post('month');
        $get_id_details=['exam_year'=>$exam_year,'exam_month'=>$exam_month];
        $sub_id = Yii::$app->request->post('sub_code');
        $batch_mapping_id = Yii::$app->request->post('batch_mapping_id');
        $mark_type = Yii::$app->request->post('mark_type');

        $arrear_dat = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%arrear%'")->queryScalar();

        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $sub_map_ids = ConfigUtilities::getSubjectMappingIds($sub_id,$exam_year,$exam_month);
        $sub_details = Subjects::findOne($sub_id);
        $check_date = new Query();
        $check_date->select(['subject_code','subject_name','UPPER(register_number) as register_number','name','CIA','ESE','total','result'])
        ->from('coe_subjects a')
        ->join('JOIN','coe_subjects_mapping b','a.coe_subjects_id=b.subject_id')
        ->join('JOIN','coe_mark_entry_master c','c.subject_map_id=b.coe_subjects_mapping_id')
        
        ->join('JOIN','coe_mark_entry f','f.subject_map_id=c.subject_map_id AND f.student_map_id=c.student_map_id AND f.year=c.year AND f.month=c.month AND f.mark_type=c.mark_type and f.term=c.term')
        ->join('JOIN','coe_student_mapping d','d.coe_student_mapping_id=c.student_map_id')
        ->join('JOIN','coe_student e','e.coe_student_id=d.student_rel_id')
        ->where(["c.year"=>$exam_year,"c.month"=>$exam_month,'c.mark_type'=>$_POST['mark_type'],'b.batch_mapping_id'=>$_POST['batch_mapping_id'],'d.course_batch_mapping_id'=>$batch_mapping_id])
        ->andWhere(['IN','c.subject_map_id',$sub_map_ids]);
        if($mark_type==$arrear_dat)
        {
            $check_date->andWhere(['<>', 'status_category_type_id', $det_cat_type]);
        }
        $check_date->andWhere(['<>', 'status_category_type_id', $det_disc_type])
        ->groupBy('c.student_map_id')->orderBy('register_number');
        $get_subjects = $check_date->createCommand()->queryAll();
        if(!empty($get_subjects))
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $header  ='<table width="100%"><tbody align="center">';                 
            $header.='<tr>
                        <td colspan=11 align="center"> 
                            <center> <h3>'.$org_name.'</h3></center>
                            <center><h5>'.$org_tagline.'</h5></center> 
                        </td>                       
                    </tr>
                    ';
                    $header.='<tr><td colspan="12" align="center"><h5>EXAMINATION HELD ON '.$exam_year.' '.ConfigUtilities::getCateName($exam_month).'</h5></td></tr>';
                    $header.='<tr><td colspan="12" align="left"><h5><b> '.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' CODE : '.$sub_details->subject_code).'</b></h5></td></tr>';
                    $header.='<tr><td colspan="12" align="left"><h5> <b>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' NAME : '.$sub_details->subject_name).'</b></h5></td></tr>';

                    $header.='<tr>
                                <th align="left">S.No</th>  
                                
                                <th align="left" colspan=3>Register Number</th>                                
                                <th align="left" colspan=3>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Name</th>                                
                                <th align="left">CIA</th>
                                <th align="left">ESE</th>
                                <th align="left">TOTAL</th>
                                <th align="left">RESULT</th>
                            </tr>';
                    
                   
                    $sn=1;
                    $full_table = $data = '';
                    foreach($get_subjects as $revaluation1)
                    {

                        if($sn%42==0)
                        {
                            $full_table .= $header.$data."</tbody></table><pagebreak />";
                            $data = '';
                        }

                        $data.='<tr>';
                        $data.='<td align="left" >'.$sn.'</td>';
                        $data.='<td colspan=3 align="left">'.$revaluation1['register_number'].'</td>';
                        $data.='<td colspan=3 align="left">'.$revaluation1['name'].'</td>';

                        $data.='<td align="left">'.$revaluation1['CIA'].'</td>';
                        $data.='<td align="left">'.$revaluation1['ESE'].'</td>
                                <td align="left" >'.$revaluation1['total'].'</td>
                                <td align="left" >'.$revaluation1['result'].'</td>
                        </tr>';
                        $sn++;
                    }
                    $table_close = $full_table.$header.$data."</tbody></table>";

            if(isset($_SESSION['verify_marks_data'])){ unset($_SESSION['verify_marks_data']);}
            $_SESSION['verify_marks_data'] = $table_close;
            
            $data1 = '<div class="box box-primary">
                        <div class="box-body">
                            <div class="row" >';
            $data1 .= '<div class="col-xs-12">';
            $data1 .= Html::a('<i class="fa fa-file-pdf-o"></i> ' . "PDF",array('verify-marks-pdf','exportPDF'=>'PDF'),array('title'=>'Export to PDF','target'=>'_blank','class'=>'pull-right btn btn-warning', 'style'=>'color:#fff'));
            
            $data1 .= '<div class="col-xs-12" >'.$table_close.'</div>
                            </div>
                        </div>
                      </div>';

            return $data1;
        }
        else
        {            
            return 0;
        }
       
    }


    public function actionWithdrawalReports()
    {
        $model = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           
            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,batch_name,A.year,I.description as month,A.student_map_id,A.subject_map_id,semester FROM coe_mark_entry_master as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id 
                JOIN coe_student as stu ON stu.coe_student_id=C.student_rel_id 
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_category_type as I ON I.coe_category_type_id=A.month
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                where (grade_name like '%WD%' OR withdraw='wd' )  and A.year<='".$_POST['MarkEntryMaster']['year']."' and status_category_type_id NOT IN('".$det_disc_type."')  group by A.subject_map_id,A.student_map_id ,A.year,A.month order by batch_name,Degree,B.semester,subject_code";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {
              return $this->render('withdrawal-reports', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['withdrawal-reports']);
            }
            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME','Welcome to Withdrawal Reports');
            return $this->render('withdrawal-reports', [
                'model' => $model,           
            ]);
        }
    }

    public function actionWithheldReports()
    {
        $model = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = DATE('Y');
            $omit_batches = $year-$omit_batch;
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           
            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,batch_name,A.year,A.month,A.student_map_id,A.subject_map_id,B.semester FROM coe_mark_entry_master as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id 
                JOIN coe_student as stu ON stu.coe_student_id=C.student_rel_id 
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                where grade_name like '%WH%' and withheld='w' and A.year='".$_POST['MarkEntryMaster']['year']."' and A.month='".$_POST['month']."'  and status_category_type_id NOT IN('".$det_disc_type."')  group by A.subject_map_id,A.student_map_id ,A.year,A.month order by batch_name,Degree,semester,subject_code";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {

              return $this->render('withheld-reports', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['withheld-reports']);
            }
            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME','Welcome to Withheld Reports');
            return $this->render('withheld-reports', [
                'model' => $model,           
            ]);
        }
    }

    public function actionAbsentReports()
    {
        $model = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           
            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,batch_name,A.year,A.month,A.student_map_id,A.subject_map_id,B.semester FROM coe_mark_entry_master as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id 
                JOIN coe_student as stu ON stu.coe_student_id=C.student_rel_id 
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                where A.result like '%Absent%' and A.year='".$_POST['MarkEntryMaster']['year']."' group by A.subject_map_id,A.student_map_id ,A.year,A.month order by batch_name,Degree,semester,subject_code";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {

              return $this->render('absent-reports', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['absent-reports']);
            }
            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME','Welcome to Withheld Reports');
            return $this->render('absent-reports', [
                'model' => $model,           
            ]);
        }
    }

    public function actionModerationReports()
    {
        $model = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = DATE('Y');
            $omit_batches = $year-$omit_batch;
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $moderation = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%moderation%'")->queryScalar();
           
            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,batch_name,A.year,A.month,A.student_map_id,A.subject_map_id,B.semester,A.category_type_id_marks FROM coe_mark_entry as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id 
                JOIN coe_student as stu ON stu.coe_student_id=C.student_rel_id 
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                where A.category_type_id='".$moderation."' and A.year='".$_POST['MarkEntryMaster']['year']."' and A.month='".$_POST['month']."' and status_category_type_id NOT IN('".$det_disc_type."')  group by A.subject_map_id,A.student_map_id ,A.year,A.month order by batch_name,Degree,semester,subject_code";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {

              return $this->render('moderation-reports', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['moderation-reports']);
            }
            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME','Welcome to Moderation Reports');
            return $this->render('moderation-reports', [
                'model' => $model,           
            ]);
        }
    }
    public function actionRevaluationReports()
    {
        $model = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $revaluation = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type='Revaluation' ")->queryScalar();
           
            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,batch_name,A.year,A.month,A.student_map_id,A.subject_map_id,B.semester,A.category_type_id_marks FROM coe_mark_entry as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id 
                JOIN coe_student as stu ON stu.coe_student_id=C.student_rel_id 
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                where A.category_type_id='".$revaluation."' and A.year='".$_POST['MarkEntryMaster']['year']."' and A.month='".$_POST['month']."' and status_category_type_id NOT IN('".$det_disc_type."')  group by A.subject_map_id,A.student_map_id ,A.year,A.month order by batch_name,Degree,semester,subject_code";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {

              return $this->render('revaluation-reports', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['revaluation-reports']);
            }
            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME','Welcome to Revaluation Reports');
            return $this->render('revaluation-reports', [
                'model' => $model,           
            ]);
        }
    }

    public function actionBorderlineMarks()
    {
        $model = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $check_date = new Query();
            $check_date->select(['DISTINCT `b`.`coe_subjects_mapping_id` AS subject_map_id'])
            ->from('coe_subjects a')
            ->join('JOIN','coe_subjects_mapping b','a.coe_subjects_id=b.subject_id')
            ->join('JOIN','coe_mark_entry_master c','c.subject_map_id=b.coe_subjects_mapping_id')
            ->where(["c.year"=>$_POST['MarkEntryMaster']['year'],"c.month"=>$_POST['month']]);
            $get_subjects = $check_date->createCommand()->queryAll();

            if(!empty($get_subjects))
            {

                $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,count(student_map_id) as count,A.subject_map_id,B.batch_mapping_id,A.year,A.month,".$_POST['MarkEntryMaster']['result']." as borderLine,D.ESE_min FROM coe_mark_entry_master as A 
                    JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                    JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id 
                    JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                    JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                    JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                    JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                    JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                    where year_of_passing='' and A.ESE >= (D.ESE_min-".$_POST['MarkEntryMaster']['result'].")  and A.ESE < D.ESE_min and A.total >= (D.total_minimum_pass-".$_POST['MarkEntryMaster']['result'].")  and A.total < D.total_minimum_pass  and A.year='".$_POST['MarkEntryMaster']['year']."' AND result like '%Fail%' and status_category_type_id NOT IN('".$det_disc_type."') and mark_type=27 AND A.month='".$_POST['month']."' group by A.subject_map_id,A.year,A.month order by Degree";

                $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

                if(!empty($fetched_data))    
                {

                  return $this->render('borderline-marks', [
                    'model' => $model,
                    'fetched_data' => $fetched_data,
                    ]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                    return $this->redirect(['borderline-marks']);
                }

            
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['borderline-marks']);
            }

            
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME','Welcome to Border Line Marks For ESE Subjects');
            return $this->render('borderline-marks', [
                'model' => $model,           
            ]);
        }
    }
    public function actionBorderLineMarksCoursewise()
    {
        $model = new MarkEntryMaster();
         
          $year = $_SESSION['border_year'];
          $month = $_SESSION['border_month'];
         $border = $_SESSION['border_marks'];
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $check_date = new Query();
        $check_date->select(['DISTINCT `b`.`coe_subjects_mapping_id` AS subject_map_id'])
        ->from('coe_subjects a')
        ->join('JOIN','coe_subjects_mapping b','a.coe_subjects_id=b.subject_id')
        ->join('JOIN','coe_mark_entry_master c','c.subject_map_id=b.coe_subjects_mapping_id')
        ->where(["c.year"=>$year,"c.month"=>$month]);
        $get_subjects = $check_date->createCommand()->queryAll();
       
        if(!empty($get_subjects))
        {

            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,A.subject_map_id,B.batch_mapping_id,A.year,A.month,".$border." as borderLine,D.ESE_min,A.ESE,A.CIA,A.total,batch_name FROM coe_mark_entry_master as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id and C.course_batch_mapping_id=B.batch_mapping_id and C.course_batch_mapping_id=E.coe_bat_deg_reg_id
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                JOIN coe_student as J ON J.coe_student_id=C.student_rel_id
                where year_of_passing='' and A.ESE >= (D.ESE_min-".$border.")  and A.ESE < D.ESE_min and A.year='".$year."' AND result like '%Fail%' and status_category_type_id NOT IN('".$det_disc_type."') and A.total >= (D.total_minimum_pass-".$border.")  and A.total < D.total_minimum_pass and mark_type=27  AND A.month='".$month."' group by A.student_map_id,A.subject_map_id,A.year,A.month order by batch_name, Degree,register_number";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {
                $month_name = Categorytype::findOne($month);
               require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $header ='<table style="overflow-x:auto;"  cellspacing="0" cellpadding="0" border="0"  class="table table-bordered table-responsive bulk_edit_table table-hover" id = "student_import_results">';
                $header .= ' <tr>
                    
                      <td align="center"> 
                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                      </td>

                      <td colspan=8  align="center"> 
                          <center><b><font size="6px">'.$org_name.'</font></b></center>
                          <center> <font size="3px">'.$org_address.'</font></center>
                          <center> Phone : <b>'.$org_phone.'</b></center>
                          <center class="tag_line"><b>'.$org_tagline.'</b></center> 
                     </td>
                      <td align="center">  
                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                      </td>
                    </tr>';
                $header .=' 
                        <tr><td colspan=10 ><h2>'.$border.' BORDER LINE MARKS FOR THE YEAR '.$year.' '.strtoupper($month_name->description).'</h2></td> </tr>
                    <tr>
                        <th>SNO</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH)).'</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE)).'</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE").'</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." NAME").'</th>
                        <th>REGISTER NUMBER</th>
                        <th>CIA</th>
                        <th>ESE</th>
                        <th>TOTAL</th>
                        <th>ESE MIN</th></tr>';
                $sno=1;
                
                foreach ($fetched_data as $values) 
                {
                    $header .='<tr>
                           <td>'.$sno.'</td>
                           <td>'.$values["batch_name"].'</td>
                           <td>'.$values["Degree"].'</td>
                           <td>'.$values["subject_code"].'</td>
                           <td>'.$values["subject_name"].'</td>
                           <td>'.$values["register_number"].'</td>
                           <td>'.$values["CIA"].'</td>
                           <td>'.$values["ESE"].'</td>
                           <td>'.$values["total"].'</td>
                           <td>'.$values["ESE_min"].'</td>
                           </tr>';
                           $sno++;
                }
                $header .='</table>';
                $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DUMMY).' MARK.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $header,                     
                    'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; } 
                        
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
                    'options' => ['title' => $border.' BORDER LINE MARKS FOR THE YEAR '.$year.' '.$month_name->description],
                    'methods' => [ 
                        'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                        'SetFooter'=>['BORDER LINE MARKS '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
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
              return $this->render('borderline-marks', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['borderline-marks']);
            }

        
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
            return $this->redirect(['borderline-marks']);
        }

        
    }
    public function actionBorderLineMarksCoursewiseExcel()
    {
        $model = new MarkEntryMaster();
         
          $year = $_SESSION['border_year'];
          $month = $_SESSION['border_month'];
         $border = $_SESSION['border_marks'];
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $check_date = new Query();
        $check_date->select(['DISTINCT `b`.`coe_subjects_mapping_id` AS subject_map_id'])
        ->from('coe_subjects a')
        ->join('JOIN','coe_subjects_mapping b','a.coe_subjects_id=b.subject_id')
        ->join('JOIN','coe_mark_entry_master c','c.subject_map_id=b.coe_subjects_mapping_id')
        ->where(["c.year"=>$year,"c.month"=>$month]);
        $get_subjects = $check_date->createCommand()->queryAll();
       
        if(!empty($get_subjects))
        {

            $fetch_data = "SELECT CONCAT(F.degree_code,'-',G.programme_code) as Degree,D.subject_code,subject_name,register_number,A.subject_map_id,B.batch_mapping_id,A.year,A.month,".$border." as borderLine,D.ESE_min,A.ESE,A.CIA,A.total,batch_name FROM coe_mark_entry_master as A 
                JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id = A.subject_map_id 
                JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id
                JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id and C.course_batch_mapping_id=B.batch_mapping_id and C.course_batch_mapping_id=E.coe_bat_deg_reg_id
                JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id 
                
                JOIN coe_degree as F ON F.coe_degree_id=E.coe_degree_id
                JOIN coe_programme as G ON G.coe_programme_id=E.coe_programme_id
                JOIN coe_batch as H ON H.coe_batch_id=E.coe_batch_id
                JOIN coe_student as J ON J.coe_student_id=C.student_rel_id
                where year_of_passing='' and A.ESE >= (D.ESE_min-".$border.")  and A.ESE < D.ESE_min and A.year='".$year."' AND result like '%Fail%' and status_category_type_id NOT IN('".$det_disc_type."') and A.total >= (D.total_minimum_pass-".$border.")  and A.total < D.total_minimum_pass and mark_type=27  AND A.month='".$month."' group by A.student_map_id,A.subject_map_id,A.year,A.month order by batch_name, Degree,register_number";

            $fetched_data = Yii::$app->db->createCommand($fetch_data)->queryAll();

            if(!empty($fetched_data))    
            {
                $month_name = Categorytype::findOne($month);
               require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $header ='<table style="overflow-x:auto;"  cellspacing="0" cellpadding="0" border="0"  class="table table-bordered table-responsive bulk_edit_table table-hover" id = "student_import_results">';
                $header .= ' <tr>
                    
                      <td align="center"> 
                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                      </td>

                      <td colspan=8  align="center"> 
                          <center><b><font size="6px">'.$org_name.'</font></b></center>
                          <center> <font size="3px">'.$org_address.'</font></center>
                          <center> Phone : <b>'.$org_phone.'</b></center>
                          <center class="tag_line"><b>'.$org_tagline.'</b></center> 
                     </td>
                      <td align="center">  
                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                      </td>
                    </tr>';
                $header .=' 
                        <tr><td colspan=10 ><h2>'.$border.' BORDER LINE MARKS FOR THE YEAR '.$year.' '.strtoupper($month_name->description).'</h2></td> </tr>
                    <tr>
                        <th>SNO</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH)).'</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE)).'</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE").'</th>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." NAME").'</th>
                        <th>REGISTER NUMBER</th>
                        <th>CIA</th>
                        <th>ESE</th>
                        <th>TOTAL</th>
                        <th>ESE MIN</th></tr>';
                $sno=1;
                
                foreach ($fetched_data as $values) 
                {
                    $header .='<tr>
                           <td>'.$sno.'</td>
                           <td>'.$values["batch_name"].'</td>
                           <td>'.$values["Degree"].'</td>
                           <td>'.$values["subject_code"].'</td>
                           <td>'.$values["subject_name"].'</td>
                           <td>'.$values["register_number"].'</td>
                           <td>'.$values["CIA"].'</td>
                           <td>'.$values["ESE"].'</td>
                           <td>'.$values["total"].'</td>
                           <td>'.$values["ESE_min"].'</td>
                           </tr>';
                           $sno++;
                }
                $header .='</table>';
                
               $fileName = 'Border Line Degree Wise Application' . date('Y-m-d-H-i-s') . '.xls';
                $options = ['mimeType' => 'application/vnd.ms-excel'];
                return Yii::$app->excel->exportExcel($header, $fileName, $options);
              return $this->render('borderline-marks', [
                'model' => $model,
                'fetched_data' => $fetched_data,
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['borderline-marks']);
            }

        
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
            return $this->redirect(['borderline-marks']);
        }

        
    }
    public function actionModerationReportsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
          $content = $_SESSION['moderation_report_sem'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => 'MODERATION REPORTS.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssInline' => ' @media all{
                        table{font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ', 
                'options' => ['title' => 'MODERATION REPORTS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['MODERATION REPORTS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }
    public function actionWithdrawalReportsExcel()
    {        
        $content = $_SESSION['withdrawa_report_sem'];
        $fileName ='Withdrawal Reports.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionModerationReportsExcel()
    {        
        
        $content = $_SESSION['moderation_report_sem'];
        
        $fileName ='Moderation Reports.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionAbsentReportsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
          $content = $_SESSION['Absent_report_sem'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => 'ABSENT REPORTS.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssInline' => ' @media all{
                        table{font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ', 
                'options' => ['title' => 'ABSENT REPORTS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['ABSENT REPORTS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }

    public function actionAbsentReportsExcel()
    {        
        
        $content = $_SESSION['Absent_report_sem'];
        
        $fileName ='Absent Reports.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionRevaluationReportsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
          $content = $_SESSION['REVALUATION_report_sem'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => 'Revaluation REPORTS.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssInline' => ' @media all{
                        table{font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ', 
                'options' => ['title' => 'Revaluation REPORTS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['Revaluation REPORTS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }

    public function actionRevaluationReportsExcel()
    {        
        
        $content = $_SESSION['REVALUATION_report_sem'];
        
        $fileName ='Revaluation Reports.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionWithheldReportsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
          $content = $_SESSION['withheld_report_sem'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => 'WITHHELD REPORTS.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssInline' => ' @media all{
                        table{font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ', 
                'options' => ['title' => 'WITHHELD REPORTS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['WITHHELD REPORTS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }

    public function actionWithheldReportsExcel()
    {        
        
        $content = $_SESSION['withheld_report_sem'];
        
        $fileName ='Withheld Reports.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionWithdrawalReportsPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
          $content = $_SESSION['withdrawa_report_sem'];
        $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 
                'filename' => 'WITHDRAWAL REPORTS.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssInline' => ' @media all{
                        table{font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ', 
                'options' => ['title' => 'WITHDRAWAL REPORTS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['WITHDRAWAL REPORTS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }

    public function actionWithdrawalsReportsExcel()
    {     
        $content = $_SESSION['withdrawa_report_sem'];
        $fileName ='Withdrawal.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionBorderLineMarksPdf()
    {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
          $content = $_SESSION['borderlinemarks'];
         

            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => 'BORDER LINE MARKS.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssInline' => ' @media all{
                        table{font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ', 
                'options' => ['title' => ' BORDER LINE MARKS'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['BORDER LINE MARKS PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
        $pdf->marginTop="5";
        $pdf->marginLeft="10";
        $pdf->marginRight="5";
        $pdf->marginBottom="5";
        $pdf->marginHeader="3";
        $pdf->marginFooter="3";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }

    public function actionExcelBorderLineMarks(){
        
        $content = $_SESSION['borderlinemarks'];
         
        $fileName ='borderlinemarks.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionExportCiaMarks()
    {
        $model = new MarkEntryMaster();
        $markEntry = new MarkEntry();
        $student = new Student();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (Yii::$app->request->post()) 
        {
            
            $exam_year = $_POST['MarkEntry']['year'];
            $batch = $_POST['bat_val'];
            $bat_map_val = $_POST['bat_map_val'];
            $exam_semester = $_POST['exam_semester'];            
            $month = $_POST['month'];
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            if(empty($exam_year) || empty($bat_map_val) || empty($exam_semester) || empty($month))
            {
                Yii::$app->ShowFlashMessages->setMsg('ERROR','NO DATA FOUND  ');
                return $this->redirect(['export-cia-marks']);
            }
            $batch = Batch::findOne($batch);
            $batch_mapping = CoeBatDegReg::findOne($bat_map_val);
            $degree = Degree::findOne($batch_mapping->coe_degree_id);
            $programme = Programme::findOne($batch_mapping->coe_programme_id);
            $elective_id_get = Categorytype::find()->where(['description'=>'Elective'])->orWhere(['category_type'=>'Elective'])->one();
            
            $get_sub_details = new Query();
            $get_sub_details->select('b.coe_subjects_mapping_id')
            ->from('coe_subjects a')
            ->join('JOIN','coe_subjects_mapping b','b.subject_id=a.coe_subjects_id')
            ->where(["b.batch_mapping_id"=>$bat_map_val,"b.semester"=>$exam_semester])
            ->andWhere(['NOT IN','b.subject_type_id',$elective_id_get['coe_category_type_id']]);
            $get_sub_result_co = $get_sub_details->createCommand()->queryAll();

            $get_sub_details_no = new Query();
            $get_sub_details_no->select('b.coe_subjects_mapping_id')
            ->from('coe_subjects a')
            ->join('JOIN','coe_subjects_mapping b','b.subject_id=a.coe_subjects_id')
            ->join('JOIN','coe_nominal c','c.coe_subjects_id=a.coe_subjects_id')
            ->where(["b.batch_mapping_id"=>$bat_map_val,"b.semester"=>$exam_semester,'c.course_batch_mapping_id'=>$bat_map_val,'c.semester'=>$exam_semester])
            ->andWhere(['IN','b.subject_type_id',$elective_id_get['coe_category_type_id']])
            ->groupBy('coe_subjects_mapping_id');
            $get_sub_result_no = $get_sub_details_no->createCommand()->queryAll();           
            $get_sub_result = !empty($get_sub_result_no) ? array_merge($get_sub_result_no,$get_sub_result_co) : $get_sub_result_co;            

            if(count($get_sub_result)>0)
            {
                foreach ($get_sub_result as $value) {
                    $sub_codes[$value['coe_subjects_mapping_id']]=$value['coe_subjects_mapping_id'];
                }
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','NO DATA FOUND '.$degree->degree_code." ".$programme->programme_code.' ');
                return $this->redirect(['export-cia-marks']);
            }            
            $get_cat_id = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE (category_type like "%Internal Final%" OR category_type like "%CIA%")')->queryScalar();
            
            $check_date = new Query();
            $check_date->select('*')
            ->from('coe_mark_entry a')
            ->where(["a.subject_map_id"=>$sub_codes,'a.category_type_id'=>$get_cat_id]);
            $get_result = $check_date->createCommand()->queryAll();

            if(!empty($get_result) && count($get_result)>0)
            {
                Yii::$app->ShowFlashMessages->setMsg('error','CIA MARKS ALREADY IMPORTED FOR '.$degree->degree_code." ".$programme->programme_code.' ');
                return $this->redirect(['export-cia-marks']);
            }

            $stu_map_query = new Query();
            $stu_map_query->select(['DISTINCT `b`.`register_number` AS register_number','b.name'])
            ->from('coe_student_mapping a')
            ->join('JOIN','coe_student b','a.student_rel_id=b.coe_student_id')
            ->join('JOIN','coe_category_type c','c.coe_category_type_id=a.status_category_type_id')
            ->where(["a.course_batch_mapping_id"=>$bat_map_val,'b.student_status'=>'Active'])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->groupBy('b.register_number')
            ->orderBy('b.register_number');;
            $student_data = $stu_map_query->createCommand()->queryAll();

            $subject_query = new Query();
            $subject_query->select(['b.subject_code','b.subject_name','CIA_max'])
            ->from('coe_subjects_mapping a')
            ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
            ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$exam_semester])
            ->andWhere(['NOT IN','a.subject_type_id',$elective_id_get['coe_category_type_id']])
            ->andWhere(['<>','CIA_max','0'])
            ->andWhere(['<>','ESE_max','0'])
            ->groupBy('b.subject_code')
            ->orderBy('b.subject_code');
            $subjects_data_co = $subject_query->createCommand()->queryAll();

            if($org_email=='info@skct.edu.in')
            {
                $subject_query_12 = new Query();
                $subject_query_12->select(['b.subject_code','b.subject_name','CIA_max'])
                ->from('coe_subjects_mapping a')
                ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
                ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$exam_semester])
                ->andWhere(['NOT IN','a.subject_type_id',$elective_id_get['coe_category_type_id']])
                ->andWhere(['=','CIA_max','100'])
                ->groupBy('b.subject_code')
                ->orderBy('b.subject_code');
                $subjects_data_co_12 = $subject_query_12->createCommand()->queryAll();
                if(!empty($subjects_data_co_12))
                {
                    $subjects_data_co = array_merge($subjects_data_co,$subjects_data_co_12);
                }
            }

            $subject_query_no = new Query();
            $subject_query_no->select(['b.subject_code','b.subject_name','CIA_max'])
            ->from('coe_subjects_mapping a')
            ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
            ->join('JOIN','coe_nominal c','c.coe_subjects_id=b.coe_subjects_id AND c.course_batch_mapping_id=a.batch_mapping_id')
            ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$exam_semester,"c.course_batch_mapping_id"=>$bat_map_val,'c.semester'=>$exam_semester])
            ->andWhere(['<>','CIA_max','0'])
            ->andWhere(['<>','ESE_max','0'])
            ->groupBy('b.subject_code')
            ->orderBy('b.subject_code');
            $subjects_data_no = $subject_query_no->createCommand()->queryAll();
            
            if($org_email=='info@skct.edu.in')
            {
                $subject_query_no1 = new Query();
                $subject_query_no1->select(['b.subject_code','b.subject_name','CIA_max'])
                ->from('coe_subjects_mapping a')
                ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
                ->join('JOIN','coe_nominal c','c.coe_subjects_id=b.coe_subjects_id AND c.course_batch_mapping_id=a.batch_mapping_id')
                ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$exam_semester,"c.course_batch_mapping_id"=>$bat_map_val,'c.semester'=>$exam_semester])
                ->andWhere(['=','CIA_max','100'])
                ->groupBy('b.subject_code')
                ->orderBy('b.subject_code');
                $subjects_data_no1 = $subject_query_no1->createCommand()->queryAll();
                if(!empty($subjects_data_no1))
                {
                    $subjects_data_no = array_merge($subjects_data_no,$subjects_data_no1);
                }
            }

            $subjects_data = !empty($subjects_data_no) ? array_merge($subjects_data_no,$subjects_data_co) : $subjects_data_co; 

            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            // Excel Generation Starts Her 
            if(!empty($subjects_data) && !empty($student_data) && $file_content_available=="Yes")
            {
                
                // Count = Number of Subjects +  Column Headings
                $count = count($subjects_data)+4;
                $styleArray = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => '000000'),
                    'size'  => 15,
                    'name'  => 'Century Gothic'                    
                 ),
                 'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                 )   
                );   
                $styleArray_1 = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => '000000'),
                    'size'  => 12, 
                    'name'  => 'Century Gothic'                    
                 ),
                 'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                 )   
                );  
                $styleArray_2 = array(
                'font'  => array(                   
                    'color' => array('rgb' => '000000'),
                    'size'  => 13,
                    'name'  => 'Century Gothic'                     
                 ),
                 'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                 )   
                );               
                // Excel Sheet Properties Settings 
                $objPHPExcel = new PHPExcel();
                $objPHPExcel->getProperties()->setCreator("Sri Krishna I-Tech Management Solutions Pvt Ltd")
                             ->setLastModifiedBy($org_name)
                             ->setTitle("Office 2007 XLSX ".$degree->degree_code.$programme->programme_code.$exam_year." Cia Marks")
                             ->setSubject("Office 2007 XLSX ".$degree->degree_code.$programme->programme_code.$exam_year." Marks Importing")
                             ->setDescription($degree->degree_code.$programme->programme_code.$exam_year." Cia Marks Import for Office 2007 XLSX")
                             ->setKeywords("Cia Marks Import")
                             ->setCategory("Marks Import File");
                $objPHPExcel->setActiveSheetIndex(0);                
                $sheet = $objPHPExcel->getSheet();
                $objSheet = $objPHPExcel->getActiveSheet();

                $objSheet->getProtection()->setSheet(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setSheet(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setSort(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setInsertRows(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setFormatCells(true);
                $objPHPExcel->getSecurity()->setLockWindows(true);
                $objPHPExcel->getSecurity()->setLockStructure(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setPassword(Yii::$app->params['password_crack']);
                // Set NAME FOR EXCEL SHEET
                $sheet->setTitle($degree->degree_code."-".$programme->programme_code." CIA MARKS");

                $firstColumn = $sheet->getHighestColumn();
                $row = 1;
                
                // $column = 65 is Character of A
                $char_change = chr(65+$count);
                if($count>22)
                {
                    $count_diff = ($count-26>0)?($count-26):(26-$count);
                    $end_char = chr(65);
                    $end_cahr1 = chr(65+$count_diff);
                    $char_change = $end_char.$end_cahr1;
                }
                $cell = $sheet->getCell($char_change.$row); 

                $lastColumn = $sheet->getHighestColumn();

                $middle_column = chr(($count/2)+65); // 65 is CHAR A
                $next_middle_column = chr(($count/2)+66); // 65 is CHAR A
              
                $objSheet->mergeCells($firstColumn.'1:'.$lastColumn.'1')->getStyle($firstColumn.'1:'.$lastColumn.'1')->applyFromArray($styleArray);

                $objSheet->protectCells($firstColumn.'1:'.$lastColumn.'1','SkItechl2018@Passo');
                $objSheet->setCellValue($firstColumn.'1',strtoupper($org_name))->getStyle($firstColumn.'1')->applyFromArray($styleArray);
                
                $objSheet->mergeCells($firstColumn.'2:'.$lastColumn.'2')->protectCells($firstColumn.'2:'.$lastColumn.'2','SkItechl2018@Passo')->getStyle($firstColumn.'2:'.$lastColumn.'2')->applyFromArray($styleArray);
                $objSheet->setCellValue($firstColumn.'2',"RECORD OF CONSOLIDATED CONTINUOUS ASSESSMENT MARKS ");
                $objSheet->mergeCells($firstColumn.'3:'.$middle_column.'3')->protectCells($firstColumn.'3:'.$middle_column.'3','SkItechl2018@Passo');
                
                $objSheet->setCellValue($firstColumn.'3',strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE))." / ".strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME))."-".$degree->degree_code." ".$programme->programme_code." / ".$batch->batch_name)->getStyle($firstColumn.'3')->applyFromArray($styleArray);

                $objSheet->mergeCells($next_middle_column.'3:'.$lastColumn.'3')->protectCells($next_middle_column.'3:'.$lastColumn.'3','SkItechl2018@Passo');
                
                $objSheet->setCellValue($next_middle_column.'3'," SEMESTER : ".$exam_semester)->getStyle($next_middle_column.'3')->applyFromArray($styleArray);

                $objSheet->setCellValue($firstColumn.'4'," SNO")->protectCells($firstColumn.'4','SkItechl2018@Passo')->getStyle($firstColumn.'4')->applyFromArray($styleArray);

                $objSheet->mergeCells(chr((ord($firstColumn)+1)).'4:'.chr((ord($firstColumn)+1)).'4')->protectCells(chr((ord($firstColumn)+1)).'4:'.chr((ord($firstColumn)+1)).'4','SkItechl2018@Passo');
                
                $objSheet->setCellValue(chr((ord($firstColumn)+1)).'4',wordwrap(strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." CODE"),7)->getStyle(chr((ord($firstColumn)+1)).'4')->applyFromArray($styleArray);
                $getSplit = str_split($lastColumn);
                $a1_value = chr(ord($getSplit[1])-1);
                $last_column_before = $getSplit[0].$a1_value;
                $objSheet->mergeCells(chr((ord($firstColumn)+2)).'4:'.$last_column_before.'4')->protectCells(chr((ord($firstColumn)+2)).'4:'.$last_column_before.'4','SkItechl2018@Passo');
                $objSheet->setCellValue(chr((ord($firstColumn)+2)).'4',strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." NAME")->getStyle(chr((ord($firstColumn)+2)).'4')->applyFromArray($styleArray);

                $objSheet->protectCells($lastColumn.'4','SkItechl2018@Passo')->setCellValue($lastColumn.'4',"MAX")->getStyle($lastColumn.'4')->applyFromArray($styleArray);

                $rowCount = 5;
                $sub_serial = 1;
               
                foreach ($subjects_data as $subjects) 
                {
                    $objSheet->protectCells($firstColumn.$rowCount.':'.$lastColumn.$rowCount,'SkItecHl2018@Passo');
                   
                    $objSheet->SetCellValue($firstColumn.$rowCount, $sub_serial)->getStyle($firstColumn.$rowCount)->applyFromArray($styleArray_2);

                    $objSheet->mergeCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount)->protectCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount,'SkItechl2018@Passo');

                    $objSheet->SetCellValue(chr((ord($firstColumn)+1)).$rowCount, $subjects['subject_code'])->getStyle(chr((ord($firstColumn)+1)).$rowCount)->applyFromArray($styleArray_2);
                    
                    $objSheet->mergeCells(chr((ord($firstColumn)+2)).$rowCount.':'.$last_column_before.$rowCount)->protectCells(chr((ord($firstColumn)+2)).$rowCount.':'.$last_column_before.$rowCount,'SkItechl2018@Passo');

                    $objSheet->SetCellValue(chr((ord($firstColumn)+2)).$rowCount, strtoupper($subjects['subject_name']))->getStyle(chr((ord($firstColumn)+2)).$rowCount)->applyFromArray($styleArray_2);
                    $objSheet->protectCells($lastColumn.$rowCount,'SkItechl2018@Passo');
                    $objSheet->SetCellValue($lastColumn.$rowCount, $subjects['CIA_max'])->getStyle($lastColumn.$rowCount)->applyFromArray($styleArray_1);
                   
                    $rowCount++;
                    $sub_serial++;
                }
               
                $objSheet->freezePane($lastColumn.$rowCount);
                $column = chr((ord($firstColumn)+3));
                $lastRow = $lastColumn;
                $student_serial = 1;
                
                $objSheet->protectCells($firstColumn.$rowCount,'SkItechl2018@Passo');
                $objSheet->setCellValue($firstColumn.$rowCount,"SNO")->getStyle($firstColumn.$rowCount)->applyFromArray($styleArray_1);

                $objSheet->mergeCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount)->protectCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount,'SkItechl2018@Passo');
                
                $objSheet->setCellValue(chr((ord($firstColumn)+1)).$rowCount,wordwrap("REGISTER NUMBER"),8)->getStyle(chr((ord($firstColumn)+1)).$rowCount)->applyFromArray($styleArray_1);

                $objSheet->mergeCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount)->protectCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount,'SkItechl2018@Passo');

              
                $objSheet->setCellValue(chr((ord($firstColumn)+2)).$rowCount,"NAME OF THE ".strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)))->getStyle(chr((ord($firstColumn)+2)).$rowCount)->applyFromArray($styleArray_1);
                $number_show = 1;
                for ($i=0; $i < count($subjects_data); $i++) 
                {
                    $objSheet->protectCells($column.$rowCount,'SkItecHl2018@Passo')->SetCellValue($column.$rowCount,strtoupper($subjects_data[$i]['subject_code']))->getStyle($column.$rowCount)->applyFromArray($styleArray_1);
                    $column++;
                    $number_show++;
                }

                $objSheet->protectCells($column.$rowCount,'SkItecHl2018@Passo')->SetCellValue($column.$rowCount,strtoupper("ATT%"))->getStyle($column.$rowCount)->applyFromArray($styleArray_1);
                $rowCount = $rowCount+1;
                $objSheet->freezePane($column.$rowCount);
                foreach ($student_data as $row) 
                {
                    
                    $objSheet->SetCellValue($firstColumn.$rowCount, $student_serial)->getStyle($firstColumn.$rowCount)->applyFromArray($styleArray_2);

                    $objSheet->mergeCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount)->protectCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount,'SkItechl2018@Passo');
                    
                    $objSheet->SetCellValue(chr((ord($firstColumn)+1)).$rowCount, $row['register_number'])->getStyle(chr((ord($firstColumn)+1)).$rowCount)->applyFromArray($styleArray_2);

                   $objSheet->mergeCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount)->protectCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount,'SkItechl2018@Passo');
                    
                    $objSheet->SetCellValue(chr((ord($firstColumn)+2)).$rowCount, $row['name'])->getStyle(chr((ord($firstColumn)+2)).$rowCount)->applyFromArray($styleArray_2);
                    
                    $objSheet->getStyle(chr((ord($firstColumn)+3)).$rowCount.':'.$lastColumn.$rowCount)
                ->getProtection()->setLocked(PHPExcel_Style_Protection::PROTECTION_UNPROTECTED
                );

                $column_valid = chr((ord($firstColumn)+3));
                for ($j=0; $j < count($subjects_data); $j++) 
                {
                   
                    $objValidation = $objSheet->getCell($column_valid.$rowCount)->getDataValidation();
                    $objValidation->setType( PHPExcel_Cell_DataValidation::TYPE_WHOLE );
                    $objValidation->setErrorStyle( PHPExcel_Cell_DataValidation::STYLE_STOP );
                    $objValidation->setAllowBlank(true);
                    $objValidation->setShowInputMessage(true);
                    $objValidation->setShowErrorMessage(true);
                    $objValidation->setErrorTitle('Input error');
                    $objValidation->setError('0 and '.$subjects_data[$j]['CIA_max']);
                    $objValidation->setPromptTitle('Allowed input');
                    $objValidation->setPrompt('0 TO '.$subjects_data[$j]['CIA_max']);
                    $objValidation->setFormula1(0);
                    $objValidation->setFormula2($subjects_data[$j]['CIA_max']);
                    $column_valid++;
                }
                    $rowCount++;
                    $student_serial++;
                }
                
                
                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel,  "Excel2007");
                ob_end_clean();
                header( "Content-type: application/vnd.ms-excel" );
                 header("Cache-Control: no-cache");
                header("Content-Type: application/xlsx; charset=utf-8");
                header('Content-Disposition: attachment;filename="'.$degree->degree_code.$programme->programme_code.$exam_year.'-CIAMARKS.xlsx"');
                header("Pragma: no-cache");
                header("Expires: 0");                
                header('Cache-Control: max-age=0');
                $objWriter->save('php://output'); exit();
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found');
                return $this->redirect(['export-cia-marks']);
            }
        } 
        else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Export CIA Marks');
            return $this->render('export-cia-marks', [
                'model' => $model,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);
        }
    }


    public function actionExportEseMarks()
    {
        $model = new MarkEntryMaster();
        $markEntry = new MarkEntry();
        $student = new Student();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (Yii::$app->request->post()) 
        {
            
            $exam_year = $_POST['MarkEntry']['year'];
            $batch = $_POST['bat_val'];
            $bat_map_val = $_POST['bat_map_val'];
            $exam_semester = $_POST['exam_semester'];            
            $month = $_POST['month'];
            if(empty($exam_year) || empty($month) || empty($batch) || empty($bat_map_val) || empty($exam_semester))
            {
                Yii::$app->ShowFlashMessages->setMsg('error',' Select Required Fields ');
                return $this->redirect(['export-ese-marks']);
            }
            $batch = Batch::findOne($batch);
            $batch_mapping = CoeBatDegReg::findOne($bat_map_val);
            $degree = Degree::findOne($batch_mapping->coe_degree_id);
            $programme = Programme::findOne($batch_mapping->coe_programme_id);
            $elective_id_get = Categorytype::find()->where(['description'=>'Elective'])->orWhere(['category_type'=>'Elective'])->one();

            $get_sub_details = new Query();
            $get_sub_details->select('b.coe_subjects_mapping_id')
            ->from('coe_subjects a')
            ->join('JOIN','coe_subjects_mapping b','b.subject_id=a.coe_subjects_id')
            ->where(["b.batch_mapping_id"=>$bat_map_val,"b.semester"=>$exam_semester]);
            $get_sub_result = $get_sub_details->createCommand()->queryAll();
            if(count($get_sub_result)>0)
            {
                foreach ($get_sub_result as $value) {
                    $sub_codes[$value['coe_subjects_mapping_id']]=$value['coe_subjects_mapping_id'];
                }
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','NO DATA FOUND '.$degree->degree_code." ".$programme->programme_code.' ');
                return $this->redirect(['export-ese-marks']);
            }            
            $get_cat_id = Yii::$app->db->createCommand('SELECT coe_category_type_id FROM coe_category_type WHERE category_type like "%ESE%"  ')->queryScalar();

            $check_date = new Query();
            $check_date->select('*')
            ->from('coe_mark_entry a')
            ->where(["a.subject_map_id"=>$sub_codes,'a.category_type_id'=>$get_cat_id]);
            $get_result = $check_date->createCommand()->queryAll();

            if(!empty($get_result) && count($get_result)>0)
            {
                Yii::$app->ShowFlashMessages->setMsg('error','ESE MARKS ALREADY IMPORTED FOR '.$degree->degree_code." ".$programme->programme_code.' ');
                return $this->redirect(['export-ese-marks']);
            }

            $stu_map_query = new Query();
            $stu_map_query->select(['DISTINCT `b`.`register_number` AS register_number','b.name'])
            ->from('coe_student_mapping a')
            ->join('JOIN','coe_student b','a.student_rel_id=b.coe_student_id')
            ->join('JOIN','coe_category_type c','c.coe_category_type_id=a.status_category_type_id')
            ->where(["a.course_batch_mapping_id"=>$bat_map_val,'b.student_status'=>'Active'])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->groupBy('b.register_number')
            ->orderBy('b.register_number');;
            $student_data = $stu_map_query->createCommand()->queryAll();

            $subject_query_com = new Query();
            $subject_query_com->select(['b.subject_code','b.subject_name','ESE_max'])
            ->from('coe_subjects_mapping a')
            ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
            ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$exam_semester])
            ->andWhere(['NOT IN','a.subject_type_id',$elective_id_get['coe_category_type_id']])
            ->andWhere(['<>','ESE_min',0])
            ->andWhere(['<>','ESE_max',0])
            ->groupBy('b.subject_code')
            ->orderBy('b.subject_code');
            $subjects_data_comm = $subject_query_com->createCommand()->queryAll();
            
            $subject_query_no = new Query();
            $subject_query_no->select(['b.subject_code','b.subject_name','ESE_max'])
            ->from('coe_subjects_mapping a')
            ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.subject_id')
            ->join('JOIN','coe_nominal c','c.coe_subjects_id=b.coe_subjects_id AND c.course_batch_mapping_id=a.batch_mapping_id')
            ->where(["a.batch_mapping_id"=>$bat_map_val,'a.semester'=>$exam_semester,"c.course_batch_mapping_id"=>$bat_map_val,'c.semester'=>$exam_semester])
            ->groupBy('b.subject_code')
            ->orderBy('b.subject_code');
            $subjects_data_no = $subject_query_no->createCommand()->queryAll();
            
            $subjects_data = !empty($subjects_data_no) ? array_merge($subjects_data_no,$subjects_data_comm) : $subjects_data_comm;
            
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            // Excel Generation Starts Her 
            if(!empty($subjects_data) && !empty($student_data) && $file_content_available=="Yes")
            {
                
                // Count = Number of Subjects +  Column Headings
                $count = count($subjects_data)+4;
                $styleArray = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => '000000'),
                    'size'  => 15,
                    'name'  => 'Century Gothic'                    
                 ),
                 'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                 )   
                );   
                $styleArray_1 = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => '000000'),
                    'size'  => 12, 
                    'name'  => 'Century Gothic'                    
                 ),
                 'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                 )   
                );  
                $styleArray_2 = array(
                'font'  => array(                   
                    'color' => array('rgb' => '000000'),
                    'size'  => 13,
                    'name'  => 'Century Gothic'                     
                 ),
                 'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
                 )   
                );               
                // Excel Sheet Properties Settings 
                $objPHPExcel = new PHPExcel();
                $objPHPExcel->getProperties()->setCreator("Sri Krishna I-Tech")
                             ->setLastModifiedBy($org_name)
                             ->setTitle("Office 2007 XLSX ".$degree->degree_code.$programme->programme_code.$exam_year." ESE Marks")
                             ->setSubject("Office 2007 XLSX ".$degree->degree_code.$programme->programme_code.$exam_year." Marks Importing")
                             ->setDescription($degree->degree_code.$programme->programme_code.$exam_year." ESE Marks Import for Office 2007 XLSX")
                             ->setKeywords("ESE Marks Import")
                             ->setCategory("Marks Import File");
                $objPHPExcel->setActiveSheetIndex(0);                
                $sheet = $objPHPExcel->getSheet();
                $objSheet = $objPHPExcel->getActiveSheet();

                $objSheet->getProtection()->setSheet(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setSheet(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setSort(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setInsertRows(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setFormatCells(true);
                $objPHPExcel->getSecurity()->setLockWindows(true);
                $objPHPExcel->getSecurity()->setLockStructure(true);
                $objPHPExcel->getActiveSheet()->getProtection()->setPassword(Yii::$app->params['password_crack']);
                // Set NAME FOR EXCEL SHEET
                $sheet->setTitle($degree->degree_code."-".$programme->programme_code." ESE MARKS");

                $firstColumn = $sheet->getHighestColumn();
                $row = 1;
                
                // $column = 65 is Character of A
                for ($column = 65; $column <65+$count; $column++) 
                {
                    $cell = $sheet->getCell(chr($column).$row); 
                    
                }
                
                $lastColumn = $sheet->getHighestColumn();
                $middle_column = chr(($count/2)+65); // 65 is CHAR A
                $next_middle_column = chr(($count/2)+66); // 65 is CHAR A
               
                $objSheet->mergeCells($firstColumn.'1:'.$lastColumn.'1')->getStyle($firstColumn.'1:'.$lastColumn.'1')->applyFromArray($styleArray);
                $objSheet->protectCells($firstColumn.'1:'.$lastColumn.'1','SkItechl2018@Passo');
                $objSheet->setCellValue($firstColumn.'1',strtoupper($org_name))->getStyle($firstColumn.'1')->applyFromArray($styleArray);
               
                $objSheet->mergeCells($firstColumn.'2:'.$lastColumn.'2')->protectCells($firstColumn.'2:'.$lastColumn.'2','SkItechl2018@Passo')->getStyle($firstColumn.'2:'.$lastColumn.'2')->applyFromArray($styleArray);
                $objSheet->setCellValue($firstColumn.'2',"RECORD OF CONSOLIDATED CONTINUOUS ASSESSMENT MARKS ");
                $objSheet->mergeCells($firstColumn.'3:'.$middle_column.'3')->protectCells($firstColumn.'3:'.$middle_column.'3','SkItechl2018@Passo');
                
                $objSheet->setCellValue($firstColumn.'3',strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE))." / ".strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME))."-".$degree->degree_code." ".$programme->programme_code." / ".$batch->batch_name)->getStyle($firstColumn.'3')->applyFromArray($styleArray);

                $objSheet->mergeCells($next_middle_column.'3:'.$lastColumn.'3')->protectCells($next_middle_column.'3:'.$lastColumn.'3','SkItechl2018@Passo');
                
                $objSheet->setCellValue($next_middle_column.'3'," SEMESTER : ".$exam_semester)->getStyle($next_middle_column.'3')->applyFromArray($styleArray);

                $objSheet->setCellValue($firstColumn.'4'," SNO")->protectCells($firstColumn.'4','SkItechl2018@Passo')->getStyle($firstColumn.'4')->applyFromArray($styleArray);

                $objSheet->mergeCells(chr((ord($firstColumn)+1)).'4:'.chr((ord($firstColumn)+1)).'4')->protectCells(chr((ord($firstColumn)+1)).'4:'.chr((ord($firstColumn)+1)).'4','SkItechl2018@Passo');

                $objSheet->setCellValue(chr((ord($firstColumn)+1)).'4',wordwrap(strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." CODE"),7)->getStyle(chr((ord($firstColumn)+1)).'4')->applyFromArray($styleArray);

                $objSheet->mergeCells(chr((ord($firstColumn)+2)).'4:'.chr((ord($lastColumn)-1)).'4')->protectCells(chr((ord($firstColumn)+2)).'4:'.chr((ord($lastColumn)-1)).'4','SkItechl2018@Passo');
                $objSheet->setCellValue(chr((ord($firstColumn)+2)).'4',strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." NAME")->getStyle(chr((ord($firstColumn)+2)).'4')->applyFromArray($styleArray);

                $objSheet->protectCells($lastColumn.'4','SkItechl2018@Passo')->setCellValue($lastColumn.'4',"MAX")->getStyle($lastColumn.'4')->applyFromArray($styleArray);

                $rowCount = 5;
                $sub_serial = 1;
                foreach ($subjects_data as $subjects) 
                {
                    $objSheet->protectCells($firstColumn.$rowCount.':'.$lastColumn.$rowCount,'SkItecHl2018@Passo');
                    $objSheet->SetCellValue($firstColumn.$rowCount, $sub_serial)->getStyle($firstColumn.$rowCount)->applyFromArray($styleArray_2);

                    $objSheet->mergeCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount)->protectCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount,'SkItechl2018@Passo');

                    $objSheet->SetCellValue(chr((ord($firstColumn)+1)).$rowCount, $subjects['subject_code'])->getStyle(chr((ord($firstColumn)+1)).$rowCount)->applyFromArray($styleArray_2);
                    
                    $objSheet->mergeCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($lastColumn)-1)).$rowCount)->protectCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($lastColumn)-1)).$rowCount,'SkItechl2018@Passo');

                    $objSheet->SetCellValue(chr((ord($firstColumn)+2)).$rowCount, strtoupper($subjects['subject_name']))->getStyle(chr((ord($firstColumn)+2)).$rowCount)->applyFromArray($styleArray_2);
                    $objSheet->protectCells($lastColumn.$rowCount,'SkItechl2018@Passo');
                    $objSheet->SetCellValue($lastColumn.$rowCount, $subjects['ESE_max'])->getStyle($lastColumn.$rowCount)->applyFromArray($styleArray_1);
                   
                    $rowCount++;
                    $sub_serial++;
                }
                
                $objSheet->freezePane($lastColumn.$rowCount);
                $column = chr((ord($firstColumn)+3));
                $lastRow = $lastColumn;
                $student_serial = 1;
                
                $objSheet->protectCells($firstColumn.$rowCount,'SkItechl2018@Passo');
                $objSheet->setCellValue($firstColumn.$rowCount,"SNO")->getStyle($firstColumn.$rowCount)->applyFromArray($styleArray_1);

                $objSheet->mergeCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount)->protectCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount,'SkItechl2018@Passo');
                
                $objSheet->setCellValue(chr((ord($firstColumn)+1)).$rowCount,wordwrap("REGISTER NUMBER"),8)->getStyle(chr((ord($firstColumn)+1)).$rowCount)->applyFromArray($styleArray_1);

                $objSheet->mergeCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount)->protectCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount,'SkItechl2018@Passo');

              
                $objSheet->setCellValue(chr((ord($firstColumn)+2)).$rowCount,"NAME OF THE ".strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)))->getStyle(chr((ord($firstColumn)+2)).$rowCount)->applyFromArray($styleArray_1);
                $number_show = 1;
                for ($i=0; $i < count($subjects_data); $i++) 
                {
                    $objSheet->protectCells($column.$rowCount,'SkItecHl2018@Passo')->SetCellValue($column.$rowCount,strtoupper($subjects_data[$i]['subject_code']))->getStyle($column.$rowCount)->applyFromArray($styleArray_1);
                    $column++;
                    $number_show++;

                }

                $objSheet->protectCells($column.$rowCount,'SkItecHl2018@Passo')->SetCellValue($column.$rowCount,strtoupper("ATT%"))->getStyle($column.$rowCount)->applyFromArray($styleArray_1);
                $rowCount = $rowCount+1;
                $objSheet->freezePane($column.$rowCount);
                foreach ($student_data as $row) 
                {
                    
                    $objSheet->SetCellValue($firstColumn.$rowCount, $student_serial)->getStyle($firstColumn.$rowCount)->applyFromArray($styleArray_2);

                    $objSheet->mergeCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount)->protectCells(chr((ord($firstColumn)+1)).$rowCount.':'.chr((ord($firstColumn)+1)).$rowCount,'SkItechl2018@Passo');
                    
                    $objSheet->SetCellValue(chr((ord($firstColumn)+1)).$rowCount, $row['register_number'])->getStyle(chr((ord($firstColumn)+1)).$rowCount)->applyFromArray($styleArray_2);

                   $objSheet->mergeCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount)->protectCells(chr((ord($firstColumn)+2)).$rowCount.':'.chr((ord($firstColumn)+2)).$rowCount,'SkItechl2018@Passo');
                    
                    $objSheet->SetCellValue(chr((ord($firstColumn)+2)).$rowCount, $row['name'])->getStyle(chr((ord($firstColumn)+2)).$rowCount)->applyFromArray($styleArray_2);
                    
                    $objSheet->getStyle(chr((ord($firstColumn)+3)).$rowCount.':'.$lastColumn.$rowCount)
                ->getProtection()->setLocked(PHPExcel_Style_Protection::PROTECTION_UNPROTECTED
                );

                    $column_valid = chr((ord($firstColumn)+3));
                    for ($j=0; $j < count($subjects_data); $j++) 
                    {                       
                        $objValidation = $objSheet->getCell($column_valid.$rowCount)->getDataValidation();
                        $objValidation->setType( PHPExcel_Cell_DataValidation::TYPE_WHOLE );
                        $objValidation->setErrorStyle( PHPExcel_Cell_DataValidation::STYLE_STOP );
                        $objValidation->setAllowBlank(true);
                        $objValidation->setShowInputMessage(true);
                        $objValidation->setShowErrorMessage(true);
                        $objValidation->setErrorTitle('Input error');
                        $objValidation->setError('Only numbers between 0 and 100 are allowed!');
                        //$objValidation->setPromptTitle('Allowed input');
                        $objValidation->setPrompt('Marks Out of 100');
                        $objValidation->setFormula1(0);
                        $objValidation->setFormula2(100);
                        $column_valid++;
                    }
                    $rowCount++;
                    $student_serial++;
                }
                
                
                $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel,  "Excel2007");
                ob_end_clean();
                header( "Content-type: application/vnd.ms-excel" );
                 header("Cache-Control: no-cache");
                header("Pragma: no-cache");
                header("Content-Type: application/xlsx; charset=utf-8");
                header('Content-Disposition: attachment;filename="'.$degree->degree_code.$programme->programme_code.$exam_year.'-ESEMARKS.xlsx"');
                header("Expires: 0");
                
                header('Cache-Control: max-age=0');
                $objWriter->save('php://output'); exit();
            }
            else
            {
                return $this->redirect(['export-ese-marks']);
            }
        } 
        else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Export ESE Marks');
            return $this->render('export-ese-marks', [
                'model' => $model,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);
        }
    }
    /**
     * Creates a new MarkEntryMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new MarkEntryMaster();
        $student = new Student();
        $checkAccess = ConfigUtilities::HasSuperAccess(Yii::$app->user->getId());
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        $getElecId= Yii::$app->db->createCommand("select * from coe_category_type where description like '%Elective%' ")->queryOne();
        $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
        if($checkAccess=='Yes')
        {
            if (Yii::$app->request->post())
            {
                $year = $_POST['year'];
                $month =$_POST['month'];
                $mark_type =$_POST['MarkEntryMaster']['mark_type'];
                $subject_code = $_POST['subject_code'];
                $dum_reg= '';
                $subjects = Subjects::find()->where(['subject_code'=>$subject_code])->one();

                if(isset($_POST['submit_ese']) && $_POST['submit_ese']=="UPDATE")
                {
                    $category_type_id = Categorytype::find()->where(['description' => "ESE(Dummy)"])->orWhere(['category_type' => "ESE(Dummy)"])->one();
                    $category_type_id_internal = Categorytype::find()->where(['description' => "CIA"])->orWhere(['category_type' => "Internal"])->one();
                     $cat_ese_mark_type = Categorytype::find()->where(['description' => "ESE"])->orWhere(['category_type' => "ESE"])->one();

                    $connection = Yii::$app->db;
                    $checkAlreadyUpdated = Yii::$app->db->createCommand('SELECT * FROM coe_mark_entry_master WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" and mark_type="'.$_POST['mark_type'].'" ')->queryOne();

                    $chekAvail = Yii::$app->db->createCommand('SELECT * FROM coe_mark_entry WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" and mark_type="'.$_POST['mark_type'].'" and category_type_id IN("'.$cat_ese_mark_type['coe_category_type_id'].'","'.$category_type_id['coe_category_type_id'].'")')->queryOne();

                    if(!empty($chekAvail))
                    {
                         $command2 = $connection->createCommand('UPDATE coe_mark_entry SET category_type_id_marks="'.$_POST['update_ese'].'",is_updated="YES" ,updated_by="'.$updated_by.'",updated_at="'.$updated_at.'" WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" and mark_type="'.$_POST['mark_type'].'" and category_type_id IN('.$cat_ese_mark_type['coe_category_type_id'].','.$category_type_id['coe_category_type_id'].' )');
                         if($command2->execute())
                        {
                            $get_sub_info = Yii::$app->db->createCommand('SELECT * FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$_POST['subMapId'].'"  ')->queryOne();
                            $ese_marks = round($_POST['update_ese']*$get_sub_info['ESE_max']/100);
                            $status_check = $ese_marks<=$get_sub_info['ESE_max'] ? 'YES' : 'NO'; 
                            $total_marks = $insert_total = $ese_marks+$checkAlreadyUpdated['CIA'];
                            $grade_cia_check = $checkAlreadyUpdated['CIA'];
                            $batchMapping = CoeBatDegReg::findOne($get_sub_info['batch_mapping_id']);
                            $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping['regulation_year']])->all();
                            
                            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                          $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];
                          $arts_college_grade = 'NO';
                          if($org_email=='coe@skasc.ac.in')
                          {
                            $convert_ese_marks =  $ese_marks = $_POST['update_ese'];
                            $insert_total = $ese_marks+$grade_cia_check;
                            if($final_sub_total<100)
                            {
                              $total_marks = round(round((($insert_total/$final_sub_total)*10),1)*10);
                            }
                            else
                            {
                              $total_marks = $ese_marks+$grade_cia_check;
                            }
                            $arts_college_grade = round(($insert_total/$final_sub_total)*10,1);

                          }

                          foreach ($grade_details as $value) 
                          {
                              if($value['grade_point_to']!='')
                              {                            
                                  if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                                  {
                                      if($grade_cia_check<$get_sub_info['CIA_min'] || $ese_marks<$get_sub_info['ESE_min'] || $insert_total<$get_sub_info['total_minimum_pass'])
                                      {
                                        $stu_result_data = ['result'=>'Fail','total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'year_of_passing'=>'','ese_marks'=>$ese_marks];        
                                      }      
                                      else
                                      {
                                        $grade_name_prit = $value['grade_name'];
                                        $grade_point_arts = $org_email=='coe@skasc.ac.in' ? $arts_college_grade : $value['grade_point'];;
                                        if(!empty($_POST['month']) && !empty($_POST['year']))
                                        {
                                            $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$_POST['month']."-".$_POST['year']];
                                        }
                                        else
                                        {
                                            $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$_POST['month']."-".$_POST['year']];
                                        }
                                        
                                      }
                                  } // Grade Point Caluclation
                              } // Not Empty of the Grade Point                               
                          }
                          
                            $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $_POST['month'] . "-" . $_POST['year']: '';

                            $command3 = $connection->createCommand('UPDATE coe_mark_entry_master SET ESE="'.$stu_result_data['ese_marks'].'",is_updated="YES",updated_by="'.$updated_by.'",updated_at="'.$updated_at.'",grade_point="'.$stu_result_data['grade_point'].'",total="'.$stu_result_data['total_marks'].'",result="'.$stu_result_data['result'].'",grade_name="'.$stu_result_data['grade_name'].'",year_of_passing="'.$year_of_passing.'" WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" and mark_type="'.$_POST['mark_type'].'"');

                            $ip_address = Yii::$app->params['ipAddress'];
                            $UpdateTracker = New UpdateTracker();
                            $UpdateTracker->student_map_id = $_POST['stuMapId'];
                            $UpdateTracker->subject_map_id = $_POST['subMapId'];
                            $UpdateTracker->exam_year = $_POST['year'];
                            $UpdateTracker->exam_month = $_POST['month'];
                            $UpdateTracker->updated_link_from = 'Marks->Update Marks';
                            $UpdateTracker->data_updated = 'PREVIOUS ESE MARKS '.$chekAvail['category_type_id_marks'].' NEW ESE MARKS'.$stu_result_data['ese_marks'];
                            $UpdateTracker->updated_ip_address = $ip_address;
                            $UpdateTracker->updated_by = ConfigUtilities::getCreatedUser();
                            $UpdateTracker->updated_at = ConfigUtilities::getCreatedTime();
                            $UpdateTracker->save();
                            unset($UpdateTracker);
                            $UpdateTracker = New UpdateTracker();

                            if( $status_check=='YES' && $command3->execute())
                            {
                                Yii::$app->ShowFlashMessages->setMsg('Success','Updated Successfully!!');
                                return $this->redirect(['create']);
                            }
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Update the Marks');
                            return $this->redirect(['create']);
                        }
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                            return $this->redirect(['create']);
                    }                       
                        
                }
                if(isset($_POST['dummy_number']) && !empty($_POST['dummy_number']) && !empty($subjects))
                {
                    
                    $getDummy = DummyNumbers::find()->where(['dummy_number'=>$_POST['dummy_number'],'month'=>$month,'year'=>$year])->one();
                    if(empty($getDummy))
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                        return $this->redirect(['create']);
                    }
                    else
                    {
                        $stu_map = StudentMapping::findOne($getDummy->student_map_id);
                        $dum_reg= Student::findOne($stu_map->student_rel_id);
                    }
                }

                
                $register_number = isset($dum_reg) && !empty($dum_reg) ? $dum_reg['register_number'] : $_POST['register_number'];
                $dummy_number = isset($_POST['dummy_number']) && !empty($_POST['dummy_number']) ? $_POST['dummy_number']:'';
               
                $student = Student::find()->where(['register_number'=>$register_number])->one();
                
                if(empty($subjects) || empty($student))
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                    return $this->redirect(['create']);
                }
                else
                {
                    $stu_map = StudentMapping::find()->where(['student_rel_id'=>$student->coe_student_id])->one();
                    $sub_map = SubjectsMapping::find()->where(['subject_id'=>$subjects->coe_subjects_id,'batch_mapping_id'=>$stu_map->course_batch_mapping_id])->one();
                    $batch_mapping_id = $stu_map->course_batch_mapping_id;
                    $student_map_id = $stu_map->coe_student_mapping_id;
                    $split_data = ConfigUtilities::getSubjectMappingIds($subjects->coe_subjects_id,$year,$month);

                    if(empty($stu_map) || empty($split_data))
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                        return $this->redirect(['create']);
                    }
                    else
                    {
                        if(is_array($split_data))
                        {
                            sort($split_data);
                            for ($k=0; $k <count($split_data) ; $k++) 
                            { 
                                if(isset($_POST['semester_val']) && !empty($_POST['semester_val']))
                                {
                                    $getElective = SubjectsMapping::findOne(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id,'semester'=>$_POST['semester_val']]);
                                }
                                else
                                {
                                    $getElective = SubjectsMapping::findOne(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id]);
                                }

                                if($getElective['subject_type_id']==$getElecId['coe_category_type_id'])
                                {
                                    $query = new Query();
                                    $query->select('*')
                                        ->from('coe_subjects_mapping a')
                                        ->join('JOIN', 'coe_student_mapping b', 'b.course_batch_mapping_id=a.batch_mapping_id')
                                        ->join('JOIN', 'coe_student C', 'C.coe_student_id=b.student_rel_id')
                                        ->join('JOIN', 'coe_nominal D', 'D.coe_student_id=C.coe_student_id and D.coe_subjects_id=a.subject_id and D.course_batch_mapping_id=b.course_batch_mapping_id and D.course_batch_mapping_id=a.batch_mapping_id and D.semester=a.semester')
                                        ->where(['a.batch_mapping_id' => $batch_mapping_id,'coe_subjects_mapping_id'=>$split_data[$k],'coe_student_mapping_id'=>$student_map_id]);
                                    $sub_map_id_ins = $query->createCommand()->queryOne();
                                  
                                }
                                else
                                {
                                    
                                    if(isset($_POST['semester_val']) && !empty($_POST['semester_val']))
                                    {
                                        $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id,'semester'=>$_POST['semester_val']])->one();
                                    }
                                    else
                                    {
                                        $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id])->one();
                                    }
                                    
                                }
                                
                                if(!empty($sub_map_id_ins))
                                {
                                    break;
                                }
                            }

                        }
                        else
                        {                            
                            if(isset($_POST['semester_val']) && !empty($_POST['semester_val']))
                            {
                                $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data,'batch_mapping_id'=>$batch_mapping_id,'semester'=>$_POST['semester_val']])->one();
                            }
                            else
                            {
                                $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data,'batch_mapping_id'=>$batch_mapping_id])->one();
                            }
                            
                        }
                        if(isset($sub_map_id_ins) && !empty($sub_map_id_ins))
                        {

                            $subject_map_id = $sub_map_id_ins['coe_subjects_mapping_id']; 
                        }
                        else
                        {
                            $subject_map_id =0; 
                        }
                        if(!empty($dummy_number))
                        {
                            $getDummy = DummyNumbers::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'dummy_number'=>$dummy_number,'month'=>$month,'year'=>$year])->one();
                            if(empty($getDummy))
                            {
                                Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                                return $this->redirect(['create']);
                            }
                        }

                        $chec_written = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'mark_type'=>$mark_type])->one();
                        
                        if(empty($chec_written))
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                            return $this->redirect(['create']);
                        }
                        else
                        {

                           return $this->render('_form', [
                                'model' => $model,
                                'student' =>$student,
                                'chec_written' =>$chec_written,
                                'dummy_number' =>$dummy_number,
                            ]);

                        }

                    }
                }
               
            } else {
                return $this->render('create', [
                    'model' => $model,
                    'student' =>$student,
                ]);
            }
        }
        else
        {
            $lockUser = Yii::$app->db->createCommand('UPDATE user SET status="11" WHERE id="'.Yii::$app->user->getId().'"')->execute();
            $created_by = $updated_by = Yii::$app->user->getId();
            $created_at = $updated_at = date("Y-m-d H:i:s");
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            $filename =  Yii::getAlias("@webroot").'/access_logs/log_'.date("j.n.Y").'.txt';
            $content  = "User Name: ".Yii::$app->user->getUsername().' - '.date("F j, Y, g:i a").PHP_EOL.
                        "Accessed URLS: ".$url.PHP_EOL.
                        "----------------------------------------------------------------".PHP_EOL;

            //print_r(parse_url($url)); // This will returns the parts of the URL
            
            $removed_path = str_replace('index.php', '', parse_url($url, PHP_URL_PATH));
            $image_path = parse_url($url, PHP_URL_SCHEME)."://".parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PORT).$removed_path.'images/notfound.png'; 
            
            $image_path = Yii::getAlias("@web").'/images/notfound.png'; 

            if(file_put_contents($filename, $content.PHP_EOL , FILE_APPEND | LOCK_EX))
            {   
                $file_content = file_get_contents($filename, true);
                echo "<div style='width:1000px;  text-align: center; margin: 0 auto;'><img src='".$image_path."' alt='not found' height='600' width='900' align='center' /></div>";
                
            }
            unset($_SESSION);
            session_destroy();
            Yii::$app->ShowFlashMessages->setMsg('Error','OOOPS You are not allowed!!! Your Account is Locked!!!');
            return $this->redirect(['site/index']);            
        }
        
    }


    /**
     * Creates a new MarkEntryMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionConsolidateMarkSheet()
    {
        $model = new MarkEntryMaster();
        $markEntry = new MarkEntry();
        $student = new Student();

        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $det_disc_rejoin_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%rejoin%'")->queryScalar();
        $semester = Yii::$app->request->post('semester');
        
        if (Yii::$app->request->post()) 
        {
            $get_batc_map = CoeBatDegReg::findOne($_POST['bat_map_val']);
            $deg_info = Degree::findOne($get_batc_map->coe_degree_id);
            $getBacthName = Batch::findOne($get_batc_map->coe_batch_id);
            $getBacthYear = $getBacthName['batch_name'];
            $reg_num_in = '';
           $ug_maximum_marks = 3500;
           // $ug_maximum_marks = 3200;
            $pg_maximum_marks = 2250;
            $ug_maximum_credits = 140;
            $pg_maximum_credits = 90;
            $get_regnum = StuInfo::find()->where(['batch_map_id'=>$_POST['bat_map_val']])->andWhere(['between','reg_num', $_POST['Student']['register_number_from'],$_POST['Student']['register_number_to']] )->all();
           if(!empty($get_regnum) && count($get_regnum)>0 && $deg_info['degree_type']=='PG')
            {   
                foreach($get_regnum as $val)
                {
                    $get_data = ConfigUtilities::getCreditDetails($val['reg_num']);
                    if($get_data['part_credits']==$pg_maximum_credits && $pg_maximum_marks==$get_data['part_total_marks'])
                    {
                        $reg_num_in .="'".$val['reg_num']."',";
                    }
                }
                $trim_reg = trim($reg_num_in,',');
            }
            else if(!empty($get_regnum) && count($get_regnum)>0 && $deg_info['degree_type']=='UG')
            {

                foreach($get_regnum as $val)
                {
                    $get_data = ConfigUtilities::getCreditDetails($val['reg_num']);
                    
                    if($get_data['part_credits']==$ug_maximum_credits && $ug_maximum_marks==$get_data['part_total_marks'])
                    {
                        $reg_num_in .="'".$val['reg_num']."',";
                    }
                }
                $trim_reg = trim($reg_num_in,',');
            }

            else{
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
                return $this->redirect(['mark-entry-master/consolidate-mark-sheet']); 
            }
            if(empty($trim_reg))
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
                return $this->redirect(['mark-entry-master/consolidate-mark-sheet']); 
            }



            $top_margin = $_POST['top_margin'];
            $bottom_margin = $_POST['bottom_margin'];
            $semester = Yii::$app->request->post('semester');
            $month = Yii::$app->request->post('month');
            $year = Yii::$app->request->post('year');
         
            $add_sem = !empty($semester) ? " AND  G.semester<='".$semester."' ":'';
            $add_year= !empty($year) ? " AND  F.year<='".$year."' ":'';
            //normal student query
         
        // $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,G.batch_mapping_id,E.programme_name,H.subject_code,F.subject_map_id,C.regulation_year,mark_type,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.CIA,F.ESE,H.ESE_max,H.CIA_max,F.year,F.month,status_category_type_id,B.course_batch_mapping_id,F.year_of_passing,part_no,paper_no,F.total,F.year as exam_year,F.month as exam_month,F.result,max(F.year_of_passing) as last_appearance ,F.term FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$_POST['bat_map_val']."' and  F.result='Pass'  AND A.register_number IN (".$trim_reg.") group by A.register_number,H.subject_code order by A.register_number,G.semester,paper_no";
            //rejoin student taking marksheet
           $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,G.batch_mapping_id,E.programme_name,H.subject_code,F.subject_map_id,C.regulation_year,mark_type,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.CIA,F.ESE,H.ESE_max,H.CIA_max,F.year,F.month,status_category_type_id,B.course_batch_mapping_id,F.year_of_passing,part_no,paper_no,F.total,F.year as exam_year,F.month as exam_month,F.result,max(F.year_of_passing) as last_appearance ,F.term FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$_POST['bat_map_val']."' and B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%'))  and F.result NOT IN ('fail','Fail','FAIL','RA','absent', 'ab','AB','Absent') AND F.grade_name not IN ('RA','wh','AB','ab','WH','wd','WD','U','RA') AND A.register_number IN (".$trim_reg.") group by A.register_number,H.subject_code order by A.register_number,G.semester,paper_no";

            $get_console_list = Yii::$app->db->createCommand($get_stu_query)->queryAll();
         //print_r($get_console_list);exit;

            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));

            if(!empty($get_console_list))
            {
                $layout_type = $_POST['layout_type'];
                if($file_content_available=="Yes")
                {
                    return $this->render('consolidate-mark-sheet', [                        
                        'get_console_list' => $get_console_list,
                        'model' => $model, 
                        'degree_type' => $deg_info['degree_type'],                       
                        'markEntry'=>$markEntry,
                        'getBacthYear'=>$getBacthYear,'top_margin'=>$top_margin,'bottom_margin'=>$bottom_margin,
                        'layout_type' => $layout_type,
                       // 'print_trimester' => $print_trimester,
                        'student' => $student,
                        'date_print' =>$_POST['created_at'],
                        'trans_semester_pass' =>$semester,
                        'year'=>$year,
                        'month'=>$month,
                    ]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"No Institute Information Found");
                    return $this->redirect(['mark-entry-master/consolidate-mark-sheet']); 
                }
                 
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
                return $this->redirect(['mark-entry-master/consolidate-mark-sheet']); 
            }


            return $this->render('consolidate-mark-sheet', [
                'model' => $model,
                'get_console_list' => $get_console_list,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);

            //return $this->redirect(['view', 'id' => $model->coe_mark_entry_master_id]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate Mark Sheet');
            return $this->render('consolidate-mark-sheet', [
                'model' => $model,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);
        }
    }    
    public function actionConsolidateMarkSheetRejoin()
    {
        $model = new MarkEntryMaster();
        $markEntry = new MarkEntry();
        $student = new Student();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Rejoin%'")->queryScalar();
        if (Yii::$app->request->post()) 
        {
            $get_batc_map = CoeBatDegReg::findOne($_POST['bat_map_val']);
            $deg_info = Degree::findOne($get_batc_map->coe_degree_id);
            $print_trimester = (($deg_info->degree_total_semesters/2)==3 && $deg_info->degree_code=='MBA') ? 1: 0;

            if(isset($_SESSION['transcript']))
            {
                unset($_SESSION['transcript']);
            }
            $_SESSION['transcript']=$is_transcript = $_POST['Transcript'];
             $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,E.programme_name,H.subject_code,C.regulation_year,mark_type,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.total,F.year,F.month,status_category_type_id,B.course_batch_mapping_id,F.year_of_passing,paper_no,F.year as exam_year,F.month as exam_month,F.result,max(F.year_of_passing) as last_appearance FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$_POST['bat_map_val']."' AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%')) and status_category_type_id IN('".$det_disc_type."') AND F.grade_name not IN ('U','wh','AB','ab','WH','U','wd','WD') AND A.register_number between '".$_POST['Student']['register_number_from']."' AND '".$_POST['Student']['register_number_to']."' group by A.register_number,H.subject_code order by paper_no,G.semester";
            
            $get_console_list = Yii::$app->db->createCommand($get_stu_query)->queryAll();
            //array_multisort(array_column($get_console_list, 'register_number'),  SORT_ASC, $get_console_list);
            $data = array_filter(['']);
            if(!empty($get_console_list))
            {       

                $old_reg_num = '';         
                foreach ($get_console_list as $key => $values) 
                {
                    $stuInfo = Student::findOne(['register_number'=>$values['register_number']]);
                    $stu_map_id = StudentMapping::findOne(['student_rel_id'=>$stuInfo['coe_student_id']]);                    
                    $reguDetails = CoeBatDegReg::findOne($stu_map_id['course_batch_mapping_id']);

                    if(!empty($stu_map_id['previous_reg_number']) && $stu_map_id['previous_reg_number']!='' && $old_reg_num!=$values['register_number'])
                    {                       
                        $GetstuInfo = Student::findOne(['register_number'=>$stu_map_id['previous_reg_number']]);
                        $geTstu_map_id = StudentMapping::findOne(['student_rel_id'=>$GetstuInfo['coe_student_id']]);
                          
                        $get_stu_query1 = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,E.programme_name,H.subject_code,'".$reguDetails['regulation_year']."' as regulation_year,mark_type,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.year,F.month,status_category_type_id,B.course_batch_mapping_id,F.year_of_passing,paper_no,F.year as exam_year,F.month as exam_month,F.result,max(F.year_of_passing) as last_appearance FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$geTstu_map_id['course_batch_mapping_id']."' AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%')) and status_category_type_id IN('".$det_cat_type."') AND F.grade_name not IN ('U','wh','AB','ab','WH','U','wd','WD') AND A.register_number='".$GetstuInfo['register_number']."' group by A.register_number,H.subject_code order by paper_no,G.semester";
                        $get_console_list_1 = Yii::$app->db->createCommand($get_stu_query1)->queryAll();
                        
                        if(!empty($get_console_list_1))
                        {
                            foreach ($get_console_list_1 as &$str) 
                            {
                                $data[] = str_replace($stu_map_id->previous_reg_number, $values['register_number'], $str);
                            }
                            $data = array_map("unserialize", array_unique(array_map("serialize", $data)));
                        }
                        $old_reg_num!==$values['register_number'];
                    }                    
                }
                
            }
            $data_filter = array_filter($data);
            if(!empty($data_filter))
            {
                $get_console_list = array_merge($data_filter,$get_console_list);
            }
            
            $degree_type = Yii::$app->db->createCommand("SELECT degree_type FROM coe_degree AS A JOIN coe_bat_deg_reg as B ON B.coe_degree_id=A.coe_degree_id WHERE coe_bat_deg_reg_id='".$_POST['bat_map_val']."' ")->queryScalar();
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));

            if(!empty($get_console_list))
            {
                $layout_type = $_POST['layout_type'];
                if($file_content_available=="Yes")
                {
                    return $this->render('consolidate-mark-sheet-rejoin', [                        
                        'get_console_list' => $get_console_list,
                        'model' => $model, 
                        'degree_type' => $degree_type,                       
                        'markEntry'=>$markEntry,
                        'layout_type' => $layout_type,
                        'print_trimester' => $print_trimester,
                        'student' => $student,
                        'date_print' =>$_POST['created_at'],
                    ]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"No Institute Information Found");
                    return $this->redirect(['mark-entry-master/consolidate-mark-sheet-rejoin']); 
                }
                 
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
                return $this->redirect(['mark-entry-master/consolidate-mark-sheet-rejoin']); 
            }


            return $this->render('consolidate-mark-sheet-rejoin', [
                'model' => $model,
                'get_console_list' => $get_console_list,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);

            //return $this->redirect(['view', 'id' => $model->coe_mark_entry_master_id]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate Mark Sheet');
            return $this->render('consolidate-mark-sheet-rejoin', [
                'model' => $model,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);
        }
    } 
    public function actionSkcetConsolidateMarkSheetPgPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));         
        $content = $_SESSION['get_console_list_pdf'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => strtoupper("consolidate").' MARK STATEMENT.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssFile' => 'css/consolidate-markstatement-arts-pg.css',
                'options' => ['title' => strtoupper("consolidate") . ' MARK STATEMENT'],
                
            ]);
          
        $pdf->marginTop = "4.8";
        $pdf->marginLeft = "-1";
        $pdf->marginRight = "3.5";
        $pdf->marginBottom = "0";
        $pdf->marginHeader = "4";
        $pdf->marginFooter = "9";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }
    public function actionConsolidateMarkSheetPgPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));         
        $content = $_SESSION['get_console_list_pdf'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 

                'filename' => strtoupper("consolidate").' MARK STATEMENT.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                'cssFile' => 'css/consolidate-markstatement.css',
                'options' => ['title' => strtoupper("consolidate") . ' MARK STATEMENT'],
                
            ]);
          
        $pdf->marginTop = "3";
        $pdf->marginLeft = "3.2";
        $pdf->marginRight = "3.1";
        $pdf->marginBottom = "0.5";
        $pdf->marginHeader = "4";
        $pdf->marginFooter = "0";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');

        return $pdf->render(); 
    }
    public function actionConsolidateMarkSheetPdfSkcet(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));         
        $content = $_SESSION['consolidatemarksheet_print'];
        if(isset($_SESSION['transcript']) && $_SESSION['transcript']==1)
        {
            $css_file = 'css/consolidate-markstatement-ug-skcet-transcript.css';
        }
        else
        {
            $css_file = 'css/consolidate-markstatement-ug-skcet.css';
        }
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 
                'filename' => 'ConsolidateMarkSheet.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => $css_file,
                'options' => ['title' => 'Consolidate Mark'],
                
            ]);
        if(isset($_SESSION['transcript']) && $_SESSION['transcript']==1)
        {
            $pdf->marginTop = "0";
            $pdf->marginLeft = "10";
            $pdf->marginRight = "-18";
            $pdf->marginBottom = "-20";
            $pdf->marginHeader = "0";
            $pdf->marginFooter = "5";
        }
        else
        {
            $pdf->marginTop = "3";
            $pdf->marginLeft = "10";
            $pdf->marginRight = "-18";
            $pdf->marginBottom = "-20";
            $pdf->marginHeader = "0";
            $pdf->marginFooter = "5";    
        }
        
       
       Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render(); 
    }
    public function actionConsolidateMarkSheetPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));         
        $content = $_SESSION['consolidatemarksheet_print'];
        
        $change_css_file = 'css/consolidate-markstatement-ug.css';
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE, 
            'filename' => 'ConsolidateMarkSheet.pdf',                
            'format' => Pdf::FORMAT_A3,                 
            'orientation' => Pdf::ORIENT_LANDSCAPE,                 
            'destination' => Pdf::DEST_BROWSER,                 
            'content' => $content,  
            'cssFile' => $change_css_file,
            'options' => ['title' => 'Consolidate Mark'],
            
        ]);
        if(isset($_SESSION['transcript']) && $_SESSION['transcript']==1 )
        {
            $pdf->marginTop = "4";
        }
        else 
        {
            $pdf->marginTop = "0";
        }

        $pdf->marginLeft = "5";
        $pdf->marginRight = "-3";
        $pdf->marginBottom = "-20";
        $pdf->marginHeader = "0";
        $pdf->marginFooter = "0";

       
       Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render(); 
    }

    /**
     * Updates an existing MarkEntryMaster model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_mark_entry_master_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing MarkEntryMaster model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $checkAccess = ConfigUtilities::HasSuperAccess(Yii::$app->user->getId());
        if($checkAccess=='Yes')
        {
            $ese_cat_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%ESE%' OR category_type like '%External%'")->queryScalar();
            $dummy_cat = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%ESE(Dummy)%'")->queryScalar();
            $cia = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Internal%' OR category_type like '%CIA%' ")->queryScalar();
            $details = $this->findModel($id);        

            $getMArkentry = MarkEntry::find()->where(['student_map_id'=>$details['student_map_id'],'subject_map_id'=>$details['subject_map_id'],'year'=>$details['year'],'month'=>$details['month'],'mark_type'=>$details['mark_type']])->andWhere(['IN','category_type_id',[$ese_cat_id,$dummy_cat]])->one();

            $getMArkentryCia = MarkEntry::find()->where(['student_map_id'=>$details['student_map_id'],'subject_map_id'=>$details['subject_map_id'],'year'=>$details['year'],'month'=>$details['month'],'mark_type'=>$details['mark_type'],'category_type_id'=>$cia])->one();

            $CheckAbsent = AbsentEntry::find()->where(['absent_student_reg'=>$details['student_map_id'],'exam_subject_id'=>$details['subject_map_id'],'exam_year'=>$details['year'],'exam_month'=>$details['month'],'exam_type'=>$details['mark_type']])->one();

            if(!empty($getMArkentry))
            {
                $this->findModelMarkEntry($getMArkentry['coe_mark_entry_id'])->delete();
            }
            if(!empty($CheckAbsent))
            {
                $this->findAbsentMarkEntry($CheckAbsent['coe_absent_entry_id'])->delete();
            }
            if($getMArkentryCia && empty($getMArkentry))
            {
                $this->findModelMarkEntry($getMArkentryCia['coe_mark_entry_id'])->delete();   
            }

            $this->findModel($id)->delete();
            return $this->redirect(['index']);
        }
        else
        {
            $lockUser = Yii::$app->db->createCommand('UPDATE user SET status="11" WHERE id="'.Yii::$app->user->getId().'"')->execute();
            $created_by = $updated_by = Yii::$app->user->getId();
            $created_at = $updated_at = date("Y-m-d H:i:s");
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            $filename =  Yii::getAlias("@webroot").'/access_logs/log_'.date("j.n.Y").'.txt';
            $content  = "User Name: ".Yii::$app->user->getUsername().' - '.date("F j, Y, g:i a").PHP_EOL.
                        "Accessed URLS: ".$url.PHP_EOL.
                        "----------------------------------------------------------------".PHP_EOL;

            //print_r(parse_url($url)); // This will returns the parts of the URL
            
            $removed_path = str_replace('index.php', '', parse_url($url, PHP_URL_PATH));
            $image_path = parse_url($url, PHP_URL_SCHEME)."://".parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PORT).$removed_path.'images/notfound.png'; 
            
            $image_path = Yii::getAlias("@web").'/images/notfound.png'; 

            if(file_put_contents($filename, $content.PHP_EOL , FILE_APPEND | LOCK_EX))
            {   
                $file_content = file_get_contents($filename, true);
                echo "<div style='width:1000px;  text-align: center; margin: 0 auto;'><img src='".$image_path."' alt='not found' height='600' width='900' align='center' /></div>";
                
            }
            unset($_SESSION);
            session_destroy();
            Yii::$app->ShowFlashMessages->setMsg('Error','OOOPS You are not allowed!!! Your Account is Locked!!!');
            return $this->redirect(['site/index']);            
        }
    }

    /**
     * Finds the MarkEntryMaster model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return MarkEntryMaster the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = MarkEntryMaster::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function findModelMarkEntry($id)
    {
        if (($model = MarkEntry::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    protected function findAbsentMarkEntry($id)
    {
        if (($model = AbsentEntry::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function valueReplaceNumber($array_data)
    {
        $array= array('0'=>'ZERO','1'=>'ONE','2'=>'TWO','3'=>'THREE','4'=>'FOUR','5'=>'FIVE','6'=>'SIX','7'=>'SEVEN','8'=>'EIGHT','9'=>'NINE','10'=>'TEN','-'=>'Absent','-1'=>'ABSENT');  
        $return_string='';
        for($i=0;$i<count($array_data);$i++)
        {
            $return_string .=$array[$array_data[$i]]." ";
        }
        return !empty($return_string)?$return_string:'No Data Found';
           
    }
    public function actionCourseanalysis()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        $subject = new Subjects();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('courseanalysis', [
            'model' => $model, 'galley' => $galley, 'subject' => $subject,
        ]);
    }
    public function actionCourseresultanalysis()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $sub_id_get = SubjectsMapping::findOne($_POST['sub_id']);
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();
        $subject_details = Yii::$app->db->createCommand("select subject_code,subject_name from coe_subjects where coe_subjects_id='" . $_POST['sub_id'] . "'")->queryAll();
        $mark_type_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['mark_type'] . "'")->queryScalar();
        $reg_year = Yii::$app->db->createCommand("select distinct(regulation_year) from coe_regulation where coe_batch_id='" . $_POST['batch'] . "' ")->queryScalar();
        $year_exam = $_POST['year'];
        $query_gr = new Query();
        $query_gr->select('grade_name')
            ->from('coe_regulation')
            ->where(['regulation_year' => $reg_year])
            ->andWhere(['NOT', ['grade_name' => '']]);
        $grade_name = $query_gr->createCommand()->queryAll();
        $crse_analysis_query = new Query();
        $crse_analysis_query->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id,description,coe_subjects_id,batch_mapping_id,degree_code,programme_code,CIA_max,ESE_max')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->join('JOIN', 'coe_bat_deg_reg e', 'b.batch_mapping_id=e.coe_bat_deg_reg_id')
            ->join('JOIN', 'coe_degree f', 'e.coe_degree_id=f.coe_degree_id')
            ->join('JOIN', 'coe_programme g', 'e.coe_programme_id=g.coe_programme_id')
            ->where(['c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type'], 'a.coe_subjects_id' =>$_POST['sub_id'],'b.subject_id' =>$_POST['sub_id']]);
        $courseanalysis = $crse_analysis_query->createCommand()->queryAll();
        if (count($courseanalysis) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $data = '<table id="checkAllFeet" class="table table-responsive table-striped" align="center" ><tbody align="center">';
            $data .= '<tr>
                        <td colspan=2> 
                            <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                        </td>
                        <td colspan=12 align="center"> 
                            <center><b><font size="4px">' . $org_name . '</font></b></center>
                            <center>' . $org_address . '</center>
                            
                            <center>' . $org_tagline . '</center> 
                        </td>
                        <td  colspan=2 align="center">  
                            <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                        </td>
                    </tr>';
            $data .= '<tr>
                        <td colspan=16 align="center"><b>' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis - ' . $month . ' ' . $_POST['year'] . '
                        </b></td>
                    </tr>';
            $data .= '<tr><td colspan=16 align="center">';
            foreach ($subject_details as $sub) {
                $data .= '<b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE  </b> = ' . $sub['subject_code'] . '&nbsp;&nbsp;&nbsp;<b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </b> = ' . $sub['subject_name'];
            }
            $data .= '</td></tr>';
            $data .= '<tr>                                                                                                                                
                    <th> S.NO </th> 
                    <th>' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Code</th>                    
                    <th>Enrolled</th>
                    <th>Appeared</th>
                    <th>' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT) . '</th>
                    <th>Withheld</th>
                    <th>Pass</th>
                    <th>Fail</th>
                    <th>Pass %</th>
                    <th>Fail %</th>
                    <th>75% Above</th>
                    <th>60% TO 74%</th>
                    <th>50% TO 59%</th>
                    <th>40% TO 49%</th>
                    <th>30% TO 39%</th>';
            
            $data .= '</tr>';
            $sn = 1;

            foreach ($courseanalysis as $crse) {
                $degree_name_l = strstr($crse['degree_code'], "MBATRISEM") ? "MBA" : $crse['degree_code'];
                $data .= '<tr><td>' . $sn . '</td><td>' . $degree_name_l . ' ' . $crse['programme_code'] . '</td>';
                if ($crse['description'] != 'Elective') {
                    $query_enroll = new Query();
                    if ($mark_type_name == "Regular") {
                        $query_enroll->select('count(student_rel_id)')
                            ->from('coe_student_mapping a')
                            ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                            ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                            ->where(['b.coe_subjects_mapping_id' => $crse['coe_subjects_mapping_id'],'mark_type' => $_POST['mark_type'], 'year' => $year_exam, 'month' => $_POST['month']])
                            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    } else {

                        $query_enroll->select('count(student_map_id)')
                            ->from('coe_student_mapping a')
                            ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                            ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                            ->where(['c.subject_map_id' => $crse['coe_subjects_mapping_id'], 'mark_type' => $_POST['mark_type'], 'year' => $year_exam, 'month' => $_POST['month']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    }
                    $student_enrol = $query_enroll->createCommand()->queryScalar();
                    $data .= '<td align="center">' . $student_enrol . '</td>';
                } else if ($crse['description'] == 'Elective') {
                    $query_enroll = new Query();
                    if ($mark_type_name == "Regular") {
                        $query_enroll->select('count(coe_student_id)')
                            ->from('coe_nominal a')
                            ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id and a.coe_subjects_id=b.subject_id')
                            ->where(['a.course_batch_mapping_id' => $crse['batch_mapping_id'], 'a.coe_subjects_id' => $crse['coe_subjects_id'],'a.semester'=>$crse['semester'],'b.semester'=>$crse['semester']]);
                    } else {
                        $query_enroll->select('count(student_map_id)')
                            ->from('coe_student_mapping a')
                            ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                            ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                            ->where(['c.subject_map_id' => $crse['coe_subjects_mapping_id'], 'mark_type' => $_POST['mark_type'], 'year' => $year_exam, 'month' => $_POST['month'],'b.semester'=>$crse['semester']])
                            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    }
                    $student_enrol = $query_enroll->createCommand()->queryScalar();
                    $data .= '<td align="center">' . $student_enrol . '</td>';
                }
                $query_appeared = new Query();
                $query_appeared->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                    ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['result' => 'Absent']])->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_appeared . '</td>';

                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                    ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])->andWhere(['LIKE','result','Abs']);
                $student_absent = $query_absent->createCommand()->queryScalar();
                $student_absent = $student_absent==0?'-':$student_absent;
                $data .= '<td align="center">' . $student_absent . '</td>';

                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                    ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $student_withheld = $student_withheld==0?'-':$student_withheld;
                $data .= '<td align="center">' . $student_withheld . '</td>';

                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                    ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']]);
                $student_pass = $query_pass->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_pass . '</td>';

                $query_fail = new Query();
                $query_fail->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                    ->join('JOIN', 'coe_mark_entry_master c', 'c.student_map_id=a.coe_student_mapping_id AND b.coe_subjects_mapping_id=c.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'year_of_passing' => '','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT LIKE', 'result','Absent'])
                    ->andWhere(['NOT IN', 'grade_name' ,['WH','wh','w','W']]);
                $student_fail = $query_fail->createCommand()->queryScalar();
                $student_fail = $student_fail==0?'-':$student_fail;
                $data .= '<td align="center">' . $student_fail . '</td>';
                $student_appeared = $student_appeared==0?1:$student_appeared;
                if ($mark_type_name == "Regular") {
                    $pass_percent = ($student_pass / $student_appeared) * 100;
                    $data .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $pass_percent = ($student_pass / $student_appeared) * 100;
                    $data .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                }
                if ($mark_type_name == "Regular") {
                    $fail_percent = ($student_fail / $student_appeared) * 100;
                    $data .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $fail_percent = ($student_fail / $student_appeared) * 100;
                    $data .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                }
                $query_75 = new Query();
                $query_75->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['>=','b.total','75'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_75 = $query_75->createCommand()->queryScalar();
                $student_75 = $student_75==0?'-':$student_75;
                $data .= '<td>' . $student_75 . '</td>';

                $total_maximum_no=$crse['ESE_max']+$crse['CIA_max'];
                $total_75= $total_maximum_no*(75/100);
                $total_74= $total_maximum_no*(74/100);
                $total_60= $total_maximum_no*(60/100);
                $total_59= $total_maximum_no*(59/100);
                $total_50= $total_maximum_no*(50/100);
                $total_49= $total_maximum_no*(49/100);
                $total_40= $total_maximum_no*(40/100);
                $total_39= $total_maximum_no*(39/100);
                $total_30= $total_maximum_no*(30/100);
                $total_29= $total_maximum_no*(29/100);
                $total_20= $total_maximum_no*(20/100);

                $query_74 = new Query();
                $query_74->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN','b.total',$total_60,$total_74])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_74 = $query_74->createCommand()->queryScalar();
                $query_74 = $query_74==0?'-':$query_74;
                $data .= '<td>' . $query_74 . '</td>';

                $query_59 = new Query();
                $query_59->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN','b.total',$total_50,$total_59])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_59 = $query_59->createCommand()->queryScalar();
                $query_59 = $query_59==0?'-':$query_59;
                $data .= '<td>' . $query_59 . '</td>';

                $query_49 = new Query();
                $query_49->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN','b.total',$total_40,$total_49])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_49 = $query_49->createCommand()->queryScalar();
                $query_49 = $query_49==0?'-':$query_49;
                $data .= '<td>' . $query_49 . '</td>';

                $query_39 = new Query();
                $query_39->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN','b.total',$total_30,$total_39])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_39 = $query_39->createCommand()->queryScalar();
                $query_39 = $query_39==0?'-':$query_39;
                $data .= '<td>' . $query_39 . '</td>';
                
                $data .= '</tr>';
                $sn++;
            }
            $data .= "<tr height='45px'><th colspan='18' >&nbsp; <br /><br /><br /><br /><br /><br /><br /><br /></th></tr>";
            $data .= "<tr><th colspan='4' >DATE </th><th colspan='4' >SEAL </th><th colspan='6' >PRINCIPAL </th><th colspan='4' >CONTROLLER OF EXAMINATIONS </th></tr>";
            $data .= '</tbody></table>';
            if (isset($_SESSION['course_analysis_print'])) {
                unset($_SESSION['course_analysis_print']);
            }
            $_SESSION['course_analysis_print'] = $data;
            return $data;
        } else {
            return 0;
        }
    }
    public function actionCourseAnalysisPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['course_analysis_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 16px; }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelCourseanalysis()
    {
        
        $content = $_SESSION['course_analysis_print'];           
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionProgrammeanalysis()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('programmeanalysis', [
            'model' => $model,
        ]);
    }
   /* public function actionProgrammeresultanalysis()
    {
        $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $degree_name = Yii::$app->db->createCommand("select concat(degree_code,'  ',programme_code) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['batch_map_id'] . "'")->queryScalar();
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();

        $mark_type_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['mark_type'] . "'")->queryScalar();
         
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reg_year = Yii::$app->db->createCommand("select regulation_year from coe_bat_deg_reg where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $query_gr = new Query();
        $query_gr->select('grade_name')
            ->from('coe_regulation')
            ->where(['regulation_year' => $reg_year])
            ->andWhere(['NOT', ['grade_name' => '']])->groupBy('grade_name');
        $grade_name = $query_gr->createCommand()->queryAll();

        $query_cr = new Query();
        $query_cr->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id, description,coe_subjects_id,batch_mapping_id,a.CIA_max,ESE_max')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->where(['b.batch_mapping_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type']]);
        $subject_list = $query_cr->createCommand()->queryAll();
        $count = count($grade_name);
        $colspan = 22-$count;
        if (count($subject_list) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $course_result_table = '<br /><br />';
            $course_result_table .= '<table  width="100%" align="center">';
            $course_result_table .= '<tr>
                                        <td colspan=2 align="left" > 
                                            <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                                        </td>
                                        <td colspan="'.$colspan.'" align="center"> 
                                            <center><b><font size="6px">' . $org_name . '</font></b></center>
                                            <center>' . $org_address . '</center>
                                            <center>' . $org_tagline . '</center> 
                                        </td>
                                        <td  colspan=2 align="right">  
                                            <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                                        </td>
                                    </tr>';
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr>

            <tr><td colspan=18 align="center"><b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . strtoupper(' Result Analysis</b> - ') . strtoupper($month . ' ' . $_POST['year']) . '</td></tr>';
            $course_result_table .= '<tr><td colspan=18 align="center"><b>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH)).'</b> - ' . strtoupper($batch_name) . ' <b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . '</b> - ' . strtoupper($degree_name) . '</td></tr>';
            $colspan = 22 - (count($grade_name) + 11); // is the number of columns
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr><tr> <td style="border: none !important;">&nbsp; </td></tr> <tr>                                                                                                                                
                            <td> S. NO </td> 
                            <td>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </td>  
                            <td colspan="' . $colspan . '" >' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </td>
                            <td>ENR</td>
                            <td>APP</td>
                            <td>ABS</td>
                            <td>WIT</td>
                            <td>PA</td>
                            <td>FA</td>
                            <td>PA %</td>
                            <td>FA %</td>
                            <td>75% Above</td>
                            <td>60% TO 74%</td>
                            <td>50% TO 59%</td>
                            <td>40% TO 49%</td>';
           
            $sn = 1;
            foreach ($subject_list as $subject) {
                $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . $subject['subject_name'] . '</td>';
                $query_enroll = new Query();
                $query_enroll->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_enrol = $query_enroll->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_enrol . '</td>';

                $query_appeared = new Query();
                $query_appeared->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT LIKE', 'b.result', 'Absent'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_absent = $query_absent->createCommand()->queryScalar();

                
                $student_absent = $student_absent==0?'-':$student_absent;
                $course_result_table .= '<td align="center">' . $student_absent . '</td>';

                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $student_withheld = $student_withheld==0?'-':$student_withheld;
                $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_pass = $query_pass->createCommand()->queryScalar();

                $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                $query_fail = new Query();
                $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' and result  not like '%Absent%' AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) and grade_name NOT IN('W','WH','w','wh') ";
                $student_fail = Yii::$app->db->createCommand($select_query)->queryScalar();
                $student_enrol = $student_enrol==0?1:$student_enrol;
                $course_result_table .= '<td align="center">' . $student_fail . '</td>';

                $student_enrol = $student_appeared==0?1:$student_appeared;
                if ($mark_type_name == "Regular") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                }
                if ($mark_type_name == "Regular") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                }
                
                $total_maximum_no=$subject['ESE_max']+$subject['CIA_max'];
                $total_75= $total_maximum_no*(75/100);
                $total_74= $total_maximum_no*(74/100);
                $total_60= $total_maximum_no*(60/100);
                $total_59= $total_maximum_no*(59/100);
                $total_50= $total_maximum_no*(50/100);
                $total_49= $total_maximum_no*(49/100);
                $total_40= $total_maximum_no*(40/100);

                $query_75 = new Query();
                $query_75->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['>=', 'total', $total_75])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_75 = $query_75->createCommand()->queryScalar();
                $student_75 = $student_75==0?'-':$student_75;
                $course_result_table .= '<td>' . $student_75 . '</td>';

                $query_74 = new Query();
                $query_74->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_60,$total_74])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_74 = $query_74->createCommand()->queryScalar();
                $query_74 = $query_74==0?'-':$query_74;
                $course_result_table .= '<td>' . $query_74 . '</td>';

                $query_59 = new Query();
                $query_59->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_50,$total_59])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_59 = $query_59->createCommand()->queryScalar();
                $query_59 = $query_59==0?'-':$query_59;
                $course_result_table .= '<td>' . $query_59 . '</td>';

                $query_49 = new Query();
                $query_49->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_40,$total_49])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_49 = $query_49->createCommand()->queryScalar();
                $query_49 = $query_49==0?'-':$query_49;
                $course_result_table .= '<td>' . $query_49 . '</td>';

                

                $course_result_table .= '</tr>';
                $sn++;
            }
            $course_result_table .= "<tr><td colspan='18' >&nbsp; <br /><br /></td></tr>";
            $course_result_table .= "<tr><td colspan='14' > &nbsp; </td>
                                 <td colspan='2'> 
                                        <ol style='line-height: 1.5em;'>
                                            <li>APP : </li>
                                            <li>ENR : </li>
                                            <li>PA : </li>
                                            <li>FA : </li>
                                            <li>WIT : </li>
                                            
                                        </ol>
                                    </td> ";
            $course_result_table .= "<td colspan='2' style='text-align: left;'> 
                                        <ul style='list-style: none !important; line-height: 1.5em;'>
                                            <li><b>APPEARED </b></li>
                                            <li><b>ENROLLED </b></li>
                                            <li><b>PASS </b></li>
                                            <li><b>FAIL </b></li>
                                            <li><b>WITH HELD</b></li>
                                            
                                        </ul>
                                    </td>";
            $course_result_table .= "</tr>";
            $course_result_table .= "<tr height='45px'><th colspan='18' >&nbsp; <br /><br /><br /><br /><br /><br /><br /><br /></th></tr>";
            $course_result_table .= "<tr><th colspan='4' >DATE </th><th colspan='4' >SEAL </th><th colspan='6' >PRINCIPAL </th><th colspan='4' >CONTROLLER OF EXAMINATIONS </th></tr>";
            $course_result_table .= '</tbody></table>';
            if (isset($_SESSION['programme_analysis_print'])) {
                unset($_SESSION['programme_analysis_print']);
            }
            $_SESSION['programme_analysis_print'] = $course_result_table;
            return $course_result_table;
        } else {
            return 0;
        }
    }
    */

    public function actionCaste()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();

        if (Yii::$app->request->post()) 
        {
           
            $batch=$_POST['bat_val'];
            $programme=$_POST['bat_map_val'];
            $year=$_POST['year'];
            $month=$_POST['month'];
            $crse_analysis_query = new Query();
        $crse_analysis_query->select('count(register_number) as  total,course_batch_mapping_id,degree_code')
            ->from('coe_student a')
            ->join('JOIN', 'coe_student_mapping d', 'd.student_rel_id=a.coe_student_id')
            ->join('JOIN','coe_mark_entry_master c','c.student_map_id=d.coe_student_mapping_id')
            ->join('JOIN', 'coe_bat_deg_reg e', 'd.course_batch_mapping_id=e.coe_bat_deg_reg_id')
            ->join('JOIN', 'coe_degree f', 'e.coe_degree_id=f.coe_degree_id')
            ->join('JOIN', 'coe_programme g', 'e.coe_programme_id=g.coe_programme_id')
            ->join('JOIN', 'coe_batch h', 'h.coe_batch_id=e.coe_batch_id')
            ->where(['c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'h.coe_batch_id' => $_POST['bat_val'], 'd.course_batch_mapping_id' =>$_POST['bat_map_val']]);
        $courseanalysis = $crse_analysis_query->createCommand()->queryAll();
        if(!empty($courseanalysis))    
            {
                $month_name = Categorytype::findOne($month);
               require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $header ='<table style="overflow-x:auto;"  cellspacing="0" cellpadding="0" border="0"  class="table table-bordered table-responsive bulk_edit_table table-hover" id = "student_import_results">';
                $header .= ' <tr>
                    
                      <td align="center"> 
                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                      </td>

                      <td colspan=8  align="center"> 
                          <center><b><font size="6px">'.$org_name.'</font></b></center>
                          <center> <font size="3px">'.$org_address.'</font></center>
                          <center> Phone : <b>'.$org_phone.'</b></center>
                          <center class="tag_line"><b>'.$org_tagline.'</b></center> 
                     </td>
                      <td align="center">  
                        <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                      </td>
                    </tr>';
                $header .=' 
                <tr><td colspan=10 ><h2>Result Report</h2></td> </tr>
                    <tr>
                       
                        <th>SNO</th>
                        <th>Total</th>
                        <th>Male</th>
                        <th>Female</th>
                       
                        </tr>';
                $sno=1;
                
                foreach ($courseanalysis as $values) 
                {
                    $header .='<tr>
                           <td>'.$sno.'</td>
                           <td>'.$values["total"].'</td>
                           <td></td>
                           <td></td>
                          
                           </tr>';
                           $sno++;
                }
                $header .='</table>';
                 $header .='</table>';
                $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DUMMY).' MARK.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $header,                     
                    'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; } 
                        
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
                    
                    'methods' => [ 
                        
                        'SetFooter'=>['BORDER LINE MARKS '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
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
              return $this->render('caste', [
                'model' => $model,
                
                ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('error','No Data Found');
                return $this->redirect(['caste']);
            }

        
        }
    }


           // print_r($courseanalysis);exit;






       
     public function actionProgrammeresultanalysis()
    {
        $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $degree_name = Yii::$app->db->createCommand("select concat(degree_code,'  ',programme_code) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['batch_map_id'] . "'")->queryScalar();
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();

        $mark_type_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['mark_type'] . "'")->queryScalar();
         
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reg_year = Yii::$app->db->createCommand("select regulation_year from coe_bat_deg_reg where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $query_gr = new Query();
        $query_gr->select('grade_name')
            ->from('coe_regulation')
            ->where(['regulation_year' => $reg_year])
            ->andWhere(['NOT', ['grade_name' => '']])->groupBy('grade_name');
        $grade_name = $query_gr->createCommand()->queryAll();

        $query_cr = new Query();
        $query_cr->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id, description,coe_subjects_id,batch_mapping_id,a.CIA_max,ESE_max')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->where(['b.batch_mapping_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type']]);
        $subject_list = $query_cr->createCommand()->queryAll();
        $count = count($grade_name);
        $colspan = 22-$count;
        if (count($subject_list) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $course_result_table = '<br /><br />';
            $course_result_table .= '<table  width="100%" align="center">';
            $course_result_table .= '<tr>
                                        <td colspan=2 align="left" > 
                                            <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                                        </td>
                                        <td colspan="'.$colspan.'" align="center"> 
                                            <center><b><font size="6px">' . $org_name . '</font></b></center>
                                            <center>' . $org_address . '</center>
                                            <center>' . $org_tagline . '</center> 
                                        </td>
                                        <td  colspan=2 align="right">  
                                            <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                                        </td>
                                    </tr>';
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr>

            <tr><td colspan=18 align="center"><b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . strtoupper(' Result Analysis</b> - ') . strtoupper($month . ' ' . $_POST['year']) . '</td></tr>';
            $course_result_table .= '<tr><td colspan=18 align="center"><b>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH)).'</b> - ' . strtoupper($batch_name) . ' <b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . '</b> - ' . strtoupper($degree_name) . '</td></tr>';
            $colspan = 22 - (count($grade_name) + 11); // is the number of columns
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr><tr> <td style="border: none !important;">&nbsp; </td></tr> <tr>                                                                                                                                
                            <td> S. NO </td> 
                            <td>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </td>  
                            <td colspan="' . $colspan . '" >' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </td>
                            <td>ENR</td>
                            <td>APP</td>
                            <td>ABS</td>
                            <td>WIT</td>
                            <td>PA</td>
                            <td>FA</td>
                            <td>PA %</td>
                            <td>FA %</td>
                            <td>75% Above</td>
                            <td>60% TO 74%</td>
                            <td>50% TO 59%</td>
                            <td>40% TO 49%</td>';
           
            $sn = 1;
            foreach ($subject_list as $subject) {
                $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . $subject['subject_name'] . '</td>';
                $query_enroll = new Query();
                $query_enroll->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_enrol = $query_enroll->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_enrol . '</td>';

                $query_appeared = new Query();
                $query_appeared->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT LIKE', 'b.result', 'Absent'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_absent = $query_absent->createCommand()->queryScalar();

                
                $student_absent = $student_absent==0?'-':$student_absent;
                $course_result_table .= '<td align="center">' . $student_absent . '</td>';

                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $student_withheld = $student_withheld==0?'-':$student_withheld;
                $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_pass = $query_pass->createCommand()->queryScalar();

                $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                $query_fail = new Query();
                $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' and result  not like '%Absent%' AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) and grade_name NOT IN('W','WH','w','wh') ";
                $student_fail = Yii::$app->db->createCommand($select_query)->queryScalar();
                $student_enrol = $student_enrol==0?1:$student_enrol;
                $course_result_table .= '<td align="center">' . $student_fail . '</td>';

                $student_enrol = $student_appeared==0?1:$student_appeared;
                if ($mark_type_name == "Regular") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                }
                if ($mark_type_name == "Regular") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                }
                
                $total_maximum_no=$subject['ESE_max']+$subject['CIA_max'];
                $total_75= $total_maximum_no*(75/100);
                $total_74= $total_maximum_no*(74/100);
                $total_60= $total_maximum_no*(60/100);
                $total_59= $total_maximum_no*(59/100);
                $total_50= $total_maximum_no*(50/100);
                $total_49= $total_maximum_no*(49/100);
                $total_40= $total_maximum_no*(40/100);

                $student_75 = $query_74=$query_59 =$query_49 ='';
                if($total_maximum_no!=0)
                {
                $query_75 = new Query();
                $query_75->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['>=', 'total', $total_75])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    //echo "<br>".$query_75->createCommand()->getRawSql();
                $student_75 = $query_75->createCommand()->queryScalar();
                $student_75 = $student_75==0?'-':$student_75;
                $course_result_table .= '<td>' . $student_75 . '</td>';

                $query_74 = new Query();
                $query_74->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_60,$total_74])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_74 = $query_74->createCommand()->queryScalar();
                $query_74 = $query_74==0?'-':$query_74;
                $course_result_table .= '<td>' . $query_74 . '</td>';

                $query_59 = new Query();
                $query_59->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_50,$total_59])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_59 = $query_59->createCommand()->queryScalar();
                $query_59 = $query_59==0?'-':$query_59;
                $course_result_table .= '<td>' . $query_59 . '</td>';

                $query_49 = new Query();
                $query_49->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_40,$total_49])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_49 = $query_49->createCommand()->queryScalar();
                $query_49 = $query_49==0?'-':$query_49;
                $course_result_table .= '<td>' . $query_49 . '</td>';

                }
                else
                {
                    $course_result_table .= '<td>-</td>';
                    $course_result_table .= '<td>-</td>';
                    $course_result_table .= '<td>-</td>';
                    $course_result_table .= '<td>-</td>';
                }

                $course_result_table .= '</tr>';
                $sn++;
            }
            $course_result_table .= "<tr><td colspan='18' >&nbsp; <br /><br /></td></tr>";
            $course_result_table .= "<tr><td colspan='14' > &nbsp; </td>
                                 <td colspan='2'> 
                                        <ol style='line-height: 1.5em;'>
                                            <li>APP : </li>
                                            <li>ENR : </li>
                                            <li>PA : </li>
                                            <li>FA : </li>
                                            <li>WIT : </li>
                                            
                                        </ol>
                                    </td> ";
            $course_result_table .= "<td colspan='2' style='text-align: left;'> 
                                        <ul style='list-style: none !important; line-height: 1.5em;'>
                                            <li><b>APPEARED </b></li>
                                            <li><b>ENROLLED </b></li>
                                            <li><b>PASS </b></li>
                                            <li><b>FAIL </b></li>
                                            <li><b>WITH HELD</b></li>
                                            
                                        </ul>
                                    </td>";
            $course_result_table .= "</tr>";
            $course_result_table .= "<tr height='45px'><th colspan='18' >&nbsp; <br /><br /><br /><br /><br /><br /><br /><br /></th></tr>";
            $course_result_table .= "<tr><th colspan='4' >DATE </th><th colspan='4' >SEAL </th><th colspan='6' >PRINCIPAL </th><th colspan='4' >CONTROLLER OF EXAMINATIONS </th></tr>";
            $course_result_table .= '</tbody></table>';
            if (isset($_SESSION['programme_analysis_print'])) {
                unset($_SESSION['programme_analysis_print']);
            }
            $_SESSION['programme_analysis_print'] = $course_result_table;
            return $course_result_table;
        } else {
            return 0;
        }
    }
    public function actionProgrammeAnalysisPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['programme_analysis_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            
                        }
                        table th{
                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                           
                        }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        $pdf->marginTop = "5";
        $pdf->marginLeft = "5";
        $pdf->marginRight = "5";
        $pdf->marginBottom = "5";
        $pdf->marginHeader = "3";
        $pdf->marginFooter = "3";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelProgrammeanalysis()
    {
        
        $content = $_SESSION['programme_analysis_print'];
         
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionRangemarks()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $exam = new ExamTimetable();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Range of Marks Analysis ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('rangemarks', [
            'model' => $model,
            'exam'=>$exam,
        ]);
    }
    public function actionRangemarksConversion()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $exam = new ExamTimetable();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Range of Marks Analysis ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('rangemarks-conversion', [
            'model' => $model,
            'exam'=>$exam,
        ]);
    }

    public function actionRangemarksanalysis()
    {
        $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $degree_name = Yii::$app->db->createCommand("select concat(degree_code,'  ',programme_code) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['batch_map_id'] . "'")->queryScalar();
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();
        $mark_type_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['mark_type'] . "'")->queryScalar();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reg_year = Yii::$app->db->createCommand("select regulation_year from coe_bat_deg_reg where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        
        $val_from = Yii::$app->request->post('val_from');
        $exam_semester = Yii::$app->request->post('exam_semester');
        $exam_subject_code = Yii::$app->request->post('exam_subject_code');
        $val_to = Yii::$app->request->post('val_to');

        $query_gr = new Query();
        $query_gr->select('grade_name')
            ->from('coe_regulation')
            ->where(['regulation_year' => $reg_year])
            ->andWhere(['NOT', ['grade_name' => '']])->groupBy('grade_name');
        $grade_name = $query_gr->createCommand()->queryAll();
        $query_cr = new Query();
        $query_cr->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id, description,coe_subjects_id,batch_mapping_id,a.CIA_max,ESE_max')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->where(['b.batch_mapping_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type'],'b.semester' => $_POST['exam_semester'],'b.coe_subjects_mapping_id' => $_POST['exam_subject_code']]);
        $subject_list = $query_cr->createCommand()->queryAll();
        $count = count($grade_name);
        $colspan = 22-$count;
        if (count($subject_list) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $course_result_table = '<br /><br />';
            $course_result_table .= '<table  width="100%" align="center">';
         
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr>

            <tr><td colspan=18 align="center"><b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . strtoupper(' Result Analysis</b> - ') . strtoupper($month . ' ' . $_POST['year']) . '</td></tr>';
            $course_result_table .= '<tr><td colspan=18 align="center"><b>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH)).'</b> - ' . strtoupper($batch_name) . ' <b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . '</b> - ' . strtoupper($degree_name) . '</td></tr>';
            $colspan = 22 - (count($grade_name) + 11); // is the number of columns
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr><tr> <td style="border: none !important;">&nbsp; </td></tr> <tr>                                                                                                                                
                            <td> S. NO </td> 
                            <td>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </td>  
                            <td colspan="' . $colspan . '" >' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </td>
                            <td>ENR</td>
                            <td>APP</td>
                            <td>ABS</td>
                            <td>WIT</td>
                            <td>PA</td>
                            <td>FA</td>
                            <td>PA %</td>
                            <td>FA %</td>
                            <td>'.$val_from.' TO '.$val_to.'</td>';
           
            $sn = 1;
            foreach ($subject_list as $subject) {
                $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . $subject['subject_name'] . '</td>';
                $query_enroll = new Query();
                $query_enroll->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'semester'=>$_POST['exam_semester']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_enrol = $query_enroll->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_enrol . '</td>';
                $query_appeared = new Query();
                $query_appeared->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT LIKE', 'b.result', 'Absent'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_absent = $query_absent->createCommand()->queryScalar();
                $student_absent = $student_absent==0?'-':$student_absent;
                $course_result_table .= '<td align="center">' . $student_absent . '</td>';
                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $student_withheld = $student_withheld==0?'-':$student_withheld;
                $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_pass = $query_pass->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                $query_fail = new Query();
                $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' and result  not like '%Absent%' AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) ";
                $student_fail = Yii::$app->db->createCommand($select_query)->queryScalar();
                $student_enrol = $student_enrol==0?1:$student_enrol;
                $course_result_table .= '<td align="center">' . $student_fail . '</td>';
                if ($mark_type_name == "Regular") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                }
                if ($mark_type_name == "Regular") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                }
                
                $total_maximum_no=$subject['ESE_max']+$subject['CIA_max'];
                $total_75= $total_maximum_no*($val_to/100);
                $total_74= $total_maximum_no*($val_from/100);

                $query_74 = new Query();
                $query_74->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_74,$total_75])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_74 = $query_74->createCommand()->queryScalar();
                $query_74 = $query_74==0?'-':$query_74;
                $course_result_table .= '<td>' . $query_74 . '</td>';
               
                $course_result_table .= '</tr>';
                $sn++;
            }
            $course_result_table .= "<tr><td colspan='18' >&nbsp; <br /><br /></td></tr>";
            
            $course_result_table .= "<tr height='45px'><th colspan='18' >&nbsp; <br /><br /><br /><br /><br /><br /><br /><br /></th></tr>";
            $course_result_table .= '</tbody></table>';
            if (isset($_SESSION['programme_analysis_print'])) {
                unset($_SESSION['programme_analysis_print']);
            }
            $_SESSION['programme_analysis_print'] = $course_result_table;
            return $course_result_table;
        } else {
            return 0;
        }
    }

    public function actionRangemarksanalysisconversion()
    {
        $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $degree_name = Yii::$app->db->createCommand("select concat(degree_code,'  ',programme_code) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['batch_map_id'] . "'")->queryScalar();
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();
        $mark_type_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['mark_type'] . "'")->queryScalar();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $ese_dummy = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%ESE(dummy)%'")->queryScalar();
        $ese_entry = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%ESE%' ")->queryScalar();
        $ese_exter = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%External%' ")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reg_year = Yii::$app->db->createCommand("select regulation_year from coe_bat_deg_reg where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        
        $val_from = Yii::$app->request->post('val_from');
        $exam_semester = Yii::$app->request->post('exam_semester');
        $exam_subject_code = Yii::$app->request->post('exam_subject_code');
        $val_to = Yii::$app->request->post('val_to');

        $query_gr = new Query();
        $query_gr->select('grade_name')
            ->from('coe_regulation')
            ->where(['regulation_year' => $reg_year])
            ->andWhere(['NOT', ['grade_name' => '']])->groupBy('grade_name');
        $grade_name = $query_gr->createCommand()->queryAll();
        $query_cr = new Query();
        $query_cr->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id, description,coe_subjects_id,batch_mapping_id,a.CIA_max,ESE_max')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->where(['b.batch_mapping_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type'],'b.semester' => $_POST['exam_semester'],'b.coe_subjects_mapping_id' => $_POST['exam_subject_code']]);
        $subject_list = $query_cr->createCommand()->queryAll();
        $count = count($grade_name);
        $colspan = 22-$count;
        if (count($subject_list) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $course_result_table = '<br /><br />';
            $course_result_table .= '<table  width="100%" align="center">';
         
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr>

            <tr><td colspan=18 align="center"><b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . strtoupper(' Result Analysis</b> - ') . strtoupper($month . ' ' . $_POST['year']) . '</td></tr>';
            $course_result_table .= '<tr><td colspan=18 align="center"><b>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH)).'</b> - ' . strtoupper($batch_name) . ' <b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . '</b> - ' . strtoupper($degree_name) . '</td></tr>';
            $colspan = 22 - (count($grade_name) + 11); // is the number of columns
            $course_result_table .= '<tr> <td style="border: none !important;">&nbsp; </td></tr><tr> <td style="border: none !important;">&nbsp; </td></tr> <tr>                                                                                                                                
                            <td> S. NO </td> 
                            <td>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </td>  
                            <td colspan="' . $colspan . '" >' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </td>
                            <td>ENR</td>
                            <td>APP</td>
                            <td>ABS</td>
                            <td>WIT</td>
                            <td>PA</td>
                            <td>FA</td>
                            <td>PA %</td>
                            <td>FA %</td>
                            <td>'.$val_from.' TO '.$val_to.'</td>';
           
            
            $sn = 1;
            foreach ($subject_list as $subject) {
                $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . $subject['subject_name'] . '</td>';
                $query_enroll = new Query();
                $query_enroll->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'semester'=>$_POST['exam_semester']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_enrol = $query_enroll->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_enrol . '</td>';
                $query_appeared = new Query();
                $query_appeared->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT LIKE', 'b.result', 'Absent'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_absent = $query_absent->createCommand()->queryScalar();
                $student_absent = $student_absent==0?'-':$student_absent;
                $course_result_table .= '<td align="center">' . $student_absent . '</td>';
                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $student_withheld = $student_withheld==0?'-':$student_withheld;
                $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_pass = $query_pass->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                $query_fail = new Query();
                $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' and result  not like '%Absent%' AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) ";
                $student_fail = Yii::$app->db->createCommand($select_query)->queryScalar();
                $student_enrol = $student_enrol==0?1:$student_enrol;
                $course_result_table .= '<td align="center">' . $student_fail . '</td>';
                if ($mark_type_name == "Regular") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                }
                if ($mark_type_name == "Regular") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $course_result_table .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                }
                
                $total_maximum_no=$subject['ESE_max']+$subject['CIA_max'];
                $total_75= $total_maximum_no*($val_to/100);
                $total_74= $total_maximum_no*($val_from/100);
                $in_array = [$ese_exter,$ese_dummy,$ese_entry];
                $query_74 = new Query();
                $query_74->select('count(distinct student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['BETWEEN', 'category_type_id_marks', $val_from,$val_to])
                    ->andWhere(['IN', 'category_type_id', $in_array])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_74 = $query_74->createCommand()->queryScalar();
                $query_74 = $query_74==0?'-':$query_74;
                $course_result_table .= '<td>' . $query_74 . '</td>';
               
                $course_result_table .= '</tr>';
                $sn++;
            }
            $course_result_table .= "<tr><td colspan='18' >&nbsp; <br /><br /></td></tr>";
            
            $course_result_table .= "<tr height='45px'><th colspan='18' >&nbsp; <br /><br /><br /><br /><br /><br /><br /><br /></th></tr>";
            $course_result_table .= '</tbody></table>';
            if (isset($_SESSION['programme_analysis_print_conversion'])) {
                unset($_SESSION['programme_analysis_print_conversion']);
            }
            $_SESSION['programme_analysis_print_conversion'] = $course_result_table;
            return $course_result_table;
        } else {
            return 0;
        }
    }
    public function actionRangeAnalysisPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['programme_analysis_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            
                        }
                        table th{
                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                           
                        }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        $pdf->marginTop = "5";
        $pdf->marginLeft = "5";
        $pdf->marginRight = "5";
        $pdf->marginBottom = "5";
        $pdf->marginHeader = "3";
        $pdf->marginFooter = "3";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelRangeanalysis()
    {
        
        $content = $_SESSION['programme_analysis_print'];
         
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionRangeAnalysisPdfConversion()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['programme_analysis_print_conversion'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            
                        }
                        table th{
                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                           
                        }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        $pdf->marginTop = "5";
        $pdf->marginLeft = "5";
        $pdf->marginRight = "5";
        $pdf->marginBottom = "5";
        $pdf->marginHeader = "3";
        $pdf->marginFooter = "3";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelRangeanalysisConversion()
    {
        
        $content = $_SESSION['programme_analysis_print_conversion'];
         
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    //Subject Information starts here
    public function actionSubjectinformation()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Information');
        return $this->render('subjectinformation', [
            'model' => $model, 'galley' => $galley,
        ]);
    }
    public function actionSubjectinformationdata()
    {
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $month_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $month . "'")->queryScalar();
        
        $table = "";
        $sn = 1;
        $query = new Query();
        $query->select('F.batch_name,C.degree_code,B.programme_name,B.programme_code,E.semester,D.subject_code,D.subject_name,D.CIA_max,D.ESE_max,D.total_minimum_pass,D.credit_points,D.CIA_min,D.ESE_min,G.description as paper_type,H.description as subject_type,I.description as course_type,paper_no,part_no')
            ->from('coe_bat_deg_reg A')
            ->join('JOIN', 'coe_programme B', 'A.coe_programme_id=B.coe_programme_id')
            ->join('JOIN', 'coe_degree C', 'A.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_subjects_mapping E', 'A.coe_bat_deg_reg_id=E.batch_mapping_id')
            ->join('JOIN', 'coe_subjects D', 'E.subject_id=D.coe_subjects_id')
            ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
            ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=E.subject_type_id')
            ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=E.course_type_id')
            ->join('JOIN', 'coe_batch F', 'A.coe_batch_id=F.coe_batch_id')
            ->join('JOIN', 'coe_mark_entry_master MN', 'MN.subject_map_id=E.coe_subjects_mapping_id')
            ->where(['MN.year'=>$year,'MN.month'=>$month])->groupBy('D.subject_code')->orderBy('semester');
        $subject = $query->createCommand()->queryAll();
       
        if (count($subject) > 0) {


            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table id="checkAllFeet" class="table table-responsive table-striped" align="center" ><tbody align="center">';
            $table .= '
                        <tr>
                            <th colspan=2> 
                                <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                            </th>
                            <th colspan=11 align="center"> 
                                <center><b><font size="6px">' . $org_name . '</font></b></center>
                                <center>' . $org_address . '</center>
                                
                                <center>' . $org_tagline . '</center> 
                            </th>
                            <th  colspan=2 align="center">  
                                <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                            </th>
                            
                        </tr>
                        <tr>
                        <td colspan=14>  '.$year.'-'.strtoupper($month_name).' '. strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)).' INFORMATION </b> </td>
                        </tr>';


            $table .= '<tr>
                        <td><b> S.NO </b></td>
                        <td><b> Year </b></td>
                        <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . '  </b></td>
                        
                        <td><b> Semester </b></td>
                        <td><b> Part No </b></td>
                        <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </b></td>
                        <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </b></td>
                        
                        <td><b> CIA Min </b></td>
                        <td><b> CIA Max </b></td>
                        <td><b> ESE Min </b></td>
                        <td><b> ESE Max </b></td>
                        <td><b> Total Min </b></td>
                        <td><b> Credits </b></td>
                        
                        <td><b> Paper Type </b></td>
                        <td><b> Course Type </b></td>
                    </tr>';
            foreach ($subject as $subject1) {
                $table .= '
                    <tr>
                        <td> ' . $sn . ' </td>
                        <td> ' . $year . ' </td>
                        <td> ' . strtoupper($subject1['degree_code']."-".$subject1['programme_code']) . ' </td>
                        <td> ' . $subject1['semester'] . ' </td>
                        <td> ' . $subject1['part_no'] . ' </td>
                        <td> ' . $subject1['subject_code'] . ' </td>
                        <td> ' . $subject1['subject_name'] . ' </td>
                        
                        <td> ' . $subject1['CIA_min'] . ' </td>
                        <td> ' . $subject1['CIA_max'] . ' </td>
                        <td> ' . $subject1['ESE_min'] . ' </td>
                        <td> ' . $subject1['ESE_max'] . ' </td>
                        <td> ' . $subject1['total_minimum_pass'] . ' </td>
                        <td> ' . $subject1['credit_points'] . ' </td>
                        <td> ' . $subject1['paper_type'] . ' </td>
                        <td> ' . $subject1['subject_type'] . ' </td>
                    </tr>';
                $sn++;
            }
            $table .= '</table>';
        } else {
            $table .= 0;
        }
        if (isset($_SESSION['subject_information_print'])) {
            unset($_SESSION['subject_information_print']);
        }
        $_SESSION['subject_information_print'] = $table;
        return $table;
    }
    public function actionSubjectinformationPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['subject_information_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information .pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; } 
                        
                        table td{
                            border: 1px solid #000;                            
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            
                        }
                        table th{
                            border: 1px solid #000;
                           
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                           
                        }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information"],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelSubjectinformation()
    {
        
            $content = $_SESSION['subject_information_print'];
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    //Subject Information starts here
    public function actionSubjectinformationEngg()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();

        if (isset($_POST['sub_info_internet'])) 
        {
            $year = $_POST['mark_year'];
            $month = $_POST['mark_month'];
            $month_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $month . "'")->queryScalar();
            $table = "";
            $sn = 1;
            $query = new Query();
            $query->select(['D.subject_code','D.subject_name','G.description as paper_type'])
                ->from('coe_subjects_mapping E')
                ->join('JOIN', 'coe_subjects D', 'E.subject_id=D.coe_subjects_id')
                ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
                ->join('JOIN', 'coe_mark_entry_master MN', 'MN.subject_map_id=E.coe_subjects_mapping_id')
                ->where(['MN.year'=>$year,'MN.month'=>$month])->groupBy('D.subject_code')->orderBy('subject_code,semester');
            $subject = $query->createCommand()->queryAll();

            $query_1 = new Query();
            $query_1->select(['CONCAT(D.subject_code,"-",sub_cat_code) as subject_code', 'CONCAT(D.subject_name,"-",E.sub_cat_name) as subject_name','G.description as paper_type'])
                ->from('coe_mandatory_subcat_subjects E') 
                ->join('JOIN', 'coe_mandatory_subjects D', 'E.man_subject_id=D.coe_mandatory_subjects_id and D.batch_mapping_id=E.batch_map_id and D.man_batch_id=E.coe_batch_id')
                ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
                ->join('JOIN', 'coe_mandatory_stu_marks MN', 'MN.subject_map_id=E.coe_mandatory_subcat_subjects_id')
                ->where(['MN.year'=>$year,'MN.month'=>$month])->groupBy('D.subject_code,E.sub_cat_code')->orderBy('subject_code,MN.semester');
            $subject_1 = $query_1->createCommand()->queryAll();
            if(!empty($subject_1))
            {
                $subject = array_merge($subject,$subject_1);
            }
            if (count($subject) > 0) {


                require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $table = '<table width="100%" border=1 align="center" >';
               

                $table .= '<tr>
                            <th> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE</th>
                            <th> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </th>                        
                            <th>FLAGS</th>
                        </tr>';
                foreach ($subject as $subject1) 
                {
                    $flag = $subject1['paper_type']=='Practical' ? 0 : 1;
                    if(strpos($subject1['subject_name'],'MANDATORY COURSE') !== FALSE)
                    {
                        $flag=0;
                    }

                    $table .= '
                        <tr>                       
                            <td> ' . $subject1['subject_code'] . ' </td>
                            <td> ' . $subject1['subject_name'] . ' </td>                       
                            <td> ' . $flag . ' </td>
                        </tr>';
                    $sn++;
                }
                $table .= '</table>';
            } else {
                $table .= 0;
            }
            if (isset($_SESSION['subject_information_print'])) {
                unset($_SESSION['subject_information_print']);
            }
            $_SESSION['subject_information_print'] = $table;
            return $this->render('subjectinformation-engg', [
                'model' => $model, 'galley' => $galley,'table'=>$table,
            ]);
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Information');
            return $this->render('subjectinformation-engg', [
                'model' => $model, 'galley' => $galley,
            ]);
        }

        
    }
    public function actionSubjectinformationdataengg()
    {
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $month_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $month . "'")->queryScalar();
        $table = "";
        $sn = 1;
        $query = new Query();
        $query->select(['D.subject_code','D.subject_name','G.description as paper_type'])
            ->from('coe_subjects_mapping E')
            ->join('JOIN', 'coe_subjects D', 'E.subject_id=D.coe_subjects_id')
            ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
            ->join('JOIN', 'coe_mark_entry_master MN', 'MN.subject_map_id=E.coe_subjects_mapping_id')
            ->where(['MN.year'=>$year,'MN.month'=>$month])->groupBy('D.subject_code')->orderBy('subject_code,semester');
        $subject = $query->createCommand()->queryAll();

        $query_1 = new Query();
        $query_1->select(['CONCAT(D.subject_code,"-",sub_cat_code) as subject_code', 'CONCAT(D.subject_name,"-",E.sub_cat_name) as subject_name','G.description as paper_type'])
            ->from('coe_mandatory_subcat_subjects E') 
            ->join('JOIN', 'coe_mandatory_subjects D', 'E.man_subject_id=D.coe_mandatory_subjects_id and D.batch_mapping_id=E.batch_map_id and D.man_batch_id=E.coe_batch_id')
            ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
            ->join('JOIN', 'coe_mandatory_stu_marks MN', 'MN.subject_map_id=E.coe_mandatory_subcat_subjects_id')
            ->where(['MN.year'=>$year,'MN.month'=>$month])->groupBy('D.subject_code,E.sub_cat_code')->orderBy('subject_code,MN.semester');
        $subject_1 = $query_1->createCommand()->queryAll();
        if(!empty($subject_1))
        {
            $subject = array_merge($subject,$subject_1);
        }
        if (count($subject) > 0) {


            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table width="100%" border=1 align="center" >';
           

            $table .= '<tr>
                        <th> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE</th>
                        <th> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </th>                        
                        <th>FLAGS</th>
                    </tr>';
            foreach ($subject as $subject1) 
            {
                $flag = $subject1['paper_type']=='Theory' ? 1 : 0;
                if(strpos($subject1['subject_name'],'MANDATORY COURSE') !== FALSE)
                {
                    $flag=0;
                }

                $table .= '
                    <tr>                       
                        <td> ' . $subject1['subject_code'] . ' </td>
                        <td> ' . $subject1['subject_name'] . ' </td>                       
                        <td> ' . $flag . ' </td>
                    </tr>';
                $sn++;
            }
            $table .= '</table>';
        } else {
            $table .= 0;
        }
        if (isset($_SESSION['subject_information_print'])) {
            unset($_SESSION['subject_information_print']);
        }
        $_SESSION['subject_information_print'] = $table;
        return $table;
    }
    public function actionSubjectinformationEnggPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['subject_information_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information .pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; } 
                        
                        table td{
                            border: 1px solid #000;                            
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            
                        }
                        table th{
                            border: 1px solid #000;
                           
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                           
                        }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information"],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelSubjectinformationEnggFormat()
    {
        
            $content = $_SESSION['subject_information_print'];
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information " . date('Y-m-d-H-i-s') . '.xls';
        $options =  ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionStudentwisearrear()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            
            $query = new  Query();
            $query->select(["distinct (H.subject_code) as subject_code", "concat(D.degree_code,'-',E.programme_code) as degree_code", "A.register_number", "A.name", "H.subject_name", "G.semester", "F.year",'F.student_map_id','F.subject_map_id', "E.programme_name", "K.batch_name", "D.degree_name",'I.description as paper_type'])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id and G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=G.paper_type_id');
                $query->Where(['A.student_status' => 'Active', 'F.year_of_passing' => ''])->andWhere(['<=','F.year',$_POST['mark_year']]);            
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.subject_map_id,F.student_map_id');
            $query->orderBy('G.semester,degree_code,register_number');
            $studentwisearrear = $query->createCommand()->queryAll();

            $studentwisearrear = array_map("unserialize", array_unique(array_map("serialize", $studentwisearrear)));

            if (!empty($studentwisearrear)) {
                return $this->render('studentwisearrear', [
                    'model' => $model,
                    'studentwisearrear' => $studentwisearrear,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry/studentwisearrear']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to INTERNET COPY ARREAR LIST ');
            return $this->render('studentwisearrear', [
                'model' => $model,
            ]);
        }
    }
    public function actionStudentWiseArrearPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
       
            $content = $_SESSION['studentwisearrear'];
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "INTERNET COPY ARREAR LIST.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . " WISE ARREAR "],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ["INTERNET COPY ARREAR LIST " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionStudentWiseArrearExcel()
    {        
        $content = $_SESSION['studentwisearrear'];
        $fileName = "INTERNET COPY ARREAR LIST.xls";
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    // Result Publish Starts here
    public function actionModerationBorderLine()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $model = new MarkEntry();
        if ($model->load(Yii::$app->request->post())) {
            if (empty($_POST['bat_map_val']) && empty($model->month) && empty($model->year) && empty($_POST['exam_semester'])) {
                Yii::$app->ShowFlashMessages->setMsg('Error', 'Select the required Information');
                return $this->redirect(['mark-entry-master/moderation-border-line']);
            }
            $sem = ConfigUtilities::semCaluclation($model->year, $model->month, $_POST['bat_map_val']);
            $exam_type = $sem == $_POST['exam_semester'] ? 'Regular' : 'Arrear';
            $cat_id = Categorytype::find()->where(['category_type' => $exam_type])->one();
            $section = $_POST['sec'] != 'All' ? $_POST['sec'] : '';
            $query = new Query();
            $query->select(['q.subject_map_id', 'q.student_map_id', 'a.name', 'a.register_number', 'concat(h.degree_code,".",h.degree_name) as degree_name', 'g.programme_code', 'k.semester', 'l.subject_name', 'l.subject_code', 'l.CIA_max','l.CIA_min', 'l.ESE_max','l.ESE_min', 'l.total_minimum_pass', 'l.end_semester_exam_value_mark', 'm.batch_name', 'q.year', 'q.CIA', 'q.ESE', 'q.total','q.withheld' ,'q.grade_point', 'q.grade_name', 'n.description as month', 'q.result'])
                ->from('coe_student a')
                ->join('JOIN', 'coe_student_mapping c', 'c.student_rel_id = a.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg d', 'd.coe_bat_deg_reg_id = c.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h', 'h.coe_degree_id = d.coe_degree_id')
                ->join('JOIN', 'coe_programme g', 'g.coe_programme_id = d.coe_programme_id')
                ->join('JOIN', 'coe_batch m', 'm.coe_batch_id = d.coe_batch_id')
                ->join('JOIN', 'coe_subjects_mapping k', 'k.batch_mapping_id = d.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_subjects l', 'l.coe_subjects_id = k.subject_id')
                ->join('JOIN', 'coe_mark_entry_master q', 'q.subject_map_id = k.coe_subjects_mapping_id and q.student_map_id=c.coe_student_mapping_id')
                ->join('JOIN', 'coe_category_type n', 'n.coe_category_type_id = q.month')
                ->join('JOIN','coe_category_type xyz','xyz.coe_category_type_id = status_category_type_id')
                ->where(['c.course_batch_mapping_id' => $_POST['bat_map_val'], 'k.batch_mapping_id' => $_POST['bat_map_val'], 'k.semester' => $_POST['exam_semester'], 'q.year' => $model->year, 'q.month' => $model->month, 'q.mark_type' => $cat_id->coe_category_type_id, 'a.student_status' => 'Active'])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['NOT LIKE','xyz.description', 'Detain'])
                ->andWhere(['NOT LIKE','xyz.description', 'Discontinued']);
            if ($section != "") {
                $query->andWhere(['c.section_name' => $_POST['sec']]);
            }
            $query->groupBy('q.subject_map_id,q.student_map_id')
                ->orderBy('a.register_number,l.subject_code');
            $send_result = $query->createCommand()->queryAll();


            $query_man = new  Query();
            $query_man->select(['F.student_map_id','H.subject_code',  'A.name', 'A.register_number', 'H.subject_name','H.ESE_max','H.ESE_min', 'H.CIA_max','H.CIA_min', 'H.end_semester_exam_value_mark', 'K.description as month',  'F.year','F.subject_map_id','F.student_map_id', 'F.ESE','F.CIA', 'F.total','F.result','F.semester' ,'F.withheld','F.grade_name', 'F.grade_point', 'concat(degree_code,".",degree_name) as degree_name','E.programme_code','total_minimum_pass','batch_name'])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_mandatory_stu_marks as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
                ->join('JOIN', 'coe_mandatory_subcat_subjects as G', 'G.coe_mandatory_subcat_subjects_id=F.subject_map_id')
                ->join('JOIN', 'coe_mandatory_subjects H', 'H.coe_mandatory_subjects_id=G.man_subject_id');
            $query_man->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], "F.semester" => $_POST['exam_semester'], 'F.year' => $model->year, 'F.month' => $model->month,'F.mark_type' => $cat_id->coe_category_type_id, 'A.student_status' => 'Active','is_additional'=>'NO'])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            if ($section != "") {
                $query_man->andWhere(['section_name' => $_POST['sec']]);
            }
            $query_man->groupBy('F.subject_map_id,F.student_map_id')
                ->orderBy('A.register_number,subject_code');               
            $mandatory_statement = $query_man->createCommand()->queryAll();

            if(!empty($mandatory_statement))
            {
                $send_result = array_merge($send_result,$mandatory_statement);
            }
            
            array_multisort(array_column($send_result, 'subject_code'),  SORT_ASC, $send_result);
            array_multisort(array_column($send_result, 'register_number'),  SORT_ASC, $send_result);

            $subject_get_data = new  Query();
            $subject_get_data->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max, H.end_semester_exam_value_mark as total')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
                ->join('JOIN','coe_category_type xyz','xyz.coe_category_type_id = status_category_type_id');
            $subject_get_data->Where(['F.year' => $model->year, 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $_POST['exam_semester'], 'F.month' => $model->month])
                             ->andWhere(['NOT LIKE','xyz.description', 'Detain']);
            if ($section != "") {
                $subject_get_data->andWhere(['B.section_name' => $_POST['sec']]);
            }
            $subject_get_data->orderBy('H.subject_code');
            $subjectsInfo = $subject_get_data->createCommand()->queryAll();


            $query_man_subs = new  Query();
            $query_man_subs->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max, H.end_semester_exam_value_mark as total')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mandatory_stu_marks as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
                ->join('JOIN', 'coe_mandatory_subcat_subjects as G', 'G.coe_mandatory_subcat_subjects_id=F.subject_map_id')
                ->join('JOIN', 'coe_mandatory_subjects H', 'H.coe_mandatory_subjects_id=G.man_subject_id');
            $query_man_subs->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.year' => $model->year, 'F.month' =>$model->month, 'A.student_status' => 'Active','F.semester'=>$_POST['exam_semester'],'is_additional'=>'NO'])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
           if ($section != "") {
                $query_man_subs->andWhere(['section_name' => $_POST['sec']]);
            }
            $query_man_subs->orderBy('H.subject_code');
               
            $mandatory_subjects = $query_man_subs->createCommand()->queryAll();
            if(!empty($mandatory_subjects))
            {
                $subjectsInfo = array_merge($subjectsInfo,$mandatory_subjects);
            }            
            array_multisort(array_column($subjectsInfo, 'subject_code'),  SORT_ASC, $subjectsInfo);


            $countQuery = new  Query();
            $countQuery->select('count( distinct H.subject_code) as count')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $countQuery->Where(['F.year' => $model->year, 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $model->month])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            if ($section != "") {
                $countQuery->andWhere(['B.section_name' => $_POST['sec']]);
            }
            $countOfSubjects = $countQuery->createCommand()->queryAll();


            $query_man_count = new  Query();
            $query_man_count->select('count( distinct H.subject_code) as count')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mandatory_stu_marks as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_mandatory_subcat_subjects as G', 'G.coe_mandatory_subcat_subjects_id=F.subject_map_id')
                ->join('JOIN', 'coe_mandatory_subjects H', 'H.coe_mandatory_subjects_id=G.man_subject_id');
            $query_man_count->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.year' => $model->year, 'F.month' => $model->month, 'A.student_status' => 'Active'])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            if ($section != "") {
                $query_man_count->andWhere(['B.section_name' => $_POST['sec']]);
            }
            $query_man_count->groupBy('F.student_map_id,F.subject_map_id')
                ->orderBy('A.register_number');
            $countOfManSubjects = $query_man_count->createCommand()->queryAll();
            if(!empty($countOfManSubjects))
            {
                $countOfSubjects = array_merge($countOfSubjects,$countOfManSubjects);
            }
            if (empty($send_result)) {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry-master/moderation-border-line']);
            }
            return $this->render('moderation-border-line', [
                'model' => $model,
                'send_result' => $send_result,
                'countOfSubjects' => $countOfSubjects,
                'subjectsInfo' => $subjectsInfo,
            ]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Result Publish');
            return $this->render('moderation-border-line', [
                'model' => $model,
            ]);
        }
    }
    public function actionResultPublishPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['result_publish'];
          
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Result Publish.pdf',
            'format' => Pdf::FORMAT_LEGAL,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            //'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse; font-family:Helvetica Neue, Helvetica, Arial, sans-serif; width:100%; font-size: 16px !important;  }

                        table td table{border: none !important;}
                        table td{
                          white-space: nowrap;
                            font-size: 13px !important; 
                            border: 1px solid #CCC;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                            padding: 1px;
                        }
                        table th{                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                        }
                       
                    }   
                ', 
            'options' => ['title' => 'Result Publish'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                
            ]
        ]);
        $pdf->marginTop = "6";
        $pdf->marginLeft = "16";
        $pdf->marginRight = "2";
        $pdf->marginBottom = "2";
        $pdf->marginHeader = "2";
        $pdf->marginFooter = "4";
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelResultPublish()
    {
        
            $content = $_SESSION['result_publish'];
            
        $fileName = "Result Publish " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    //Subject Information Ends here
    //Withdraw starts here
    public function actionWithdrawWithoutMarks()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();
        $sn = Yii::$app->request->post('sn');
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        $exam_data_show_xam_type = Categorytype::find()->where(['description'=>'Regular'])->one();
        $mark_type = $exam_data_show_xam_type->coe_category_type_id;
        $exam_data_show_exam_term = Categorytype::find()->where(['description'=>'End'])->one();
        $term = $exam_data_show_exam_term->coe_category_type_id;
        $internal = Categorytype::find()->where(['description'=>'Internal'])->orWhere(['description'=>'CIA'])->one();
        $external = Categorytype::find()->where(['description'=>'ESE'])->orWhere(['description'=>'External'])->one();
        $term = $exam_data_show_exam_term->coe_category_type_id;
        if(Yii::$app->request->post())
        {
            for ($k = 1; $k <= $sn; $k++) 
            {
                if (isset($_POST["withdraw" . $k])) 
                {
                    $check_CIA_marks = MarkEntry::find()->where(['category_type_id'=>$internal->coe_category_type_id,'student_map_id'=>$_POST['stu_map_id'],'subject_map_id'=>$_POST['sub_code' . $k]])->orderBy('coe_mark_entry_id desc')->one();
                    if(!empty($check_CIA_marks))
                    {
                        $model_save = new MarkEntry();
                        $model_save->student_map_id = $_POST['stu_map_id'];
                        $model_save->subject_map_id = $_POST['sub_code' . $k];
                        $model_save->category_type_id = $external->coe_category_type_id;
                        $model_save->category_type_id_marks = 0;
                        $model_save->year = $_POST['withdraw_year'];
                        $model_save->month = $_POST['withdraw_month'];
                        $model_save->mark_type = $mark_type;
                        $model_save->term = $term;
                        $model_save->status_id = 0;
                        $model_save->attendance_percentage = '75';
                        $model_save->attendance_remarks = 'Allowed';
                        $model_save->created_by = $updated_by;
                        $model_save->created_at = $updated_at;
                        $model_save->updated_by = $updated_by;
                        $model_save->updated_at = $updated_at;

                        $check_mark_entry_wd = MarkEntry::find()->where(['category_type_id'=>$external->coe_category_type_id,'student_map_id'=>$_POST['stu_map_id'],'subject_map_id'=>$_POST['sub_code' . $k],'year'=>$_POST['withdraw_year'],'month'=>$_POST['withdraw_month'],'mark_type'=>$mark_type])->orderBy('coe_mark_entry_id desc')->one();
                        $check_mark_entry_master_wd = MarkEntryMaster::find()->where(['student_map_id'=>$_POST['stu_map_id'],'subject_map_id'=>$_POST['sub_code' . $k],'year'=>$_POST['withdraw_year'],'month'=>$_POST['withdraw_month'],'mark_type'=>$mark_type])->one();
                        if(empty($check_mark_entry_wd) && empty($check_mark_entry_master_wd) && $model_save->save(false))
                        {
                            $MarkEntryMasterModel = new MarkEntryMaster();
                            $MarkEntryMasterModel->student_map_id = $_POST['stu_map_id'];
                            $MarkEntryMasterModel->subject_map_id = $_POST['sub_code' . $k];
                            $MarkEntryMasterModel->CIA = $check_CIA_marks['category_type_id_marks'];
                            $MarkEntryMasterModel->ESE = 0;
                            $MarkEntryMasterModel->total = $check_CIA_marks['category_type_id_marks'];
                            $MarkEntryMasterModel->result = 'Absent';
                            $MarkEntryMasterModel->grade_point = 0;
                            $MarkEntryMasterModel->grade_name = 'WD';
                            $MarkEntryMasterModel->year = $_POST['withdraw_year'];
                            $MarkEntryMasterModel->month = $_POST['withdraw_month'];
                            $MarkEntryMasterModel->term = $term;
                            $MarkEntryMasterModel->mark_type = $mark_type;
                            $MarkEntryMasterModel->year_of_passing = '';
                            $MarkEntryMasterModel->withdraw='WD';
                            $MarkEntryMasterModel->attempt = 1;
                            $MarkEntryMasterModel->status_id = 0;
                            $MarkEntryMasterModel->created_by = $updated_by;
                            $MarkEntryMasterModel->created_at = $updated_at;
                            $MarkEntryMasterModel->updated_by = $updated_by;
                            $MarkEntryMasterModel->updated_at = $updated_at;
                            if($MarkEntryMasterModel->save(false))
                            {
                                Yii::$app->ShowFlashMessages->setMsg('Success', 'With Draw Status Updated Successfully!!');
                            }
                            unset($MarkEntryMasterModel);
                            unset($model_save);
                        }
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error', 'Upload CIA Marks To Add the Student as Withdrawal');
                        return $this->redirect(['mark-entry-master/withdraw-without-marks']);
                    }
                } 
            }// For Loop Ends Here 
            return $this->redirect(['mark-entry-master/withdraw-without-marks']);
        }
        else
        {
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Withdraw Entry');
        return $this->render('withdraw-without-marks', ['model' => $model, 'student' => $student, 'subject' => $subject]);
        }

    }

    public function actionWithdrawsublist()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $sem = Yii::$app->request->post('sem');
        $reg = Yii::$app->request->post('reg');

        $check_result_publish = ConfigUtilities::getResultPublishStatus($year,$month);
        if($check_result_publish==1)
        {
            Yii::$app->ShowFlashMessages->setMsg('Error', 'Results already published');
            return $this->redirect(['withdraw-without-marks']);
        }

        $mark_check_fail = Yii::$app->db->createCommand("select coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry_master as E where A.coe_subjects_id=B.subject_id and C.coe_student_id=D.student_rel_id and B.coe_subjects_mapping_id=E.subject_map_id and D.coe_student_mapping_id=E.student_map_id and C.register_number='" . $reg . "' and B.semester<='" . $sem . "' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and E.year<='" . $year . "' and E.month<='" . $month . "' and result like '%fail%' and status_id=1")->queryAll();

        $check_withdraw = Yii::$app->db->createCommand("select coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry_master as E where A.coe_subjects_id=B.subject_id and C.coe_student_id=D.student_rel_id and B.coe_subjects_mapping_id=E.subject_map_id and D.coe_student_mapping_id=E.student_map_id and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and C.register_number='" . $reg . "' and E.withdraw like '%WD%'  and status_id=1")->queryAll();

        if(!empty($mark_check_fail))
        {
            Yii::$app->ShowFlashMessages->setMsg('Error', '<b> '.$reg. ' NOT <b>ELIGIBLE</b> for WITHDRAWAL');
            return $this->redirect(['withdraw-without-marks']);
        }
       
        $query_man = new Query();
        $query_man->select('subject_map_id')
            ->from('coe_subjects A')
            ->join('JOIN', 'coe_subjects_mapping B', 'B.subject_id=A.coe_subjects_id')
            ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=B.batch_mapping_id')
            ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
            ->join('JOIN', 'coe_mark_entry_master C', 'C.subject_map_id=B.coe_subjects_mapping_id and C.student_map_id=E.coe_student_mapping_id')
            ->where(['register_number' => $reg,'semester' => $sem,'year'=>$year,'month'=>$month ])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type]);
        $getSubsList = $query_man->createCommand()->queryAll();
        $subMaps = array_filter(['']);
        if(!empty($getSubsList))
        {
            foreach ($getSubsList as $key => $valuessa) 
            {
               $subMaps[$valuessa['subject_map_id']]=$valuessa['subject_map_id'];
            }
        }

        $stu_elective = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Elective%'")->queryScalar();
        $query = new Query();
        $query->select('coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id')
            ->from('coe_subjects A')
            ->join('JOIN', 'coe_subjects_mapping B', 'B.subject_id=A.coe_subjects_id')
            ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=B.batch_mapping_id')
            ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
            ->where(['register_number' => $reg,'semester' => $sem ])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
            ->andWhere(['<>', 'subject_type_id', $stu_elective]);
            if(!empty($subMaps))
            {
                $query->andWhere(['NOT IN', 'coe_subjects_mapping_id', $subMaps]);
            }
            $query->orderBy('semester,paper_no');
        $subject_common = $query->createCommand()->queryAll();

        $query_ele = new Query();
        $query_ele->select('coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id')
            ->from('coe_subjects A')
            ->join('JOIN', 'coe_subjects_mapping B', 'B.subject_id=A.coe_subjects_id')
            ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=B.batch_mapping_id')
            ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
            ->join('JOIN', 'coe_nominal C', 'C.coe_student_id=D.coe_student_id and C.semester=B.semester and C.coe_student_id=E.student_rel_id and C.coe_subjects_id=A.coe_subjects_id and C.coe_subjects_id=B.subject_id and C.course_batch_mapping_id=E.course_batch_mapping_id and C.course_batch_mapping_id=B.batch_mapping_id')
            ->where(['register_number' => $reg,'B.semester' => $sem,'C.semester' => $sem,'subject_type_id'=>$stu_elective ])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type]);
        if(!empty($subMaps))
        {
            $query_ele->andWhere(['NOT IN', 'coe_subjects_mapping_id', $subMaps]);
        }
        $query_ele->orderBy('B.semester,paper_no');
        $subject_elective = $query_ele->createCommand()->queryAll();
        
        if(!empty($subject_elective))
        {
            $sub_list = array_merge($subject_common,$subject_elective);
        }
        else
        {
            $sub_list = $subject_common;
        }

        $table = '';
        $sn = 1;
        $table .= '<table id="checkAllFeat" class="table table-striped" align="right" border=1>     
                   <thead id="t_head">                                                                                                               
                    <th> S.NO </th> 
                    <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </th>
                    <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </th>  
                    <th> Status </th>
                    </thead><tbody>';
        if (count($sub_list) > 0) 
        {
            foreach ($sub_list as $sublist) {
                $table .= "<tr>" .
                    "<td><input type='hidden' name='sn' value=" . $sn . ">" . $sn . "</td> " .
                    "<td><input type='hidden' name=sub_code" . $sn . " value='" . $sublist['coe_subjects_mapping_id'] . "'>" . $sublist['subject_code'] . "</td>" .
                    "<td><input type='hidden' name=sub_name" . $sn . " value='" . $sublist['subject_name'] . "'>" . $sublist['subject_name'] . "</td>";
                $table .= "<input type='hidden' name='stu_map_id' id='stu_map_id' value='" . $sublist['coe_student_mapping_id'] . "'>";
                $check_mark_entry_master = Yii::$app->db->createCommand("select student_map_id from coe_mark_entry_master where student_map_id='" . $sublist['coe_student_mapping_id'] . "' and subject_map_id='" . $sublist['coe_subjects_mapping_id'] . "' and year='" . $year . "' and month='" . $month . "' and withdraw='wd'")->queryAll();
                if (count($check_mark_entry_master) > 0) {
                    $table .= "</td><td align='center'><input type='checkbox' name=withdraw" . $sn . " checked></td>";
                } else {
                    $table .= "</td><td align='center'><input type='checkbox' onchange='withdraw_check(this.id)' name=withdraw" . $sn . " id=withdraw_" . $sn . "></td>";
                }
                $table .= "</tr>";
                $sn++;
            }
            $table .= "</tbody></table>";
            return $table;
        } else {
            return 0;
        }
    }
    public function actionRegularCountOverall()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = DATE('Y');
            $omit_batches = $year-$omit_batch;
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $pract_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Practical%'")->queryAll();
           
            foreach ($pract_id as $key => $value) {
               $pracIds[$value['coe_category_type_id']]=$value['coe_category_type_id'];
            }
            
            $query = new  Query();
            $query->select(['coe_subjects_id',"subject_code", "H.subject_name",'batch_name','F.exam_year as year','F.exam_month as month','degree_code','programme_code'])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')              
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id and G.batch_mapping_id=C.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_exam_timetable as F', 'F.subject_mapping_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_hall_allocate as I', 'I.exam_timetable_id=F.coe_exam_timetable_id and I.year=F.exam_year and I.month=F.exam_month')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
                $query->Where(['A.student_status' => 'Active', 'F.exam_year'=>$_POST['mark_year'],'F.exam_month'=>$_POST['MarkEntry']['month'],'F.exam_type'=>27,'I.year'=>$_POST['mark_year'],'I.month'=>$_POST['MarkEntry']['month']]);            
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                  ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                  //->andWhere(['>=', 'batch_name', $omit_batches])
                  ->andWhere(['NOT IN', 'paper_type_id', $pracIds]);
            $query->groupBy('subject_code,degree_code');
            $query->orderBy('subject_code');
            $total_appearred = $query->createCommand()->queryAll();
            
            if (!empty($total_appearred)) {
                return $this->render('regular-count-overall', [
                    'model' => $model,
                    'total_appearred' => $total_appearred,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry-master/regular-count-overall']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Regular Appeared Count ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('regular-count-overall', [
                'model' => $model,
            ]);
        }
    }
    public function actionArrearCountOverall()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = DATE('Y');
            $omit_batches = $year-$omit_batch;
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $pract_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Practical%'")->queryAll();
            $pracIds =array_filter(['']);
            foreach ($pract_id as $key => $value) {
               $pracIds [$value['coe_category_type_id']]=$value['coe_category_type_id'];
            }
            $query = new  Query();
            $query->select(['coe_subjects_id',"subject_code", "H.subject_name",'batch_name','F.exam_year as year','F.exam_month as month','semester','degree_code','programme_code','batch_name','coe_subjects_mapping_id'])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                //->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')                
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id and G.batch_mapping_id=C.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_exam_timetable as F', 'F.subject_mapping_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_hall_allocate as I', 'I.exam_timetable_id=F.coe_exam_timetable_id and I.year=F.exam_year and I.month=F.exam_month')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
                $query->Where(['A.student_status' => 'Active', 'F.exam_year'=>$_POST['mark_year'],'F.exam_month'=>$_POST['MarkEntry']['month'],'F.exam_type'=>28,'I.year'=>$_POST['mark_year'],'I.month'=>$_POST['MarkEntry']['month']]);            
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                  //->andWhere(['>=', 'batch_name', $omit_batches])
                  ->andWhere(['NOT IN', 'paper_type_id', $pracIds]);
            $query->groupBy('subject_code,semester');
            $query->orderBy('batch_name,semester');
            $total_appearred = $query->createCommand()->queryAll();
            
            if (!empty($total_appearred)) {
                return $this->render('arrear-count-overall', [
                    'model' => $model,
                    'total_appearred' => $total_appearred,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry-master/arrear-count-overall']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Arrear Appeared Count ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('arrear-count-overall', [
                'model' => $model,
            ]);
        }
    }
    public function actionRegularCountOverallPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));        
        $content = $_SESSION['regular-count-overall'];   
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "REGULAR APPEARED COUNT.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' =>'Regular Appeared Count'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Regular Appeared Count PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionRegularCountOverallExcel()
    {
        $content = $_SESSION['regular-count-overall'];            
        $fileName =  "Regular Appeared Count " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionArrearCountOverallPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['arrear-count-overall'];
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "ARREAR APPEARED COUNT.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' =>'Arrear Appeared Count'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Arrear Appeared Count PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionArrearCountOverallExcel()
    {        
        $content = $_SESSION['arrear-count-overall'];            
        $fileName =  "Arrear Appeared Count " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionOmrExport()
    {
        $model = new MarkEntry();
        $galley = new HallAllocate();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to OMR SHEET Export');
        return $this->render('omr-export', [
            'model' => $model, 'galley' => $galley,
        ]);        
    }
    public function actionOmrexportdata()
    {
        
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
        $stu_elective = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Elective%'")->queryScalar();
       

        $year = Yii::$app->request->post('year');
        $mark_type = Yii::$app->request->post('mark_type');
        $fees_status = Yii::$app->request->post('fees_status');
        $batch_map_id = Yii::$app->request->post('batch_map_id');
        $batch_id = Yii::$app->request->post('batch_id');
        $month = Yii::$app->request->post('month');
        $month_name = Categorytype::findOne($month);
        $month_name_print = $month_name->description;
        $table = "";
        $a="1";
        $sn = 1;
        if(isset($batch_id) && !empty($batch_id))
        {
            $bath_name = Batch::findOne($batch_id);
            $batch_name_id = $bath_name->batch_name;
        }
        $getAllBatch = CoeBatDegReg::find()->where(['coe_batch_id'=>$batch_id])->all();
        $max_sem =  array_filter(['']);
        foreach ($getAllBatch as $key => $value) 
        {   
            $max_sem[] = ConfigUtilities::SemCaluclation($year,$month,$value['coe_bat_deg_reg_id']);
        }
        $semester = max($max_sem);

        if($mark_type==27)
        {
            $query = new Query();
            $query->select('L.batch_name,degree_code,programme_code,ESE_max, D.register_number,D.name,G.semester, F.subject_code, F.subject_name')
                ->from('coe_bat_deg_reg A')
                ->join('JOIN', 'coe_degree B', 'A.coe_degree_id=B.coe_degree_id')
                ->join('JOIN', 'coe_programme C', 'A.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
                ->join('JOIN', 'coe_subjects_mapping G', 'G.batch_mapping_id=E.course_batch_mapping_id and G.batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_subjects F', 'F.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_batch L', 'A.coe_batch_id=L.coe_batch_id')
                ->where(['L.coe_batch_id'=>$batch_id,'A.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id,'G.semester'=>$semester,'E.course_batch_mapping_id'=>$batch_map_id,'G.batch_mapping_id'=>$batch_map_id]);
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['<>', 'subject_type_id', $stu_elective])
                ->groupBy('D.register_number,F.subject_code')
                ->orderBy('D.register_number,G.semester');
            $subject = $query->createCommand()->queryAll();

            $query_1 = new Query();
            $query_1->select('L.batch_name,degree_code,programme_code,ESE_max, D.register_number,D.name,G.semester,F.subject_code, F.subject_name')
                ->from('coe_bat_deg_reg A')
                ->join('JOIN', 'coe_degree B', 'A.coe_degree_id=B.coe_degree_id')
                ->join('JOIN', 'coe_programme C', 'A.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_batch L', 'A.coe_batch_id=L.coe_batch_id')
                ->join('JOIN', 'coe_student_mapping E', 'A.coe_bat_deg_reg_id=E.course_batch_mapping_id')
                ->join('JOIN', 'coe_student D', 'E.student_rel_id=D.coe_student_id')
                ->join('JOIN', 'coe_subjects_mapping G', 'A.coe_bat_deg_reg_id=G.batch_mapping_id')
                ->join('JOIN', 'coe_subjects F', 'G.subject_id=F.coe_subjects_id')
                ->join('JOIN', 'coe_nominal N', 'N.coe_subjects_id=F.coe_subjects_id AND N.coe_student_id=D.coe_student_id and N.course_batch_mapping_id=E.course_batch_mapping_id and N.semester=G.semester and N.course_batch_mapping_id=G.batch_mapping_id')
                ->where(['L.coe_batch_id'=>$batch_id,'A.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id,'G.semester'=>$semester,'N.semester'=>$semester,'E.course_batch_mapping_id'=>$batch_map_id,'G.batch_mapping_id'=>$batch_map_id]);
                $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->groupBy('D.register_number,F.subject_code')
                ->orderBy('D.register_number,G.semester');
            $subject_elec = $query_1->createCommand()->queryAll();
            if(!empty($subject))
            {
                $subject = array_merge($subject,$subject_elec);
            }
        }
        else if($mark_type==28 && (empty($fees_status) || $fees_status === 'ALL'))
        {
            $query = new Query();
            $query->select('L.batch_name,degree_code,programme_code,ESE_max, D.register_number,D.name,G.semester, F.subject_code, F.subject_name,subject_map_id, student_map_id')
                ->from('coe_bat_deg_reg A')
                ->join('JOIN', 'coe_degree B', 'A.coe_degree_id=B.coe_degree_id')
                ->join('JOIN', 'coe_programme C', 'A.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
                ->join('JOIN', 'coe_subjects_mapping G', 'G.batch_mapping_id=E.course_batch_mapping_id and G.batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_mark_entry_master H', 'H.student_map_id=E.coe_student_mapping_id and H.subject_map_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_subjects F', 'F.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_batch L', 'A.coe_batch_id=L.coe_batch_id')
                ->where(['L.coe_batch_id'=>$batch_id,'A.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id,'E.course_batch_mapping_id'=>$batch_map_id,'G.batch_mapping_id'=>$batch_map_id,'year_of_passing'=>'']);
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                  ->andWhere(['<=', 'G.semester', $semester])
                  ->groupBy('student_map_id,subject_map_id')
                  ->orderBy('D.register_number,G.semester');
            $subject = $query->createCommand()->queryAll();
        }
        else if($mark_type==28 && !empty($fees_status) && $fees_status!=='ALL')
        {
            $query = new Query();
            $query->select('L.batch_name,degree_code,programme_code,ESE_max, D.register_number,D.name,G.semester, F.subject_code, F.subject_name,fees.subject_map_id, fees.student_map_id')
                ->from('coe_bat_deg_reg A')
                ->join('JOIN', 'coe_degree B', 'A.coe_degree_id=B.coe_degree_id')
                ->join('JOIN', 'coe_programme C', 'A.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
                ->join('JOIN', 'coe_subjects_mapping G', 'G.batch_mapping_id=E.course_batch_mapping_id and G.batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_mark_entry_master H', 'H.student_map_id=E.coe_student_mapping_id and H.subject_map_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_fees_paid fees', 'fees.student_map_id=H.student_map_id and fees.subject_map_id=H.subject_map_id')
                ->join('JOIN', 'coe_subjects F', 'F.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_batch L', 'A.coe_batch_id=L.coe_batch_id')
                ->where(['L.coe_batch_id'=>$batch_id,'A.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id,'E.course_batch_mapping_id'=>$batch_map_id,'G.batch_mapping_id'=>$batch_map_id,'year_of_passing'=>'','fees.status'=>$fees_status]);
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                  ->andWhere(['<=', 'G.semester', $semester])
                  ->groupBy('student_map_id,subject_map_id')
                  ->orderBy('D.register_number,G.semester');
            $subject = $query->createCommand()->queryAll();
        }
        
        $subject = array_map("unserialize", array_unique(array_map("serialize", $subject)));
            array_multisort(array_column($subject, 'register_number'),  SORT_ASC, $subject); 
         if (count($subject) > 0) 
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table width="100%" class="table table-responsive table-bordered table-active table-dark" >
            <tbody align="left">';
            $table .= '<tr>
                            <td><b> S.NO </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH) . ' </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' </b></td>
                            <td><b> Register number </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' Name </b></td>
                            <td><b> Semester </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </b></td> 
                            <td>ESE MAX</td>                          
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM) . ' Year </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM) . ' Month </b></td>
                        </tr>';
            foreach ($subject as $subject1) 
            {
                if($mark_type==27)
                {
                    $table .= '
                        <tr>
                            <td> ' . $sn . ' </td>
                            <td> ' . $subject1['batch_name'] . ' </td>
                            <td> ' . $subject1['degree_code']." ".$subject1['programme_code'] . ' </td>
                            <td> ' . $subject1['register_number'] . ' </td>
                            <td> ' . $subject1['name'] . ' </td>
                            <td> ' . $subject1['semester'] . ' </td>
                            <td> ' . $subject1['subject_code'] . ' </td>
                            <td> ' . $subject1['subject_name'] . ' </td>
                            <td> ' . $subject1['ESE_max'] . ' </td>
                            <td> ' . $year . ' </td>
                            <td> ' . $month_name_print. ' </td>
                        </tr>';
                    $sn++;
                }
                else
                {
                    $check_pass = MarkEntryMaster::find()->where(['student_map_id'=>$subject1['student_map_id'],'subject_map_id'=>$subject1['subject_map_id'],'result'=>'Pass'])->one();
                    if(empty($check_pass))
                    {
                        $table .= '
                            <tr>
                                <td> ' . $sn . ' </td>
                                <td> ' . $subject1['batch_name'] . ' </td>
                                <td> ' . $subject1['degree_code']." ".$subject1['programme_code'] . ' </td>
                                <td> ' . $subject1['register_number'] . ' </td>
                                <td> ' . $subject1['name'] . ' </td>
                                <td> ' . $subject1['semester'] . ' </td>
                                <td> ' . $subject1['subject_code'] . ' </td>
                                <td> ' . $subject1['subject_name'] . ' </td>
                                <td> ' . $subject1['ESE_max'] . ' </td>
                                <td> ' . $year . ' </td>
                                <td> ' . $month_name_print. ' </td>
                            </tr>';
                        $sn++;
                    }
                }
                
            }
            $table .= '</tbody></table>';
        } else {
            $table .= 0;
        }
        if (isset($_SESSION['hallticket_export_print'])) {
            unset($_SESSION['hallticket_export_print']);
        }
        $_SESSION['hallticket_export_print'] = $table;
        return $table;
    }
    public function actionOmrexportPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['hallticket_export_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'OMR DATA Export.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse; font-family:Helvetica Neue, Helvetica, Arial, sans-serif; width:100%; font-size: 16px !important;  }

                        table td table{border: none !important;}
                        table td{
                            font-size: 13px !important; 
                            border: 1px solid #CCC;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                            padding: 1px;
                        }
                        table th{                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                        }
                    }   
                ',
            'options' => ['title' => 'OMR DATA export Information'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['OMR DATA ' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelOmrexport()
    {
        $content = $_SESSION['hallticket_export_print'];
        $fileName = "OMR DATA export Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionConsolidateSkcetOldNonCbcsPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['consolidate_pg_mark_statement_pdf'];
        $change_css_file = 'css/skcet_non_cbcs_pg_newmarkstatement.css';
        
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'CONSOLIDATED MARK STATEMENT.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,         
            'cssFile' => $change_css_file,
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)) . ' MARK STATEMENT'],
        ]); 

        $pdf->marginTop = '12';
        $pdf->marginLeft = "5";
        $pdf->marginRight = "3.4";
        $pdf->marginBottom = "0";
        $pdf->marginHeader = "3";
        $pdf->marginFooter = "0";

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
	}
    public function actionRevalMarksUpdate()
    {
        $model = new MarkEntryMaster();
        $student = new Student();
        $checkAccess = ConfigUtilities::HasSuperAccess(Yii::$app->user->getId());
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        $getElecId= Yii::$app->db->createCommand("select * from coe_category_type where description like '%Elective%' ")->queryOne();
        $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
        if($checkAccess=='Yes')
        {
            if (Yii::$app->request->post())
            {
                $year = $_POST['year'];
                $month =$_POST['month'];
                $subject_code = $_POST['subject_code'];
                $dum_reg= '';
                $subjects = Subjects::find()->where(['subject_code'=>$subject_code])->one();

                if(isset($_POST['submit_ese']) && $_POST['submit_ese']=="UPDATE")
                {
                    $category_type_id = Categorytype::find()->where(['description' => "Revaluation"])->orWhere(['category_type' => "Revaluation"])->one();
                    $category_type_id_internal = Categorytype::find()->where(['description' => "CIA"])->orWhere(['category_type' => "Internal"])->one();
                    $connection = Yii::$app->db;
                    $checkAlreadyUpdated = Yii::$app->db->createCommand('SELECT * FROM coe_mark_entry_master WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" ')->queryOne();

                    $checkCIAMarks = Yii::$app->db->createCommand('SELECT * FROM coe_mark_entry WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and category_type_id="'.$category_type_id_internal['coe_category_type_id'].'" ORDER BY coe_mark_entry_id desc')->queryOne();

                    $chekAvail = Yii::$app->db->createCommand('SELECT * FROM coe_mark_entry WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" and category_type_id IN("'.$category_type_id['coe_category_type_id'].'")')->queryOne();

                    if(!empty($chekAvail))
                    {
                         $command2 = $connection->createCommand('UPDATE coe_mark_entry SET category_type_id_marks="'.$_POST['update_ese'].'",is_updated="YES" ,updated_by="'.$updated_by.'",updated_at="'.$updated_at.'" WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'"  and category_type_id ="'.$category_type_id['coe_category_type_id'].'"');
                         if($command2->execute())
                        {
                            $get_sub_info = Yii::$app->db->createCommand('SELECT * FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$_POST['subMapId'].'"  ')->queryOne();

                            $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' .$_POST['subMapId'] . '" AND student_map_id="' . $_POST['stuMapId']. '" AND result not like "%pass%"')->queryScalar();

                            if ($check_attempt >= $config_attempt) 
                            {        
                                $ciaMArks = 0; 
                                $grade_cia_check = $checkCIAMarks['category_type_id_marks'];         
                                $ese_marks =$_POST['update_ese'];
                                $total_marks =$_POST['update_ese'];
                                $status_check = 'YES'; 
                            } else {
                                $convert_marks = round($_POST['update_ese']*$get_sub_info['ESE_max']/100);
                                $ciaMArks = $grade_cia_check = $checkCIAMarks['category_type_id_marks'];
                                $ese_marks = $convert_marks;
                                $total_marks = $ese_marks+$ciaMArks;
                                $status_check = $ese_marks<=$get_sub_info['ESE_max'] ? 'YES' : 'NO'; 
                            }

                            $batchMapping = CoeBatDegReg::findOne($get_sub_info['batch_mapping_id']);
                            $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping['regulation_year']])->all();

                            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                          $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];
                          $arts_college_grade = 'NO';
                          if($org_email=='coe@skasc.ac.in')
                          {
                            $convert_ese_marks =  $ese_marks;
                            $insert_total = $ese_marks+$grade_cia_check;
                            if($final_sub_total<100)
                            {
                              $total_marks = round(round((($insert_total/$final_sub_total)*10),1)*10);
                            }
                            else
                            {
                              $total_marks = $ese_marks+$grade_cia_check;
                            }
                            $arts_college_grade = round(($insert_total/$final_sub_total)*10,1);

                          }

                          foreach ($grade_details as $value) 
                          {
                              if($value['grade_point_to']!='')
                              {                            
                                  if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                                  {
                                      if($grade_cia_check<$get_sub_info['CIA_min'] || $ese_marks<$get_sub_info['ESE_min'] || $total_marks<$get_sub_info['total_minimum_pass'])
                                      {
                                        $stu_result_data = ['result'=>'Fail','total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'year_of_passing'=>'','ese_marks'=>$ese_marks];        
                                      }      
                                      else
                                      {
                                        $grade_name_prit = $value['grade_name'];
                                        $grade_point_arts = $org_email=='coe@skasc.ac.in' ? $arts_college_grade : $value['grade_point'];;
                                        if(!empty($_POST['month']) && !empty($_POST['year']))
                                        {
                                            $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$_POST['month']."-".$_POST['year']];
                                        }
                                        else
                                        {
                                            $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$_POST['month']."-".$_POST['year']];
                                        }
                                        
                                      }
                                  } // Grade Point Caluclation
                              } // Not Empty of the Grade Point                               
                          }
                            
                            $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $_POST['month'] . "-" . $_POST['year']: '';

                            $command3 = $connection->createCommand('UPDATE coe_mark_entry_master SET ESE="'.$stu_result_data['ese_marks'].'",is_updated="YES",updated_by="'.$updated_by.'",updated_at="'.$updated_at.'",grade_point="'.$stu_result_data['grade_point'].'",total="'.$stu_result_data['total_marks'].'",result="'.$stu_result_data['result'].'",grade_name="'.$stu_result_data['grade_name'].'",year_of_passing="'.$year_of_passing.'" WHERE student_map_id="'.$_POST['stuMapId'].'" and subject_map_id="'.$_POST['subMapId'].'" and year="'.$_POST['year'].'" and month="'.$_POST['month'].'" ');
                            if( $status_check=='YES' && $command3->execute())
                            {
                                Yii::$app->ShowFlashMessages->setMsg('Success','Updated Successfully!!');
                                return $this->redirect(['reval-marks-update']);
                            }
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Update the Marks');
                            return $this->redirect(['reval-marks-update']);
                        }
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                            return $this->redirect(['reval-marks-update']);
                    }                       
                        
                }
                if(isset($_POST['dummy_number']) && !empty($_POST['dummy_number']) && !empty($subjects))
                {                    
                    $getDummy = DummyNumbers::find()->where(['dummy_number'=>$_POST['dummy_number'],'month'=>$month,'year'=>$year])->one();
                    if(empty($getDummy))
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                        return $this->redirect(['reval-marks-update']);
                    }
                    else
                    {
                        $stu_map = StudentMapping::findOne($getDummy->student_map_id);
                        $dum_reg= Student::findOne($stu_map->student_rel_id);
                    }
                }

                
                $register_number = isset($dum_reg) && !empty($dum_reg) ? $dum_reg['register_number'] : $_POST['register_number'];
                $dummy_number = $_POST['dummy_number'];
                $category_type_id = Categorytype::find()->where(['description' => "Revaluation"])->orWhere(['category_type' => "Revaluation"])->one();
                $student = Student::find()->where(['register_number'=>$register_number])->one();
                
                if(empty($subjects) || empty($student))
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                    return $this->redirect(['reval-marks-update']);
                }
                else
                {
                    $stu_map = StudentMapping::find()->where(['student_rel_id'=>$student->coe_student_id])->one();
                    $sub_map = SubjectsMapping::find()->where(['subject_id'=>$subjects['coe_subjects_id'],'batch_mapping_id'=>$stu_map->course_batch_mapping_id])->one();
                    $batch_mapping_id = $stu_map->course_batch_mapping_id;
                    $student_map_id = $stu_map->coe_student_mapping_id;
                    $split_data = ConfigUtilities::getSubjectMappingIds($subjects->coe_subjects_id,$year,$month);

                    if(empty($stu_map) || empty($split_data))
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                        return $this->redirect(['reval-marks-update']);
                    }
                    else
                    {
                        if(is_array($split_data))
                        {
                            sort($split_data);
                            for ($k=0; $k <count($split_data) ; $k++) 
                            { 
                                if(isset($_POST['semester_val']) && !empty($_POST['semester_val']))
                                {
                                    $getElective = SubjectsMapping::findOne(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id,'semester'=>$_POST['semester_val']]);
                                }
                                else
                                {
                                    $getElective = SubjectsMapping::findOne(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id]);
                                }

                                if($getElective['subject_type_id']==$getElecId['coe_category_type_id'])
                                {
                                    $query = new Query();
                                    $query->select('*')
                                        ->from('coe_subjects_mapping a')
                                        ->join('JOIN', 'coe_student_mapping b', 'b.course_batch_mapping_id=a.batch_mapping_id')
                                        ->join('JOIN', 'coe_student C', 'C.coe_student_id=b.student_rel_id')
                                        ->join('JOIN', 'coe_nominal D', 'D.coe_student_id=C.coe_student_id and D.coe_subjects_id=a.subject_id and D.course_batch_mapping_id=b.course_batch_mapping_id and D.course_batch_mapping_id=a.batch_mapping_id and D.semester=a.semester')
                                        ->where(['a.batch_mapping_id' => $batch_mapping_id,'coe_subjects_mapping_id'=>$split_data[$k],'coe_student_mapping_id'=>$student_map_id]);
                                    $sub_map_id_ins = $query->createCommand()->queryOne();
                                  
                                }
                                else
                                {
                                    
                                    if(isset($_POST['semester_val']) && !empty($_POST['semester_val']))
                                    {
                                        $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id,'semester'=>$_POST['semester_val']])->one();
                                    }
                                    else
                                    {
                                        $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data[$k],'batch_mapping_id'=>$batch_mapping_id])->one();
                                    }
                                    
                                }
                                
                                if(!empty($sub_map_id_ins))
                                {
                                    break;
                                }
                            }

                        }
                        else
                        {                            
                            if(isset($_POST['semester_val']) && !empty($_POST['semester_val']))
                            {
                                $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data,'batch_mapping_id'=>$batch_mapping_id,'semester'=>$_POST['semester_val']])->one();
                            }
                            else
                            {
                                $sub_map_id_ins = SubjectsMapping::find()->where(['coe_subjects_mapping_id'=>$split_data,'batch_mapping_id'=>$batch_mapping_id])->one();
                            }
                            
                        }
                        if(isset($sub_map_id_ins) && !empty($sub_map_id_ins))
                        {

                            $subject_map_id = $sub_map_id_ins['coe_subjects_mapping_id']; 
                        }
                        else
                        {
                            $subject_map_id =0; 
                        }
                        if(!empty($dummy_number))
                        {
                            $getDummy = DummyNumbers::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'dummy_number'=>$dummy_number,'month'=>$month,'year'=>$year])->one();
                            if(empty($getDummy))
                            {
                                Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                                return $this->redirect(['reval-marks-update']);
                            }
                        }
                        /*$chec_written = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'category_type_id'=>$category_type_id['coe_category_type_id']])->one();*/
                        $chec_written = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month])->one();
                        
                        if(empty($chec_written))
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!!');
                            return $this->redirect(['reval-marks-update']);
                        }
                        else
                        {

                           return $this->render('reval-marks-update', [
                                'model' => $model,
                                'student' =>$student,
                                'chec_written' =>$chec_written,
                                'dummy_number' =>$dummy_number,
                            ]);

                        }

                    }
                }
               
            } else {
                return $this->render('reval-marks-update', [
                    'model' => $model,
                    'student' =>$student,
                ]);
            }
        }
        else
        {
            $lockUser = Yii::$app->db->createCommand('UPDATE user SET status="11" WHERE id="'.Yii::$app->user->getId().'"')->execute();
            $created_by = $updated_by = Yii::$app->user->getId();
            $created_at = $updated_at = date("Y-m-d H:i:s");
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

            $filename =  Yii::getAlias("@webroot").'/access_logs/log_'.date("j.n.Y").'.txt';
            $content  = "User Name: ".Yii::$app->user->getUsername().' - '.date("F j, Y, g:i a").PHP_EOL.
                        "Accessed URLS: ".$url.PHP_EOL.
                        "----------------------------------------------------------------".PHP_EOL;

            //print_r(parse_url($url)); // This will returns the parts of the URL
            
            $removed_path = str_replace('index.php', '', parse_url($url, PHP_URL_PATH));
            $image_path = parse_url($url, PHP_URL_SCHEME)."://".parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PORT).$removed_path.'images/notfound.png'; 
            
            $image_path = Yii::getAlias("@web").'/images/notfound.png'; 

            if(file_put_contents($filename, $content.PHP_EOL , FILE_APPEND | LOCK_EX))
            {   
                $file_content = file_get_contents($filename, true);
                echo "<div style='width:1000px;  text-align: center; margin: 0 auto;'><img src='".$image_path."' alt='not found' height='600' width='900' align='center' /></div>";
                
            }
            unset($_SESSION);
            session_destroy();
            Yii::$app->ShowFlashMessages->setMsg('Error','OOOPS You are not allowed!!! Your Account is Locked!!!');
            return $this->redirect(['site/index']);            
        }
        
    }
    //Withdraw ends here
    public function actionConsolidateMarkSheetTransfer()
	 {
		   $model = new MarkEntryMaster();
		  $markEntry = new MarkEntry();
        $student = new Student();
		$studentcategorydetails=new StudentCategoryDetails;
	$det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Transfer%'")->queryScalar();	 
		  if (Yii::$app->request->post()) 
        {
             $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,E.programme_name,
			 H.subject_code,C.regulation_year,mark_type,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,
			 F.year,F.month,status_category_type_id,B.course_batch_mapping_id,F.year_of_passing,paper_no,F.year as exam_year,F.month as exam_month,
			 F.result,max(F.year_of_passing) as last_appearance FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id 
			 JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id
			 JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id 
			 JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id 
			 JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  
			 AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' 
			 and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%')) 
			 and status_category_type_id IN('".$det_cat_type."') AND F.grade_name not IN ('U','wh','AB','ab','WH','U','wd','WD') 
			
			 group by A.register_number,H.subject_code order by paper_no,G.semester";
            
            $get_console_list = Yii::$app->db->createCommand($get_stu_query)->queryAll();
			
			    $data = array_filter(['']);
		
    array_multisort(array_column($get_console_list, 'paper_no'),  SORT_ASC, $get_console_list);
//$degree_type = Yii::$app->db->createCommand("SELECT degree_type FROM coe_degree AS A JOIN coe_bat_deg_reg as B ON B.coe_degree_id=A.coe_degree_id WHERE coe_bat_deg_reg_id='".$_POST['bat_map_val']."' ")->queryScalar();
		
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));

		  if(!empty($get_console_list))
            {
                $layout_type = $_POST['layout_type'];
                if($file_content_available=="Yes")
                {
		  return $this->render('consolidate-mark-sheet-transfer', [                        
                        
                        'model' => $model, 
                        'markEntry'=>$markEntry,
						'student'=>$student,
						'get_console_list' => $get_console_list,
                        'model' => $model, 
                        
                        'layout_type' => $layout_type,
                        
                      
                        'date_print' =>$_POST['created_at'],

                    ]);
				}
			
	  else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"No Institute Information Found");
                    return $this->redirect(['mark-entry-master/consolidate-mark-sheet-transfer']); 
                }
                 
            }
			 else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
                return $this->redirect(['mark-entry-master/consolidate-mark-sheet-transfer']); 
            }
		

            return $this->render('consolidate-mark-sheet-transfer', [
                'model' => $model,
                'get_console_list' => $get_console_list,
                'student' => $student,
            ]);

            //return $this->redirect(['view', 'id' => $model->coe_mark_entry_master_id]);
		}     else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate Mark Sheet Transfer');
            return $this->render('consolidate-mark-sheet-transfer', [
                'model' => $model,
                'markEntry'=>$markEntry,
                'student' => $student,
            ]);
        
	 
		}
	 }

  public function actionArrearReport()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

      
          // $sem_count = ConfigUtilities::SemCaluclation($_POST['mark_year'],$_POST['month'],$_POST['bat_map_val']);
           
            $query = new  Query();
            $query->select(["distinct (H.subject_code) as subject_code", "concat(D.degree_code,'-',E.programme_code) as degree_code", "A.register_number", "A.name", "H.subject_name", "G.semester", "F.year",'F.student_map_id','F.subject_map_id',"E.programme_name", "K.batch_name", "D.degree_name"])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id and G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=G.paper_type_id');
                $query->Where(['A.student_status' => 'Active', 'F.year_of_passing' => '','K.coe_batch_id' => $_POST['bat_val']]);
                 // $query->andWhere(['G.semester'=>$_POST['exam_semester']]);        
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.subject_map_id,F.student_map_id');
            $query->orderBy('G.semester,degree_code,register_number');
            $arrearreport = $query->createCommand()->queryAll();
           

            if (!empty($arrearreport)) {
                return $this->render('arrear-report', [
                    'model' => $model,
                    'arrearreport' => $arrearreport,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry-master/arrear-report']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ARREAR REPORT ');
            return $this->render('arrear-report', [
                'model' => $model,
            ]);
        }
    }
       public function actionArrearReportPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
       
            $content = $_SESSION['arrearreport'];
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "ARREAR REPORT.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . " WISE ARREAR "],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ["ARREAR REPORT " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionArrearReportExcel()
    {        
        $content = $_SESSION['arrearreport'];
        $fileName = "ARREAR REPORT".DATE('d-m-Y-H-i-s').".xls";
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
  public function actionSubjectarrearReport()
     {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
      
           
            $query = new  Query();
            $query->select(["distinct (H.subject_code) as subject_code", "concat(D.degree_code,'',E.programme_code) as degree_code", "A.register_number", "A.name", "H.subject_name", "G.semester", "F.year",'F.student_map_id','F.subject_map_id', "E.programme_name", "K.batch_name", "D.degree_name"])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id and G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=G.paper_type_id');
                $query->Where(['A.student_status' => 'Active', 'F.year_of_passing' => ''])->andWhere(['<=','F.year',$_POST['mark_year']]); 
                   $query->andWhere(['H.subject_code' => $_POST['subject_code']]);        
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.subject_map_id,F.student_map_id');
            $query->orderBy('G.semester,degree_code,register_number');
            $subjectarrear = $query->createCommand()->queryAll();
           

            if (!empty($subjectarrear)) {
                return $this->render('subjectarrear-report', [
                    'model' => $model,
                    'subjectarrear' => $subjectarrear,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry-master/subjectarrear-report']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to SUBJECT WISE ARREAR REPORT ');
            return $this->render('subjectarrear-report', [
                'model' => $model,
            ]);
        }
    }
  public function actionSubjectarrearReportPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
       
            $content = $_SESSION['subjectarrear'];
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "ARREAR REPORT.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . " WISE ARREAR "],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ["SUBJECT WISE ARREAR REPORT " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionSubjectarrearReportExcel()
    {        
        $content = $_SESSION['subjectarrear'];
        $fileName = "ARREAR REPORT".DATE('d-m-Y-H-i-s').".xls";
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    } 
    public function actionFeesPaid()
    {
      $model = new MarkEntry();
      $markentrymaster = new MarkEntryMaster();
      $feespaid=new FeesPaid();
      $galley = new HallAllocate();
      $subject = new Subjects();
      $created_at = ConfigUtilities::getCreatedTime();
      $created_by = ConfigUtilities::getCreatedUser();
      $updated_at = ConfigUtilities::getCreatedTime();
      $updated_by = ConfigUtilities::getCreatedUser();
      $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where  category_type like '%Discontinued%'")->queryScalar();
      if (Yii::$app->request->post()) 
      {
          $stu_map_ids=$_POST['reg_number'];
          $year=$_POST['year'];
          $month=$_POST['month'];
          
          $check_date = new Query();
          $check_date->select('*')
          ->from('coe_fees_paid a')
          ->where(["a.year"=>$year,"a.month"=>$month,'subject_map_id'=>$_POST['sub_map_id'],'student_map_id'=>$stu_map_ids]);
          $get_result = $check_date->createCommand()->queryAll();
          if(!empty($get_result) && count($get_result)>0)
          {
              Yii::$app->ShowFlashMessages->setMsg('error','Already fees paid is inserted ');
              return $this->redirect(['fees-paid']);
          }
          for ($i=0; $i <count($stu_map_ids) ; $i++)  
          { 
             $model = new MarkEntry();
             $markentrymaster = new MarkEntryMaster();
             $feespaid=new FeesPaid();
             if(isset($_POST['reg_number'][$i]))
             {
                  $pas_status = $_POST['status_'.$_POST['reg_number'][$i]];
                  $feespaid->student_map_id =$_POST['reg_number'][$i];
                  $feespaid->subject_map_id  =$_POST['sub_map_id'][$i];
                  $feespaid->year =$year;
                  $feespaid->month =$month;
                  $feespaid->status= $pas_status;
                  $feespaid->created_by = $updated_by;
                  $feespaid->created_at = $updated_at;
                  $feespaid->updated_by = $updated_by;
                  $feespaid->updated_at = $updated_at;
                  $feespaid->save(false);
                  unset( $feespaid);
              } 
          }                       
            Yii::$app->ShowFlashMessages->setMsg('Success','Successfully Inserted Fees Paid');
            return $this->redirect(['fees-paid']);                           
      }
      else 
      {
           return $this->render('fees-paid', [                        
              'model' => $model, 
              'markentrymaster'=>$markentrymaster,
              'galley'=>$galley,
              'subject' =>$subject,
              'feespaid'=> $feespaid,
          ]);
      }  
    } 
    public function actionViewFeesPaid()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $feespaid=new FeesPaid();
        $galley = new HallAllocate();
        $subject = new Subjects();
        
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to View Fees Paid List ');
        return $this->render('view-fees-paid', [
                'model' => $model,                    
                'markentrymaster' => $markentrymaster,
                 'galley'=>$galley,
                'subject'=>$subject,
                 'feespaid'=> $feespaid,
           ]);
    }
}