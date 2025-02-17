<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\db\Query;
use yii\helpers\Html;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\Configuration;
use kartik\widgets\Growl;
// Below models related to the Cateogry
use app\models\Categories;
use app\models\Categorytype;
// Below Models Related to Batch
use app\models\Regulation;
use app\models\Degree;
use app\models\Programme;
use app\models\Batch;
use app\models\CoeBatDegReg;
use app\models\Student;
use app\models\StudentMapping;
/* Absent */
use app\models\AbsentEntry;
use app\models\ExamTimetable;
use app\models\HallAllocate;
/* Absent End */
/* Migration Requirement */
use app\models\FacultyHallArrange;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\ValuationFacultyAllocate;
use app\models\QpSetting;
use app\models\ValuationFaculty;
use app\models\ValuationScrutiny;
use app\models\MarkEntry;
use kartik\mpdf\Pdf;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Alignment;
error_reporting(0);
/**
 * BatchController implements the CRUD actions for Batch model.
 */
class QpController extends Controller {

     public function actionQpsetting()
    {
        $subjectdata=array_filter(['']);
        $model = new QpSetting();
        if (Yii::$app->request->post()) 
        { 
           $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

           $pract_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where coe_category_type_id!='137' AND category_type like '%Theory%'")->queryAll();
                   //print_r($pract_id);exit();
                    foreach ($pract_id as $key => $value) {
                       $pracIds[$value['coe_category_type_id']]=$value['coe_category_type_id'];
                    }

                    $pracIds1='';
                            foreach ($pract_id as $key => $value) {
                               $pracIds1.=$value['coe_category_type_id'].',';
                            }

                            $practid='';
                            if(!empty($pract_id))
                            {
                                $pracIds1=rtrim($pracIds1,',');

                                $practid='AND paper_type_id IN ('.$pracIds1.')';
                            }

                    

            if($_POST['qpfinshed']==1)
            { 
                $created_at = date("Y-m-d H:i:s");
                $updateBy = Yii::$app->user->getId();

                if($_POST['exam_type']==28)
                {
                    $month=($_POST['month']=='29')?'30':'29';
                    $year=($_POST['month']==29)?($_POST['year']-1):$_POST['year'];
                }
                else
                {
                     $month=$_POST['month'];
                     $year=$_POST['year'];
                }

                $dmonth=$_POST['month'];
                $dyear=$_POST['year'];               
                $batch_id=$_POST['batch'];
                $exam_type =$_POST['exam_type'];

                $subject_id =$_POST['subject_id'];

                $num_question_set =$_POST['num_question_set'];
                //print_r($subject_code); exit;
                for ($i=0; $i <count($num_question_set) ; $i++) 
                { 
                    $update = Yii::$app->db->createCommand('UPDATE coe_qp_setting SET num_question_set='.$num_question_set[$i].', updated_at="'.$created_at.'", updated_by="'.$updateBy.'" WHERE year='.$year.' AND month='.$month.' AND exam_type='.$exam_type.' AND subject_id="'.$subject_id[$i].'" AND faculty1_id!=""')->execute();
                }

                $pquery = new Query();
                $pquery->select('coe_bat_deg_reg_id')->from('coe_bat_deg_reg A')->where(['coe_batch_id' => $batch_id]);
                $pgmdata = $pquery->createCommand()->queryone();

                $sem_count = ConfigUtilities::SemCaluclation($year,$month,$pgmdata['coe_bat_deg_reg_id']);

                $update = Yii::$app->db->createCommand('UPDATE coe_qp_setting SET status=1, updated_at="'.$created_at.'", updated_by="'.$updateBy.'" WHERE batch_id="'.$batch_id.'" AND faculty1_id!=""')->execute();

                // if($update)
                // {
                        if($exam_type==27)
                        {
                            $qpfinshed = new Query();
                             $qpfinshed->select('A.*, C.faculty_name as faculty1,C.college_code,D.subject_name,C.faculty_board')
                                    ->from('coe_qp_setting A')
                                    ->join('JOIN', 'coe_valuation_faculty C', 'C.coe_val_faculty_id=A.faculty1_id')
                                    ->join('JOIN', 'coe_subjects D', 'A.subject_id=D.coe_subjects_id')
                                    ->where(['A.exam_type' => $exam_type, 'A.status' => 1, 'A.year' => $dyear, 'A.month' => $dmonth, 'A.batch_id' => $batch_id]);
                            $qpfinsheddata = $qpfinshed->createCommand()->queryAll();   
                        }
                        else
                        {
                            $qry='select DISTINCT (D.subject_code),I.coe_batch_id,I.batch_name,D.subject_name,D.coe_subjects_id, Q.*,V.faculty_name as faculty1,V.college_code,V.faculty_board
                                from coe_mark_entry_master as A 
                                JOIN coe_student_mapping as B ON B.coe_student_mapping_id=A.student_map_id 
                                JOIN coe_subjects_mapping as C ON C.coe_subjects_mapping_id=A.subject_map_id and B.course_batch_mapping_id=C.batch_mapping_id 
                                JOIN coe_subjects as D ON D.coe_subjects_id=C.subject_id 
                                JOIN coe_student as E ON E.coe_student_id=B.student_rel_id 
                                JOIN coe_bat_deg_reg as F ON F.coe_bat_deg_reg_id=B.course_batch_mapping_id and F.coe_bat_deg_reg_id=C.batch_mapping_id  
                                JOIN coe_degree as G ON G.coe_degree_id=F.coe_degree_id JOIN coe_programme as H ON H.coe_programme_id=F.coe_programme_id 
                                JOIN coe_batch as I ON I.coe_batch_id=F.coe_batch_id
                                JOIN coe_qp_setting as Q ON Q.subject_id=D.coe_subjects_id
                                JOIN coe_valuation_faculty as V ON V.coe_val_faculty_id=Q.faculty1_id
                            where year_of_passing="" and subject_map_id NOT IN(select subject_map_id from coe_mark_entry_master
                            where student_map_id=A.student_map_id and result like "%Pass%") and 
                            F.coe_batch_id="'.$batch_id.'" and I.coe_batch_id="'.$batch_id.'" AND Q.month="'.$dmonth.'" AND Q.year="'.$dyear.'" AND A.month="'.$month.'" AND A.year="'.$year.'" '.$practid.' AND status_category_type_id NOT IN('.$det_disc_type.') group by D.subject_code';//exit;
                           $qpfinsheddata = Yii::$app->db->createCommand($qry)->queryAll(); 
                        }

                         Yii::$app->ShowFlashMessages->setMsg('Success','QP Faculty assigneds Successfully');
                        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Setting');

                        $monthname = Categorytype::findOne($dmonth);
                        $exam_typename = Categorytype::findOne($exam_type);
                        $batch = Batch::findOne($batch_id);

                        $_SESSION['batch_sem'] = ' Batch - '.$batch['batch_name'];
                        $_SESSION['get_examtype'] = $exam_type;
                        $_SESSION['get_qpsettingxl'] = $qpfinsheddata;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$dyear.' '.$exam_typename['category_type'];


                         return $this->render('qp_setting', [
                        'model' => $model,
                        'subjectdata' =>'',
                        'qpfinsheddata'=>$qpfinsheddata,
                        'qpfinsh'=>'',
                        'year'=>$year,
                        'month'=>$month,
                        'batch'=>$batch_id,
                        'exam_type'=>$exam_type,
                            'dyear'=>$dyear,
                            'dmonth'=>$dmonth,
                            'semester'=>$sem_count
                         ]);
                   
                // }
                // else
                // {
                //     Yii::$app->ShowFlashMessages->setMsg('Error', "No Finish Status Update! Please Check");
                //     return $this->redirect(['qp/qpsetting']);
                // }
            }
            else
            { 
                if($_POST['qpexam_type']==28)
                {
                    $month=($_POST['qpassign_month']=='29')?'30':'29';
                    $year=($_POST['qpassign_month']==29)?($_POST['qp_year']-1):$_POST['qp_year'];
                }
                else
                {
                     $month=$_POST['qpassign_month'];
                     $year=$_POST['qp_year'];
                }

                $dmonth=$_POST['qpassign_month'];
                $dyear=$_POST['qp_year'];
                $batch_id=$_POST['bat_val'];
                $exam_type =$_POST['qpexam_type'];

                $pquery = new Query();
                $pquery->select('coe_bat_deg_reg_id')->from('coe_bat_deg_reg A')->where(['coe_batch_id' => $batch_id]);
                $pgmdata = $pquery->createCommand()->queryone();

                $sem_count = ConfigUtilities::SemCaluclation($year,$month,$pgmdata['coe_bat_deg_reg_id']);

                $assign_update='0';
                if(isset($_POST['assign_update']) && $_POST['assign_update']=='on'){$assign_update=1 ||$assign_update=18;}

                $batchdata = Yii::$app->db->createCommand("select count(*) from coe_qp_setting where year='".$dyear."' AND  month='".$dmonth."' AND  batch_id='".$batch_id."' AND exam_type ='".$exam_type."'")->queryScalar();

                $batchfinsheddata = Yii::$app->db->createCommand("select count(*) from coe_qp_setting where year='".$dyear."' AND  month='".$dmonth."' AND  batch_id='".$batch_id."' AND exam_type ='".$exam_type."' AND status=1 ")->queryScalar();

                if($batchfinsheddata==$batchdata && $batchfinsheddata!=0 && $assign_update==0) // finish qp
                {   //echo "hi";exit;
                    if($exam_type==27)
                    {
                        $qpfinshed = new Query();
                         $qpfinshed->select('A.*, C.faculty_name as faculty1,C.college_code,D.subject_name,C.faculty_board')
                                ->from('coe_qp_setting A')
                                ->join('JOIN', 'coe_valuation_faculty C', 'C.coe_val_faculty_id=A.faculty1_id')
                                ->join('JOIN', 'coe_subjects D', 'A.subject_id=D.coe_subjects_id')
                                ->where(['A.exam_type' => $exam_type, 'A.status' => 1, 'A.year' => $dyear, 'A.month' => $dmonth, 'A.batch_id' => $batch_id])
                                ->andWhere(['NOT IN', 'type_id',109]);
                        $qpfinsheddata = $qpfinshed->createCommand()->queryAll();   
                        
                        $monthname = Categorytype::findOne($dmonth);
                        $exam_typename = Categorytype::findOne($exam_type);
                        $batch = Batch::findOne($batch_id);

                        $_SESSION['batch_sem'] = ' Batch - '.$batch['batch_name'].' Semester - '.$sem_count;
                        $_SESSION['get_examtype'] = $exam_type;
                        $_SESSION['get_qpsettingxl'] = $qpfinsheddata;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$dyear.' '.$exam_typename['category_type'];

                    }
                    else
                    {
                         $qry='select DISTINCT (D.subject_code),I.coe_batch_id,I.batch_name,D.subject_name,D.coe_subjects_id, Q.*,V.faculty_name as faculty1,V.college_code,V.faculty_board
                                from coe_mark_entry_master as A 
                                JOIN coe_student_mapping as B ON B.coe_student_mapping_id=A.student_map_id 
                                JOIN coe_subjects_mapping as C ON C.coe_subjects_mapping_id=A.subject_map_id and B.course_batch_mapping_id=C.batch_mapping_id 
                                JOIN coe_subjects as D ON D.coe_subjects_id=C.subject_id 
                                JOIN coe_student as E ON E.coe_student_id=B.student_rel_id 
                                JOIN coe_bat_deg_reg as F ON F.coe_bat_deg_reg_id=B.course_batch_mapping_id and F.coe_bat_deg_reg_id=C.batch_mapping_id  
                                JOIN coe_degree as G ON G.coe_degree_id=F.coe_degree_id JOIN coe_programme as H ON H.coe_programme_id=F.coe_programme_id 
                                JOIN coe_batch as I ON I.coe_batch_id=F.coe_batch_id
                                JOIN coe_qp_setting as Q ON Q.subject_id=D.coe_subjects_id
                                JOIN coe_valuation_faculty as V ON V.coe_val_faculty_id=Q.faculty1_id
                            where year_of_passing="" and subject_map_id NOT IN(select subject_map_id from coe_mark_entry_master
                            where student_map_id=A.student_map_id and result like "%Pass%") and 
                            F.coe_batch_id="'.$batch_id.'" and I.coe_batch_id="'.$batch_id.'" AND Q.month="'.$dmonth.'" AND Q.year="'.$dyear.'" AND A.month="'.$month.'" AND A.year="'.$year.'" '.$practid.' AND status_category_type_id NOT IN('.$det_disc_type.') group by D.subject_code';//exit;
                           $qpfinsheddata = Yii::$app->db->createCommand($qry)->queryAll();

                        $monthname = Categorytype::findOne($dmonth);
                        $exam_typename = Categorytype::findOne($exam_type);
                        $batch = Batch::findOne($batch_id);

                        $_SESSION['batch_sem'] = ' Batch - '.$batch['batch_name'].' Semester - '.$sem_count;
                        $_SESSION['get_examtype'] = $exam_type;
                        $_SESSION['get_qpsettingxl'] = $qpfinsheddata;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$dyear.' '.$exam_typename['category_type'];
                    }

                        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Setting');

                         return $this->render('qp_setting', [
                        'model' => $model,
                        'subjectdata' =>'',
                        'qpfinsheddata'=>$qpfinsheddata,
                        'qpfinsh'=>'',
                        'year'=>$year,
                        'month'=>$month,
                        'batch'=>$batch_id,
                        'exam_type'=>$exam_type,
                            'dyear'=>$dyear,
                            'dmonth'=>$dmonth,
                            'assign_update'=>$assign_update,
                            'semester'=>$sem_count
                         ]);
                }
                else // not finish qp or new
                {                    
                   
                    if(!empty($pgmdata))
                    {                      
                        $subjectdata1 =array();
                        $query = new Query();

                        if($exam_type==27)
                        {
                            if($_POST['qpexam_type']==27)
                            {
                                $month11=($_POST['qpassign_month']=='30')?'29':'30';
                                $year11=($_POST['qpassign_month']==30)?$_POST['qp_year']:($_POST['qp_year']-1);
                            }

                            $qry='SELECT DISTINCT (D.subject_code),I.coe_batch_id,I.batch_name,D.subject_name,D.coe_subjects_id, A.subject_map_id
                                from coe_mark_entry_master as A 
                                JOIN coe_student_mapping as B ON B.coe_student_mapping_id=A.student_map_id 
                                JOIN coe_subjects_mapping as C ON C.coe_subjects_mapping_id=A.subject_map_id and B.course_batch_mapping_id=C.batch_mapping_id 
                                JOIN coe_subjects as D ON D.coe_subjects_id=C.subject_id 
                                JOIN coe_student as E ON E.coe_student_id=B.student_rel_id                             
                                JOIN coe_bat_deg_reg as F ON F.coe_bat_deg_reg_id=B.course_batch_mapping_id and F.coe_bat_deg_reg_id=C.batch_mapping_id  
                                JOIN coe_degree as G ON G.coe_degree_id=F.coe_degree_id JOIN coe_programme as H ON H.coe_programme_id=F.coe_programme_id 
                                JOIN coe_batch as I ON I.coe_batch_id=F.coe_batch_id
                            where year_of_passing="" and subject_map_id NOT IN(select subject_map_id from coe_mark_entry_master
                            where student_map_id=A.student_map_id and result like "%Pass%") and 
                            A.year="'.$year11.'" and A.month="'.$month11.'" '.$practid.' AND status_category_type_id NOT IN('.$det_disc_type.') group by  D.subject_code'; //exit;
                           
                            $presubject= Yii::$app->db->createCommand($qry)->queryAll(); 

                           

                             $pquery = new Query();
                            $pquery->select('coe_bat_deg_reg_id')->from('coe_bat_deg_reg A')->where(['coe_batch_id' => $batch_id]);
                            $pgmdata = $pquery->createCommand()->queryAll();

                            $not_in_sub = array_filter(['']);
                            if(!empty($pgmdata))
                            {
                                foreach ($pgmdata as $key => $notIn) 
                                {
                                    $sem = ConfigUtilities::SemCaluclation($year,$month,$notIn['coe_bat_deg_reg_id']);
                                    $not_in_sub[$sem] = $sem;
                                }
                            }

                            //print_r($presubject); exit;


                            $query->select('DISTINCT (D.subject_code),A.coe_batch_id,F.batch_name,D.subject_name,D.coe_subjects_id')
                                ->from('coe_bat_deg_reg A')
                                ->join('JOIN', 'coe_programme B', 'A.coe_programme_id=B.coe_programme_id')
                                ->join('JOIN', 'coe_degree C', 'A.coe_degree_id=C.coe_degree_id')
                                ->join('JOIN', 'coe_subjects_mapping E', 'A.coe_bat_deg_reg_id=E.batch_mapping_id')
                                ->join('JOIN', 'coe_subjects D', 'E.subject_id=D.coe_subjects_id')
                                ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
                                ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=E.subject_type_id')
                                ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=E.course_type_id')
                                 ->join('JOIN', 'coe_category_type j', 'j.coe_category_type_id=E.type_id')
                                ->join('JOIN', 'coe_batch F', 'A.coe_batch_id=F.coe_batch_id')
                               // ->join('JOIN', 'coe_exam_timetable X', 'X.subject_mapping_id=E.coe_subjects_mapping_id')
                                ->where(['A.coe_batch_id' => $batch_id])
                                ->andWhere(['IN', 'paper_type_id', $pracIds])
                                ->andWhere(['IN', 'E.semester', $not_in_sub])
                                ->andWhere(['NOT IN', 'type_id',109])->orderBy('subject_code');
                                //echo $query->createCommand()->getrawsql(); exit;
                              $subjectdata = $query->createCommand()->queryAll();

                               
                            $presubject = array_merge($presubject,$subjectdata);   
                        }
                        else
                        {
                            
                            $qry='select DISTINCT (D.subject_code),I.coe_batch_id,I.batch_name,D.subject_name,D.coe_subjects_id
                                from coe_mark_entry_master as A 
                                JOIN coe_student_mapping as B ON B.coe_student_mapping_id=A.student_map_id 
                                JOIN coe_subjects_mapping as C ON C.coe_subjects_mapping_id=A.subject_map_id and B.course_batch_mapping_id=C.batch_mapping_id 
                                JOIN coe_subjects as D ON D.coe_subjects_id=C.subject_id 
                                JOIN coe_student as E ON E.coe_student_id=B.student_rel_id 
                            
                                JOIN coe_bat_deg_reg as F ON F.coe_bat_deg_reg_id=B.course_batch_mapping_id and F.coe_bat_deg_reg_id=C.batch_mapping_id  
                                JOIN coe_degree as G ON G.coe_degree_id=F.coe_degree_id JOIN coe_programme as H ON H.coe_programme_id=F.coe_programme_id 
                                JOIN coe_batch as I ON I.coe_batch_id=F.coe_batch_id
                            where year_of_passing="" and subject_map_id NOT IN(select subject_map_id from coe_mark_entry_master
                            where student_map_id=A.student_map_id and result like "%Pass%") and 
                            F.coe_batch_id="'.$batch_id.'" and I.coe_batch_id="'.$batch_id.'" '.$practid.' AND status_category_type_id NOT IN('.$det_disc_type.') group by  D.subject_code';//exit;
                           
                            $subjectdata= Yii::$app->db->createCommand($qry)->queryAll(); 

                            $query->select('DISTINCT (D.subject_code),A.coe_batch_id,F.batch_name,D.subject_name,D.coe_subjects_id')
                                ->from('coe_bat_deg_reg A')
                                ->join('JOIN', 'coe_programme B', 'A.coe_programme_id=B.coe_programme_id')
                                ->join('JOIN', 'coe_degree C', 'A.coe_degree_id=C.coe_degree_id')
                                ->join('JOIN', 'coe_subjects_mapping E', 'A.coe_bat_deg_reg_id=E.batch_mapping_id')
                                ->join('JOIN', 'coe_subjects D', 'E.subject_id=D.coe_subjects_id')
                                ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
                                ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=E.subject_type_id')
                                ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=E.course_type_id')
                                 ->join('JOIN', 'coe_category_type j', 'j.coe_category_type_id=E.type_id')
                                ->join('JOIN', 'coe_batch F', 'A.coe_batch_id=F.coe_batch_id')
                               // ->join('JOIN', 'coe_exam_timetable X', 'X.subject_mapping_id=E.coe_subjects_mapping_id')
                                ->where(['A.coe_batch_id' => $batch_id])
                                ->andWhere(['IN', 'paper_type_id', $pracIds])->orderBy('subject_code');
                           // echo $query->createCommand()->getrawsql(); exit;
                            $presubject = $query->createCommand()->queryAll();


                            $presubject = array_merge($presubject,$subjectdata);                    
                           
                        }
                         

                                                         
                       
                    }
                   // print_r($subjectdata); exit;
                   $valuation_faculty = Yii::$app->db->createCommand("SELECT coe_val_faculty_id,faculty_name,college_code,faculty_board  FROM  coe_valuation_faculty as A   where A.faculty_status='ACTIVE' ORDER BY coe_val_faculty_id")->queryAll();
                     
                    Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Setting');

                    if (!empty($subjectdata)) {
                        return $this->render('qp_setting', [
                            'model' => $model,
                            'subjectdata' => $subjectdata,
                            'valuation_faculty'=>$valuation_faculty,
                            'qpfinsh'=>1,
                            'year'=>$year,
                            'month'=>$month,
                            'batch'=>$batch_id,
                            'exam_type'=>$exam_type,
                            'dyear'=>$dyear,
                            'dmonth'=>$dmonth,
                            'assign_update'=>$assign_update,
                            'semester'=>$sem_count,
                            'presubjectdata'=>$presubject
                        ]);
                    } else {
                        Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/qpsetting']);
                    }
                }
            }
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Setting');
            return $this->render('qp_setting', [
                'model' => $model,
                'subjectdata' => $subjectdata,
                'qpfinsh'=>'',
                'dyear'=>'',
                'dmonth'=>'',
                'batch'=>'',
                'exam_type'=>'',
            ]);
        }
       
    }

