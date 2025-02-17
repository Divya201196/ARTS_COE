<?php

namespace app\controllers;
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');
use Yii;
use kartik\mpdf\Pdf;
// Student Information
use app\models\Student;
use app\models\StudentSearch;
use app\models\StuAddress;
use app\models\StuAddressSearch;
use app\models\Guardian;
use app\models\GuardianSearch;
use app\models\StudentMapping;
use app\models\CoeBatDegReg;
use app\models\Nominal;
use app\models\Categorytype;
use app\models\MarkEntry;
use app\models\MarkEntryMaster;
use app\models\Batch;

use app\models\AbsentEntry;
use app\models\HallAllocate;


use yii\db\Query;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use kartik\widgets\Growl;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
// Configuaration Information
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
//Pagination 
use yii\data\Pagination;

use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use PHPExcel_Style_Fill;
/**
 * StudentController implements the CRUD actions for Student model.
 */
class StudentController extends Controller
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
     * Lists all Student models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new StudentSearch(); 
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        if(Yii::$app->request->post()  && !empty(Yii::$app->request->post('finalString')))
        {
          $finalString = Yii::$app->request->post('finalString');
          $exam_id = split('[\^]', $finalString);
          $del_ids =[];
          $exam_dates='';
          for($i=0;$i<count($exam_id)-1;$i++)
          {     

            if(!empty($exam_id[$i]))
            {
                $stu_model = Student::findOne($exam_id[$i]);
                $stuMapping = StudentMapping::find()->where(['student_rel_id'=>$stu_model->coe_student_id]);
                
               $mark_entry = MarkEntry::findOne(['student_map_id'=>$stuMapping->coe_student_mapping_id]);
                $mark_entry_master = MarkEntryMaster::findOne(['student_map_id'=>$stuMapping->coe_student_mapping_id]);
                $nominal_check = Nominal::findOne(['coe_student_id'=>$exam_id[$i]]);
                
                $halll_allocation_entry = HallAllocate::findOne(['register_number'=>$stu_model->register_number]);
                $absent_entry = AbsentEntry::findOne(['absent_student_reg'=>$stuMapping->coe_student_mapping_id]);

                if(empty($mark_entry) && empty($mark_entry_master)  && empty($nominal_check)  && empty($halll_allocation_entry) && empty($absent_entry))
                {
                    $del_ids[$exam_id[$i]] = $exam_id[$i];                    
                }  
                $stu_reg_nums[] = !empty($stu_model) ? $stu_model->register_number:'';

            }            
          }
          $del_ids = array_filter($del_ids);
          $stu_reg_nums = array_unique(array_filter($stu_reg_nums));

          $stu_reg_nums = implode(', ', $stu_reg_nums); 
          $stu_reg_nums = trim($stu_reg_nums,', ');
          if(!empty($del_ids))
          {
             
             $stuMapping = (new Query)
                    ->createCommand()
                    ->delete('coe_student_mapping', ['IN','student_rel_id',$del_ids])
                    ->execute();
            $guardian = (new Query)
                    ->createCommand()
                    ->delete('coe_stu_guardian', ['IN','stu_guardian_id',$del_ids])
                    ->execute();
            $stuAddress = (new Query)
                    ->createCommand()
                    ->delete('coe_stu_address', ['IN','stu_address_id',$del_ids])
                    ->execute();

            $studentDel = (new Query)
                    ->createCommand()
                    ->delete('coe_student', ['IN','coe_student_id',$del_ids])
                    ->execute();

            Yii::$app->ShowFlashMessages->setMsg('Success','Selected '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT). ' Registration Numbers <b>'.$stu_reg_nums.'</b> Deleted Successfully!!');
          }
          else
          {
                Yii::$app->ShowFlashMessages->setMsg('Error','Unable to Delete '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT). ' <b>'.$stu_reg_nums.'</b> already in Use');
          }
          return $this->redirect(['index']);
          
        }



        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT). ' Management');
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionStudentBioData()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            
            $batch_id = $_POST['Student']['stu_batch_id'];
            //print_r($batch_id);exit;
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
           // $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           if(!empty($studentIds))
           {
               $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','G.programme_code','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name','guardian_relation','B.*'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id])
                    //->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    /*->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])*/
                    ->andWhere(['A.student_status'=>'Active'])
                    ->orderBy('degree_code ASC'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('student-bio-data', [
                    'stu_data' => $stu_data,
                     'deg' =>  $deg,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('student-bio-data', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Bio Data Export");
            return $this->render('student-bio-data', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }


     public function actionPassedout()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            
            $batch_id = $_POST['Student']['stu_batch_id'];
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           if(!empty($studentIds))
           {
               $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','G.programme_code','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name','guardian_relation','B.*','D.coe_student_mapping_id'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['A.student_status'=>'Active'])
                    ->orderBy('degree_code ASC'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('passedout', [
                    'stu_data' => $stu_data,
                     'deg' =>  $deg,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('passedout', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Degree info Export");
            return $this->render('passedout', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }


    public function actionPassedoutExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_bio_data'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DEGREE INFO.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                //'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                 'cssInline' => ' @media all{
                        table{text-align: left; border-collapse: collapse;   font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; font-weight: bold; text-transform: uppercase; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                            text-transform: uppercase;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                            height: 30px;
                            text-transform: uppercase;
                        }
                    }   
                ',
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DEGREE INFO'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                   
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    public function actionPassedoutExportExcel()
    {        
        $content = $_SESSION['stu_bio_data'];         
        $fileName = "Student Degree Info " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionVerifyTcDetails()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            
            $batch_id = $_POST['Student']['stu_batch_id'];
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           if(!empty($studentIds))
           {
               $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','G.programme_code','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name','guardian_relation','B.*'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id])
                    ->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])
                    ->andWhere(['A.student_status'=>'Active'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->orderBy('degree_code ASC'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('verify-tc-details', [
                    'stu_data' => $stu_data,
                     'deg' =>  $deg,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('verify-tc-details', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Bio Data Export");
            return $this->render('verify-tc-details', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }
    public function actionBulkupdate()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if(isset($_POST['get_edit_bulk']))
        {
            if(!empty($_POST['bulk_edit_of']))
            {
                $updatedStudents = $_POST['bulk_edit_of']; 
                foreach ($updatedStudents as $key => $value) 
                {
                    $model= $this->findModel($key);
                    $guardian = Guardian::findOne(['stu_guardian_id'=>$key]);       
                    $stuAddress = StuAddress::findOne(['stu_address_id'=>$key]);
                    $stuMapping = StudentMapping::findOne(['student_rel_id'=>$key]);

                    $model->name = $value['name'];
                    $model->dob = $value['dob'];
                    $model->email_id = $value['email_id'];
                    $model->mobile_no = $value['mobile_no'];
                    $model->aadhar_number = $value['aadhar_number'];

                    //$model->attributes = $value;
                    $guardian->attributes = $value;
                    $stuAddress->attributes = $value;
                    
                    $model->save(false);
                    $stuAddress->save(false);
                    $stuMapping->save(false);
                    $guardian->save(false);
                }
                Yii::$app->ShowFlashMessages->setMsg('Success',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Data Updated Successfully");
                return $this->redirect(['student/bulkupdate']);
                
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Resolve your Submission");
                return $this->render('bulkupdate', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
            }
        }
        else if ($model->load(Yii::$app->request->post()))
        {  
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
           if(!empty($studentIds))
           {
               
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.bloodgroup','C.guardian_name','C.guardian_mobile_no','C.guardian_email'])  
                                ->from('coe_student as A')
                                ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                                ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                                ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                                ->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])
                                ->andWhere(['A.student_status'=>'Active'])
                                
                                ->orderBy('A.register_number'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('bulkupdate', [
                    'stu_data' => $stu_data,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('bulkupdate', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Bulk Edit");
            return $this->render('bulkupdate', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }

    public function actionDobVerify()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
           if(!empty($studentIds))
           {
               
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','A.EMIS_no','A.abc_number_id','C.guardian_name','H.batch_name','G.programme_name','F.degree_name','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')                    
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    
                    ->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])
                    ->andWhere(['A.student_status'=>'Active'])
                    
                    ->orderBy('A.register_number'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('dob-verify', [
                    'stu_data' => $stu_data,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('dob-verify', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Bio Data Export");
            return $this->render('dob-verify', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }

    public function actionStuStatusList()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            
            $batch_id = $_POST['Student']['stu_batch_id'];
            $send_param = $model->admission_status;
               
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.register_number','A.name','H.batch_name','G.programme_name','G.programme_code','F.degree_code','I.description as admission_status'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')                    
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.status_category_type_id')
                    ->Where(['H.coe_batch_id' => $batch_id,'E.coe_batch_id'=>$batch_id])
                    ->andWhere(['D.status_category_type_id'=>$send_param])
                    ->andWhere(['A.student_status'=>'Active'])
                    ->groupBy('A.register_number')
                    ->orderBy('A.register_number'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('stu-status-list', [
                    'stu_data' => $stu_data,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Status Download");
            return $this->render('stu-status-list', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }
    public function actionStudentStatusExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_status_list'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' STATUS VERIFICATION.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                //'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; height:100;font-size: 25px; } 
                        
                        table td{
                            padding:20px;
                            border: 1px solid #000;
                            white-space: nowrap;
                            font-size: 18px; 
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                        }
                        table th{
                             padding:20px;
                            border: 1px solid #000;
                            white-space: nowrap;
                             font-size: 18px; 
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                        }
                    }   
                ',
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' STATUS VERIFICATION'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' STATUS VERIFICATION'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    public function actionStudentStatusExportExcel()
    {        
        $content = $_SESSION['stu_status_list'];         
        $fileName = "Student Status  " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionStudentDobExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_dob_bio_data'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DOB VERIFICATION.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                       table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:130%; height:120;font-size: 40px; } 
                        
                        table td{
                            padding:30px;
                            border: 1px solid #000;
                            text-align: left;
                            line-height: 2em;
                        }
                        table th{
                             padding:30px;
                            line-height: 2em;
                            border: 1px solid #000;
                            text-align: left;
                        }
                    }   
                ',
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DOB VERIFICATION'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DOB VERIFICATION'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    







    public function actionStudentDobExportExcel()
    {        
        $content = $_SESSION['stu_dob_bio_data'];         
        $fileName = "Student DOB Verify  " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionStudentExamApplication()
    {
        
        $model = new Student();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if(isset($_SESSION['student_application_date'])){ unset($_SESSION['student_application_date']);}
        if($model->load(Yii::$app->request->post()) )
        {   

            if(!empty($model->register_number_from) && !empty($model->register_number_to) )
            {

                $app_month = isset($model->app_month)?$model->app_month:$_POST['Student']['app_month'];
                $exam_year=$model->app_year;
                $batch_mapping_id = $model->stu_programme_id;                    
                $sem = ConfigUtilities::semCaluclation($exam_year,$app_month,$batch_mapping_id);
                
                if($sem==0)
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"OOPS Data Submitted Wrongly");
                    return $this->redirect(['student/student-exam-application']);
                }

                $nominal_check = Nominal::find()->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();

                $get_exam_type = CategoryType::find()->where(['coe_category_type_id'=>$model->exam_type])->one();

                $data_1=[];
                $query = new  Query();
                $query_1 = new Query();
                $query->select('A.name,A.register_number, C.semester, D.subject_fee,D.subject_code,D.subject_name, A.dob, H.current_address, H.current_city, H.current_state, H.current_country, H.current_pincode, F.programme_name,G.degree_name')
                        ->from('coe_student as A')                    
                        ->join('JOIN','coe_student_mapping as B','A.coe_student_id=B.student_rel_id')
                        ->join('JOIN','coe_stu_address as H','A.coe_student_id=H.stu_address_id')
                        ->join('JOIN','coe_subjects_mapping as C','C.batch_mapping_id=B.course_batch_mapping_id')
                        ->join('JOIN','coe_subjects as D','D.coe_subjects_id=C.subject_id')
                        ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=C.batch_mapping_id')
                        ->join('JOIN','coe_programme as F','F.coe_programme_id=E.coe_programme_id')
                        ->join('JOIN','coe_degree as G','G.coe_degree_id=E.coe_degree_id')
                        ->join('JOIN','coe_exam_timetable as J','J.subject_mapping_id=C.coe_subjects_mapping_id')
                        ->join('JOIN','coe_category_type as I','I.coe_category_type_id=C.subject_type_id')
                        ->join('JOIN','coe_category_type as Z','Z.coe_category_type_id=B.status_category_type_id');
               

                if(stristr($get_exam_type->category_type, "Arrear") || stristr($get_exam_type->description, "Arrear"))
                {
                    $query->join('JOIN','coe_mark_entry_master q','q.student_map_id=B.coe_student_mapping_id and q.subject_map_id=C.coe_subjects_mapping_id')
                        ->join('JOIN','coe_fees_paid ab','ab.student_map_id=q.student_map_id and q.subject_map_id=ab.subject_map_id');       
                    $query->Where(['q.year_of_passing'=>'','A.student_status'=>'Active'])
                                ->andWhere('C.batch_mapping_id=:programme', [':programme' => $model->stu_programme_id])
                                ->andWhere(['between', "A.register_number", $model->register_number_from,$model->register_number_to ])
                                ->andWhere('YEAR(J.exam_date)=:exam_year', [':exam_year' => $model->app_year])
                                ->andWhere('C.semester< :sem',[':sem'=>$sem])
                                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                                ->andWhere(['<>', 'ab.status', 'NO']);
                                               
                    $query->groupBy('A.coe_student_id, D.coe_subjects_id')
                    ->orderBy('A.register_number,D.coe_subjects_id'); 
                    $data = $query->createCommand()->queryAll();

                    if(count($data)>0)
                    {
                        return $this->render('student-exam-application', [
                            'model' => $model,
                            'data' => $data,
                            'data_1'=>$data_1,
                        ]);
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found Kindly re-check your Submission");
                        return $this->redirect(['student/student-exam-application']);
                        
                    } 
                }


                    if(!empty($nominal_check))
                    {
                        $query_1->select('A.name,A.register_number, C.semester, D.subject_fee,D.subject_code,D.subject_name, A.dob, H.current_address, H.current_city, H.current_state, H.current_country, H.current_pincode, F.programme_name,G.degree_name')
                            ->from('coe_student as A')                    
                            ->join('JOIN','coe_student_mapping as B','A.coe_student_id=B.student_rel_id')
                            ->join('JOIN','coe_stu_address as H','A.coe_student_id=H.stu_address_id')
                            ->join('JOIN','coe_subjects_mapping as C','C.batch_mapping_id=B.course_batch_mapping_id')
                            ->join('JOIN','coe_subjects as D','D.coe_subjects_id=C.subject_id')
                            ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=C.batch_mapping_id')
                            ->join('JOIN','coe_programme as F','F.coe_programme_id=E.coe_programme_id')
                            ->join('JOIN','coe_degree as G','G.coe_degree_id=E.coe_degree_id')
                            ->join('JOIN','coe_exam_timetable as J','J.subject_mapping_id=C.coe_subjects_mapping_id')
                            ->join('JOIN','coe_category_type as I','I.coe_category_type_id=J.exam_type')
                            ->join('JOIN','coe_category_type as Z','Z.coe_category_type_id=B.status_category_type_id');
                            $query_1->join('JOIN','coe_nominal l','l.course_batch_mapping_id = E.coe_bat_deg_reg_id and l.coe_student_id=A.coe_student_id and l.coe_subjects_id=D.coe_subjects_id ');
                        
                            $query_1->where('B.section_name=:section_name', [':section_name' => $model->stu_section_name])
                            ->andWhere(['A.student_status'=>'Active'])
                            ->andWhere('C.batch_mapping_id=:programme', [':programme' => $model->stu_programme_id])
                            ->andWhere('C.semester=:sem',[':sem'=>$sem]) 
                            ->andWhere(['between', "A.register_number", $model->register_number_from,$model->register_number_to ])
                            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                            ->andWhere('J.exam_month=:exam_month', [':exam_month' => $app_month]);
                        
                            $query_1->groupBy('A.coe_student_id, D.coe_subjects_id')
                            ->orderBy('A.register_number,D.coe_subjects_id');

                            $data_1 = $query_1->createCommand()->queryAll();
                        }        
                   
                            $query->Where(['A.student_status'=>'Active'])
                            ->andWhere('C.batch_mapping_id=:programme', [':programme' => $model->stu_programme_id])
                            ->andWhere(['between', "A.register_number", $model->register_number_from,$model->register_number_to ])
                            ->andWhere('C.semester=:sem',[':sem'=>$sem]) 
                            ->andWhere('J.exam_month=:exam_month', [':exam_month' => $app_month]) 
                            ->andWhere(['NOT LIKE','I.description', 'Elective'])
                            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    
                        $query->groupBy('A.coe_student_id, D.coe_subjects_id')
                        ->orderBy('A.register_number,D.coe_subjects_id'); 

                     $data = $query->createCommand()->queryAll();
                   
                    if(!empty($data_1) || !empty($data))
                    {
                        return $this->render('student-exam-application', [
                            'model' => $model,
                            'data' => $data,
                            'data_1' => $data_1,
                           
                        ]);
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error','No Data Found On Your Submission!!!');
                        return $this->redirect(['student/student-exam-application']);
                    }
                    /*$data = isset($data_1)?array_merge($data,$data_1):$data;
                
                   $data = array_intersect($data_1, $data);*/
                    return $this->render('student-exam-application', [
                        'model' => $model,
                        'data' => $data,
                        'data_1' => $data_1,
                       
                    ]);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','Re-Check Your Application Submission!!!');
                return $this->render('student-exam-application', [
                    'model' => $model,
                    
                ]);
            }
        }
        else
        {

            Yii::$app->ShowFlashMessages->setMsg('Welcome',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Application Printing!!!');
            return $this->render('student-exam-application', [
                'model' => $model,
               
            ]);
        }
        
    }

    public function actionStudentBioExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_bio_data'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' BIO DATA.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                //'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                 'cssInline' => ' @media all{
                        table{text-align: left; border-collapse: collapse;   font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; font-weight: bold; text-transform: uppercase; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                            text-transform: uppercase;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                            height: 30px;
                            text-transform: uppercase;
                        }
                    }   
                ',
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' BIO DATA'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                   // 'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' BIO DATA'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    public function actionStudentBioExportExcel()
    {        
        $content = $_SESSION['stu_bio_data'];         
        $fileName = "Student Bio Data " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionPrintApplicationPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['student_application_date'];
        //unset($_SESSION['student_application_date']);
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE, 
                
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Application.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }td{border:1px solid #999}
                    }   
                ', 
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Application'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Application'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    
    public function actionRegisterNumbers($q = null, $programme=null,$section=null)
    {   
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            $out = ['results' => ['id' => '', 'text' => '']];
            if (!is_null($q)) 
            {

                $query = (new \yii\db\Query());
                $query->select('A.register_number as id,A.register_number AS text')
                //$query->select('count(D.*) as count')
                    ->from('coe_student as A')                    
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->where(['like', 'A.register_number', $q]);
                if($section!='All')
                {
                    $query->andWhere('D.section_name=:section_name', [':section_name' => $section]);
                }
                
                    $query->andWhere('D.course_batch_mapping_id=:programme', [':programme' => $programme])
                    ->andWhere(['A.student_status'=>'Active'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->orderBy('A.register_number');                      
                $command = $query->createCommand();
                $data = $command->queryAll();

                $out['results'] = array_values($data);
            }
            // elseif ($id > 0) {
            //     $out['results'] = ['id' => $id, 'text' => Student::find($id)->register_number];
            // }
            return $out;
    }

    /**
     * Displays a single Student model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model= $this->findModel($id);

        $guardian = Guardian::findOne(['stu_guardian_id'=>$id]);       
        $stuAddress = StuAddress::findOne(['stu_address_id'=>$id]);
        $stuMapping = StudentMapping::findOne(['student_rel_id'=>$id]);
        return $this->render('view', [
            'model' => $model,
            'guardian' => $guardian,
            'stuAddress' => $stuAddress,
            'stuMapping' => $stuMapping,
        ]);
    }

    /**
     * Creates a new Student model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();
        $admission_count = Student::find()->count();
        
        if (Yii::$app->request->isAjax) {
            if($model->load(Yii::$app->request->post())) {
                array('onclick'=>'$("#student_form_required_page").dialog("open"); return false;');
                \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ActiveForm::validate($model);
            }
        }
        if(empty($admission_count)) {
            $admission_count = 1;
        }
        else {
            $chk_id = Student::find()->where(['coe_student_id' => $admission_count])->exists();
            if($chk_id)
                $admission_count = $admission_count + 1;
            }
        
        if ($model->load(Yii::$app->request->post()) && 
            $stuAddress->load(Yii::$app->request->post())  &&
            $guardian->load(Yii::$app->request->post())  
            ) {

          
            if(isset($_POST['StudentMapping']['previous_reg_number']) && !empty($_POST['StudentMapping']['previous_reg_number']))
                {
                    $check_reg_number = Student::find()->where(['register_number'=>$_POST['StudentMapping']['previous_reg_number']])->all();
                    if(empty($check_reg_number))
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Find the Previous Registration Number <b>".$_POST['StudentMapping']['previous_reg_number'].'</b> Kindly Re-Check Your Submissio');
                        return $this->render('create',  [
                            'model' => $model,
                            'guardian'=>$guardian,
                            'stuAddress'=>$stuAddress,
                            'stuMapping' => $stuMapping,
                            'admission_count' => $admission_count,
                        ]);
                    }

                }
            $stuAddress->attributes = $_POST['StuAddress'];

            $guardian->attributes = $_POST['Guardian'];   
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId(); 
            $model->dob = date('Y-m-d', strtotime(str_replace('-','/', $_POST['dob'])));
            $model->admission_date = date('Y-m-d');
            $model->created_at = $created_at;
            $model->created_by = $updateBy;
            $model->updated_at = $created_at;
            $model->updated_by = $updateBy; 
            $admission_category_type_id = $_POST['StudentMapping']['admission_category_type_id']; 

            if($model->save(false))
            {
                $stuAddress->stu_address_id =  $model->coe_student_id;
                $guardian->stu_guardian_id = $model->coe_student_id;
                $stuMapping->student_rel_id = $model->coe_student_id;
                if(isset($_POST['StudentMapping']['previous_reg_number']) && !empty($_POST['StudentMapping']['previous_reg_number']))
                {
                    $stuMapping->previous_reg_number = $_POST['StudentMapping']['previous_reg_number'];
                }
                
                $stuMapping->course_batch_mapping_id = $model->stu_programme_id;
                $stuMapping->section_name = $model->stu_section_name;
                $stuMapping->status_category_type_id = $model->admission_status;
                $stuMapping->admission_category_type_id = $admission_category_type_id;
                $stuMapping->created_at = $created_at;
                $stuMapping->created_by = $updateBy;
                $stuMapping->updated_at = $created_at;
                $stuMapping->updated_by = $updateBy;
                
                if($stuAddress->save(false) && $guardian->save(false) && $stuMapping->save())
                {
                    if(isset($_POST['Guardian']['guardian_name_1']) && isset($_POST['Guardian']['guardian_relation_1'])) 
                    {
                        unset($guardian);
                        $guardian = new Guardian();
                        $guardian->stu_guardian_id = $model->coe_student_id;
                        $guardian->guardian_name = $_POST['Guardian']['guardian_name_1'];
                        $guardian->guardian_mobile_no = $_POST['Guardian']['guardian_mobile_no_1'];
                        $guardian->guardian_relation = $_POST['Guardian']['guardian_relation_1'];
                        $guardian->guardian_address = $_POST['Guardian']['guardian_address'];
                        $guardian->guardian_income = 100000;
                        $guardian->save(false);

                    }

                    Yii::$app->ShowFlashMessages->setMsg('Success',$model->register_number.' Added Successfully!!!');
                    unset($guardian);unset($stuMapping);unset($stuAddress);unset($model);unset($admission_count);
                    $admission_count = Student::find()->count()+1;
                    $model = new Student(); $guardian = new Guardian(); $stuAddress = new StuAddress();
                    $stuMapping = new StudentMapping();
                }
                else
                {
                    $id =$model->coe_student_id;
                    $model = $this->findModel($id);  
                    $register_number = $model->register_number;
                    if($model->delete()){
                        $stuMapping = StudentMapping::deleteAll(['student_rel_id' => $id]);
                        $guardian = Guardian::deleteAll(['stu_guardian_id' => $id]);
                        $stuAddress = StuAddress::deleteAll(['stu_address_id' => $id]);
                        $admission_count = Student::find()->count()+1;
                    }
                    Yii::$app->ShowFlashMessages->setMsg('Error',$register_number.' Not Added Please re Submit the form');
                }
                return $this->render('create',  [
                    'model' => $model,
                    'guardian'=>$guardian,
                    'stuAddress'=>$stuAddress,
                    'stuMapping' => $stuMapping,
                    'admission_count' => $admission_count,
                ]);
            }
            else {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Perform the action for ".$model->register_number.' <br /> Please re-check the Submitted Data');
                    return $this->render('create', [
                        'model' => $model,
                        'admission_count' => $admission_count,
                        'guardian'=>$guardian,
                        'stuAddress'=>$stuAddress,'stuMapping' => $stuMapping,
                    ]);
            }
            

            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT));
            return $this->render('create', [
                'admission_count' => $admission_count,
                'model' => $model,
                'guardian'=>$guardian,
                'stuAddress'=>$stuAddress,'stuMapping' => $stuMapping,
            ]);
        }
    }

    /**
     * Updates an existing Student model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id); 
       
        $guardian = Guardian::findOne(['stu_guardian_id'=>$model->coe_student_id]);       
        $stuAddress = StuAddress::findOne(['stu_address_id'=>$model->coe_student_id]);
        $stuMapping = StudentMapping::findOne(['student_rel_id'=>$model->coe_student_id]);
       
        if ($model->load(Yii::$app->request->post()) ) 
        {
            if(isset($_POST['StudentMapping']['previous_reg_number']) && !empty($_POST['StudentMapping']['previous_reg_number']))
            {
                $check_reg_number = Student::find()->where(['register_number'=>$_POST['StudentMapping']['previous_reg_number']])->one();
                
                if(empty($check_reg_number))
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Find the Previous Registration Number <b>".$_POST['StudentMapping']['previous_reg_number'].'</b> Kindly Re-Check Your Submissio');
                     return $this->redirect([
                        'update', 'id' => $model->coe_student_id
                    ]);
                }
                else
                {
                    $stuMapping->previous_reg_number = $check_reg_number['register_number'];
                }

            }
            $model->stu_programme_id = $_POST['stu_programme_id'];
            $model->stu_batch_id = $_POST['stu_batch_id'];
            $model->stu_section_name = isset($_POST['Student']['stu_section_name'])? $_POST['Student']['stu_section_name']:$_POST['stu_section_name'];
            $admission_category_type_id = $_POST['StudentMapping']['admission_category_type_id'];
            $guardian->attributes = $_POST['Guardian'];
            $stuAddress->attributes = $_POST['StuAddress'];   
            
            $updated_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId(); 
           
            $model->dob = date('Y-m-d', strtotime(str_replace('-','/', $_POST['dob'])));

            $model->aadhar_number = $model->aadhar_number;                        
            $model->admission_date = date('Y-m-d', strtotime(str_replace('-','/', $model->admission_date)));
            $model->updated_at = $updated_at;
            $model->updated_by = $updateBy; 

            $model->save(false);
            
            $stuAddress->stu_address_id =  $model->coe_student_id;
            $guardian->stu_guardian_id = $model->coe_student_id;
            $stuMapping->student_rel_id = $model->coe_student_id;
            $stuMapping->course_batch_mapping_id = $model->stu_programme_id;
            $stuMapping->section_name = $model->stu_section_name;
            $stuMapping->status_category_type_id = $model->admission_status;
            $stuMapping->admission_category_type_id = $admission_category_type_id;                
            $stuMapping->updated_at = $updated_at;
            $stuMapping->updated_by = $updateBy;
            $guardian->save(false);
            $stuAddress->save(false);
            $stuMapping->save(false);

            if(isset($_POST['Guardian']['guardian_name_1']) && isset($_POST['Guardian']['guardian_relation_1']) && !empty($_POST['Guardian']['guardian_name_1']) && !empty($_POST['Guardian']['guardian_relation_1']))
            {
                $get_guardian_id = Guardian::find()->where(['stu_guardian_id'=>$model->coe_student_id])->andWhere(['<>','guardian_relation',$_POST['Guardian']['guardian_relation']])->one();
               
                $guardian_1_update = Guardian::findOne(['coe_guardian_id'=>$get_guardian_id->coe_guardian_id]);  

                $guardian_1_update->guardian_name=$_POST['Guardian']['guardian_name_1'];
                $guardian_1_update->guardian_relation=$_POST['Guardian']['guardian_relation_1'];
                $guardian_1_update->guardian_mobile_no=$_POST['Guardian']['guardian_mobile_no_1'];
                $guardian_1_update->save(false);

            }

            return $this->redirect([
                'view', 'id' => $model->coe_student_id
            ]);

            
        } else {

            $batch = CoeBatDegReg::findOne($stuMapping->course_batch_mapping_id);            
            $model->stu_programme_id = $batch->coe_programme_id;
            $model->stu_batch_id = $batch->coe_batch_id;
            $model->stu_section_name = $stuMapping->section_name;
            return $this->render('update', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,

            ]);
        }
    }

    /**
     * Deletes an existing Student model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {

        $model = $this->findModel($id);
        $stuMapping = StudentMapping::findOne(['student_rel_id' => $id]);  
        $mark_entry = $nominal_check = $mark_entry_master = $halll_allocation_entry = $absent_entry = [];
        if(!empty($stuMapping))
        {
             $mark_entry = MarkEntry::findOne(['student_map_id'=>$stuMapping->coe_student_mapping_id]);
            $mark_entry_master = MarkEntryMaster::findOne(['student_map_id'=>$stuMapping->coe_student_mapping_id]);
            $nominal_check = Nominal::findOne(['coe_student_id'=>$id]);
            
            $halll_allocation_entry = HallAllocate::findOne(['register_number'=>$model->register_number]);
            $absent_entry = AbsentEntry::findOne(['absent_student_reg'=>$stuMapping->coe_student_mapping_id]);
        }

       
        $register_number = $model->register_number;
        if(!empty($nominal_check) || !empty($mark_entry) || !empty($mark_entry_master) || !empty($halll_allocation_entry) || !empty($absent_entry))
        {
            //$model->student_status="In-Active";
                Yii::$app->ShowFlashMessages->setMsg('Error',"You are not allowed to Delete the ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Because <b>".$register_number.' </b> Has linked with the Marks OR Nominal.');
                return $this->redirect(['index']);

        }
        else{
                $stuMapping = StudentMapping::deleteAll(['student_rel_id' => $id]);
                $guardian = Guardian::deleteAll(['stu_guardian_id' => $id]);
                $stuAddress = StuAddress::deleteAll(['stu_address_id' => $id]);
                if($model->delete())
                {
                    Yii::$app->ShowFlashMessages->setMsg('Success',"Register Number ".$register_number.' Deleted Successfully!! ');
                    return $this->redirect(['index']);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Perform the action for ".$register_number.' <br /> Technical Problem');
                    return $this->redirect(['index']);
                }
        }

        
               
    }


    public function actionListPrintPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['stu_bio_data_download'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' =>  'Student List With Names.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #000;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            padding: 5px;
                        }
                        table th{
                            border: 1px solid #000;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                            padding: 5px;
                        }
                    }   
                ',
            'options' => ['title' => 'Student List With Names'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ 'Student List With Names' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
       
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelListPrint()
    {
       
        $content = $_SESSION['stu_bio_data_download'];
          
        $fileName = 'Student List With Names.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionListPrint()
    {
        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
           if(!empty($studentIds))
           {
            
               $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')                    
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id');
                    if(isset($model->stu_section_name) && $model->stu_section_name!='All')
                    {
                        $fetch_query->where(['D.section_name'=>$model->stu_section_name]); 
                    }
                     $fetch_query->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])
                    ->andWhere(['A.student_status'=>'Active']);
                 $fetch_query->groupBy('A.register_number')->orderBy('A.register_number'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('list-print', [
                    'stu_data' => $stu_data,
                     'deg' =>  $deg,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('list-print', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Bio Data Export");
            return $this->render('list-print', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }

    /**
     * Finds the Student model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Student the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Student::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


    public function actionStudentDegreeInfo()
    {

        $model = new Student();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            
            $batch_id = $_POST['Student']['stu_batch_id'];
            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           if(!empty($studentIds))
           {
               $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','G.programme_code','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name','guardian_relation','B.*','D.coe_student_mapping_id'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['A.student_status'=>'Active'])
                    ->orderBy('degree_code ASC'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

                return $this->render('student-degree-info', [
                    'stu_data' => $stu_data,
                     'deg' =>  $deg,
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('student-degree-info', [
                    'model' => $model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Degree info Export");
            return $this->render('student-degree-info', [
                'model' => $model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }


    public function actionStudentDegreeExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_bio_data'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DEGREE INFO.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                //'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                 'cssInline' => ' @media all{
                        table{text-align: left; border-collapse: collapse;   font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; font-weight: bold; text-transform: uppercase; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                            text-transform: uppercase;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                            height: 30px;
                            text-transform: uppercase;
                        }
                    }   
                ',
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' DEGREE INFO'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                   
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    public function actionStudentDegreeExportExcel()
    {        
        $content = $_SESSION['stu_bio_data'];         
        $fileName = "Student Degree Info " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    
  public function actionAbcAccountCreation()
    {

        $model = new Student();
        $Model = new MarkEntry();
        $guardian = new Guardian();
        $stuAddress = new StuAddress();
        $stuMapping = new StudentMapping();

        if ($model->load(Yii::$app->request->post()))
        {  
            
            $batch_id = $_POST['Student']['stu_batch_id'];
            $abc_id = $_POST['MarkEntry']['mark_type'];
           // print_r($abc_id);exit;

            $studentIds = $stuMapping->find()->select('student_rel_id')->where(['course_batch_mapping_id'=>$model->stu_programme_id])->all();
           // $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
           if(!empty($studentIds))
           {

            if(!empty($abc_id))
            {


              $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.abc_number_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','G.programme_code','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name','guardian_relation','B.*'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id])
                    ->andWhere(['<>','abc_number_id',''])
                    //->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    /*->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])*/
                    ->andWhere(['A.student_status'=>'Active'])
                    ->orderBy('degree_code ASC'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

           
            }
            else

            {
            
            $deg = Yii::$app->db->createCommand('SELECT DISTINCT degree_code,programme_name,batch_name FROM `coe_degree` as A,coe_programme as B,coe_bat_deg_reg as C,coe_student as D,coe_student_mapping as E,coe_batch as F where A.coe_degree_id=C.coe_degree_id and B.coe_programme_id=C.coe_programme_id and D.coe_student_id=E.student_rel_id and C.coe_bat_deg_reg_id=E.course_batch_mapping_id and F.coe_batch_id=C.coe_batch_id and E.course_batch_mapping_id="'.$model->stu_programme_id.'"')->queryOne(); 
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','A.gender','A.nationality','A.sub_caste','A.admission_year','A.admission_date','A.aadhar_number','A.mobile_no','A.email_id','A.abc_number_id','A.name','A.dob','A.religion', 'A.caste','A.sub_caste','A.bloodgroup','C.guardian_name','H.batch_name','G.programme_name','G.programme_code','F.degree_name','F.degree_code','C.guardian_mobile_no','C.guardian_email','I.description as admission_status','D.section_name','guardian_relation','B.*'])  
                    ->from('coe_student as A')
                    ->join('JOIN', 'coe_stu_address as B','A.coe_student_id=B.stu_address_id')      
                    ->join('JOIN', 'coe_stu_guardian as C','A.coe_student_id=C.stu_guardian_id')
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    ->join('JOIN','coe_category_type as I','I.coe_category_type_id=D.admission_category_type_id')
                    ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id])
                    ->andWhere(['IN','abc_number_id',''])
                    //->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    /*->andWhere('D.course_batch_mapping_id = :course_batch_mapping_id', [':course_batch_mapping_id' => $model->stu_programme_id])*/
                    ->andWhere(['A.student_status'=>'Active'])
                    ->orderBy('degree_code ASC'); 
                $stu_data = $fetch_query->createCommand()->queryAll();

            }


                return $this->render('abc-account-creation', [
                    'stu_data' => $stu_data,
                     'deg' =>  $deg,
                    'model' => $model,
                    'Model' => $Model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('abc-account-creation', [
                    'model' => $model,
                    'Model' => $Model,
                    'guardian' => $guardian,
                    'stuAddress' => $stuAddress,
                    'stuMapping' => $stuMapping,
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Bio Data Export");
            return $this->render('abc-account-creation', [
                'model' => $model,
                'Model' => $Model,
                'guardian' => $guardian,
                'stuAddress' => $stuAddress,
                'stuMapping' => $stuMapping,
            ]);
        } 
    }

    public function actionAbcAccountCreationExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_bio_data'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' BIO DATA.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                //'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                 'cssInline' => ' @media all{
                        table{text-align: left; border-collapse: collapse;   font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; font-weight: bold; text-transform: uppercase; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                            text-transform: uppercase;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                            height: 30px;
                            text-transform: uppercase;
                        }
                    }   
                ',
                'options' => ['title' => 'ABC ACCOUNT CREATION'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                   // 'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' BIO DATA'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
  
    public function actionAbcAccountCreationExportExcel()
    {
        
          
        $content = $_SESSION['get_excel_query'];
     
         $objPHPExcel = new \PHPExcel();
         $objPHPExcel->createSheet(0); //Setting index when creating
         $objPHPExcel->setActiveSheetIndex(0);
         $objWorkSheet = $objPHPExcel->getActiveSheet();
         $objWorkSheet->setTitle('Abc Account Creation Web');

         $objWorkSheet->setCellValue('A1','ORG_NAME');
         $objWorkSheet->setCellValue('B1','DEPT_NAME');
         $objWorkSheet->setCellValue('C1','PROGRAMME NAME');
         $objWorkSheet->setCellValue('D1','REGN_NO');
         $objWorkSheet->setCellValue('E1','CNAME');
         $objWorkSheet->setCellValue('F1','DOB');
         $objWorkSheet->setCellValue('G1','GENDER');
         $objWorkSheet->setCellValue('H1','MOBILE');
         $objWorkSheet->setCellValue('I1','EMAIL');
         $objWorkSheet->setCellValue('J1','AADHAAR_NO');
         


    $objPHPExcel->setActiveSheetIndex(0);                
    $sheet = $objPHPExcel->getSheet();
    $objSheet = $objPHPExcel->getActiveSheet();
    $firstColumn = $sheet->getHighestColumn();
    $row = 1;
    
    
     $strt=21;

                          
  $sno=1;$row=2;
 foreach ($content as  $rows)
 {
    
       
 require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
    

       $date=date_create($rows['dob']);
       $dob= date_format($date,"d-m-Y ");

        $objWorkSheet->setCellValue('A'.$row,$org_name);
        $objWorkSheet->setCellValue('B'.$row,$rows["degree_code"].'-'.$rows["programme_code"]);
        $objWorkSheet->setCellValue('C'.$row,$rows["programme_name"]);
        $objWorkSheet->setCellValue('D'.$row,$rows["register_number"]);
        $objWorkSheet->setCellValue('E'.$row,$rows["name"]);
        $objWorkSheet->setCellValue('F'.$row,$rows["gender"]);
        $objWorkSheet->setCellValue('G'.$row,$dob);
        $objWorkSheet->setCellValue('H'.$row,$rows['aadhar_number']);
       

     $strt=21;


     $sll=1;


  $row++;
$sno++;
}
      

      header('Content-type: application/.csv');
        header('Content-Disposition: attachment; filename="report.csv"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

        }


}
