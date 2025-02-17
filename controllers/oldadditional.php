<?php

namespace app\controllers;

use Yii;
use app\models\CoeAddExamTimetable;
use app\models\ExamTimetable;

use app\models\CoeAddExamTimetableSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use app\models\CoevalueSubjects;
use app\models\CoeValueMarkEntry;
use app\models\CoeValueMarkEntrySearch;
use app\models\Regulation;
use app\models\Nominal;
use app\models\Sub;
use app\models\DummyNumbers;
use app\models\DummyNumbersSearch;
use app\models\ValuationFacultyAllocate;
use app\models\ValuationAdcFacultyAllocate;
use app\models\StuInfo;
use app\models\ValuationFaculty;
use app\models\ValuationScrutiny;
use app\models\Configuration;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\HallAllocate;
use app\models\StudentMapping;
use app\models\AbsentEntry;
use app\models\AnswerPacket;
use app\models\MarkEntry;
use app\models\MarkEntryMaster;
use app\models\Categorytype;
use kartik\mpdf\Pdf;
use yii\widgets\ActiveForm;

/**
 * CoeAddExamTimetableController implements the CRUD actions for CoeAddExamTimetable model.
 */
class CoeAddExamTimetableController extends Controller
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
     * Lists all CoeAddExamTimetable models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CoeAddExamTimetableSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CoeAddExamTimetable model.
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
     * Creates a new CoeAddExamTimetable model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    /*public function actionCreate()
    {
        $model = new CoeAddExamTimetable();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_add_exam_timetable_id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }*/

     public function actionCreate()
    {
        $model = new CoeAddExamTimetable();

        if (Yii::$app->request->isAjax) {
            if($model->load(Yii::$app->request->post())) {
                array('onclick'=>'$("#student_form_required_page").dialog("open"); return false;');
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ActiveForm::validate($model);
            }
        }

        if ($model->load(Yii::$app->request->post())) 
        {

            $batch = $_POST['bat_val'];
            $batch_map_id = $_POST['bat_map_val'];
            $sem = $_POST['exam_semester'];
            $sub_id = $_POST['exam_subject_code'];
            $sub_name = $_POST['exam_subject_name'];

            $date = Yii::$app->formatter->asDate($_POST['exam_date'], 'yyyy-MM-dd');
            $display_data = Yii::$app->formatter->asDate($_POST['exam_date'], 'dd-MM-yyyy');
            $exam_year = $model->exam_year;

            $subject = new Query();
            $subject->select("A.subject_code,B.coe_sub_mapping_id,B.subject_type_id")
                ->from("coe_value_subjects A")
                ->join('JOIN','sub B','A.coe_val_sub_id=B.val_subject_id')
                ->where(['batch_mapping_id'=>$batch_map_id,'B.coe_sub_mapping_id'=>$sub_id,'B.semester'=>$sem]);
            $sub_det = $subject->createCommand()->queryOne();
            
            $model->subject_mapping_id=$sub_det['coe_sub_mapping_id'];
            $model->exam_year=$exam_year;
            $model->exam_date=$date;
            $model->created_at = new \yii\db\Expression('NOW()');
            $model->created_by = Yii::$app->user->getId();
            $model->updated_at = new \yii\db\Expression('NOW()');
            $model->updated_by = Yii::$app->user->getId();

            $cat_sub_type = Categorytype::find()->where(['coe_category_type_id'=>$sub_det['subject_type_id']])->one();

            $same_date = new Query();
            $same_date->select("B.*")
                ->from("coe_sub A")
                ->join('JOIN','coe_add_exam_timetable B','B.subject_mapping_id=A.coe_sub_mapping_id')
                ->where(['A.batch_mapping_id'=>$batch_map_id,'B.exam_date'=>$date,'B.exam_session'=>$model->exam_session]);
            $course_exam_date = $same_date->createCommand()->queryAll();

            $same_sub_date = new Query();
            $same_sub_date->select("C.subject_mapping_id")
                ->from("coe_value_subjects A")
                ->join('JOIN','sub B','B.val_subject_id=A.coe_val_sub_id')
                ->join('JOIN','coe_add_exam_timetable C','C.subject_mapping_id=B.coe_sub_mapping_id')
                ->where(['B.batch_mapping_id'=>$batch_map_id,'B.coe_sub_mapping_id'=>$sub_id,'B.semester'=>$sem,'exam_year'=>$exam_year,'exam_month'=>$model->exam_month,'exam_term'=>$model->exam_term]);
            $same_subject_date = $same_sub_date->createCommand()->queryAll();

            $without_elective_date = Yii::$app->db->createCommand("select * from sub as A,coe_add_exam_timetable as B where A.coe_sub_mapping_id=B.subject_mapping_id and batch_mapping_id='".$batch_map_id."' and exam_date='".$date."' and exam_session='".$model->exam_session."' and subject_type_id!='".$cat_sub_type->coe_category_type_id."'")->queryAll();

            if($cat_sub_type->category_type!='Elective')
            {
                if(count($course_exam_date)>0)
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' Can not be Created Because Same '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)." has multiple ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." on Same <b>".$display_data."</b> and Same Session ");
                    return $this->redirect(['create']);
                }
                else
                {
                    if(count($same_subject_date)>0)
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' date already created for this '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
                        return $this->redirect(['create']);
                    }
                    else
                    {
                        $model->save();
                        Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Date <b>".$display_data."</b> Has created Successfully!!! for <b>".$sub_det['subject_code']."</b>");
                        return $this->redirect(['create']);
                    }
                }
            }
            else//elective
            {
                
                if(!empty($without_elective_date))
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' Can not be Created Because Same '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)." has multiple ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." on Same <b>".$display_data."</b> and Same Session ");
                    return $this->redirect(['create']);
                }
               
                if(!empty($same_subject_date))
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' date already created for this '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME));
                    return $this->redirect(['create']);
                }
                else
                {
                    $model->save(false);
                    Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Date <b>".$display_data."</b> Has created Successfully!!! for <b>".$sub_det['subject_code']."</b>");
                    return $this->redirect(['create']);
                }
                
            }

           return $this->redirect(['create']);
        } 
        else 
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM). ' Timetable');
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing CoeAddExamTimetable model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_add_exam_timetable_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing CoeAddExamTimetable model.
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
     * Finds the CoeAddExamTimetable model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CoeAddExamTimetable the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CoeAddExamTimetable::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


     public function actionCoverAbsent()
    {
        $model = new AbsentEntry();
        $examTimetable = new ExamTimetable();
        $exam = new CoeAddExamTimetable();

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).' List');
        return $this->render('cover-absent', [
            'model' => $model,
            'examTimetable' => $examTimetable,
             'exam' => $exam,
        ]);
    }


    public function actionConsolidateExcelAbPdf()
    {        
         $content = $_SESSION['consolidate_absent_list'];
        $fileName = "CONSOLIDATE ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).date('Y-m-d').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionConsolidateAbsentPdf()
    {

        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['consolidate_absent_list'];
        $pdf = new Pdf([
           
            'mode' => Pdf::MODE_CORE,
            'filename' => "CONSOLIDATE ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).' LIST.pdf',                
            'format' => Pdf::FORMAT_A4,                 
            'orientation' => Pdf::ORIENT_PORTRAIT,                 
            'destination' => Pdf::DEST_BROWSER,                 
            'content' => $content,  
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: left;  font-family:"Roboto, sans-serif";  border: 1px solid #000; width:100%; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                            font-size: 12px;
                            line-height: 1.5em;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                            font-size: 12px;
                            line-height:1.5em;
                        }
                        table td{padding:3px  !important;  } 
                    table tr{ line-height: 30px !important; height: 20px !important;}
                    }   
                ',
            'options' => ['title' => "CONSOLIDATE ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT)],
            'methods' => [ 
                'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 

            ]
        ]);


          Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }




    public function actionBoardWiseAbsent()
    {

     $model = new AbsentEntry();
     $ans = new AnswerPacket();
     $examTimetable = new ExamTimetable();
     $exam = new CoeAddExamTimetable();


        //Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).' List');
        return $this->render('board-wise-absent', [
            'model' => $model,
            'examTimetable' => $examTimetable,
            'ans'=>$ans,
            'exam'=>$exam,
        ]);
    }


     public function actionConsolidateExcelBoardPdf()
    {        
         $content = $_SESSION['consolidate_absent_list'];
        $fileName = "CONSOLIDATE BATCH  WISE ANALYSIS  ".'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionConsolidateAbsentBoardPdf()
    {

        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['consolidate_absent_list'];
        $pdf = new Pdf([
           
            'mode' => Pdf::MODE_CORE,
            'filename' => "Board Wise Analysis",                
            'format' => Pdf::FORMAT_A4,                 
            'orientation' => Pdf::ORIENT_PORTRAIT,                 
            'destination' => Pdf::DEST_BROWSER,                 
            'content' => $content,  
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: left;  font-family:"Roboto, sans-serif";  border: 1px solid #000; width:100%; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                            font-size: 14px;
                            line-height: 1.3em;
                            height:60px;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                            font-size: 13px;
                            line-height:1.3em;
                            height:60px;
                        }
                        table td{padding:3px  !important;  } 
                    table tr{ line-height: 40px !important; height: 30px !important;}
                    }   
                ',
            'options' => ['title' => "Board Wise Analysis"],
            'methods' => [ 
                'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 

            ]
        ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }

     public function actionBoardWiseAbsentAdd()
    {

     $model = new AbsentEntry();
     $ans = new AnswerPacket();
     $examTimetable = new ExamTimetable();
     $exam = new CoeAddExamTimetable();


        //Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).' List');
        return $this->render('board-wise-absent-add', [
            'model' => $model,
            'examTimetable' => $examTimetable,
            'ans'=>$ans,
            'exam'=>$exam,
        ]);
    }
   /* public function actionExternalScore()
    {

     $model = new CoeAddExamTimetable();
     $ans = new AnswerPacket();
     
     


        //Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).' List');
        return $this->render('external-score', [
            'model' => $model,
           
            'ans'=>$ans,
           
        ]);
    }*/


    public function actionExternalScore()
    {
        if(isset($_SESSION['external_score_data']))
        {
            unset($_SESSION['external_score_data']);
        }

        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
         $exam_type_g = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%arrear%'")->queryScalar();
       
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
        $model = new CoeAddExamTimetable();
            $ans = new AnswerPacket();
        if ($model->load(Yii::$app->request->post())) 
        { 
          
            $exam_year=$_POST['CoeAddExamTimetable']['exam_year'];
            $exam_month_add1=$_POST['CoeAddExamTimetable']['exam_month'];
            $qp_code=$_POST['qp_code'];
            //print_r( $qp_code);exit;
           
          
          $query = "SELECT DISTINCT B.answer_packet_number as packet,B.subject_code,C.qp_code,A.total_answer_scripts,A.print_script_count ,A.exam_date,A.exam_session,B.subject_name,B.stu_reg_no,H.degree_name,I.batch_name,A.exam_year,A.exam_month,A.subject_name,E.course_batch_mapping_id,E.coe_student_mapping_id,B.subject_mapping_id FROM coe_add_answer_packet  as A join coe_add_answerpack_regno as B on B.exam_date=A.exam_date join coe_add_exam_timetable as C on C.subject_mapping_id=B.subject_mapping_id join coe_student as D on D.register_number=B.stu_reg_no join coe_student_mapping as E on E.student_rel_id=D.coe_student_id join coe_bat_deg_reg as F on F.coe_bat_deg_reg_id=E.course_batch_mapping_id join coe_programme as G on G.coe_programme_id=F.coe_programme_id join coe_degree as H on H.coe_degree_id=F.coe_degree_id join coe_batch as I on I.coe_batch_id=F.coe_batch_id join sub as J on J.coe_sub_mapping_id=B.subject_mapping_id join coe_value_subjects  as K on K.coe_val_sub_id=J.val_subject_id  WHERE   C.exam_month='".$exam_month_add1."' and C.exam_year='".$exam_year."'   and  B.answer_packet_number='".$qp_code."'  GROUP BY B.stu_reg_no order by stu_reg_no,C.qp_code ";

           
           $external_score_2 = Yii::$app->db->createCommand($query)->queryAll();

            $query = "SELECT DISTINCT B.answer_packet_number as packet,B.subject_code,C.qp_code,A.total_answer_scripts,A.print_script_count ,A.exam_date,A.exam_session,B.subject_name,B.stu_reg_no,H.degree_name,I.batch_name,A.exam_year,A.exam_month,A.subject_name,E.course_batch_mapping_id,E.coe_student_mapping_id,B.subject_mapping_id FROM coe_add_answer_packet  as A join coe_add_abanswerpack_regno as B on B.exam_date=A.exam_date join coe_add_exam_timetable as C on C.subject_mapping_id=B.subject_mapping_id join coe_student as D on D.register_number=B.stu_reg_no join coe_student_mapping as E on E.student_rel_id=D.coe_student_id join coe_bat_deg_reg as F on F.coe_bat_deg_reg_id=E.course_batch_mapping_id join coe_programme as G on G.coe_programme_id=F.coe_programme_id join coe_degree as H on H.coe_degree_id=F.coe_degree_id join coe_batch as I on I.coe_batch_id=F.coe_batch_id join sub as J on J.coe_sub_mapping_id=B.subject_mapping_id join coe_value_subjects  as K on K.coe_val_sub_id=J.val_subject_id  WHERE   C.exam_month='".$exam_month_add1."' and C.exam_year='".$exam_year."'   and  B.answer_packet_number='".$qp_code."'  GROUP BY B.stu_reg_no order by stu_reg_no,C.qp_code ";

           
           $external_score_1 = Yii::$app->db->createCommand($query)->queryAll();
           // print_r($external_score_1);exit;
           
                $external_score = array_merge($external_score_1,$external_score_2);
            
           
            return $this->render('external-score', [
                'model' => $model,
                'external_score'=>$external_score,
                 'external_score_2'=>$external_score_2,
                  'external_score_1'=>$external_score_1,
                'ans'=>$ans,
            ]);
        }
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to External Score Card');
        return $this->render('external-score', [
            'model' => $model,
            
        ]);
    }
 public function actionExportexternalArts()
    {

        $content = $_SESSION['external_score_data'];
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $pdf = new Pdf([
                'mode' => Pdf::MODE_CORE, 
                'filename' => 'External-Score-Card.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                         table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; } 
                        
                        table td{
                            border: 1px solid #000;
                            overflow: hidden;
                           
                            text-align: center;
                            line-height: 1.5em;
                        }
                        table th{
                            border: 1px solid #000;
                            overflow: hidden;
                            white-space: nowrap;
                            text-overflow: ellipsis;
                            text-align: center;
                        }
                    }   
                ',
                'options' => ['title' =>'External Score Card'],
                'methods' => [ 
                    
                  
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }


    public function actionDummyNumberEntryadc()
    {

        $model = new DummyNumbers();
        $examtimetable = new ExamTimetable();
        $connection = Yii::$app->db;
        $factallModel = new ValuationFacultyAllocate();
        $factallmodel = new ValuationAdcFacultyAllocate();
        $verify_stu_data='';  $_SESSION['get_print_dummy_mark'] ='';


        if(Yii::$app->request->post())
        {  
            $totalSuccess=0;
            $exam_year = $_POST['dummynumber_exam_year'];
                    
            $exam_month = $_POST['dummynumber_exam_month1'];            

            $qp_code=$_POST['dummynumber_val_faculty_id'];
            //print_r($qp_code);exit;

            // $val_facl_id=explode('-',$val_facl_id);

            // $val_fac_all_id= $val_facl_id[0];

            // $exam_subject_code= $val_facl_id[1];

            // $pack_no=$val_facl_id[2];

            // $val_fac_allcoate = ValuationFacultyAllocate::find()->where(['val_faculty_all_id'=>$val_fac_all_id])->one();

            // $val_date = $val_fac_allcoate['valuation_date'];

            // $val_fac_name = ValuationFaculty::find()->where(['coe_val_faculty_id'=>$val_fac_allcoate['coe_val_faculty_id']])->one();
            
            // $examiner_name = $val_fac_name['faculty_name'];

            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId(); 

            $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->one();
            $category_type_id = Categorytype::find()->where(['description'=>"ESE(Dummy)"])->one();
            //$cia_type_id = Categorytype::find()->where(['description'=>"CIA"])->orWhere(['description'=>'Internal'])->one();
            $reg_cat_id = Categorytype::find()->where(['description'=>"Regular"])->one();
            $arr_cat_id = Categorytype::find()->where(['description'=>"Arrear"])->one();
            $exam_term_id = Categorytype::find()->where(['description'=>"End"])->one();
            $exam_term = $exam_term_id->coe_category_type_id;
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

          
            //echo  count($verify_stu_data)."<br>".$check_verify_count; exit;
           // print_r($_POST['register_numbers']);exit;
                 //new mark entered save to mark entry table
            if( isset($_POST['register_numbers'])) 
            {
                $get_id_details_data = ['exam_year'=>$exam_year,'exam_month'=>$exam_month];

                for ($i=0; $i <count($_POST['register_numbers']) ; $i++) 
                {
                   //echo $_POST['register_numbers'][$i];
                    $subject_map_id_dum = $_POST['sub_map_id'][$i];
                 
                    $stu_ese_marks = CoeValueMarkEntry::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'year'=>$get_id_details_data['exam_year'],'month'=>$get_id_details_data['exam_month'],'subject_map_id'=>$subject_map_id_dum])->all();
                    //print_r($stu_ese_marks);exit;

                    if(empty($stu_ese_marks))
                    {
                        $mark_entry_master = new CoeValueMarkEntry();
                        $mark_entry = new MarkEntry();
                        $regulation = new Regulation();
                        $student = StudentMapping::findOne($_POST['register_numbers'][$i]);
                        $checkMarkEntry = CoeValueMarkEntry::find()->where(['student_map_id'=>$_POST['register_numbers'][$i],'subject_map_id'=>$subject_map_id_dum])->one();

                        $sub_map_ids = $subject_map_id_dum;
                        $exam_type = empty($checkMarkEntry)?$reg_cat_id->coe_category_type_id:$arr_cat_id->coe_category_type_id;
                
                        if(empty($stu_cia_marks_check))
                        {
                            $stu_reg=StuInfo::findOne(['stu_map_id'=>$_POST['register_numbers'][$i]]);
                           
                            if($_POST['ese_marks'][$i]!='-1' && $_POST['ese_marks'][$i]>='0')
                            {                                

                                $getbatch=Yii::$app->db->createCommand("SELECT A.batch_name,A.coe_batch_id FROM coe_batch A JOIN coe_bat_deg_reg B ON B.coe_batch_id=A.coe_batch_id JOIN coe_student_mapping C ON C.course_batch_mapping_id=B.coe_bat_deg_reg_id WHERE B.coe_bat_deg_reg_id='".$stu_reg['batch_map_id']."'")->queryone();

                                $batch_name= $getbatch['batch_name'];

                               // $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                //$CIA=$stu_cia_marks_check['category_type_id_marks'];

                                $subese_marks= Yii::$app->db->createCommand("SELECT * FROM coe_value_subjects A JOIN sub B ON B.val_subject_id=A.coe_val_sub_id WHERE B.coe_sub_mapping_id='" . $subject_map_id_dum . "' ")->queryOne();
                                $stu_result_data=array();
                                if($subese_marks['ESE_max']==25 ||$subese_marks['ESE_max']==30)
                                {  
                                     $convert_ese_marks = round((($_POST['ese_marks'][$i])/2),0);
                                    //print_r($convert_ese_marks);exit;
                                     $INSERT_ESE_MARKS = $convert_ese_marks;
                                     $cia_marks=$CIA;
                                     $final_sub_total = $subese_marks['ESE_max']+$subese_marks['CIA_max'];
                                      // $total_marks = $convert_ese_marks+$cia_marks;


                                     $insert_total=$convert_ese_marks+$cia_marks;

                                     $total_marks = round(($insert_total/$final_sub_total)*10,1);//exit;

                                     $total_marks=$total_marks*10;
                                      $regulation = CoeBatDegReg::find()->where(['coe_batch_id'=>$getbatch['coe_batch_id'],'coe_bat_deg_reg_id'=>$subese_marks['batch_mapping_id']])->one();

        
                                    $grade_details = Regulation::find()->where(['regulation_year'=>$regulation->regulation_year])->all();
                                    $get_sub_max = $subject_details = CoeValueSubjects::findOne($subese_marks['coe_val_sub_id']);
                                      $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                                        $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_value_mark_entry WHERE subject_map_id="' . $subject_map_id_dum . '" AND student_map_id="' .$_POST['register_numbers'][$i] . '" AND result not like "%pass%" ')->queryScalar();
                                        $attempt = isset($check_attempt) && $check_attempt!="" ? (count($check_attempt)+1) : 0;


                                     foreach ($grade_details as $value) 
                                      {


                                          if($value['grade_point_to']!='')
                                          {
                                             
                                              //echo $insert_total;//exit;

                                              if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                                              {
                                                  if( $subject_details->CIA_max!=0 && ( $cia_marks<$subject_details->CIA_min || $convert_ese_marks<$subject_details->ESE_min || $insert_total<$subject_details->total_minimum_pass ) )
                                                  {
                                                    $result_stu = 'Fail';
                                                    $stu_result_data = ['result'=>$result_stu,'total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                                  } 
                                                  else if($subject_details->CIA_max==0 && ( $convert_ese_marks<$subject_details->ESE_min || $insert_total<$subject_details->total_minimum_pass ) )
                                                  {
                                                    $result_stu = 'Fail';
                                                    $stu_result_data = ['result'=>$result_stu,'total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                                  }      
                                                  else
                                                  {
                                                    $grade_name_prit = $value['grade_name'];
                                                    
                                                    $grade_point_arts = round(($insert_total/($get_sub_max->ESE_max+$get_sub_max->CIA_max) *10),1) ;
                                                  if($subject_details['CIA_max']==0)
                                                  {
                                                    $grade_point_arts = round(( (0+$convert_ese_marks)/$final_sub_total)*10,1);
                                                  }

                                                    $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$convert_ese_marks,'year_of_passing'=>$get_id_details_data['exam_month']."-".$get_id_details_data['exam_year']];

                                                   }



                        
                                                  
                                              }
                                          } 
                                          // Not Empty of the Grade Point 


                                      }

                                }
                                else
                                {

                                     $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                    $stu_result_data = ConfigUtilities::StudentResult($_POST['register_numbers'][$i], $subject_map_id_dum, $INSERT_ESE_MARKS,$get_id_details_data['exam_year'],$get_id_details_data['exam_month']);
                                }
                                //print_r($stu_result_data);exit; 

                                $model_save = new MarkEntry();
                                $model_save->student_map_id = $_POST['register_numbers'][$i];
                                $model_save->subject_map_id = $subject_map_id_dum;
                                $model_save->category_type_id =$externAl->coe_category_type_id;
                                $model_save->category_type_id_marks =$INSERT_ESE_MARKS;
                                $model_save->year = $get_id_details_data['exam_year'];
                                $model_save->month = $get_id_details_data['exam_month'];
                                $model_save->term = $exam_term;
                                $model_save->mark_type = $exam_type;
                                $model_save->created_at = $created_at;
                                $model_save->created_by = $updateBy;
                                $model_save->updated_at = $created_at;
                                $model_save->updated_by = $updateBy;

                                if($model_save->save())
                                {
                                    $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $get_id_details_data['exam_month']. "-" . $get_id_details_data['exam_year'] : '';
                                    $res_update = $_POST['ese_marks'][$i]=='-1'?'Absent':$stu_result_data['result'];
                                    $grade_name = $_POST['ese_marks'][$i]=='-1'?'AB':$stu_result_data['grade_name'];

                                    $markentrymaster = new ();
                                    $markentrymaster->student_map_id = $_POST['register_numbers'][$i];
                                    $markentrymaster->subject_map_id =$subject_map_id_dum;
                                    $markentrymaster->CIA = $CIA;
                                    $markentrymaster->ESE = $stu_result_data['ese_marks'];
                                    $markentrymaster->total = $stu_result_data['total_marks'];
                                    $markentrymaster->result = $res_update;
                                    $markentrymaster->grade_point = $stu_result_data['grade_point'];
                                    $markentrymaster->grade_name = $grade_name;
                                    $markentrymaster->attempt = $stu_result_data['attempt'];
                                    $markentrymaster->year = $get_id_details_data['exam_year'];
                                    $markentrymaster->month = $get_id_details_data['exam_month'];
                                    $markentrymaster->term = $exam_term;
                                    $markentrymaster->mark_type = $exam_type;
                                    $markentrymaster->year_of_passing = $year_of_passing;
                                    $markentrymaster->status_id = 0;
                                    $markentrymaster->created_by = $updateBy;
                                    $markentrymaster->created_at = $created_at;
                                    $markentrymaster->updated_by = $updateBy;
                                    $markentrymaster->updated_at = $created_at;
                                 
                                    if($markentrymaster->save(false))
                                    {
                                        try
                                        {
                                            
                                            $totalSuccess+=1;
                                        }
                                        catch(\Exception $e)
                                        {

                                           $dispResults[] = ['type' => 'E',  'message' => $message];
                                        }
                                        
                                        $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                    }
                                }
                            }
                            else
                            {
                               $getbatch=Yii::$app->db->createCommand("SELECT A.batch_name FROM coe_batch A JOIN coe_bat_deg_reg B ON B.coe_batch_id=A.coe_batch_id JOIN coe_student_mapping C ON C.course_batch_mapping_id=B.coe_bat_deg_reg_id WHERE B.coe_bat_deg_reg_id='".$stu_reg['batch_map_id']."'")->queryone();

                                $batch_name= $getbatch['batch_name'];

                                $INSERT_ESE_MARKS = $_POST['ese_marks'][$i]=='-1'?0:$_POST['ese_marks'][$i];
                                $CIA=$stu_cia_marks_check['category_type_id_marks'];
                                $stu_result_data = ConfigUtilities::StudentResult($_POST['register_numbers'][$i], $subject_map_id_dum, $CIA, $INSERT_ESE_MARKS,$get_id_details_data['exam_year'],$get_id_details_data['exam_month']);

                                $model_save = new MarkEntry();
                                $model_save->student_map_id = $_POST['register_numbers'][$i];
                                $model_save->subject_map_id = $subject_map_id_dum;
                                $model_save->category_type_id =43;
                                $model_save->category_type_id_marks =0;
                                $model_save->year = $get_id_details_data['exam_year'];
                                $model_save->month = $get_id_details_data['exam_month'];
                                $model_save->term = $exam_term;
                                $model_save->mark_type = $exam_type;
                                $model_save->created_at = $created_at;
                                $model_save->created_by = $updateBy;
                                $model_save->updated_at = $created_at;
                                $model_save->updated_by = $updateBy;

                                if($model_save->save(false))
                                {
                                    $year_of_passing =  '';
                                    
                                    $markentrymaster = new MarkEntryMaster();
                                    $markentrymaster->student_map_id = $_POST['register_numbers'][$i];
                                    $markentrymaster->subject_map_id =$subject_map_id_dum;
                                    $markentrymaster->CIA = $CIA;
                                    $markentrymaster->ESE = 0;
                                    $markentrymaster->total = $CIA;
                                    $markentrymaster->result = 'Absent';
                                    $markentrymaster->grade_point = 0;
                                    $markentrymaster->grade_name = 'U';
                                    $markentrymaster->attempt = $stu_result_data['attempt'];
                                    $markentrymaster->year = $get_id_details_data['exam_year'];
                                    $markentrymaster->month = $get_id_details_data['exam_month'];
                                    $markentrymaster->term = $exam_term;
                                    $markentrymaster->mark_type = $exam_type;
                                    $markentrymaster->year_of_passing = $year_of_passing;
                                    $markentrymaster->status_id = 0;
                                    $markentrymaster->created_by = $updateBy;
                                    $markentrymaster->created_at = $created_at;
                                    $markentrymaster->updated_by = $updateBy;
                                    $markentrymaster->updated_at = $created_at;
                                 
                                    if($markentrymaster->save(false))
                                    {
                                        try
                            
                                        {
                                            Yii::$app->db->createCommand('UPDATE coe_valuation_mark_details SET approved_by="'.$updateBy.'", approved_at="'.$created_at.'" WHERE val_faculty_all_id="' . $val_facl_id[0] . '" AND stu_reg_no="'.$stu_reg['reg_num'].'"')->execute();

                                             $check_data = "SELECT * FROM coe_absent_entry WHERE absent_student_reg='".$_POST['register_numbers'][$i]."' AND exam_type='".$exam_type."' AND absent_term='".$exam_term."' and exam_month='".$get_id_details_data['exam_month']."' and exam_year='".$get_id_details_data['exam_year']."' AND exam_subject_id='".$subject_map_id_dum." '";
                                            
                                            $available_data = Yii::$app->db->createCommand($check_data)->queryAll();
                                            if(empty($available_data))
                                            {
                                                $query_insert = 'INSERT INTO coe_absent_entry (`absent_student_reg`,`exam_type`,`absent_term`,`exam_subject_id`,`exam_absent_status`,`exam_month`,`exam_year`,`created_by`,`created_at`,`updated_by`,`updated_at`) VALUES ("'.$_POST['register_numbers'][$i].'","'.$exam_type.'","'.$exam_term.'","'.$subject_map_id_dum.'","'.$get_cat_entry_type['coe_category_type_id'].'","'.$get_id_details_data['exam_month'].'","'.$get_id_details_data['exam_year'].'","'.$updateBy.'","'.$created_at.'","'.$updateBy.'","'.$created_at.'")';
                                                $Insert_absent = Yii::$app->db->createCommand($query_insert)->execute();
                                            }


                                            $totalSuccess+=1;
                                        }
                                        catch(\Exception $e)
                                        {

                                           $dispResults[] = ['type' => 'E',  'message' => ''];
                                        }
                                        
                                        $dispResults[] = ['type' => 'S',  'message' => 'Success']; 
                                    }
                                    else
                                    {
                                        $dispResults[] = ['type' => 'E',  'message' => 'Error'];
                                    }
                                }
                            }
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found!! Kindly Check CIA Mark.');
                            return $this->redirect(['coe-add-exam-timetable/dummy-number-entryadc']);  
                        }
                    }
                    else
                    {
                        $stu_reg=StuInfo::findOne(['stu_map_id'=>$_POST['register_numbers'][$i]]);
                        //Yii::$app->db->createCommand('UPDATE coe_valuation_mark_details SET approved_by="'.$updateBy.'", approved_at="'.$created_at.'" WHERE val_faculty_all_id="' . $val_facl_id[0] . '" AND stu_reg_no="'.$stu_reg['reg_num'].'"')->execute();
                    }
                }

                if(isset($totalSuccess) && $totalSuccess>0)
                {
                    Yii::$app->db->createCommand('UPDATE coe_valuation_adc_faculty_allocate SET valuation_status="5" WHERE subject_code ="'.$qp_code.'"')->execute();
                
                    $get_allocate_id=Yii::$app->db->createCommand("SELECT val_faculty_all_id FROM coe_valuation_adc_faculty_allocate G WHERE G.exam_month='" . $exam_month . "' AND G.exam_year='" . $exam_year . "' AND G.subject_code='".$qp_code."' and valuation_status=5")->queryAll();
   
                           
                    if(count($get_allocate_id)>0)
                    { 
     
                         Yii::$app->ShowFlashMessages->setMsg('Success','Data Inserted Successfully');
                        return $this->redirect(['coe-add-exam-timetable/dummy-number-entryadc']);
                    }  
                    else
                    {
                         Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found! Please Check');
                        return $this->redirect(['coe-add-exam-timetable/dummy-number-entryadc']);
                    }
                          
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Insert the Data. Please Check');
                     return $this->redirect(['coe-add-exam-timetable/dummy-number-entryadc']);
                }

            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','Something Error! Please Check');
                return $this->redirect(['coe-add-exam-timetable/dummy-number-entryadc']);
            }
                
           
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DUMMY).' Mark Entry');
           return $this->render('dummy-number-entryadc', [
                'model' => $model,
                'examtimetable' => $examtimetable,
                'factallModel' =>$factallModel,
                'factallmodel' =>$factallmodel,
                'verify_stu_data'=>$verify_stu_data,
                'mark_diff_count'=>'0',
                'valuation_status'=>'',
                'title'=>'',
                'exam_year'=>'',
            ]); 
        } 
        
    }




}