     public function actionQpsettingstatus()
    {
        $subjectdata=array_filter(['']);
        $model = new QpSetting();
        if (Yii::$app->request->post()) 
        { 
            $sem_count='';
                
                $status=$_POST['qpstatus'];

                if($status=='1')
                {
                    $status='on';
                }
                else
                {
                    $status='off';
                }

                $dmonth=$_POST['qpassign_month'];
                $dyear=$_POST['qp_year'];

                $fromdate=$_POST['fromdate'];
                $todate=$_POST['todate'];     

                if($_POST['fromdate']!='' && $_POST['todate']!='')
                {
                    $fromdate=date("Y-m-d",strtotime($_POST['fromdate'])).' 00:00:00';
                    $todate=date("Y-m-d",strtotime($_POST['todate'])).' 23:59:59';
                }

                    $query = new Query();

                    $query->select('D.subject_code, D.subject_name,D.coe_subjects_id,S.*')
                        ->from('coe_qp_setting S')
                        ->join('JOIN', 'coe_subjects D', 'S.subject_id=D.coe_subjects_id')
                        ->where(['S.year' => $dyear, 'S.month' => $dmonth])
                        ->andWhere(['<>', 'faculty1_id', 0]);
                   
                    if($_POST['fromdate']!='' && $_POST['todate']!='')
                    {
                        $query->andWhere(['between', 'S.updated_at', $fromdate, $todate]);
                    }
                    
                    if($status=='off')
                    {
                        $query->groupby('faculty1_id');
                    }
                    
                    $query->orderBy('faculty1_id');

                     //echo $query->createCommand()->getrawsql(); exit;
                    $subjectdata = $query->createCommand()->queryAll();      
                   

                    if (!empty($subjectdata)) 
                    {
                        $monthname = Categorytype::findOne($dmonth);
                        
                        $_SESSION['claimdate'] ='';
                        $_SESSION['headingdate'] ='';
                        if($_POST['fromdate']!='' && $_POST['todate']!='')
                        {
                            $_SESSION['claimdate'] = ' AND updated_at BETWEEN "'.$fromdate.'" AND "'.$todate.'"';

                            $_SESSION['headingdate'] ='Claim Date: '.$_POST['fromdate'].' - '.$_POST['todate'];
                        }

                        $_SESSION['get_qpsettingstatus'] = $subjectdata;
                        $_SESSION['get_examyear'] = strtoupper($monthname['category_type']).' - '.$dyear;
                        $_SESSION['examyear'] = $dyear;
                        $_SESSION['exammonth'] = $dmonth;

                        $_SESSION['statusstatus'] = $status;

                         Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Setting Status');
                        return $this->render('qp_setting_status', [
                            'model' => $model,
                            'subjectdata' => $subjectdata,
                            'qpfinsh'=>1,
                            'year'=>$dyear,
                            'month'=>$dmonth,
                            'dyear'=>$dyear,
                            'dmonth'=>$dmonth,
                            'semester'=>$sem_count,
                            'status'=>$status
                        ]);
                    } else {
                        Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/qpsettingstatus']);
                    }
               
        }
        else
        {
             Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Setting Status');
            return $this->render('qp_setting_status', [
                'model' => $model,
                'subjectdata' => $subjectdata,
                'dyear'=>'',
                'dmonth'=>'',
                'batch'=>'',
                'exam_type'=>'',
            ]);
        }
       
    }

    public function actionQpsettingPdf()
    {
        $content=$_SESSION['get_qpsetting'];
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'qpsettingsassign.pdf',                
                    'format' => Pdf::FORMAT_A3,                 
                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $content, 
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                    'cssInline' => ' @media all{
                            table{border-collapse: collapse; width:100%; } 
                            table tr{
                                border: 1px solid #CCC;
                            }
                            table td{
                                border: 1px solid #CCC;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                height: 20px;
                            }
                            table th{
                               border: 1px solid #CCC;
                                text-align: center;
                            }
                        }   
                    ',  
                        'options' => ['title' => 'QP Settings Claim'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['QP Settings Claim - {PAGENO}'],
                        ],
                        
                    ]);
                   
                    $pdf->marginLeft="8";
                    $pdf->marginRight="8";
                    $pdf->marginBottom="8";
                    $pdf->marginFooter="4";
                    Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                    $headers = Yii::$app->response->headers;
                    $headers->add('Content-Type', 'application/pdf');
                    return $pdf->render(); 
                
        
    }

    public function actionQpsettingstatusExcel()
    {        
        
            $content=$_SESSION['get_qpsettingstatus'];

            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));

            if($_SESSION['statusstatus']=='on')
            {

                $objPHPExcel = new \PHPExcel();

                 $objPHPExcel->createSheet(0); //Setting index when creating
                
                 $objPHPExcel->setActiveSheetIndex(0);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
                 $objWorkSheet = $objPHPExcel->getActiveSheet();

                 $objWorkSheet->setTitle('QUESTION PAPER SETTERS LIST ');


                $head=strtoupper($org_name);
                $head1='QUESTION PAPER SETTERS LIST '.$_SESSION['get_examyear'];

                $objWorkSheet->getCell('A1')->setValue($head);
                $objWorkSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $objWorkSheet->mergeCells('A1:I1');
                $objWorkSheet->getCell('A2')->setValue($head1);
                 $objWorkSheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                 $objWorkSheet->mergeCells('A2:I2');
                 $objWorkSheet->setCellValue('A3','SNO');
                 $objWorkSheet->setCellValue('B3','NAME OF THE QUESTION PAPER  SETTERS');
                 $objWorkSheet->setCellValue('C3','COURSE CODE');
                 $objWorkSheet->setCellValue('D3','COURSE NAME');
                 $objWorkSheet->setCellValue('E3','NAME OF THE BANK');
                 $objWorkSheet->setCellValue('F3','BRANCH');
                 $objWorkSheet->setCellValue('G3','IFSC CODE');
                 $objWorkSheet->setCellValue('H3','ACCOUNT NUMBER');
                 $objWorkSheet->setCellValue('I3','AMOUNT');
                   $objWorkSheet->getStyle('B3')->getAlignment()->setWrapText(true);
                 $objWorkSheet->getStyle("A1:I3")->getFont()->setBold(true);

                $row = 4; $sno=1;  $qpamount = Yii::$app->db->createCommand("SELECT ug_amt FROM coe_val_claim_amt WHERE claim_id=3")->queryScalar();

                foreach($content as $value)
                {
                    $valuation_faculty1 = Yii::$app->db->createCommand("SELECT * FROM coe_valuation_faculty WHERE coe_val_faculty_id='".$value['faculty1_id']."'")->queryone();

                    if($_SESSION['claimdate']!='')
                    {
                        $f1cnt = Yii::$app->db->createCommand("SELECT num_question_set FROM coe_qp_setting WHERE subject_code='".$value['subject_code']."' AND year=".$value['year']." AND month=".$value['month']." AND faculty1_id=".$value['faculty1_id'].$_SESSION['claimdate'])->queryScalar();
                    }
                    else
                    {   
                        $f1cnt = Yii::$app->db->createCommand("SELECT num_question_set FROM coe_qp_setting WHERE subject_code='".$value['subject_code']."' AND year=".$value['year']." AND month=".$value['month']." AND faculty1_id=".$value['faculty1_id'])->queryScalar();
                    }
                               

                    $totalscript=($f1cnt);
                    $total_renum=$qpamount*$totalscript;

                    $objWorkSheet->setCellValue('A'.$row,$sno);
                    $objWorkSheet->setCellValue('B'.$row,$valuation_faculty1['faculty_name']);
                    $objWorkSheet->setCellValue('C'.$row,$value['subject_code']);
                    $objWorkSheet->setCellValue('D'.$row,$value['subject_name']);
                    $objWorkSheet->setCellValue('E'.$row,$valuation_faculty1['bank_name']);
                    $objWorkSheet->setCellValue('F'.$row,$valuation_faculty1['bank_branch']);
                    $objWorkSheet->setCellValue('G'.$row,$valuation_faculty1['bank_ifsc']);
                    $objWorkSheet->setCellValue('H'.$row,$valuation_faculty1['bank_accno']);
                    $objWorkSheet->setCellValue('I'.$row,$total_renum);
                     $objWorkSheet->getStyle('D'.$row,$value['subject_name'])->getAlignment()->setWrapText(true);
                     
                    $row++;
                    $sno++;
                }
            }


            if($_SESSION['statusstatus']=='off')
            {

                $objPHPExcel = new \PHPExcel();

                 $objPHPExcel->createSheet(0); //Setting index when creating
                
                 $objPHPExcel->setActiveSheetIndex(0);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
                 $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
                 $objWorkSheet = $objPHPExcel->getActiveSheet();

                 $objWorkSheet->setTitle('QUESTION PAPER SETTERS LIST ');


                $head=strtoupper($org_name);
                $head1='QUESTION PAPER SETTERS LIST '.$_SESSION['get_examyear'];

                $objWorkSheet->getCell('A1')->setValue($head);
                $objWorkSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $objWorkSheet->mergeCells('A1:I1');
                $objWorkSheet->getCell('A2')->setValue($head1);
                 $objWorkSheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                 $objWorkSheet->mergeCells('A2:I2');
                 $objWorkSheet->setCellValue('A3','SNO');
                 $objWorkSheet->setCellValue('B3','NAME OF THE QUESTION PAPER SETTERS');
                 $objWorkSheet->setCellValue('C3','NAME OF THE BANK');
                 $objWorkSheet->setCellValue('D3','BRANCH');
                 $objWorkSheet->setCellValue('E3','IFSC CODE');
                 $objWorkSheet->setCellValue('F3','ACCOUNT NUMBER');
                 $objWorkSheet->setCellValue('G3','AMOUNT');
                 $objWorkSheet->getStyle('B3')->getAlignment()->setWrapText(true);
                 $objWorkSheet->getStyle("A1:I3")->getFont()->setBold(true);

                $row = 4; $sno=1;  $qpamount = Yii::$app->db->createCommand("SELECT ug_amt FROM coe_val_claim_amt WHERE claim_id=3")->queryScalar();

                foreach($content as $value)
                {
                    $valuation_faculty1 = Yii::$app->db->createCommand("SELECT * FROM coe_valuation_faculty WHERE coe_val_faculty_id='".$value['faculty1_id']."'")->queryone();

                    if($_SESSION['claimdate']!='')
                    {
                        $f1cnt = Yii::$app->db->createCommand("SELECT sum(num_question_set) FROM coe_qp_setting WHERE year=".$value['year']." AND month=".$value['month']." AND faculty1_id=".$value['faculty1_id'].$_SESSION['claimdate'])->queryScalar();
                    }
                    else
                    {   
                        $f1cnt = Yii::$app->db->createCommand("SELECT sum(num_question_set) FROM coe_qp_setting WHERE year=".$value['year']." AND month=".$value['month']." AND faculty1_id=".$value['faculty1_id'])->queryScalar();
                    }
                               

                    $totalscript=($f1cnt);
                    $total_renum=$qpamount*$totalscript;

                    $objWorkSheet->setCellValue('A'.$row,$sno);
                    $objWorkSheet->setCellValue('B'.$row,$valuation_faculty1['faculty_name']);
                    $objWorkSheet->setCellValue('C'.$row,$valuation_faculty1['bank_name']);
                    $objWorkSheet->setCellValue('D'.$row,$valuation_faculty1['bank_branch']);
                    $objWorkSheet->setCellValue('E'.$row,$valuation_faculty1['bank_ifsc']);
                    $objWorkSheet->setCellValue('F'.$row,$valuation_faculty1['bank_accno']);
                    $objWorkSheet->setCellValue('G'.$row,$total_renum);
                     
                    $row++;
                    $sno++;
                }
            }
        
        header('Content-type: application/.xlsx');
        header('Content-Disposition: attachment; filename="QP Setting Claim.xlsx"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
    }

    public function actionQpsettingExcel()
    {        
        if($_SESSION['get_examtype']==27)
        {
            $content=$_SESSION['get_qpsettingxl'];
          
            $objPHPExcel = new \PHPExcel();

             $objPHPExcel->createSheet(0); //Setting index when creating
            
             $objPHPExcel->setActiveSheetIndex(0);
             $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
             $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(50);
             $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
             $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(50);
             $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
             $objWorkSheet = $objPHPExcel->getActiveSheet();    

             $objWorkSheet->setTitle('QP Setting');

             $head='QP Setting Report '.$_SESSION['get_examyear'].' Examinations';
              $head1=$_SESSION['batch_sem'];

            $objWorkSheet->getCell('A1')->setValue($head);
             $objWorkSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
             $objWorkSheet->mergeCells('A1:F1');
             $objWorkSheet->getCell('A2')->setValue($head1);
             $objWorkSheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
             $objWorkSheet->mergeCells('A2:F2');
             $objWorkSheet->setCellValue('A3','S.No.');
             $objWorkSheet->setCellValue('B3','Subject Code');
             $objWorkSheet->setCellValue('C3','Subject Name');
             $objWorkSheet->setCellValue('D3','Faculty1');
             $objWorkSheet->setCellValue('E3','Email');
             $objWorkSheet->setCellValue('F3','Phone No');

             $objWorkSheet->getStyle("A1:F3")->getFont()->setBold(true);

              $row = 4; $sno=1;
            foreach($content as $value)
            {

                $clgcode=($value['college_code']!='')?"(".$value['college_code'].")":"";

                $f2='';
                
                $valuation_faculty1 = Yii::$app->db->createCommand("SELECT email,phone_no FROM coe_valuation_faculty WHERE coe_val_faculty_id='".$value['faculty1_id']."'")->queryone();

                $objWorkSheet->setCellValue('A'.$row,$sno);
                $objWorkSheet->setCellValue('B'.$row,$value['subject_code']);
                $objWorkSheet->setCellValue('C'.$row,$value['subject_name']);
                $objWorkSheet->setCellValue('D'.$row,$value['faculty1'].$clgcode);
                $objWorkSheet->setCellValue('E'.$row,$valuation_faculty1['email']);
                $objWorkSheet->setCellValue('F'.$row,$valuation_faculty1['phone_no']);

                $row++;
                $sno++;
            }

        }
        else
        {
             $content=$_SESSION['get_qpsettingxl'];

            $objPHPExcel = new \PHPExcel();

             $objPHPExcel->createSheet(0); //Setting index when creating
            
             $objPHPExcel->setActiveSheetIndex(0);
             $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
             $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(50);
             $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
             $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(50);
             $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);

             $objWorkSheet = $objPHPExcel->getActiveSheet();

              $head='QP Setting Report '.$_SESSION['get_examyear'].' Examinations';
               $head1='';//$_SESSION['batch_sem'];

            $objWorkSheet->getCell('A1')->setValue($head);
             $objWorkSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
             $objWorkSheet->mergeCells('A1:G1');
             $objWorkSheet->getCell('A2')->setValue($head1);
             $objWorkSheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
             $objWorkSheet->mergeCells('A2:G2');
             $objWorkSheet->setCellValue('A3','S.No.');
             $objWorkSheet->setCellValue('B3','Semester');
             $objWorkSheet->setCellValue('C3','Subject Code');
             $objWorkSheet->setCellValue('D3','Subject Name');
             $objWorkSheet->setCellValue('E3','Faculty1');
             $objWorkSheet->setCellValue('F3','Email');
             $objWorkSheet->setCellValue('G3','Phone');
             $objWorkSheet->getStyle("A1:G3")->getFont()->setBold(true);

            

              $row = 4; $sno=1;
            foreach($content as $value)
            {
                 $qry='select B.semester from coe_subjects as A JOIN coe_subjects_mapping as B ON A.coe_subjects_id=B.subject_id where A.subject_code="'.$value['subject_code'].'"';

                    $subjectsem = Yii::$app->db->createCommand($qry)->queryAll(); 

                    $subsem=''; $temp='';
                    foreach ($subjectsem as $subvalue) 
                    {
                        if($subvalue['semester']!=$temp)
                        {
                            $subsem.=$subvalue['semester'].',';
                        }                        
                        $temp=$subvalue['semester'];
                    }

                    $subsem=rtrim($subsem,",");

                 $clgcode=($value['college_code']!='')?"(".$value['college_code'].")":"";

                 $valuation_faculty1 = Yii::$app->db->createCommand("SELECT email,phone_no FROM coe_valuation_faculty WHERE coe_val_faculty_id='".$value['faculty1_id']."'")->queryone();

                $objWorkSheet->setCellValue('A'.$row,$sno);
                $objWorkSheet->setCellValue('B'.$row,$subsem);
                $objWorkSheet->setCellValue('C'.$row,$value['subject_code']);
                $objWorkSheet->setCellValue('D'.$row,$value['subject_name']);
                $objWorkSheet->setCellValue('E'.$row,$value['faculty1'].$clgcode);
                $objWorkSheet->setCellValue('F'.$row,$valuation_faculty1['email']);
                $objWorkSheet->setCellValue('G'.$row,$valuation_faculty1['phone_no']);

                $row++;
                $sno++;
            }
        }
        
        header('Content-type: application/.xlsx');
        header('Content-Disposition: attachment; filename="QP Setting.xlsx"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
    }

    public function actionQpsettingExcel1()
    {

        $content = $_SESSION['get_qpsetting1'];          
        $fileName = 'qpsetting ' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

   public function actionFacultyHallArrange()
    {
        $model = new FacultyHallArrange();
        $modelfa = new ValuationFacultyAllocate();
        if (Yii::$app->request->post()) 
        { 
           
            $month=$_POST['fh_month'];
            $year=$_POST['fh_year'];
            $fh_date=$_POST['fh_date'];
            $fh_session=$_POST['fh_session'];
                       
            $namearr = explode("&",trim($_POST['hallName'],"&"));

            //$namearr_rhs = explode("&",trim($_POST['hallName_rhs'],"&"));

            //echo count($namearr); exit;
           // print_r( $namearr);exit;
                //echo count($namearr_rhs); exit;
             $faculty_hall_data = Yii::$app->db->createCommand("SELECT hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, concat(E.faculty_name,', ',E.faculty_designation,', ', E.faculty_board,' <br> ',COALESCE(E.college_code,'ARTS')) as aur, concat(F.faculty_name,', ',F.faculty_designation,', ', F.faculty_board,' <br> ',COALESCE(F.college_code,'ARTS')) as chieff FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id JOIN coe_valuation_faculty E ON E.coe_val_faculty_id=A.aur JOIN coe_valuation_faculty F ON F.coe_val_faculty_id=A.chief WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' order By C.faculty_board ASC")->queryAll();
          


            
            
            
                

                $hall_data = Yii::$app->db->createCommand("SELECT DISTINCT hall_master_id FROM coe_hall_allocate A JOIN coe_exam_timetable B ON B.coe_exam_timetable_id=A.exam_timetable_id WHERE B.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND B.exam_session='" . $fh_session . "'")->queryAll(); //
                   //print_r($hall_data);exit;
               
               shuffle($namearr);

               //echo count($hall_data)."==".count($namearr);exit;
                //if(count($hall_data) == count($namearr))
               if(count($hall_data))
                {
                 //print_r($namearr_rhs);                
                    //exit;
                  
                    for ($i=0; $i <count($hall_data) ; $i++) 
                    { 
                        $check_inserted = FacultyHallArrange::find()->where(['year'=>$year,'month'=>$month,'hall_master_id'=>$hall_data[$i]['hall_master_id'],'exam_date'=>date('Y-m-d',strtotime($fh_date)),'exam_session'=>$fh_session])->one();
                        //print_r( $check_inserted);exit;

                      

                        if(empty($check_inserted))
                        {
                            $created_at = date("Y-m-d H:i:s");
                            $updateBy = Yii::$app->user->getId();
                            $model1 = new FacultyHallArrange();
                            $model1->hall_master_id = $hall_data[$i]['hall_master_id'];
                            $model1->year = $year;
                            $model1->month = $month;
                            $model1->exam_date =date('Y-m-d',strtotime($fh_date));
                            $model1->exam_session = $fh_session;
                            $model1->faculty_id = $namearr[$i];
                            //$model1->aur = 0;
                           // $model1->rhs = 0; 
                           // $model1->chief = $chief;                   
                            $model1->created_at = $created_at;
                            $model1->created_by = $updateBy;
                            
                            if($model1->save(false))
                            {
                                $Success= $Success+1;
                            }
                            else
                            {
                                $Error1= $Error1+1;
                            }
                        }
                        else
                        {
                            $Error2= $Error2+1;
                        }

                        $r++;
                    }
                    //echo $Success; exit;
                    $faculty_hall_data = Yii::$app->db->createCommand("SELECT hall_name,concat(C.faculty_name) as faculty_name  FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id  WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "'")->queryAll();
                   // print_r( $faculty_hall_data);exit;

                    //$rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, concat(D.faculty_name,', ',D.faculty_designation,', ', D.faculty_board,' <br> ',COALESCE(D.college_code,'SKCT')) as aur, concat(E.faculty_name,', ',E.faculty_designation,', ', E.faculty_board,' <br> ',COALESCE(E.college_code,'SKCT')) as chieff FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs JOIN coe_valuation_faculty D ON D.coe_val_faculty_id=A.aur JOIN coe_valuation_faculty E ON E.coe_val_faculty_id=A.chief WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "'")->queryAll();

                    if(!empty($faculty_hall_data))    
                    {
                        $monthname = Categorytype::findOne($month);

                        $ex_session = Categorytype::findOne($fh_session);
                               
                        $_SESSION['faculty_hall_dataxl'] = $faculty_hall_data;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year;
                        $_SESSION['get_examsession'] = 'Exam Date '.$fh_date.' & '.$ex_session['category_type'];

                        return $this->render('faculty_hall_arrange', [
                                'model' => $model,
                                'modelfa'=> $modelfa,
                                'faculty_hall_data'=>$faculty_hall_data,
                               // 'rhsdata'=>$rhsdata,
                                'year'=>$year,
                                'month'=>$month,
                                'fh_date'=>$fh_date,
                                'fh_session'=>$fh_session,
                                 ]);
                    }    
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                                return $this->redirect(['qp/faculty-hall-arrange']);
                    }  
                  
                }    
                else
                {   
                    //echo "else"; exit;
                    Yii::$app->ShowFlashMessages->setMsg('Error', "Faculty are not sufficient to assign hall");
                            return $this->redirect(['qp/faculty-hall-arrange']);
                }  

            
                  
               
        }
        else
        {
             Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation');
            return $this->render('faculty_hall_arrange', [
                'model' => $model,
                'modelfa'=> $modelfa,
                'faculty_hall_data'=>'',
            ]);
        }
       
    }

    public function actionQpfacultyhallPdf()
    {
        $content=$_SESSION['faculty_hall_data'];
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'FacultyHallArrangement.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $content, 
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                    'cssInline' => ' @media all{
                            table{border-collapse: collapse; width:100%; } 
                            table tr{
                                border: 1px solid #CCC;
                            }
                            table td{
                                border: 1px solid #CCC;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                height: 20px;
                            }
                            table th{
                               border: 1px solid #CCC;
                                text-align: center;
                            }
                        }   
                    ',  
                        'options' => ['title' => 'Hall Invigilation'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['Hall Invigilation - {PAGENO}'],
                        ],
                        
                    ]);
                   
                    $pdf->marginLeft="8";
                    $pdf->marginRight="8";
                    $pdf->marginBottom="8";
                    $pdf->marginFooter="4";
                    Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                    $headers = Yii::$app->response->headers;
                    $headers->add('Content-Type', 'application/pdf');
                    return $pdf->render(); 
                
        
    }

    public function actionQpfacultyhallExcel()
    {        
       
            $content=$_SESSION['faculty_hall_dataxl'];

            $objPHPExcel = new \PHPExcel();

             $objPHPExcel->createSheet(0); //Setting index when creating
            
             $objPHPExcel->setActiveSheetIndex(0);
             $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
             $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);

             $objWorkSheet = $objPHPExcel->getActiveSheet();

            $head='Hall Invigilation '.$_SESSION['get_examyear'].' End Semester Regular/Arrear Examinations';

            $head1=$_SESSION['get_examsession'];

            $objWorkSheet->getCell('A1')->setValue($head);
             $objWorkSheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
             $objWorkSheet->mergeCells('A1:H1');
              $objWorkSheet->getCell('A2')->setValue($head1);
             $objWorkSheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
             $objWorkSheet->mergeCells('A2:H2');
             $objWorkSheet->setCellValue('A3','S.No.');
             $objWorkSheet->setCellValue('B3','Hall Name');
             $objWorkSheet->setCellValue('C3','Faculty');
             $objWorkSheet->getStyle("A1:C3")->getFont()->setBold(true);

              $row = 4; $sno=1;
            foreach($content as $value)
            {
                

                $objWorkSheet->setCellValue('A'.$row,$sno);
                $objWorkSheet->setCellValue('B'.$row,$value['hall_name']);
                $objWorkSheet->setCellValue('C'.$row,$value['faculty_name']);
                $row++;
                $sno++;
            }
        
        
        header('Content-type: application/.xlsx');
        header('Content-Disposition: attachment; filename="Hall Invigilation.xlsx"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
    }

    public function actionConsolidatedfacultyhall()
    {        
        
        $model = new FacultyHallArrange();
        $examTimetable = new ExamTimetable();
       
        if (Yii::$app->request->post())  
        {            
            $year=$_POST['fh_year'];
            $month=$_POST['fh_month'];
            $fh_date=$_POST['fh_date'];
            $fh_session=$_POST['fh_session'];                    

            $query_1 = new Query(); 
            $query_1->select([ 'A.exam_date','D.category_type','A.exam_session'])
                    ->from('coe_exam_timetable A')
                    ->join('JOIN','coe_hall_allocate B','B.exam_timetable_id = A.coe_exam_timetable_id')
                    ->join('JOIN','coe_faculty_hall_arrange C','C.hall_master_id = B.hall_master_id AND C.exam_date=A.exam_date AND C.exam_session=A.exam_session')
                    ->join('JOIN','coe_category_type D','D.coe_category_type_id = A.exam_session')
                    ->Where(['A.exam_year'=>$year,'A.exam_month'=>$month]);
            
            if(!empty($fh_date) && !empty($fh_session))
            {
                    $query_1->andWhere(['A.exam_date'=>date('Y-m-d',strtotime($fh_date))]);
                    $query_1->andWhere(['A.exam_session'=>$fh_session]);
            }
            if(!empty($fh_date) && empty($fh_session))
            {
                    $query_1->andWhere(['A.exam_date'=>date('Y-m-d',strtotime($fh_date))]);
            }

                   $query_1 ->groupby('A.exam_date,A.exam_session')->orderBy('A.exam_date');

            $consolidateddata = $query_1->createCommand()->queryAll();

            if(!empty($consolidateddata))
            {
                
                $monthname = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" .$month . "'")->queryScalar();   

                $ex_session = Categorytype::findOne($fh_session);
                $_SESSION['get_examyear'] = $monthname.' - '.$year;
                $_SESSION['get_examsession'] = 'Exam Date '.$fh_date.' & '.$ex_session['category_type'];


                return $this->render('consolidatedfacultyhall', [
                    'model' => $model,
                    'examTimetable' => $examTimetable,
                    'consolidateddata'=>$consolidateddata
                ]);
            }

             Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/consolidatedfacultyhall']);
                
        }

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidated Hall Invigilation');
        return $this->render('consolidatedfacultyhall', [
            'model' => $model,'examTimetable' => $examTimetable,'consolidateddata'=>''
        ]);
    }

    public function actionConsolidatedfacultyhallPdf()
    {
        $content=$_SESSION['Consolidatedfacultyhalldata'];
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'Consolidatedfacultyhalldata.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $content, 
                   
                    'cssInline' => ' @media all{
                            table{border-collapse: collapse; width:100%; } 
                            
                            table td{
                                border: 1px solid #CCC;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                            }
                            table th{
                               border: 1px solid #CCC;
                                text-align: center;
                            }
                            
                        }   
                    ',  
                        'options' => ['title' => 'Hall Invigilation Report'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['Hall Invigilation Report - {PAGENO}'],
                        ],
                        
                    ]);
                   
                    $pdf->marginLeft="8";
                    $pdf->marginRight="8";
                    $pdf->marginBottom="8";
                    $pdf->marginFooter="4";
                    Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                    $headers = Yii::$app->response->headers;
                    $headers->add('Content-Type', 'application/pdf');
                    return $pdf->render(); 
                
        
    }

     public function actionFacultyHallArrangeUpdate()
    {
        $model = new FacultyHallArrange();
        $modelfa = new ValuationFacultyAllocate();
        if (Yii::$app->request->post()) 
        { 
           
            $month=$_POST['fh_month'];
            $year=$_POST['fh_year'];
            $fh_date=$_POST['fh_date'];
            $fh_session=$_POST['fh_session'];

             $hallmaster = Yii::$app->db->createCommand("SELECT coe_hall_master_id,hall_name FROM coe_hall_master")->queryAll();

            $intfaculty = ValuationFaculty::find()->where(['faculty_mode'=>'INTERNAL'])->orderBy(['coe_val_faculty_id'=>SORT_ASC])->all();

            $extfaculty = ValuationFaculty::find()->where(['faculty_mode'=>'EXTERNAL'])->orderBy(['coe_val_faculty_id'=>SORT_ASC])->all();
             

             $faculty_hall_data = Yii::$app->db->createCommand("SELECT A.faculty_id,fh_arrange_id,hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, aur, chief, concat(F.faculty_name,', ',F.faculty_designation,', ',F.faculty_board) as chieff FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id JOIN coe_valuation_faculty F ON F.coe_val_faculty_id=A.chief WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "'")->queryAll();


             if(!empty($faculty_hall_data))
            {
                if(!empty($faculty_hall_data))    
                {
                        $rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, aur, chief, concat(F.faculty_name,', ',F.faculty_designation,', ',F.faculty_board) as chieff FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs JOIN coe_valuation_faculty D ON D.coe_val_faculty_id=A.aur JOIN coe_valuation_faculty F ON F.coe_val_faculty_id=A.chief WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND rhs!=0")->queryAll();

                         $additional_staff = Yii::$app->db->createCommand("SELECT A.faculty_id,fh_arrange_id,hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, aur, chief,additional_staff,hall_master_id FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND additional_staff=1")->queryAll();

                        $monthname = Categorytype::findOne($month);

                        $ex_session = Categorytype::findOne($fh_session);
                               
                        $_SESSION['faculty_hall_dataxl'] = $faculty_hall_data;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year;
                        $_SESSION['get_examsession'] = 'Exam Date '.$fh_date.' & '.$ex_session['category_type'];

                         $_SESSION['year'] = $year;
                          $_SESSION['month'] = $month;
                           $_SESSION['fh_date'] = $fh_date;
                            $_SESSION['fh_session'] = $fh_session;

                        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation Update');
                        return $this->render('faculty_hall_arrange_update', [
                                'model' => $model,
                                'modelfa'=> $modelfa,
                                'faculty_hall_data'=>$faculty_hall_data,
                                'rhsdata'=>$rhsdata,
                                'year'=>$year,
                                'month'=>$month,
                                'fh_date'=>$fh_date,
                                'fh_session'=>$fh_session,
                                'intfaculty'=>$intfaculty,
                                'extfaculty'=>$extfaculty,
                                'hallmaster'=>$hallmaster,
                                'additional_staff'=>$additional_staff
                                 ]);
                }    
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                                return $this->redirect(['qp/faculty-hall-arrange-update']);
                }  
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['qp/faculty-hall-arrange-update']);
            }  
                  
               
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation Update');
            return $this->render('faculty_hall_arrange_update', [
                'model' => $model,
                'modelfa'=> $modelfa,
                'faculty_hall_data'=>'',
            ]);
        }
       
    }

    public function actionQpfacultyhallupdatePdf()
    {
        $month=$_SESSION['month'];
        $year=$_SESSION['year'];
        $fh_date=$_SESSION['fh_date'];
        $fh_session=$_SESSION['fh_session'];

        $faculty_hall_data = Yii::$app->db->createCommand("SELECT A.faculty_id,fh_arrange_id,hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, concat(E.faculty_name,', ',E.faculty_designation,', ', E.faculty_board,' <br> ',COALESCE(E.college_code,'SKCT')) as aur, concat(F.faculty_name,', ',F.faculty_designation,', ',F.faculty_board) as chieff FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id JOIN coe_valuation_faculty E ON E.coe_val_faculty_id=A.aur  JOIN coe_valuation_faculty F ON F.coe_val_faculty_id=A.chief WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "'")->queryAll();

       $rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name,concat(D.faculty_name,', ',D.faculty_designation,', ', D.faculty_board,' <br> ',COALESCE(D.college_code,'SKCT')) as aur, concat(F.faculty_name,', ',F.faculty_designation,', ',F.faculty_board) as chieff FROM coe_faculty_hall_arrange A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs JOIN coe_valuation_faculty D ON D.coe_val_faculty_id=A.aur JOIN coe_valuation_faculty F ON F.coe_val_faculty_id=A.chief  WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "'")->queryAll();

        $monthname = Categorytype::findOne($month);

        $ex_session = Categorytype::findOne($fh_session);
               
        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year;
        $_SESSION['get_examsession'] = 'Exam Date '.$fh_date.' & '.$ex_session['category_type'];

        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));

        $html = "";
        $header = "";
        $body ="";
        $footer = "";          

        $header .="<table width='100%' style='overflow-x:auto;'  align='center' class='table table-striped '>";
        $header .= '<tr>
                    <td align="center">
                        <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                    </td>
                    <td colspan=2 align="center">
                        <h3> 
                          <center><b><font size="5px">' . $org_name . '</font></b></center>
                            <center> <font size="3px">' . $org_address . '</font></center>
                            <center class="tag_line"><b>' . $org_tagline . '</b></center> 
                        </h3>
                         <h4> Hall Invigilation - '.$_SESSION['get_examyear'].' End Semester Regular/Arrear Examinations </h4>
                         <h4> '.$_SESSION['get_examsession'].' </h4>
                    </td>
                <td align="center">  
                    <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                </td>
            </tr></table> ';      
    

        $header .="<table width='100%' style='overflow-x:auto;'  align='center' class='table table-striped '>
            <tr>
                <th rowspan=2>S.No.</th>
                
                <th rowspan=2>Hall Superintendent</th>
                <th rowspan=2>Hall Name</th>
                <th colspan=2>Signature</th>
            </tr>
            <tr>

                            <th>Before</th>
                            <th>After</th>
                        </tr>
            <tbody>"; 

        $sl=1;
        foreach ($faculty_hall_data as  $value) 
        { 
       
        
            $body .='<tr>';
            $body .='<td width="5%">'.$sl.'</td>';
            $body .='<td>'.$value['faculty_name'].'</td>';
            $body .='<td width="10%">'.$value['hall_name'].'</td>';                        
            $body .='<td width="25%"></td>';
            $body .='<td width="25%"></td>';
            $body .='</tr>';
                                
             $sl++;

        }
        $body .='</tbody></table>';


        if(!empty($rhsdata))
        {

        $body .="<table width='100%' style='overflow-x:auto;'  align='center' class='table table-striped '>
            <tr>
                <th>S.No.</th>
                <th>Reserve Hall Superintendent</th>
                <th colspan=2>Signature</th>
            </tr>
            <tbody>"; 

        $sl=1;
        foreach ($rhsdata as  $value) 
        { 
       
        
            $body .='<tr>';
            $body .='<td>'.$sl.'</td>';
            $body .='<td>'.$value['faculty_name'].'</td>';
            $body .='<td width="25%"></td>';
            $body .='<td width="25%"></td>';
            $body .='</tr>';
                                
             $sl++;

        }
        $body .='</tbody></table>';
        }

        $body .="<table width='100%' style='overflow-x:auto;'  align='center' class='table table-striped '>
            <tr>
                <th>Anna University Representative</th>
                <th>Signature</th>
            </tr>
            <tbody>";                    
        
            $body .='<tr>';
            $body .='<td  align="center" >'.$faculty_hall_data[0]['aur'].'</td>';
            $body .='<td width="40%"></td>';
            $body .='</tr>';
                                
             $sl++;                   
        $body .='</tbody></table>';

        $body .="<table width='100%' style='overflow-x:auto;'  align='center' class='table table-striped '>
                        <tr>
                            <th>Chief Superintendent</th>
                            <th>Signature</th>
                        </tr>
                        <tbody>";                    
                    
                        $body .='<tr>';
                        $body .='<td  align="center">'.$faculty_hall_data[0]['chieff'].'</td>';
                        $body .='<td width="40%"></td>';
                        $body .='</tr>';
                                            
                         $sl++;                   
                    $body .='</tbody></table>';

                    $body .="<table width='100%' style='overflow-x:auto;'  align='left' class='table table-striped '>
                        <tr>
                            <th align='left'>No. of Candidates Registered</th>
                            <th align='left'>No. of Candidates Absent</th>
                             <th align='left'>No. of Candidates Present</th>
                        </tr>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>";
                                         
                    $body .='</tbody></table>';

                    $footer .='<table width="100%" style="overflow-x:auto;"  align="center" class="table table-striped ">
            
                    <tr height="100px"  >

                    <td style="height: 50px; text-align: right; margin-right: 5px;"  height="100px" colspan="4"><br>                        
                       <br>
                        <div style="text-align: right; font-size: 14px; " ><b>'.strtoupper("Controller Of Examinations").'</b> </div>
                      </td>
                    </tr></tbody></table>';

        $content = $header.$body.$footer;

         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'Hall Invigilation Updated.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $content, 
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                    'cssInline' => ' @media all{
                            table{border-collapse: collapse; width:100%; } 
                            table tr{
                                border: 1px solid #CCC;
                            }
                            table td{
                                border: 1px solid #CCC;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                height: 20px;
                            }
                            table th{
                               border: 1px solid #CCC;
                                text-align: center;
                            }
                        }   
                    ',  
                        'options' => ['title' => 'Hall Invigilation Updated'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['Hall Invigilation Updated- {PAGENO}'],
                        ],
                        
                    ]);
                   
                    $pdf->marginLeft="8";
                    $pdf->marginRight="8";
                    $pdf->marginBottom="8";
                    $pdf->marginFooter="4";
                    Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                    $headers = Yii::$app->response->headers;
                    $headers->add('Content-Type', 'application/pdf');
                    return $pdf->render(); 
                
        
    }

     public function actionClaimhall()
    {        
        
        $model = new FacultyHallArrange();
        $examTimetable = new ExamTimetable();
       
        if (Yii::$app->request->post())  
        {            
            $year=$_POST['fh_year'];
            $month=$_POST['fh_month'];
            
            if($_POST['from_date']!='' && $_POST['to_date']!='')
            {                
                $from_date=date('Y-m-d',strtotime($_POST['from_date']));
                $to_date=date('Y-m-d',strtotime($_POST['to_date']));
            }

                $query_1 = new Query();
                $query_1->select([ 'A.faculty_id','A.exam_date','B.*'])
                ->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                ->Where(['A.year'=>$year,'A.month'=>$month])            
                ->andWhere(['<','A.exam_date',date("Y-m-d")]);
                
                if($_POST['from_date']!='' && $_POST['to_date']!='')
                {                
                   $query_1->andWhere(['between','A.exam_date',$from_date,$to_date]);
                }

                $query_1->groupby('A.faculty_id');
                $hall_faculty = $query_1->createCommand()->queryAll();

                $query_2 = new Query();
                $query_2->select([ 'A.rhs as faculty_id','A.exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.rhs')
                ->Where(['A.year'=>$year,'A.month'=>$month])
                ->andWhere(['<','A.exam_date',date("Y-m-d")]);
                if($_POST['from_date']!='' && $_POST['to_date']!='')
                {                
                   $query_2->andWhere(['between','A.exam_date',$from_date,$to_date]);
                }
                $query_2->groupby('A.rhs');
                $rhs_faculty = $query_2->createCommand()->queryAll();

                 $query_3 = new Query();
                $query_3->select([ 'A.*','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.aur')
                ->Where(['A.year'=>$year,'A.month'=>$month])
                ->andWhere(['<','A.exam_date',date("Y-m-d")]);
                if($_POST['from_date']!='' && $_POST['to_date']!='')
                {                
                   $query_3->andWhere(['between','A.exam_date',$from_date,$to_date]);
                }
                $query_3->groupby('A.aur');

                $aur_faculty = $query_3->createCommand()->queryAll();

                 $query_4 = new Query();
                $query_4->select([ 'A.*','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.chief')
                ->Where(['A.year'=>$year,'A.month'=>$month])
                ->andWhere(['<','A.exam_date',date("Y-m-d")]);
                if($_POST['from_date']!='' && $_POST['to_date']!='')
                {                
                   $query_4->andWhere(['between','A.exam_date',$from_date,$to_date]);
                }
                $query_4->groupby('A.chief');
            
                $chief_faculty = $query_4->createCommand()->queryAll();

            $consolidateddata =[];
             $consolidateddata = array_merge($hall_faculty,$rhs_faculty);
             
             $consolidateddata =$this->getUniqueclaim($consolidateddata);

           
            if(!empty($consolidateddata))
            {
                
                $monthname = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" .$month . "'")->queryScalar();   

               $_SESSION['get_examyear'] = $monthname.' - '.$year;               

                return $this->render('claimhall', [
                    'model' => $model,
                    'examTimetable' => $examTimetable,
                    'consolidateddata'=>$consolidateddata,
                    'aur_faculty'=>$aur_faculty,
                    'year'=>$year,
                    'month'=>$month,
                    'chief_faculty'=>$chief_faculty,
                    'from_date'=>$from_date,
                    'to_date'=>$to_date
                ]);
            }

             Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/claimhall']);
                
        }

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation Claim');
        return $this->render('claimhall', [
            'model' => $model,'examTimetable' => $examTimetable,'consolidateddata'=>''
        ]);
    }

    public function actionClaimhallindiv()
    {        
        
        $model = new FacultyHallArrange();
        $examTimetable = new ExamTimetable();
       
        if (Yii::$app->request->post())  
        {            
            $year=$_POST['fh_year'];
            $month=$_POST['fh_month'];
            $sesionname='';$sesion='';
             $aur_faculty =$consolidateddata =[];
             $hall_date='';
            if(Yii::$app->user->getId()==1 || Yii::$app->user->getId()==11)
            {
                $hall_date=$_POST['hall_date'];
                $sesion=$_POST['hall_session'];
                
                    $query_1 = new Query();
                    $query_1->select([ 'A.faculty_id','A.exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                    ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                    //->join('JOIN','coe_val_faculty_claim C','C.val_faculty_id = A.faculty_id AND C.claim_type=7 AND paid_status=0')
                    ->Where(['A.year'=>$year,'A.month'=>$month])            
                    ->andWhere(['=','A.exam_date',date("Y-m-d",strtotime($hall_date))])
                    ->andWhere(['=','A.exam_session',$sesion])
                    ->groupby('A.faculty_id');
                    $hall_faculty = $query_1->createCommand()->queryAll();

                    $query_2 = new Query();
                    $query_2->select([ 'A.rhs as faculty_id','A.exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                    ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.rhs')
                    // ->join('JOIN','coe_val_faculty_claim C','C.val_faculty_id = A.rhs AND C.claim_type=7 AND C.paid_status=0')
                    ->Where(['A.year'=>$year,'A.month'=>$month])
                    ->andWhere(['=','A.exam_date',date("Y-m-d",strtotime($hall_date))])
                    ->andWhere(['=','A.exam_session',$sesion])
                    ->groupby('A.rhs');
                    $rhs_faculty = $query_2->createCommand()->queryAll();

                   
                     $consolidateddata = array_merge($hall_faculty,$rhs_faculty);
                     
                     $consolidateddata =$this->getUniqueclaim($consolidateddata);
               
            }
            else
            {
                
                if(14>date('H'))
                {
                   $sesion='36';
                     $sesionname='FN';
                }
                else if(date('A')=='PM' && (14<=date('H')) && (18>date('H')))
                {
                    $sesion='37';
                    $sesionname='AN';
                }
                    $query_1 = new Query();
                    $query_1->select([ 'A.faculty_id','A.exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                    ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                    //->join('JOIN','coe_val_faculty_claim C','C.val_faculty_id = A.faculty_id AND C.claim_type=7 AND paid_status=0')
                    ->Where(['A.year'=>$year,'A.month'=>$month])            
                    ->andWhere(['=','A.exam_date',date("Y-m-d")])
                    ->andWhere(['=','A.exam_session',$sesion])
                    ->groupby('A.faculty_id');
                    $hall_faculty = $query_1->createCommand()->queryAll();

                    $query_2 = new Query();
                    $query_2->select([ 'A.rhs as faculty_id','A.exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                    ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.rhs')
                    // ->join('JOIN','coe_val_faculty_claim C','C.val_faculty_id = A.rhs AND C.claim_type=7 AND C.paid_status=0')
                    ->Where(['A.year'=>$year,'A.month'=>$month])
                    ->andWhere(['=','A.exam_date',date("Y-m-d")])
                    ->andWhere(['=','A.exam_session',$sesion])
                    ->groupby('A.rhs');
                    $rhs_faculty = $query_2->createCommand()->queryAll();

                    $consolidateddata = array_merge($hall_faculty,$rhs_faculty);
             
                     $consolidateddata =$this->getUniqueclaim($consolidateddata);
            
            }
                   

            if(!empty($consolidateddata))
            {
                
                $monthname = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" .$month . "'")->queryScalar();   

               $_SESSION['get_examyear'] = $monthname.' - '.$year;               

                return $this->render('claimhallindiv', [
                    'model' => $model,
                    'examTimetable' => $examTimetable,
                    'consolidateddata'=>$consolidateddata,
                    'aur_faculty'=>$aur_faculty,
                    'year'=>$year,
                    'month'=>$month,
                    'sesion'=>$sesion,
                    'sesionname'=>$sesionname,
                    'hall_date'=>$hall_date
                ]);
            }

             Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/claimhallindiv']);
                
        }

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation Indiviual Claim');
        return $this->render('claimhallindiv', [
            'model' => $model,'examTimetable' => $examTimetable,'consolidateddata'=>''
        ]);
    }


    public function actionClaimhallPdf()
    {
        $content=$_SESSION['claimhalldata'];
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'claimhalldata.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $content, 
                   
                    'cssInline' => ' @media all{
                            table{border-collapse: collapse; width:100%; } 
                            
                            table td{
                                border: 1px solid #CCC;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                            }
                            table th{
                               border: 1px solid #CCC;
                                text-align: center;
                            }
                            
                        }   
                    ',  
                        'options' => ['title' => 'Hall Invigilation Claim'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['Hall Invigilation Claim - {PAGENO}'],
                        ],
                        
                    ]);
                   
                    $pdf->marginLeft="8";
                    $pdf->marginRight="8";
                    $pdf->marginBottom="8";
                    $pdf->marginFooter="4";
                    Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                    $headers = Yii::$app->response->headers;
                    $headers->add('Content-Type', 'application/pdf');
                    return $pdf->render(); 
                
        
    }

    public function actionClaimhallExcel()
    {

        $content = $_SESSION['claimhalldataexcel'];          
        $fileName = 'claimhalldata ' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    protected function getUniqueclaim($claim) 
    {
      $claimdata = array();

      foreach($claim as $clm) {
        $niddle = $clm['faculty_id'];
        if(array_key_exists($niddle, $claimdata)) continue;
        $claimdata[$niddle] = $clm;
      }

      return $claimdata;
    }

   public function actionHallcountreport()
    {        
               
        $model = new FacultyHallArrange();
        $examTimetable = new ExamTimetable();
       
        if (Yii::$app->request->post())  
        {            
            $year=$_POST['fh_year'];
            $month=$_POST['fh_month'];

            $assign_update='0';
            if(isset($_POST['assign_update']) && $_POST['assign_update']=='on'){$assign_update=1;}

                $query_1 = new Query();
                $query_1->select([ 'A.faculty_id','exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                ->Where(['A.year'=>$year,'A.month'=>$month])
                ->andWhere(['<','exam_date',date("Y-m-d")])
                ->groupby('A.faculty_id')->orderBy(['faculty_board'=>SORT_ASC]);
                $hall_faculty = $query_1->createCommand()->queryAll();

                $query_2 = new Query();
                $query_2->select([ 'A.rhs as faculty_id','exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.rhs')
                ->Where(['A.year'=>$year,'A.month'=>$month])
                ->andWhere(['<','exam_date',date("Y-m-d")])
                ->groupby('A.rhs')->orderBy(['faculty_board'=>SORT_ASC]);
                $rhs_faculty = $query_2->createCommand()->queryAll();

                $query_date = new Query();
                $query_date->select([ 'exam_date'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                ->Where(['A.year'=>$year,'A.month'=>$month])
                ->andWhere(['<','exam_date',date("Y-m-d")])
                ->groupby('exam_date');
                //echo $query_date->createCommand()->getrawsql(); exit;
                $hall_date = $query_date->createCommand()->queryAll();


            $consolidateddata =[];
             $consolidateddata = array_merge($hall_faculty,$rhs_faculty);
             
             $consolidateddata =$this->getUniqueclaim($consolidateddata);

             array_multisort(array_column($consolidateddata, 'faculty_board'),  SORT_ASC, $consolidateddata);

            if(!empty($consolidateddata))
            {
                
                $monthname = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" .$month . "'")->queryScalar();   

               $_SESSION['get_examyear'] = $monthname.' - '.$year;               

                return $this->render('hallcountreport', [
                    'model' => $model,
                    'examTimetable' => $examTimetable,
                    'consolidateddata'=>$consolidateddata,
                    'year'=>$year,
                    'month'=>$month,
                    'hall_date'=>$hall_date,
                    'assign_update'=>$assign_update
                ]);
            }

             Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/hallcountreport']);
                
        }

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation Count Report');
        return $this->render('hallcountreport', [
            'model' => $model,'examTimetable' => $examTimetable,'consolidateddata'=>''
        ]);
    }

     public function actionHallcountreportPdf()
    {
        $content=$_SESSION['hallcountreport'];
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'hallcountreport.pdf',                
                    'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                    'content' => $content, 
                   
                    'cssInline' => ' @media all{
                            table{border-collapse: collapse; width:100%; } 
                            
                            table td{
                                border: 1px solid #CCC;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                            }
                            table th{
                               border: 1px solid #CCC;
                                text-align: center;
                            }
                            
                        }   
                    ',  
                        'options' => ['title' => 'Hall Invigilation Count Report'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['Hall Invigilation Count Report - {PAGENO}'],
                        ],
                        
                    ]);
                   
                    $pdf->marginLeft="8";
                    $pdf->marginRight="8";
                    $pdf->marginBottom="8";
                    $pdf->marginFooter="4";
                    Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                    $headers = Yii::$app->response->headers;
                    $headers->add('Content-Type', 'application/pdf');
                    return $pdf->render(); 
                
        
    }

    public function actionHallcountreportExcel()
    {

        $content = $_SESSION['hallcountreport'];          
        $fileName = 'Hallcountreport ' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionConsolidatedfacultyhallmail()
    {        
        
        $model = new FacultyHallArrange();
        $examTimetable = new ExamTimetable();
       
         if (Yii::$app->request->post())  
        {            
            $year=$_POST['fh_year'];
            $month=$_POST['fh_month'];

            $from_date=$_POST['from_date'];
            $to_date=$_POST['to_date'];

                $query_1 = new Query();
                $query_1->select([ 'A.faculty_id','exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                ->Where(['A.year'=>$year,'A.month'=>$month]);
                if($from_date!='' && $to_date!='')
                {
                    $query_1->andWhere(['between','exam_date',date("Y-m-d",strtotime($from_date)),date("Y-m-d",strtotime($to_date))]);
                }
                
                $query_1->groupby('A.faculty_id')->orderBy(['faculty_board'=>SORT_ASC]);
                $hall_faculty = $query_1->createCommand()->queryAll();

                $query_2 = new Query();
                $query_2->select([ 'A.rhs as faculty_id','exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.rhs')
                ->Where(['A.year'=>$year,'A.month'=>$month]);
                if($from_date!='' && $to_date!='')
                {
                    $query_2->andWhere(['between','exam_date',date("Y-m-d",strtotime($from_date)),date("Y-m-d",strtotime($to_date))]);
                }                
                $query_2->groupby('A.rhs')->orderBy(['faculty_board'=>SORT_ASC]);
                $rhs_faculty = $query_2->createCommand()->queryAll();

                $query_3 = new Query();
                $query_3->select([ 'A.chief as faculty_id','exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.chief')
                ->Where(['A.year'=>$year,'A.month'=>$month]);
                if($from_date!='' && $to_date!='')
                {
                    $query_3->andWhere(['between','exam_date',date("Y-m-d",strtotime($from_date)),date("Y-m-d",strtotime($to_date))]);
                } 
                $query_3->groupby('A.chief')->orderBy(['faculty_board'=>SORT_ASC]);
                $chief_faculty = $query_3->createCommand()->queryAll();

                 $query_4 = new Query();
                $query_4->select([ 'A.aur as faculty_id','exam_date','B.*'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.aur')
                ->Where(['A.year'=>$year,'A.month'=>$month]);
                if($from_date!='' && $to_date!='')
                {
                    $query_4->andWhere(['between','exam_date',date("Y-m-d",strtotime($from_date)),date("Y-m-d",strtotime($to_date))]);
                }
                $query_4->groupby('A.aur')->orderBy(['faculty_board'=>SORT_ASC]);
                $aur_faculty = $query_4->createCommand()->queryAll();

                $query_date = new Query();
                $query_date->select([ 'exam_date'])->from('coe_faculty_hall_arrange A')
                ->join('JOIN','coe_valuation_faculty B','B.coe_val_faculty_id = A.faculty_id')
                ->Where(['A.year'=>$year,'A.month'=>$month]);
                if($from_date!='' && $to_date!='')
                {
                    $query_date->andWhere(['between','exam_date',date("Y-m-d",strtotime($from_date)),date("Y-m-d",strtotime($to_date))]);
                }
                $query_date->groupby('exam_date')->orderBy('exam_date');
                //echo $query_date->createCommand()->getrawsql(); exit;
                $hall_date = $query_date->createCommand()->queryAll();


            $consolidateddata =[];
             $consolidateddata = array_merge($hall_faculty,$rhs_faculty);
              $consolidateddata = array_merge($consolidateddata,$chief_faculty);
               $consolidateddata = array_merge($consolidateddata,$aur_faculty);
             
             $consolidateddata =$this->getUniqueclaim($consolidateddata);

             array_multisort(array_column($consolidateddata, 'exam_date'),  SORT_ASC, $consolidateddata);
             //print_r($consolidateddata); exit;
            if(!empty($consolidateddata))
            {
                
                $monthname = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" .$month . "'")->queryScalar();   

               $_SESSION['get_examyear'] = $monthname.' - '.$year;               

                return $this->render('consolidatedfacultyhallmail', [
                    'model' => $model,
                    'examTimetable' => $examTimetable,
                    'consolidateddata'=>$consolidateddata,
                    'year'=>$year,
                    'month'=>$month,
                    'hall_date'=>$hall_date
                ]);
            }

             Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                        return $this->redirect(['qp/consolidatedfacultyhallmail']);
                
        }

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidated Hall Invigilation');
        return $this->render('consolidatedfacultyhallmail', [
            'model' => $model,'examTimetable' => $examTimetable,'consolidateddata'=>''
        ]);
    }


     public function actionHallcountreportmailExcel()
    {

        $content = $_SESSION['hallcountreportxl'];          
        $fileName = 'Hallcountreport ' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
  
}