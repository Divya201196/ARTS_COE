<?php
namespace app\controllers;

use Yii;
use kartik\mpdf\Pdf;
use yii\helpers\Html;
use app\models\MarkEntry;
use app\models\AbsentEntry;
use app\models\ExamTimetable;
use app\models\CoeBatDegReg;
use app\models\Degree;
use app\models\Batch;
use app\models\Categories;
use app\models\MandatorySubjects;
use app\models\MarkEntrySearch;
use app\models\MarkEntryMaster;
use app\models\MarkEntryMasterSearch;
use app\models\Categorytype;
use app\models\Student;
use app\models\StudentMapping;
use app\models\Subjects;
use app\models\Regulation;
use app\models\Revaluation;
use app\models\SubjectsMapping;
use app\models\HallAllocate;
use app\models\DummyNumbers;
use app\models\AdditionalCredits;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use yii\db\Query;
use yii\helpers\Json;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Alignment;
use app\models\TcData;
use app\models\StuInfo;
use app\models\SubInfo;
use app\models\TransferCertificates;
use app\models\EqualentSubjects;
use app\models\DelQualentSubjects;
/**
 * MarkEntryController implements the CRUD actions for MarkEntry model.
 */
class MarkEntryController extends Controller
{
    /**
     * @inheritdoc
    **/
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
     * Lists all MarkEntry models.
     * @return mixed
    **/
    public function actionIndex()
    {
        $searchModel = new MarkEntrySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
    /**
     * Lists all MarkEntry models.
     * @return mixed
     * @throws \yii\db\Exception
     */
    public function actionMarkStatement()
    {
        $model = new MarkEntry();
        $student = new Student();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (isset($_POST['get_marks']) && Yii::$app->request->post() && !empty($_POST['bat_map_val'])) 
        {            
            $get_batc_map = CoeBatDegReg::findOne($_POST['bat_map_val']);
            $deg_info = Degree::findOne($get_batc_map->coe_degree_id);
            $print_trimester = (($deg_info->degree_total_semesters/2)==3 && $deg_info->degree_code=='MBA') ? 1: 0;
            $top_margin = $_POST['top_margin'];
            $bottom_margin = $_POST['bottom_margin'];
            $mark_statement_type = $_POST['deg_credit_type'];
            $withheld_list = Yii::$app->db->createCommand('SELECT DISTINCT student_map_id as id FROM coe_mark_entry_master WHERE month="'.$_POST['MarkEntry']['month'].'" AND year="'.$_POST['MarkEntry']['year'].'" AND withheld="w" ')->queryAll();
            $withheld = [];
            foreach ($withheld_list as $key => $value) {
                $withheld[$value['id']]=$value['id'];
            }
            //$withheld = array_filter($withheld);
            $query = new  Query();
            $query->select('G.paper_no,F.student_map_id,H.subject_code,  A.name, A.register_number, G.semester, H.subject_name,A.dob,A.gender, H.credit_points, H.ESE_min,H.ESE_max, H.CIA_max,H.CIA_min, H.end_semester_exam_value_mark as sub_total_marks, B.course_batch_mapping_id,K.description as month, F.month as add_month , C.regulation_year,F.year, F.term,F.ESE,F.CIA, F.total,F.result,F.withheld,F.grade_name, F.grade_point, D.degree_code,D.degree_name,E.programme_name,F.year_of_passing, sum(H.credit_points) as cumulative,part_no,I.batch_name')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], "C.coe_batch_id" => $_POST['bat_val'], 'F.year' => $_POST['MarkEntry']['year'], 'F.month' => $_POST['MarkEntry']['month'], 'F.term' => $_POST['MarkEntry']['term'], 'A.student_status' => 'Active'])
        //    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
      //  ->andWhere(['NOT IN', "F.student_map_id", $withheld])
                ->andWhere(['between', "A.register_number", $_POST['from_reg'], $_POST['to_reg']]);
            $query->groupBy('F.student_map_id,F.subject_map_id,G.semester')
                ->orderBy('A.register_number,G.semester,G.paper_no');
               
            $mark_statement = $query->createCommand()->queryAll();
           
            $query_man = new  Query();
            $query_man->select('G.paper_no,F.student_map_id,H.subject_code,  A.name, A.register_number, H.subject_name,A.dob,A.gender,credit_points, H.ESE_min,H.ESE_max, H.CIA_max,H.CIA_min, H.end_semester_exam_value_mark as sub_total_marks, B.course_batch_mapping_id,K.description as month, F.month as add_month , C.regulation_year,F.year,F.term,F.subject_map_id,F.student_map_id, F.ESE,F.CIA, F.total,F.result,F.semester ,F.withheld,F.grade_name, F.grade_point, G.is_additional, D.degree_code,D.degree_name,E.programme_name,F.year_of_passing, sum(credit_points) as cumulative,part_no,I.batch_name')
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
            $query_man->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], "C.coe_batch_id" => $_POST['bat_val'], 'F.year' => $_POST['MarkEntry']['year'], 'F.month' => $_POST['MarkEntry']['month'], 'F.term' => $_POST['MarkEntry']['term'],'A.student_status' => 'Active'])
               ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['NOT IN', "F.student_map_id", $withheld])
                ->andWhere(['between', "A.register_number", $_POST['from_reg'], $_POST['to_reg']]);
            $query_man->groupBy('F.student_map_id,F.subject_map_id')
                ->orderBy('A.register_number,F.semester,G.paper_no');
               
            $mandatory_statement = $query_man->createCommand()->queryAll();
            if(!empty($mandatory_statement))
            {
                $mark_statement = array_merge($mark_statement,$mandatory_statement);
            }

            //print_r($mark_statement );exit;
            array_multisort(array_column($mark_statement, 'semester'),  SORT_ASC, $mark_statement);
            array_multisort(array_column($mark_statement, 'paper_no'),  SORT_ASC, $mark_statement);
            array_multisort(array_column($mark_statement, 'register_number'),  SORT_ASC, $mark_statement);
            $_SESSION['degree_info'] = $deg_info;
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            if (!empty($mark_statement)) {
                if ($file_content_available == "Yes") {
                    return $this->render('mark-statement', [
                        'model' => $model,
                        'top_margin'=>$top_margin,
                        'bottom_margin'=>$bottom_margin,
                        'student' => $student,
                        'mark_statement' => $mark_statement,
                        'print_trimester' => $print_trimester,
                        'mark_statement_type' => $mark_statement_type,
                        'date_print' =>$_POST['created_at'],
                        'deg_info' =>$deg_info,
                    ]);
                } else {
                    Yii::$app->ShowFlashMessages->setMsg('Error', "No Institute Information Found");
                    return $this->redirect(['mark-entry/mark-statement']);
                }
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found ");
                return $this->redirect(['mark-entry/mark-statement']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Mark Statement');
            return $this->render('mark-statement', [
                'model' => $model,
                'student' => $student,
            ]);
        }
    }
    // Mark Statement Pdf
    public function actionMarkStatementPrintPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['mark_statement_pdf'];
        $change_css_file =  'css/newmarkstatement.css';
        $degree_code = 'UG';   
        $ug_top_margin = 7.4;
        if($org_email=='coe@skcet.in')
        {
            $change_css_file = 'css/skcet_oldmarkstatement.css';
        }
        if(isset($_SESSION['degree_info']))
        {
            $degree_code = $_SESSION['degree_info']->degree_type;
            $ug_top_margin = 9.8;
        }
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' MARK STATEMENT.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,         
            'cssFile' => $change_css_file,
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)) . ' MARK STATEMENT'],
        ]);
        if($org_email=='coe@skcet.in')
        {
            $pdf->marginTop = "7";
            $pdf->marginLeft = "5.5";
            $pdf->marginRight = "3";
            $pdf->marginBottom = "0";
            $pdf->marginHeader = "3";
            $pdf->marginFooter = "0";
        }
        else
        {
            $pdf->marginTop = $ug_top_margin;
            $pdf->marginLeft = "6.2";
            $pdf->marginRight = "2";
            $pdf->marginBottom = "0";
            $pdf->marginHeader = "3";
            $pdf->marginFooter = "0";
        
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
    }
    public function actionTransferCertificate()
    {
        $model = new MarkEntry();
        $student = new Student();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Transfer Certificate');
        return $this->render('transfer-certificate', [
            'model' => $model,
            'student' => $student,
        ]);
    }
    public function actionTransfercerficatedata()
    {

        $batch = Yii::$app->request->post('batch');
        $bat_map_id = Yii::$app->request->post('bat_map_id');
        $reg_from = Yii::$app->request->post('reg_from');
        $reg_to = Yii::$app->request->post('reg_to');
        $date_issue = Yii::$app->request->post('date_issue');
        $final_student = TransferCertificates::find()->where(['between','register_number',$reg_from,$reg_to])->all();
        $table = "";
        $sn = 1;
        $query = new Query();
        if (count($final_student) > 0) 
        {
            foreach ($final_student as $tc) 
            {                
                 $table .= '<table style="background: #FFF;" class="main_table" width="100%" >
                <tr>
                    <td colspan="5">

                      <table class="right_table"    width="100%">
                        
                            <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="stuname" valign="middle" height="70px"><b> '.$tc['name'].'</b></td>
                            </tr>
                             <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="fatname"  style="padding-bottom:-4px;" valign="middle" height="70px"><b> '.$tc['parent_name'].'</b></td>
                            </tr>
                             <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="nationality_print"  valign="bottom" height="70px"><b> '.$tc['nationality'].'</b></td>
                            </tr>
                            <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="stu_religion" valign="bottom" height="80px"><b> '.$tc['religion'].'</b></td>
                            </tr>
                            
                             <tr> 
                                <td colspan="4"> &nbsp; </td> 
                                <td  class="subcaste_print" valign="bottom"  height="80px"><b>'.strtoupper($tc['caste']).'</b></td> 
                             </tr>

                             <tr>
                                <td colspan="4"> &nbsp; </td> 
                                <td  class="comunity_name" valign="bottom" height="80px"><b>'.strtoupper($tc['community']).'</b></td> 
                            </tr>
                             <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td class="dob" valign="bottom" height="80px"><b> '.DATE('d/m/Y',strtotime($tc['dob'])).' </b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td  class="dob_words" valign="bottom" height="80px"><b> '.(date("F d, Y", strtotime($tc['dob']))).' </b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td  valign="middle"  style="padding-bottom:-115px;" height="110px"><b> '.strtoupper($tc['class_studying']).'</b></td>


                            </tr>




                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td  class="admission_p" height="160px"><b> '.DATE('d/m/Y',strtotime($tc['admission_date'])).' </b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td valign="bottom" class="admission_down" height="90px"><b> '.strtoupper($tc['reason']).'</b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td valign="bottom" class="char" height="90px"><b> '.strtoupper($tc['conduct_char']).' </b></td>
                            </tr>
                            <tr class="push_down">
                               
                               <td colspan="4"> &nbsp; </td> 
                               <td valign="bottom" class="date_tc"  height="90px"><b> '.date('d/m/Y', strtotime($tc['date_of_app_tc'])).' </b></td>
                            </tr>
                            <tr class="push_down">
                                
                                <td colspan="4"> &nbsp; </td> 
                                <td  valign="bottom"  class="date_left" height="90px"><b> '.date('d/m/Y', strtotime($tc['date_of_left'])).' </b></td>
                            </tr>
                            <tr class="push_down">
                                <td colspan="4"> &nbsp; </td> 
                                <td valign="bottom" class="date_issue"  height="90px"><b> '.date('d/m/Y', strtotime($date_issue)).' </b></td>
                            </tr>
                            
                    </table>
                    </td>
                    <td colspan="5">
                       <table class="right_table_tc_1"   width="165%">
                        
                           <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="stuname" valign="middle" height="70px"><b> '.$tc['name'].'</b></td>
                            </tr>
                             <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="fatname"  style="padding-bottom:-4px;" valign="middle" height="70px"><b> '.$tc['parent_name'].'</b></td>
                            </tr>
                             <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="nationality_print"  valign="bottom" height="70px"><b> '.$tc['nationality'].'</b></td>
                            </tr>
                            <tr> 
                                <td colspan="4" > &nbsp; </td> 
                                <td  class="stu_religion" valign="bottom" height="80px"><b> '.$tc['religion'].'</b></td>
                            </tr>
                            
                             <tr> 
                                <td colspan="4"> &nbsp; </td> 
                                <td  class="subcaste_print" valign="bottom"  height="80px"><b>'.strtoupper($tc['community']).'</b></td> 
                             </tr>

                             <tr>
                                <td colspan="4"> &nbsp; </td> 
                                <td  class="comunity_name" valign="bottom" height="80px"><b>'.strtoupper($tc['caste']).'</b></td> 
                            </tr>
                             <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td class="dob" valign="bottom" height="80px"><b> '.DATE('d/m/Y',strtotime($tc['dob'])).' </b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td  class="dob_words" valign="bottom" height="80px"><b> '.(date("F d, Y", strtotime($tc['dob']))).' </b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td  valign="middle"  style="padding-bottom:-115px;" height="110px"><b> '.strtoupper($tc['class_studying']).'</b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td  class="admission_p" height="160px"><b> '.DATE('d/m/Y',strtotime($tc['admission_date'])).' </b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td valign="bottom" class="admission_down" height="90px"><b> '.strtoupper($tc['reason']).'</b></td>
                            </tr>
                            <tr>
                                <td colspan="4"> &nbsp; </td>
                                <td valign="bottom" class="char" height="90px"><b> '.strtoupper($tc['conduct_char']).' </b></td>
                            </tr>
                            <tr class="push_down">
                               
                               <td colspan="4"> &nbsp; </td> 
                               <td valign="bottom" class="date_tc"  height="90px"><b> '.date('d/m/Y', strtotime($tc['date_of_app_tc'])).' </b></td>
                            </tr>
                            <tr class="push_down">
                                
                                <td colspan="4"> &nbsp; </td> 
                                <td  valign="bottom"  class="date_left" height="90px"><b> '.date('d/m/Y', strtotime($tc['date_of_left'])).' </b></td>
                            </tr>
                            <tr class="push_down">
                                <td colspan="4"> &nbsp; </td> 
                                <td valign="bottom" class="date_issue"  height="90px"><b> '.date('d/m/Y', strtotime($date_issue)).' </b></td>
                            </tr>
                            
                            
                    </table>
                    </td>
                    </tr>
                    </table> '; 
                    $sn++;
                if($sn<=count($final_student))
                {
                    $table .='<pagebreak />';
                }
            }
            if (isset($_SESSION['transfer_certificate'])) 
            {
                unset($_SESSION['transfer_certificate']);
            }
            $_SESSION['transfer_certificate'] = $table;
            return $table;

        } else {
            return 0;
        }
        
    }
    public function actionTransferCertificatePdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['transfer_certificate'];
        $change_css_file =  'css/tc.css';
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Transfer Certificate.pdf',
            'format' => Pdf::FORMAT_A3,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => $change_css_file,
            
            'options' => ['title' => 'Transfer Certificate'],
            
        ]);
       
        $pdf->marginLeft = "-3";

        
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers =Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }

  
    public function actionMarkStatementPrintSkcetNewPgPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['mark_statement_pdf'];
        $change_css_file =  'css/newmarkstatement.css';   
        if($org_email=='coe@skcet.in')
        {
            $change_css_file = 'css/skcet_newmarkstatement_pg.css';
        }

        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' MARK STATEMENT PG.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,         
            'cssFile' => $change_css_file,
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)) . ' MARK STATEMENT PG'],
        ]);
        if($org_email=='coe@skcet.in')
        {
            $pdf->marginTop = "6";
            $pdf->marginLeft = "4.7";
            $pdf->marginRight = "4.7";
            $pdf->marginBottom = "0";
            $pdf->marginHeader = "3";
            $pdf->marginFooter = "0";
        }
        else
        {
            $pdf->marginTop = "7";
            $pdf->marginLeft = "4.7";
            $pdf->marginRight = "3";
            $pdf->marginBottom = "0";
            $pdf->marginHeader = "3";
            $pdf->marginFooter = "0";
        
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
    }
    public function actionMarkStatementPrintSkcetNewPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['mark_statement_pdf'];
        $change_css_file =  'css/newmarkstatement.css';   
        if($org_email=='coe@skcet.in')
        {
            $change_css_file = 'css/skcet_newmarkstatement.css';
        }

        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' MARK STATEMENT.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,         
            'cssFile' => $change_css_file,
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)) . ' MARK STATEMENT'],
        ]);
        if($org_email=='coe@skcet.in')
        {
            $pdf->marginTop = "5.5";
            $pdf->marginLeft = "4.7";
            $pdf->marginRight = "4.7";
            $pdf->marginBottom = "0";
            $pdf->marginHeader = "3";
            $pdf->marginFooter = "0";
        }
        else
        {
            $pdf->marginTop = "7";
            $pdf->marginLeft = "4.7";
            $pdf->marginRight = "3";
            $pdf->marginBottom = "0";
            $pdf->marginHeader = "3";
            $pdf->marginFooter = "0";
        
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
    }
    public function actionMarkStatementArtsPrintPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['mark_statement_pdf'];
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' MARK STATEMENT.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,         
            'cssFile' => 'css/arts_college_mark_statement.css',
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)) . ' MARK STATEMENT'],
        ]);
        
        $pdf->marginTop = "2.9";
        $pdf->marginLeft = "6.2";
        $pdf->marginRight = "2";
        $pdf->marginBottom = "2";
        $pdf->marginHeader = "2";
        $pdf->marginFooter = "2";
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
    }
    public function actionMarkStatementArtsPrintPgPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['mark_statement_pdf'];
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' MARK STATEMENT.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,         
            'cssFile' => 'css/arts_college_mark_statement.css',
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)) . ' MARK STATEMENT'],
        ]);
        
        $pdf->marginTop = "3.0";
        $pdf->marginLeft = "6";
        $pdf->marginRight = "3.5";
        $pdf->marginBottom = "2";
        $pdf->marginHeader = "2";
        $pdf->marginFooter = "2";
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
    }
    public function actionExcelMarkstatementprint()
    {
        
        $content = $_SESSION['mark_statement_pdf'];
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' Application' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    // // Mark Statement Pdf
    // Reports Starts Here
    public function actionArrearreport()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = $_POST['mark_year'];
            $omit_batches = $year-$omit_batch;
                  $query_1= "select batch_mapping_id,batch_name,programme_code,subject_code,count(distinct student_map_id) as count from coe_subjects as A  JOIN coe_subjects_mapping as B ON 
B.subject_id=A.coe_subjects_id JOIN coe_mark_entry_master as C ON C.subject_map_id=B.coe_subjects_mapping_id JOIN coe_bat_deg_reg as E ON E.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as D ON D.coe_programme_id=E.coe_programme_id JOIN coe_batch as F ON F.coe_batch_id=E.coe_batch_id  JOIN coe_student_mapping as abc ON abc.course_batch_mapping_id=B.batch_mapping_id where C.year<='".$year."' and student_map_id NOT IN (select student_map_id FROM coe_mark_entry_master as abc where abc.subject_map_id=C.subject_map_id and abc.student_map_id=C.student_map_id and result like '%Pass%' ) and C.result not like '%Pass%' and (year_of_passing='' or year_of_passing=NULL) and status_category_type_id!='".$det_disc_type."' group by batch_mapping_id,subject_map_id order by batch_name,D.programme_code asc"; 

            
            $arrear = Yii::$app->db->createCommand($query_1)->queryAll();
            if (!empty($arrear)) {
                return $this->render('arrearreport', [
                    'model' => $model,
                    'arrear' => $arrear,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found Kindly re-check your Submission");
                return $this->redirect(['mark-entry/arrearreport']);
            }
        } else {
            return $this->render('arrearreport', [
                'model' => $model,
            ]);
        }
    }
    public function actionSubArrearCountPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['sub_arrear_count'];
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' COUNT ARREAR.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
             'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'content' => $content,    
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' COUNT ARREAR LIST'],
        ]);
        
        
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        
        return $pdf->render();
    }
    public function actionSubArrearCountExcel()
    {
        
        $content = $_SESSION['sub_arrear_count'];
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' COUNT ARREAR .xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionRevalfeespaid()
    {
        $model = new MarkEntry();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (Yii::$app->request->post()) 
        {
            if(empty($_POST['reval_entry_month']) || empty($_POST['reval_entry_year']))
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', "Select Required Month");
                return $this->redirect(['mark-entry/revalfeespaid']);
            }
            $reval_fees_paid_list = Revaluation::find()->where(['year'=>$_POST['reval_entry_year'],'month'=>$_POST['reval_entry_month'],'reval_status'=>'YES'])->all();
            $month_name = Categorytype::findOne($_POST['reval_entry_month']);
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $headers = $body = '';
            $headers ='<table border="1"  class="table table-bordered table-responsive bulk_edit_table table-hover" >';                 
            $headers.='<tr>
                        <td> 
                            <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                        </td>
                        <td colspan=6 align="center"> 
                            <center><b><font size="4px">'.$org_name.'</font></b></center>
                            <center>'.$org_address.'</center>
                            
                            <center>'.$org_tagline.'</center> 
                        </td>
                        <td align="right">  
                            <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                        </td>
                    </tr>';
            $headers.='<tr><td colspan="8" align="center"><h3 align="center">Revaluation Fees Paid List For - '.$_POST['reval_entry_year'].'-'.$month_name->description.'</h3></td></tr>';
            if(isset($_POST['MarkEntry']['mark_type'][0]) && !empty($_POST['MarkEntry']['mark_type'][0]))
            {
                $headers.='<tr>
                            <th>SNO</th>
                            <th>REGISTER NUMBER</th>
                            <th>NAME</th>
                            <th colspan=2>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DUMMY)).'</th>
                            <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)).' CODE</th>
                            <th colspan=2>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)).' NAME</th>
                            </tr>';    
                $sn=0;   
                $old_reg_num = '';
                foreach ($reval_fees_paid_list as $value) 
                {
                    $reg_no = Yii::$app->db->createCommand('SELECT register_number,name FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id WHERE B.coe_student_mapping_id="'.$value['student_map_id'].'" and status_category_type_id NOT IN("'.$det_disc_type.'") ')->queryOne();
                    $course = Yii::$app->db->createCommand('SELECT subject_code,subject_name FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE B.coe_subjects_mapping_id="'.$value['subject_map_id'].'"')->queryOne();
                    $dummynumber = DummyNumbers::find()->where(['year'=>$_POST['reval_entry_year'],'month'=>$_POST['reval_entry_month'],'student_map_id'=>$value['student_map_id'],'subject_map_id'=>$value['subject_map_id']])->one();

                    $sn = $sn+1;
                    $body .='<tr>';
                        $body .= '<td>'.$sn.'</td>';
                        $body .='<td>'.strtoupper($reg_no['register_number']).'</td>';
                        $body .='<td>'.strtoupper($reg_no['name']).'</td>';
                        $body .='<td colspan=2>'.strtoupper($dummynumber['dummy_number']).'</td>';
                        $body .='<td>'.strtoupper($course['subject_code']).'</td>';
                        $body .='<td colspan=2>'.strtoupper($course['subject_name']).'</td>';
                    $body .='</tr>';         
                }
                $print_pdf = Html::a('<i class="fa fa-file-pdf-o"></i> PDF', ['mark-entry/revaluation-fees-paid-pdf'], [
                            'class' => 'pull-right btn btn-block btn-primary',
                            'target' => '_blank',
                            'data-toggle' => 'tooltip',
                            'title' => 'Will open the generated PDF file in a new window'
                ]);
                $print_excel = Html::a('<i class="fa fa-file-pdf-o"></i> EXCEL', ['/mark-entry/excel-revaluation-fees-paid'], [
                            'class' => 'pull-right btn btn-block btn-warning',
                            'target' => '_blank',
                            'data-toggle' => 'tooltip',
                            'title' => 'Will open the generated PDF file in a new window'
                ]);
                $body .='</table>';
                $_SESSION['reval_fees_paid_list'] = $content = $headers. $body;

                $final_body = '<div class="box box-primary">
                                    <div class="box-body">
                                        <div class="row" >
                                            <div class="col-xs-12" >
                                                <div class="col-lg-2" > ' . $print_pdf. $print_excel . ' </div>
                                                <div class="col-lg-10" >' . $headers. $body . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
            }
            else
            {        
                $headers.='<tr>
                            <th>SNO</th>
                            <th>REGISTER NUMBER</th>
                            <th>NAME</th>
                            <th colspan=2>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)).' CODE</th>
                            <th colspan=3>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)).' NAME</th>
                            </tr>';    
                $sn=0;   
                $old_reg_num = '';
                foreach ($reval_fees_paid_list as $value) 
                {
                    $reg_no = Yii::$app->db->createCommand('SELECT register_number,name FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id WHERE B.coe_student_mapping_id="'.$value['student_map_id'].'" and status_category_type_id NOT IN("'.$det_disc_type.'")')->queryOne();
                    $course = Yii::$app->db->createCommand('SELECT subject_code,subject_name FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE B.coe_subjects_mapping_id="'.$value['subject_map_id'].'"')->queryOne();
                    $sn = $old_reg_num!==$reg_no ? $sn+1 : $sn;
                    $body .='<tr>';
                        $body .= $old_reg_num!==$reg_no ? '<td>'.$sn.'</td>' : '<td>&nbsp;</td>';
                        $body .='<td>'.strtoupper($reg_no['register_number']).'</td>';
                        $body .='<td>'.strtoupper($reg_no['name']).'</td>';
                        $body .='<td colspan=2>'.strtoupper($course['subject_code']).'</td>';
                        $body .='<td colspan=3>'.strtoupper($course['subject_name']).'</td>';
                    $body .='</tr>';   
                    $old_reg_num = $reg_no;           
                }
                $print_pdf = Html::a('<i class="fa fa-file-pdf-o"></i> PDF', ['mark-entry/revaluation-fees-paid-pdf'], [
                            'class' => 'pull-right btn btn-block btn-primary',
                            'target' => '_blank',
                            'data-toggle' => 'tooltip',
                            'title' => 'Will open the generated PDF file in a new window'
                ]);
                $print_excel = Html::a('<i class="fa fa-file-pdf-o"></i> EXCEL', ['/mark-entry/excel-revaluation-fees-paid'], [
                            'class' => 'pull-right btn btn-block btn-warning',
                            'target' => '_blank',
                            'data-toggle' => 'tooltip',
                            'title' => 'Will open the generated PDF file in a new window'
                ]);
                $body .='</table>';
                $_SESSION['reval_fees_paid_list'] = $content = $headers. $body;

                $final_body = '<div class="box box-primary">
                                    <div class="box-body">
                                        <div class="row" >
                                            <div class="col-xs-12" >
                                                <div class="col-lg-2" > ' . $print_pdf. $print_excel . ' </div>
                                                <div class="col-lg-10" >' . $headers. $body . '</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
            }
            if (empty($reval_fees_paid_list)) 
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->render('revalfeespaid', [
                    'model' => $model,
                ]);
            }
            else if (!empty($final_body)) {
                return $this->render('revalfeespaid', [
                    'model' => $model,
                    'final_body' => $final_body,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found Kindly re-check your Submission");
                return $this->redirect(['mark-entry/revalfeespaid']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('WELCOME', "WELCOME TO REVALUATION FEES PAID LIST");
            return $this->render('revalfeespaid', [
                'model' => $model,
            ]);
        }
    }
    public function actionRevaluationFeesPaidPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));        
        $content = $_SESSION['reval_fees_paid_list'];           
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "Revaluation Fees Paid List.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' =>"Revaluation Fees Paid List"],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ "Revaluation Fees Paid List " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ],
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelRevaluationFeesPaid()
    {
        $content = $_SESSION['reval_fees_paid_list'];
        $fileName = "Revaluation Fees Paid List " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionGetarrearstudent()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $pgm_code = Yii::$app->request->post('pgm_code');
        $sub_code = Yii::$app->request->post('sub_code');
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $reg = Yii::$app->db->createCommand("select register_number,name from coe_programme as A,coe_bat_deg_reg as B,coe_subjects as C,coe_subjects_mapping as D,coe_student as E,coe_student_mapping as F,coe_mark_entry_master as G where A.coe_programme_id=B.coe_programme_id and C.coe_subjects_id=D.subject_id and E.coe_student_id=F.student_rel_id and D.batch_mapping_id=F.course_batch_mapping_id and B.coe_bat_deg_reg_id=D.batch_mapping_id and D.coe_subjects_mapping_id=G.subject_map_id and F.coe_student_mapping_id=G.student_map_id and F.course_batch_mapping_id ='" . $pgm_code . "' and D.batch_mapping_id='".$pgm_code."' and C.subject_code='" . $sub_code . "' and G.year<='" . $year . "' and (result='Fail' or result='Absent') and status_category_type_id NOT IN('".$det_disc_type."') and student_map_id NOT IN(select student_map_id FROM coe_mark_entry_master as abc where abc.subject_map_id = G.subject_map_id and result like '%Pass%') and (year_of_passing='' or year_of_passing=NULL) group by register_number")->queryAll();
        return Json::encode($reg);
    }
    public function actionProgrammewisearrear()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            
            $select_query = "SELECT distinct (H.subject_code) as subject_code,concat(D.degree_code,'-',E.programme_code) as degree_code,batch_name,count(DISTINCT F.student_map_id) as count,H.subject_name,G.semester,F.year,E.programme_name,D.degree_name FROM  coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id  JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_subjects_mapping as G ON G.batch_mapping_id=B.course_batch_mapping_id and G.batch_mapping_id=C.coe_bat_deg_reg_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_subjects_mapping_id JOIN coe_subjects H ON H.coe_subjects_id=G.subject_id JOIN coe_batch I ON I.coe_batch_id=C.coe_batch_id WHERE I.coe_batch_id='".$_POST['bat_val']."' and B.course_batch_mapping_id='".$_POST['bat_map_val']."' and G.batch_mapping_id='".$_POST['bat_map_val']."' AND A.student_status='Active' and F.year_of_passing='' and F.student_map_id NOT IN (SELECT student_map_id FROM coe_mark_entry_master WHERE subject_map_id=F.subject_map_id and student_map_id=F.student_map_id and result like '%pass%' ) AND F.year<='".$_POST['mark_year']."' AND  status_category_type_id NOT IN ('".$det_disc_type."') group by F.subject_map_id order by degree_code,G.semester";
            
            $programmewisearrear = Yii::$app->db->createCommand($select_query)->queryAll();

            if (!empty($programmewisearrear)) {
                return $this->render('programmewisearrear', [
                    'model' => $model,
                    'programmewisearrear' => $programmewisearrear,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found Kindly re-check your Submission");
                return $this->redirect(['mark-entry/programmewisearrear']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Wise Arrear ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('programmewisearrear', [
                'model' => $model,
            ]);
        }
    }

    public function actionProgrammewisearrearnominal()
    {
        $model = new MarkEntry();
        if(isset($_SESSION['programmewisearrearnominal'])){ unset($_SESSION['programmewisearrearnominal']);}
        if (Yii::$app->request->post()) 
        {
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = DATE('Y');
            $omit_batches = $year-$omit_batch;
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $month = $_POST['MarkEntry']['month'];
            $batch_val = $_POST['bat_val'];
            $catType = Categories::find()->where(['category_name'=>'Trisem'])->orWhere(['description'=>'Trisem'])->orWhere(['category_name'=>'Trisemester'])->one();
            $month_ids = '';
            $practical_paper_1 = Categorytype::find()->where(['category_type'=>'Practical'])->orWhere(['description'=>'Practical'])->one();
            $practical_paper_2 = Categorytype::find()->where(['category_type'=>'Theory with Lab Components'])->orWhere(['description'=>'Theory with Lab Components'])->one();
            $practical_paper_3 = Categorytype::find()->where(['category_type'=>'Theory&practical'])->orWhere(['description'=>'Theory & practical'])->orWhere(['description'=>'Theory&practical'])->orWhere(['category_type'=>'Theory & practical'])->one();

            $practical_paers = $practical_paper_1['coe_category_type_id'].",".$practical_paper_2['coe_category_type_id'].",".$practical_paper_3['coe_category_type_id'];

            $reval_status = isset($_POST['MarkEntry']['mark_type'][0])?$_POST['MarkEntry']['mark_type'][0]:'';
            $remove_det = isset($_POST['MarkEntry']['term'][0])?$_POST['MarkEntry']['term'][0]:'';
            $addIntheQuery = $reval_status=='yes'?' and paper_type_id IN('.$practical_paers.') ':'';

            $addIntheQueryDet = $remove_det=='yes'?" AND status_category_type_id NOT IN ('".$det_disc_type."','".$det_cat_type."') ":" AND status_category_type_id NOT IN ('".$det_disc_type."') ";
            if($month==29 || $month==30)
            {
                $month_ids = $month;   
            }
            else
            {
                if(!empty($catType))
                {
                    $triMonths = Categorytype::find()->where(['category_id'=>$catType->coe_category_id])->all();
                    foreach ($triMonths as $key => $value) 
                    {
                        $month_ids .="'".$value['coe_category_type_id']."',";
                    }
                    $month_ids = trim($month_ids,",");
                }
            }
            
            $select_query = "SELECT distinct (H.subject_code) as subject_code,G.semester,concat(D.degree_code,'-',E.programme_code) as degree_code,count(DISTINCT F.student_map_id) as count,H.subject_name,I.description as subject_type,J.description as course_type,K.description as paper_type ,E.programme_name,D.degree_name,bat.batch_name  FROM  coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id  JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_batch as bat ON bat.coe_batch_id=C.coe_batch_id  JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects H ON H.coe_subjects_id=G.subject_id JOIN coe_category_type as I ON I.coe_category_type_id=G.subject_type_id JOIN coe_category_type as J ON J.coe_category_type_id=G.course_type_id JOIN coe_category_type as K ON K.coe_category_type_id=G.paper_type_id WHERE  A.student_status='Active' and F.year_of_passing='' and F.student_map_id NOT IN (SELECT student_map_id FROM coe_mark_entry_master WHERE subject_map_id=F.subject_map_id and result like '%pass%' ) AND F.year<='".$_POST['mark_year']."' and C.coe_batch_id='".$batch_val."' and bat.coe_batch_id='".$batch_val."' ".$addIntheQueryDet." ".$addIntheQuery." group by B.course_batch_mapping_id,F.subject_map_id order by batch_name,degree_code,G.semester";
            
            $programmewisearrearnominal = Yii::$app->db->createCommand($select_query)->queryAll();

            if (!empty($programmewisearrearnominal)) {
                return $this->render('programmewisearrearnominal', [
                    'model' => $model,
                    'programmewisearrearnominal' => $programmewisearrearnominal,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found Kindly re-check your Submission");
                return $this->redirect(['mark-entry/programmewisearrearnominal']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Wise Arrear ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('programmewisearrearnominal', [
                'model' => $model,
            ]);
        }
    }
    public function actionElectiveCount()
    {
        $model = new MarkEntry();
        if(isset($_SESSION['electivecount'])){ unset($_SESSION['electivecount']);}
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
           
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $sem_verify = ConfigUtilities::SemCaluclation($_POST['mark_year'],$_POST['MarkEntry']['month'],$_POST['bat_map_val']);
            $select_query = "SELECT distinct (H.subject_code) as subject_code,concat(D.degree_code,'-',E.programme_code) as degree_code,count(DISTINCT F.coe_student_id) as count,B.course_batch_mapping_id,H.subject_name,F.coe_student_id,F.coe_subjects_id,F.semester,E.programme_name,D.degree_name,bat.batch_name  FROM  coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id  JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_batch as bat ON bat.coe_batch_id=C.coe_batch_id  JOIN coe_nominal as F ON F.coe_student_id=A.coe_student_id JOIN coe_subjects H ON H.coe_subjects_id=F.coe_subjects_id JOIN coe_subjects_mapping as G ON G.subject_id=H.coe_subjects_id WHERE B.course_batch_mapping_id='".$_POST['bat_map_val']."' and F.semester='".$sem_verify."' and  A.student_status='Active' AND  status_category_type_id NOT IN ('".$det_cat_type."','".$det_disc_type."') group by subject_code,semester order by batch_name,degree_code,F.semester";
            
            $programmewisearrearnominal = Yii::$app->db->createCommand($select_query)->queryAll();

            if (!empty($programmewisearrearnominal)) {
                return $this->render('elective-count', [
                    'model' => $model,
                    'programmewisearrearnominal' => $programmewisearrearnominal,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found Kindly re-check your Submission");
                return $this->redirect(['mark-entry/elective-count']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Elective Count');
            return $this->render('elective-count', [
                'model' => $model,
            ]);
        }
    }

    public function actionProgrammeWiseArrearPdfElective()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['elective-count'];
        
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "FULL ARREAR LIST.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => "FULL ARREAR LIST"],
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
    public function actionProgrammeWiseArrearElectiveExcel()
    {
       
        $content = $_SESSION['elective-count'];
        
        $fileName = "FULL ARREAR LIST-" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
	public function actionElectiveStudentRepoExcel()
    {       
        $content = $_SESSION['elective_stu_count'];        
        $fileName = "Elective Count-" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionElectiveStudentRepoPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['elective_stu_count'];
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "Elective Count.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => "Elective Count"],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ["Elective Count" . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionProgrammeWiseArrearPdfNominal()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['programmewisearrearnominal'];
        
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "FULL ARREAR LIST.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => "FULL ARREAR LIST"],
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
    public function actionProgrammeWiseArrearNominalExcel()
    {
       
        $content = $_SESSION['programmewisearrearnominal'];
        
        $fileName = "FULL ARREAR LIST-" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionProgrammeWiseArrearPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['programmewisearrear'];
           
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . " WISE ARREAR.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: left;  font-family:"Roboto, sans-serif"; width:100%; font-size: 13px; } 
                        
                        table td{
                            border: 1px solid #000;
                            text-align: left;
                        }
                        table th{
                            border: 1px solid #000;
                            text-align: left;
                        }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . " WISE ARREAR "],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . " WISE ARREAR " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionProgrammeWiseArrearExcel()
    {
        
            $content = $_SESSION['programmewisearrear'];
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . " WISE ARREAR " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    // Programme Wise Arrear Ends Here
    public function actionCoursewisearrear()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            
            $query = new  Query();
            
            $omit_batch = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_MAX_EXAM_CONDUTION);
            $year = DATE('Y');
            $omit_batches = $year-$omit_batch;
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

            $query->select(["distinct (H.subject_code) as subject_code", "concat(D.degree_code,'-',E.programme_code) as degree_code", "A.register_number", "A.name", "H.subject_name", "G.semester", "F.year",'F.student_map_id','F.subject_map_id',"F.grade_name", "E.programme_name", "K.batch_name", "D.degree_name"])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['H.subject_code'=>$_POST['subject_code'], 'A.student_status' => 'Active', 'F.year_of_passing' => ''])
                 ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                 //->andWhere(['>=','batch_name',$omit_batches])     
                 ->andWhere(['NOT LIKE','F.result','Pass']) ;            
            $query->groupBy('F.subject_map_id,F.student_map_id');
            $query->orderBy('batch_name,degree_code,subject_code,semester,register_number');
            $coursewisearrear = $query->createCommand()->queryAll();
            
            if (!empty($coursewisearrear)) 
            {
                return $this->render('coursewisearrear', [
                    'model' => $model,
                    'coursewisearrear' => $coursewisearrear,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry/coursewisearrear']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Wise Arrear ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('coursewisearrear', [
                'model' => $model,
            ]);
        }
    }
    public function actionCourseWiseArrearPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
       
            $content = $_SESSION['coursewisearrear'];
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " WISE ARREAR.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " WISE ARREAR "],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " WISE ARREAR " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionCourseWiseArrearExcel()
    {
        
            $content = $_SESSION['coursewisearrear'];
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " WISE ARREAR " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    // COURSE Wise Arrear Ends Here
    //Student Wise Arrear
    public function actionStudentwisearrear()
    {
        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            
            $sem_count = ConfigUtilities::SemCaluclation($_POST['mark_year'],$_POST['MarkEntry']['month'],$_POST['bat_map_val']);

            $query = new  Query();
            $query->select(["distinct (H.subject_code) as subject_code", "concat(D.degree_code,'-',E.programme_code) as degree_code", "A.register_number", "A.name", "H.subject_name", "G.semester", "F.year",'F.student_map_id','F.subject_map_id', "E.programme_name", "K.batch_name", "D.degree_name"])
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_batch as K', 'K.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'A.student_status' => 'Active', 'F.year_of_passing' => ''])->andWhere(['<=','F.year',$_POST['mark_year']])
                ->andWhere(['<=','G.semester',$sem_count]);            
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.student_map_id,F.subject_map_id');
            $query->orderBy('semester,register_number');
            $studentwisearrear = $query->createCommand()->queryAll();
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
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' Wise Arrear ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
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
            'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . " WISE ARREAR.pdf",
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
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . " WISE ARREAR " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
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
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . " WISE ARREAR " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    // Student wise arrear ends here
    public function actionCiamarklist()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
        $category_type_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Internal%' OR category_type like '%CIA%' ")->queryScalar();

        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $sem_valc = ConfigUtilities::SemCaluclation($_POST['mark_year'],$_POST['month'],$_POST['bat_map_val']);
            $query = new  Query();
            $query->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,A.register_number,F.category_type_id_marks as CIA,F.year,E.programme_name,D.degree_name')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_subjects_mapping_id')                
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc, 'A.student_status' => 'Active','F.category_type_id'=>$category_type_id])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->orderBy('A.register_number,H.subject_code');
            $cia_list = $query->createCommand()->queryAll();

            $subject_get_data = new  Query();
            $subject_get_data->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,total_minimum_pass as min_pass')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $subject_get_data->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'F.category_type_id'=>$category_type_id])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->orderBy('H.subject_code');
            $subjectsInfo = $subject_get_data->createCommand()->queryAll();
            $countQuery = new  Query();
            $countQuery->select('count( distinct H.subject_code) as count')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $countQuery->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'F.category_type_id'=>$category_type_id])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $countOfSubjects = $countQuery->createCommand()->queryAll();
            if (!empty($cia_list)) {
                return $this->render('ciamarklist', [
                    'model' => $model,
                    'cia_list' => $cia_list,
                    'subjectsInfo' => $subjectsInfo,
                    'countOfSubjects' => $countOfSubjects,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry/ciamarklist']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to CIA Mark List');
            return $this->render('ciamarklist', [
                'model' => $model,
            ]);
        }
    }
    public function actionCiaMarkListPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['cia_mark_list'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'CIA MARK LIST.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:150%; font-size: 13.5px; }
                    }   
                ',
            'options' => ['title' => 'CIA MARK LIST'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Conslidate CIA Mark List' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelCiaMarkList()
    {
        
        $content = $_SESSION['cia_mark_list'];
            
        $fileName = "CIA Mark List" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionEsemarklist()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $model = new MarkEntry();
        if (Yii::$app->request->post()) {
            $query = new  Query();
            $query->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,A.register_number,F.CIA,F.ESE,F.grade_name,F.year,J.category_type as month,K.category_type as exam_type,E.programme_name,D.degree_name,B.coe_student_mapping_id,G.coe_subjects_mapping_id,F.month as exam_month')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
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
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
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
                ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $countQuery->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $countOfSubjects = $countQuery->createCommand()->queryAll();
            if (!empty($ese_list)) {
                return $this->render('esemarklist', [
                    'model' => $model,
                    'ese_list' => $ese_list,
                    'subjectsInfo' => $subjectsInfo,
                    'countOfSubjects' => $countOfSubjects,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry/esemarklist']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ESE Mark List');
            return $this->render('esemarklist', [
                'model' => $model,
            ]);
        }
    }
    public function actionEseMarkListPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['ese_mark_list'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'ESE MARK LIST.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
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
            'options' => ['title' => 'ESE MARK LIST'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Conslidate ESE Mark List' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelEseMarkList()
    {
        
        $content = $_SESSION['ese_mark_list'];
           
        $fileName = "ESE Mark List" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    // Reports ends Here
    /**
     * Displays a single MarkEntry model.
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
     * Creates a new MarkEntry model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $student = new StudentMapping();
        $subjects = new SubjectsMapping();
        $updated_by = Yii::$app->user->getId();
        $updated_at = new \yii\db\Expression('NOW()');
        if ($model->load(Yii::$app->request->post())) 
        {
            $sn = Yii::$app->request->post('sn');
            $year = $model->year;
            $month = $model->month;
            
            $exam_data_show_xam_type = Categorytype::find()->where(['category_type'=>'Regular'])->one();
            $mark_type = $exam_data_show_xam_type->coe_category_type_id;
            $exam_data_show_exam_term = Categorytype::find()->where(['category_type'=>'End'])->one();
            $term = $exam_data_show_exam_term->coe_category_type_id;
            $batch_map_id = Yii::$app->request->post('bat_map_val');
            $sub = Yii::$app->request->post('sub_val');
            $internal_type = Yii::$app->request->post('cat_type_val');
            $sub_marks = Subjects::find()->where(['coe_subjects_id' => $sub])->one();
            
            $degree_name_get = Yii::$app->db->createCommand('SELECT CONCAT(degree_code," ",programme_code) FROM coe_bat_deg_reg as A JOIN coe_degree as B ON B.coe_degree_id=A.coe_degree_id JOIN coe_programme as C ON C.coe_programme_id=A.coe_programme_id where coe_bat_deg_reg_id="'.$batch_map_id.'"')->queryScalar();
            
            $sub_map_id = Yii::$app->db->createCommand("select coe_subjects_mapping_id from coe_subjects_mapping where batch_mapping_id='" . $batch_map_id . "' and subject_id='" . $sub . "' AND semester = '".$_POST['exam_semester']."'")->queryScalar();
            for ($i = 1; $i <= $sn; $i++) {
                $reg_no = $_POST["reg$i"];
                $stu_id = Student::find()->where(['register_number' => $reg_no, 'student_status' => 'Active'])->one();
                $stu_map_id = StudentMapping::find()->where(['student_rel_id' => $stu_id['coe_student_id']])->one();
                $mark_entry_check = MarkEntry::find()->where(['student_map_id' => $stu_map_id['coe_student_mapping_id'], 'subject_map_id' => $sub_map_id, 'category_type_id' => $internal_type, 'year' => $year])->one();
                //Mark Entry Table (Internal)
                $model->student_map_id = $stu_map_id['coe_student_mapping_id'];
                $model->subject_map_id = $sub_map_id;
                $model->category_type_id = $internal_type;
                $model->category_type_id_marks = $_POST["mark$i"];
                $model->year = $year;
                $model->month = $month;
                $model->mark_type = $mark_type;
                $model->term = $term;
                $model->status_id = 0;
                $model->attendance_percentage = $_POST["attendance_percentage$i"];
                $model->attendance_remarks = $_POST["attendance_remark$i"];
                $model->created_by = $updated_by;
                $model->created_at = $updated_at;
                $model->updated_by = $updated_by;
                $model->updated_at = $updated_at;
                //Mark Entry Master Table (Internal and External)

                $markentrymaster->student_map_id = $model->student_map_id;
                $markentrymaster->subject_map_id = $model->subject_map_id;
                
                $markentrymaster->CIA = $_POST["mark$i"];
                $exam_term_check = Categorytype::find()->where(['description' => "End"])->one();
                $sem = ConfigUtilities::semCaluclation($year, $month, $batch_map_id);
                $subjectsMapping_data = SubjectsMapping::findOne($sub_map_id);
                $subjectsData = SubjectsMapping::findOne($sub_map_id);
                $categoryTypeFind = $sem == $subjectsData->semester ? 'Regular' : 'Arrear';
                $exam_type_check = Categorytype::find()->where(['description' => $categoryTypeFind])->one();
				if ($sub_marks['ESE_min'] == 0 && $sub_marks['ESE_max'] == 0) 
                {
					
                $stu_result_data = ConfigUtilities::StudentResult($stu_map_id['coe_student_mapping_id'], $sub_map_id, $_POST["mark$i"], 0,$year,$month);

                $markEntryMasterCheck = MarkEntryMaster::find()->where(['student_map_id' => $stu_map_id['coe_student_mapping_id'], 'subject_map_id' => $sub_map_id, 'year' => $year, 'month' => $month, 'mark_type' => $exam_type_check->coe_category_type_id, 'term' => $exam_term_check->coe_category_type_id])->all();
                
                $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month . "-" . $year : '';
                
                $markentrymaster->ESE = $stu_result_data['ese_marks'];
                $markentrymaster->total = $stu_result_data['total_marks'];
                $markentrymaster->result = $stu_result_data['result'];
                $markentrymaster->grade_point = $stu_result_data['grade_point'];
                $markentrymaster->grade_name = $stu_result_data['grade_name'];
                $markentrymaster->year = $model->year;
                $markentrymaster->month = $month;
                $markentrymaster->term = $term;
                $markentrymaster->mark_type = $mark_type;
                $markentrymaster->status_id = 0;
                $markentrymaster->year_of_passing = $year_of_passing;
                $markentrymaster->attempt = $stu_result_data['attempt'];
                $markentrymaster->created_by = $updated_by;
                $markentrymaster->created_at = $updated_at;
                $markentrymaster->updated_by = $updated_by;
                $markentrymaster->updated_at = $updated_at;
                
                    if (isset($_POST["mark$i"]) && isset($_POST["attendance_percentage$i"]) && isset($_POST["attendance_remark$i"])) {
                        $sem = ConfigUtilities::semCaluclation($year, $month, $batch_map_id);
                        $subjectsMapping_data = SubjectsMapping::findOne($sub_map_id);
                        $subjectsData = SubjectsMapping::findOne($sub_map_id);
                        $categoryTypeFind = $sem == $subjectsData->semester ? 'Regular' : 'Arrear';
                        $exam_type_check = Categorytype::find()->where(['description' => $categoryTypeFind])->one();
                        $exam_term_check = Categorytype::find()->where(['description' => "End"])->one();
                        if (count($mark_entry_check) > 0) {
                            $result = Yii::$app->db->createCommand("update coe_mark_entry set category_type_id_marks ='" . $_POST["mark$i"] . "',updated_by='".$updated_by."',updated_at='".$updated_at."'  where student_map_id='" . $stu_map_id['coe_student_mapping_id'] . "' and subject_map_id='" . $sub_map_id . "' and category_type_id='" . $internal_type . "' and year='" . $year . "'")->query();
                            $stu_result_data = ConfigUtilities::StudentResult($stu_map_id['coe_student_mapping_id'], $sub_map_id, $_POST["mark$i"], 0,$year,$month);
                            $markEntryMasterCheck = MarkEntryMaster::find()->where(['student_map_id' => $stu_map_id['coe_student_mapping_id'], 'subject_map_id' => $sub_map_id, 'year' => $year, 'month' => $month, 'mark_type' => $exam_type_check->coe_category_type_id, 'term' => $exam_term_check->coe_category_type_id])->all();
                            $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month . "-" . $year : '';
                            $created_at = date("Y-m-d H:i:s");
                            $updateBy = Yii::$app->user->getId();
                            if (!empty($markEntryMasterCheck)) {
                                Yii::$app->db->createCommand("update coe_mark_entry_master set CIA ='" . $_POST["mark$i"] . "',updated_by='".$updated_by."',updated_at='".$updated_at."',ESE=0,total='" . $_POST["mark$i"] . "',result='" . $stu_result_data['result'] . "',grade_point='" . $stu_result_data['grade_point'] . "',grade_name='" . $stu_result_data['grade_name'] . "',year_of_passing='" . $year_of_passing . "' where student_map_id='" . $stu_map_id['coe_student_mapping_id'] . "' and mark_type='" . $exam_type_check->coe_category_type_id . "' and term='" . $exam_term_check->coe_category_type_id . "' and subject_map_id='" . $sub_map_id . "'  and month='" . $month . "' and year='" . $year . "' ")->query();
                            } else {
                                $MarkEntryMasterModel = new MarkEntryMaster();
                                $MarkEntryMasterModel->student_map_id = $stu_map_id['coe_student_mapping_id'];
                                $MarkEntryMasterModel->subject_map_id = $sub_map_id;
                                $MarkEntryMasterModel->CIA = $_POST["mark$i"];
                                $MarkEntryMasterModel->ESE = 0;
                                $MarkEntryMasterModel->total = $_POST["mark$i"];
                                $MarkEntryMasterModel->result = $stu_result_data['result'];
                                $MarkEntryMasterModel->grade_point = $stu_result_data['grade_point'];
                                $MarkEntryMasterModel->grade_name = $stu_result_data['grade_name'];
                                $MarkEntryMasterModel->year = $year;
                                $MarkEntryMasterModel->month = $month;
                                $MarkEntryMasterModel->term = $exam_term_check->coe_category_type_id;
                                $MarkEntryMasterModel->mark_type = $exam_type_check->coe_category_type_id;
                                $MarkEntryMasterModel->year_of_passing = $year_of_passing;
                                $MarkEntryMasterModel->attempt = 0;
                                $MarkEntryMasterModel->status_id = 0;
                                $MarkEntryMasterModel->created_by = $updateBy;
                                $MarkEntryMasterModel->created_at = $created_at;
                                $MarkEntryMasterModel->updated_by = $updateBy;
                                $MarkEntryMasterModel->updated_at = $created_at;
                                $MarkEntryMasterModel->save(false);
                                unset($MarkEntryMasterModel);
                            }
                            Yii::$app->ShowFlashMessages->setMsg('Success', 'Marks updated successfuly');
                        } else if ($model->save()) {
                            $markentrymaster->save();
                            unset($markentrymaster);
                            unset($model);
                            Yii::$app->ShowFlashMessages->setMsg('Success', 'Marks inserted successfuly');
                        } else {
                            Yii::$app->ShowFlashMessages->setMsg('Error', 'Please complete the mark entry for all ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT));
                        }
                    }
                    $markentrymaster = new MarkEntryMaster();
                    $model = new MarkEntry();
                } else {
                    if (isset($_POST["mark$i"]) && isset($_POST["attendance_percentage$i"]) && isset($_POST["attendance_remark$i"])) {
                        if (count($mark_entry_check) > 0) {
                            $result = Yii::$app->db->createCommand("update coe_mark_entry set category_type_id_marks ='" . $_POST["mark$i"] . "',updated_by='".$updated_by."',updated_at='".$updated_at."'  where student_map_id='" . $stu_map_id['coe_student_mapping_id'] . "' and subject_map_id='" . $sub_map_id . "' and category_type_id='" . $internal_type . "' and year='" . $year . "'")->query();
                            Yii::$app->ShowFlashMessages->setMsg('Success', 'Marks updated successfuly');
                        } else if ($model->save()) {
                            unset($model);
                            Yii::$app->ShowFlashMessages->setMsg('Success', 'Marks inserted successfuly');
                        } else {
                            Yii::$app->ShowFlashMessages->setMsg('Error', 'Please complete the mark entry for all ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT));
                        }
                    }
                    $model = new MarkEntry();
                }
            }
            return $this->render('create', [
                'model' => $model,
            ]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Internal Mark Entry');
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }
    public function actionPrintApplicationPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['moderation_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Moderation Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }td,th{border:1px solid #999}
                    }   
                ',
            'options' => ['title' => 'Moderation Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' Moderation' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExternalmarkentry()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $sn = Yii::$app->request->post('sn');
        $batch_id = Yii::$app->request->post('bat_val');
        $batch_map_id = Yii::$app->request->post('bat_map_val');
        $section = Yii::$app->request->post('sec');
        $sem = Yii::$app->request->post('exam_semester');
        $sub = Yii::$app->request->post('sub_val');
        $model_type = Yii::$app->request->post('select_mod_type');

        if(Yii::$app->request->post())
        {
            $exam_month = $_POST['month'];
            $exam_year = $_POST['mark_year'];
        }
        $sub_max = Subjects::find(['ESE_min', 'total_minimum_pass'])->where(['coe_subjects_id' => $sub])->one();
        $sub_map_id = SubjectsMapping::find()->where(['batch_mapping_id' => $batch_map_id, 'subject_id' => $sub])->one();
        $c_paper_type = Categorytype::find()->where(['coe_category_type_id' => $sub_map_id['paper_type_id']])->one();
        $cat_cia_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'internal final%' or category_type like 'cia 1%' or category_type like 'internal 1%'")->queryScalar();
        if (stristr($c_paper_type['category_type'], "Practical")) 
        {
            if ($model_type == "With Model") {
                for ($k = 1; $k <= $sn; $k++) {
                    if (isset($_POST["mark$k"])) {
                        $reg_no = $_POST["reg$k"];
                        $stu_id = Student::find()->where(['register_number' => $reg_no, 'student_status' => 'Active'])->one();
                        $stu_map_id = StudentMapping::find()->where(['student_rel_id' => $stu_id['coe_student_id']])->one();
                        $model->student_map_id = $stu_map_id->coe_student_mapping_id;
                        $model->subject_map_id = $sub_map_id->coe_subjects_mapping_id;
                        $cat_ese_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'model 1%' or category_type like 'model1%'")->queryScalar();
                        $model->category_type_id = $cat_ese_val;
                        $model->category_type_id_marks = $_POST["mark$k"];
                        $model->year = Yii::$app->request->post('mark_year');
                        $model->month = Yii::$app->request->post('month');
                        $model->term = Yii::$app->request->post('term');
                        $model->mark_type = Yii::$app->request->post('mark_type');
                        $model->mark_out_of = $_POST['mod_1'];
                        $model->status_id = 0;
                        $model->created_at = new \yii\db\Expression('NOW()');
                        $model->created_by = Yii::$app->user->getId();
                        $model->updated_at = new \yii\db\Expression('NOW()');
                        $model->updated_by = Yii::$app->user->getId();
                        $model->save();
                        unset($model);
                        $model = new MarkEntry();
                    }
                }
                for ($k = 1; $k <= $sn; $k++) {
                    if (isset($_POST["mark1$k"])) {
                        $reg_no = $_POST["reg$k"];
                        $stu_id = Student::find()->where(['register_number' => $reg_no, 'student_status' => 'Active'])->one();
                        $stu_map_id = StudentMapping::find()->where(['student_rel_id' => $stu_id['coe_student_id']])->one();
                        $model->student_map_id = $stu_map_id->coe_student_mapping_id;
                        $model->subject_map_id = $sub_map_id->coe_subjects_mapping_id;
                        $cat_ese_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'model 2%' or category_type like 'model2%'")->queryScalar();
                        $model->category_type_id = $cat_ese_val;
                        $model->category_type_id_marks = $_POST["mark1$k"];
                        $model->year = Yii::$app->request->post('mark_year');
                        $model->month = Yii::$app->request->post('month');
                        $model->term = Yii::$app->request->post('term');
                        $model->mark_type = Yii::$app->request->post('mark_type');
                        $model->mark_out_of = $_POST['mod_2'];
                        $model->status_id = 0;
                        $model->created_at = new \yii\db\Expression('NOW()');
                        $model->created_by = Yii::$app->user->getId();
                        $model->updated_at = new \yii\db\Expression('NOW()');
                        $model->updated_by = Yii::$app->user->getId();
                        $model->save();
                        unset($model);
                        $model = new MarkEntry();
                    }
                }
            } else {
                //$model = new MarkEntry();
                $cat_ese_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'ese%' or category_type like 'external%'")->queryScalar();
                for ($k = 1; $k <= $sn; $k++) {
                    //print_r($_POST["mark$k"]);
                    if (isset($_POST["mark$k"])) {
                        $reg_no = $_POST["reg$k"];
                        $cia_mark = $_POST["cia$k"];
                        $total = $_POST["total$k"];
                        $result = $_POST["result$k"];
                        $stu_id = Student::find()->where(['register_number' => $reg_no, 'student_status' => 'Active'])->one();
                        $stu_map_id = StudentMapping::find()->where(['student_rel_id' => $stu_id['coe_student_id']])->one();
                        $model->student_map_id = $stu_map_id->coe_student_mapping_id;
                        $model->subject_map_id = $sub_map_id->coe_subjects_mapping_id;
                        $model->category_type_id = $cat_ese_val;
                        $model->category_type_id_marks = $_POST["mark$k"];
                        $model->year = Yii::$app->request->post('mark_year');
                        $model->month = Yii::$app->request->post('month');
                        $model->term = Yii::$app->request->post('term');
                        $model->mark_type = Yii::$app->request->post('mark_type');
                        $model->status_id = 0;
                        $model->created_by = Yii::$app->user->getId();
                        $model->created_at = new \yii\db\Expression('NOW()');
                        $model->updated_by = Yii::$app->user->getId() . " ";
                        $model->updated_at = new \yii\db\Expression('NOW()');
                        $model->save();
                        unset($model);
                        $model = new MarkEntry();
                    }
                }
            }
            for ($k = 1; $k <= $sn; $k++) {
                if (isset($_POST["converted_marks_$k"])) {
                    $stu_id = Student::find()->where(['register_number' => $_POST["reg$k"], 'student_status' => 'Active'])->one();
                    $stu_map_id = StudentMapping::find()->where(['student_rel_id' => $stu_id['coe_student_id']])->one();
                    $cat_model1_ese_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'model 1%' or category_type like 'model1%'")->queryScalar();
                    $model1_mark = Yii::$app->db->createCommand("select category_type_id_marks from coe_mark_entry where student_map_id='" . $stu_map_id->coe_student_mapping_id . "' and subject_map_id='" . $sub_map_id->coe_subjects_mapping_id . "' and category_type_id='" . $cat_model1_ese_val . "'")->queryScalar();
                    $cat_model2_ese_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'model 2%' or category_type like 'model2%'")->queryScalar();
                    $model2_mark = Yii::$app->db->createCommand("select category_type_id_marks from coe_mark_entry where student_map_id='" . $stu_map_id->coe_student_mapping_id . "' and subject_map_id='" . $sub_map_id->coe_subjects_mapping_id . "' and category_type_id='" . $cat_model2_ese_val . "'")->queryScalar();
                    $markentrymaster->student_map_id = $stu_map_id->coe_student_mapping_id;
                    $markentrymaster->subject_map_id = $sub_map_id->coe_subjects_mapping_id;
                    $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $sub_map_id->coe_subjects_mapping_id . '" AND student_map_id="' . $stu_map_id->coe_student_mapping_id . '" AND result not like "%pass%"')->queryScalar();
                    $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                    if ($check_attempt >= $config_attempt) {
                        $stu_result_data = ConfigUtilities::StudentResult($stu_map_id->coe_student_mapping_id, $sub_map_id->coe_subjects_mapping_id, 0, $_POST["mark$k"],$exam_year,$exam_month);
                        $markentrymaster->CIA = 0;
                    } else {
                        $stu_result_data = ConfigUtilities::StudentResult($stu_map_id->coe_student_mapping_id, $sub_map_id->coe_subjects_mapping_id, $_POST["cia$k"], $_POST["mark$k"],$exam_year,$exam_month);
                        $markentrymaster->CIA = $_POST["cia$k"];
                    }
                    $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? Yii::$app->request->post('month'). "-" . Yii::$app->request->post('mark_year') : '';

                    $markentrymaster->ESE = $stu_result_data['ese_marks'];
                    $markentrymaster->total = $stu_result_data['total_marks'];
                    $markentrymaster->result = $stu_result_data['result'];
                    $markentrymaster->grade_point = $stu_result_data['grade_point'];
                    $markentrymaster->grade_name = $stu_result_data['grade_name'];
                    $markentrymaster->attempt = $stu_result_data['attempt'];
                    $markentrymaster->year = Yii::$app->request->post('mark_year');
                    $markentrymaster->month = Yii::$app->request->post('month');
                    $markentrymaster->term = Yii::$app->request->post('term');
                    $markentrymaster->mark_type = Yii::$app->request->post('mark_type');
                    $markentrymaster->status_id = 0;
                    $markentrymaster->created_by = Yii::$app->user->getId();
                    $markentrymaster->created_at = new \yii\db\Expression('NOW()');
                    $markentrymaster->updated_by = Yii::$app->user->getId();
                    $markentrymaster->updated_at = new \yii\db\Expression('NOW()');
                    $markentrymaster->year_of_passing = $year_of_passing;
                    $markentrymaster->save();
                    unset($markentrymaster);
                    $markentrymaster = new MarkEntryMaster();
                }
            }
        }//practicals
        else {
            $cat_ese_val = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like 'ese%' or category_type like 'external%'")->queryScalar();
            for ($k = 1; $k <= $sn; $k++) {
                if (isset($_POST["mark$k"])) {
                    $reg_no = $_POST["reg$k"];
                    $cia_mark = $_POST["cia$k"];
                    $total = $_POST["total$k"];
                    $result = $_POST["result$k"];
                    $stu_id = Student::find()->where(['register_number' => $reg_no, 'student_status' => 'Active'])->one();
                    $stu_map_id = StudentMapping::find()->where(['student_rel_id' => $stu_id['coe_student_id']])->one();
                    $model->student_map_id = $stu_map_id->coe_student_mapping_id;
                    $model->subject_map_id = $sub_map_id->coe_subjects_mapping_id;
                    $model->category_type_id = $cat_ese_val;
                    $model->category_type_id_marks = $_POST["mark$k"];
                    $model->year = Yii::$app->request->post('mark_year');
                    $model->month = Yii::$app->request->post('month');
                    $model->term = Yii::$app->request->post('term');
                    $model->mark_type = Yii::$app->request->post('mark_type');
                    $model->status_id = 0;
                    $model->created_by = Yii::$app->user->getId();
                    $model->created_at = new \yii\db\Expression('NOW()');
                    $model->updated_by = Yii::$app->user->getId() . " ";
                    $model->updated_at = new \yii\db\Expression('NOW()');
                    if ($model->save()) {
                        $markentrymaster->student_map_id = $stu_map_id->coe_student_mapping_id;
                        $markentrymaster->subject_map_id = $sub_map_id->coe_subjects_mapping_id;
                        $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $sub_map_id->coe_subjects_mapping_id . '" AND student_map_id="' . $stu_map_id->coe_student_mapping_id . '" AND result not like "%pass%"')->queryScalar();
                        $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                        if ($check_attempt >= $config_attempt) {
                            $stu_result_data = ConfigUtilities::StudentResult($stu_map_id->coe_student_mapping_id, $sub_map_id->coe_subjects_mapping_id, 0, $_POST["mark$k"],$exam_year,$exam_month);
                            $markentrymaster->CIA = 0;
                        } else {
                            $stu_result_data = ConfigUtilities::StudentResult($stu_map_id->coe_student_mapping_id, $sub_map_id->coe_subjects_mapping_id, $_POST["cia$k"], $_POST["mark$k"],$exam_year,$exam_month);
                            $markentrymaster->CIA = $_POST["cia$k"];
                        }
                        $markentrymaster->ESE = $stu_result_data['ese_marks'];
                        $markentrymaster->total = $stu_result_data['total_marks'];
                        $markentrymaster->result = $stu_result_data['result'];
                        $markentrymaster->grade_point = $stu_result_data['grade_point'];
                        $markentrymaster->grade_name = $stu_result_data['grade_name'];
                        $markentrymaster->attempt = $stu_result_data['attempt'];
                        $markentrymaster->year = Yii::$app->request->post('mark_year');
                        $markentrymaster->month = Yii::$app->request->post('month');
                        $markentrymaster->term = Yii::$app->request->post('term');
                        $markentrymaster->mark_type = Yii::$app->request->post('mark_type');
                        $markentrymaster->status_id = 0;
                        $markentrymaster->created_by = Yii::$app->user->getId();
                        $markentrymaster->created_at = new \yii\db\Expression('NOW()');
                        $markentrymaster->updated_by = Yii::$app->user->getId();
                        $markentrymaster->updated_at = new \yii\db\Expression('NOW()');
                        $markentrymaster->year_of_passing = $stu_result_data['year_of_passing'];
                        $markentrymaster->save();
                        unset($markentrymaster);
                        unset($model);
                        $model = new MarkEntry();
                        $markentrymaster = new MarkEntryMaster();
                    }
                }
                $model = new MarkEntry();
                $markentrymaster = new MarkEntryMaster();
            }
        }
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to External Mark Entry');
        return $this->render('externalmarkentry', [
            'model' => $model, 'markentrymaster' => $markentrymaster,
        ]);
    }
    public function actionModeration()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        
        if (isset($_POST['mod_submit_btn'])) 
        {
            $sn = Yii::$app->request->post('sn');
            
            $year = $_POST['MarkEntry']['year'];
            $moderation_marks = $_POST['MarkEntry']['marks_out_of'];
            $month = $_POST['MarkEntry']['month'];

            $mod_category_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%Moderation%'")->queryScalar();
            $year_of_passing = $month . "-" . $year;
            $notEligible = '';
            $succesS =0;
            for ($k = 1; $k <= $sn; $k++) 
            {
                if (isset($_POST['mod' . $k])) 
                {
                    $student_map_id = $_POST['student_map_id_' . $k];
                    $subject_map_id = $_POST['subject_map_id_' . $k];
                        
                    if(!empty($student_map_id) && !empty($subject_map_id))
                    {
                        $stu_mapGet = StudentMapping::findOne($student_map_id);
                        $stuDentMark = Student::findOne($stu_mapGet->student_rel_id);
                        $coe_batch_id = CoeBatDegReg::findOne($stu_mapGet['course_batch_mapping_id']);
                        $regulation_year = Regulation::find()->distinct()->where(['regulation_year' => $coe_batch_id->regulation_year])->one();

                        $query_min_pass = new Query();
                        $minimum_pass = $query_min_pass->select('a.ESE_min,a.total_minimum_pass')
                            ->from('coe_subjects a')
                            ->join('JOIN', 'coe_subjects_mapping b', 'b.subject_id=a.coe_subjects_id')
                            ->where(['coe_subjects_mapping_id'=>$subject_map_id])->createCommand()->queryOne();
                        $grade_100 = $minimum_pass['total_minimum_pass'];
                        $grade_10 = ($minimum_pass['total_minimum_pass'] / 100) * 10;

                        $query_exam_map_id = new Query();
                       
                        $max_grade = Yii::$app->db->createCommand("select max(grade_point_to) from coe_regulation where regulation_year='" . $regulation_year->regulation_year . "' and grade_name is not null")->queryScalar();
                        $round_max_grade = round($max_grade);
                        if ($round_max_grade > 10) 
                        {
                            $query_grade = new Query();
                            $min_grade = $query_grade->select('grade_name,grade_point')
                                ->from('coe_regulation')
                                ->where(['grade_point_from' => $grade_100, 'regulation_year' => $regulation_year->regulation_year])->createCommand()->queryOne();
                        } else {
                            
                            $query_grade = new Query();
                            $min_grade = $query_grade->select('grade_name,grade_point')
                                ->from('coe_regulation')
                                ->where(['grade_point_from' => $grade_10, 'regulation_year' => $regulation_year->regulation_year])->createCommand()->queryOne();
                        }
                        $ese_mark = $_POST['ESE_' . $k];
                        $cia = $_POST['CIA_' . $k];
                        $stu_total_mark = $cia + $minimum_pass['ESE_min'];
                        $mod_category_marks = $minimum_pass['ESE_min'] - $ese_mark;

                        $getStuMode = Yii::$app->db->createCommand('SELECT sum(category_type_id_marks) FROM coe_mark_entry WHERE student_map_id="'.$student_map_id.'" and year="'.$year.'" and month="'.$month.'" and category_type_id="'.$mod_category_id.'"')->queryScalar();

                        if($stu_total_mark>=$minimum_pass['total_minimum_pass'] && $getStuMode<=$moderation_marks && (($mod_category_marks+$getStuMode)<=$moderation_marks))
                        {
                            $updated_by = Yii::$app->user->getId();
                            $updated_at = date('Y-m-d-H-i-s');
                            $mark_entry_model = new MarkEntry();
                            $mark_entry_model->student_map_id = $student_map_id;
                            $mark_entry_model->subject_map_id = $subject_map_id;
                            $mark_entry_model->category_type_id = $mod_category_id;
                            $mark_entry_model->category_type_id_marks = $mod_category_marks;
                            $mark_entry_model->year = $year;
                            $mark_entry_model->month = $month;
                            $mark_entry_model->term = $_POST['term' . $k];
                            $mark_entry_model->mark_type = $_POST['mark_type' . $k];
                            $mark_entry_model->status_id = 0;
                            $mark_entry_model->created_by = $updated_by;
                            $mark_entry_model->created_at = $updated_at;
                            $mark_entry_model->updated_by = $updated_by;
                            $mark_entry_model->updated_at = $updated_at;
                            if($mark_entry_model->save(false)) 
                            {
                                $update_stu_mark = Yii::$app->db->createCommand("update coe_mark_entry_master set ESE='" . $minimum_pass['ESE_min'] . "',total='" . $stu_total_mark . "',result='Pass',grade_point='" . $min_grade['grade_point'] . "',updated_by='".$updated_by."',updated_at='".$updated_at."',grade_name='" . $min_grade['grade_name'] . "',year_of_passing='" . $year_of_passing . "' where subject_map_id='" . $subject_map_id . "' and student_map_id='" . $student_map_id. "' and year='" . $year . "' and month='" . $month . "' and mark_type='".$_POST['mark_type' . $k]."' and term='".$_POST['term' . $k]."'")->execute();
                                $succesS++;
                            }
                        }
                        else
                        {
                            $subMarp = SubjectsMapping::findOne($subject_map_id);
                            $subjectS = Subjects::findOne($subMarp->subject_id);
                            $notEligible .=$stuDentMark->register_number."-".$subjectS->subject_code."<br />";
                        }
                    }                    
                }
            }
            if($succesS!=0)
            {
                $add_string = !empty($notEligible) ?'<b>Not Eligible For '.$notEligible."</b>":'';
                Yii::$app->ShowFlashMessages->setMsg('Success', 'Moderation Applied Successfully '.$add_string.' !!!');
                return $this->redirect(['moderation']);
            }
            else if($notEligible!='')
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', "<b>".$notEligible.'</b> Not Eligible for Moderation Where Total Minimum Pass Not Secured');
                return $this->redirect(['moderation']);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', 'Nothing Updated');
                return $this->redirect(['moderation']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Moderation Mark Entry');
            return $this->render('moderation', [
                'model' => $model,
            ]);
        }
    }
    public function actionViewmoderation()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        if (isset($_POST['view_mod'])) {
            $moderation_category_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%moderation%'")->queryScalar();
            
                /*$query = new Query();
                $query->select(['concat(i.programme_code," - ",h.degree_code) as degree_name', 'c.subject_code', 'e.register_number', 'b.semester', 'f.CIA', '`f`.`ESE`-`a`.`category_type_id_marks` as `oldESE`', 'a.category_type_id_marks as moderation', 'f.ESE as newESE', 'f.total','f.grade_name', 'f.result', 'k.category_type'])
                    ->from('coe_mark_entry a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.subject_map_id=b.coe_subjects_mapping_id')
                    ->join('JOIN', 'coe_subjects c', 'b.subject_id=c.coe_subjects_id')
                    ->join('JOIN', 'coe_student_mapping d', 'a.student_map_id=d.coe_student_mapping_id')
                    ->join('JOIN', 'coe_student e', 'd.student_rel_id=e.coe_student_id')
                    ->join('JOIN', 'coe_mark_entry_master f', 'a.subject_map_id=f.subject_map_id and a.student_map_id=f.student_map_id and a.year=f.year and a.month=f.month and a.mark_type=f.mark_type')
                    ->join('JOIN', 'coe_bat_deg_reg g', 'd.course_batch_mapping_id=g.coe_bat_deg_reg_id')
                    ->join('JOIN', 'coe_degree h', 'g.coe_degree_id=h.coe_degree_id')
                    ->join('JOIN', 'coe_programme i', 'g.coe_programme_id=i.coe_programme_id')
                    ->join('JOIN', 'coe_category_type k', 'k.coe_category_type_id =a.month')
                    ->where(['a.year' => $_POST['view_mod_mark_year'], 'a.month' => $_POST['mod_month'],  'a.category_type_id' => $moderation_category_id, 'e.student_status' => 'Active'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                    ->orderBy('register_number,category_type_id_marks');*/

                    $query = new Query();
                $query->select(['concat(i.programme_code," - ",h.degree_code) as degree_name', 'c.subject_code', 'e.register_number', 'b.semester', 'f.CIA', '`f`.`ESE`-`a`.`category_type_id_marks` as `oldESE`', 'a.category_type_id_marks as moderation', 'f.ESE as newESE', 'f.total','f.grade_name', 'f.result', 'k.category_type'])
                    ->from('coe_mark_entry a')
                    ->join('JOIN', 'coe_subjects_mapping b', 'a.subject_map_id=b.coe_subjects_mapping_id')
                    ->join('JOIN', 'coe_subjects c', 'b.subject_id=c.coe_subjects_id')
                    ->join('JOIN', 'coe_student_mapping d', 'a.student_map_id=d.coe_student_mapping_id')
                    ->join('JOIN', 'coe_student e', 'd.student_rel_id=e.coe_student_id')
                    ->join('JOIN', 'coe_mark_entry_master f', 'a.subject_map_id=f.subject_map_id and a.student_map_id=f.student_map_id and a.year=f.year and a.month=f.month and a.mark_type=f.mark_type')
                    ->join('JOIN', 'coe_bat_deg_reg g', 'd.course_batch_mapping_id=g.coe_bat_deg_reg_id')
                    ->join('JOIN', 'coe_degree h', 'g.coe_degree_id=h.coe_degree_id')
                    ->join('JOIN', 'coe_programme i', 'g.coe_programme_id=i.coe_programme_id')
                    ->join('JOIN', 'coe_category_type k', 'k.coe_category_type_id =a.month')
                    ->where(['a.year' => $_POST['view_mod_mark_year'], 'a.month' => $_POST['mod_month'],   'e.student_status' => 'Active','f.total'=>50,'f.result'=>'Fail','h.degree_type'=>'PG'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                    ->orderBy('register_number,category_type_id_marks');

                $view_moderation = $query->createCommand()->queryAll();
            
            if (count($view_moderation) > 0) {
                return $this->render('viewmoderation', [
                    'model' => $model,
                    'view_moderation' => $view_moderation,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Moderation");
                return $this->render('viewmoderation', [
                    'model' => $model,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Moderation ');
            return $this->render('viewmoderation', [
                'model' => $model,
            ]);
        }
    }
    /* Withheld Starts Here */
    public function actionWithheld()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
		
        if (Yii::$app->request->post()) 
		{
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $sn = Yii::$app->request->post('sn');
            $year = Yii::$app->request->post('mark_year');
            $month = Yii::$app->request->post('month');
            $stu_map_id = Yii::$app->request->post('withheld_stu_reg_num');
            $month_year = $month . '-' . $year;
            $updated_at = new \yii\db\Expression('NOW()');
            $updated_by = Yii::$app->user->getId();
            $change_ra = 'U';
            for ($k = 1; $k <= $sn; $k++) 
            {
                $sub_map_id = $_POST['sub_code' . $k];
                if (isset($_POST["withheld" . $k])) 
                {
                    $RESult_cha = $_POST["result" . $k]=='Absent'?'Absent':'Fail';
                    Yii::$app->db->createCommand("update coe_mark_entry_master set year_of_passing='' ,grade_name='WH',grade_point=0, updated_by='".$updated_by."',result='".$RESult_cha."',updated_at='".$updated_at."', withheld='w',withheld_remarks='".$_POST["remarks" . $k]."' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $sub_map_id . "' and year='" . $year . "' and month='" . $month . "'")->execute();
                } 
                else 
                {   
                    $ciaMArks = $grade_cia_check = $_POST["cia" . $k];
                    $ese_marks = $_POST["ese" . $k];
                    $total_marks = $ese_marks+$ciaMArks;
                    
                    $sub_map_id_mar = SubjectsMapping::findOne($sub_map_id);
                    $batchMapping = CoeBatDegReg::findOne($sub_map_id_mar->batch_mapping_id);
                    $get_sub_info = Subjects::findOne($sub_map_id_mar->subject_id);
                    $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping->regulation_year])->all();

                    foreach ($grade_details as $value) 
                      {
                          if($value['grade_point_to']!='')
                          {                 
                              $arts_college_grade = $value['grade_point'];
                              $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];

                              if($org_email=='coe@skasc.ac.in')
                              {
                                $convert_ese_marks =  $ese_marks;
                                $insert_total = $ese_marks+$grade_cia_check;
                                $change_ra = 'U';
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

                              if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                              {

                                  if($grade_cia_check<$get_sub_info['CIA_min'] || $ese_marks<$get_sub_info['ESE_min'] || $total_marks<$get_sub_info['total_minimum_pass'])
                                  {
                                    $result_calc = ['result'=>'Fail','total_marks'=>$insert_total,'grade_name'=>$change_ra,'grade_point'=>0,'year_of_passing'=>'','ese_marks'=>$ese_marks];
                                  }      
                                  else
                                  {
                                    $grade_name_prit = $value['grade_name'];
                                    $grade_point_arts = $arts_college_grade;                                
                                    $result_calc = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                                                    
                                  }
                              } // Grade Point Caluclation
                          } // Not Empty of the Grade Point 
                      }
                    
                    Yii::$app->db->createCommand("update coe_mark_entry_master set year_of_passing='" . $result_calc['year_of_passing']. "',result='".$result_calc['result']."' ,updated_by='".$updated_by."',grade_point='" . $result_calc['grade_point']. "',updated_at='".$updated_at."', withheld='',withheld_remarks='', grade_name='".$result_calc['grade_name']."' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $sub_map_id . "' and year='" . $year . "' and month='" . $month . "'")->execute();
                }
            }
            Yii::$app->ShowFlashMessages->setMsg('Success', 'Updated Successfully!!!');
            return $this->redirect(['withheld']);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Withheld Entry!!');
            return $this->render('withheld', [
                'model' => $model,
            ]);
        }
    }
    /* Withheld Ends Here */
    /* Revaluation Starts Here */
    public function actionRevaluationentry()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $model = new Revaluation();
        $student = new Student();
        $ab_locking = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REVAL_LOCKING);
        $last_exam_data = ExamTimetable::find()->orderBy('exam_date DESC')->one();
        $today=date('Y-m-d');
        if(!empty($last_exam_data))
        {
            $grace_period = date_add(date_create($last_exam_data->exam_date), date_interval_create_from_date_string($ab_locking.' days'));
            $final_date = date_format($grace_period, 'Y-m-d');
        }
        if(isset($final_date) && !empty($final_date) && $today>$final_date)
        {
             Yii::$app->ShowFlashMessages->setMsg('Error',"Revaluation application Date has been Passed");
            return $this->render('revaluationentry', [
                    'model' => $model,
                    'student'=>$student,
                ]);
        }
        if (Yii::$app->request->post()) 
        {           
            $sn = Yii::$app->request->post('sn');
           
            for ($k = 1; $k <= $sn; $k++) 
            {
                if (isset($_POST["transparency" . $k])) 
                {
                    $query = new Query();
                    $query->select(['coe_student_mapping_id'])
                        ->from('coe_student A')
                        ->join('JOIN', 'coe_student_mapping B', 'B.student_rel_id=A.coe_student_id')
                        ->where(['A.register_number' => $_POST['stu_reg_num']])
                        ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $stu_map_id = $query->createCommand()->queryScalar();
                    $check_data = Revaluation::find()->where(['student_map_id' => $stu_map_id, 'subject_map_id' => $_POST['sub_code' . $k]
                        , 'year' => $_POST['reval_entry_year'], 'month' => $_POST['reval_entry_month']])->one();
                    if (empty($check_data)) 
                    {
                        $model->student_map_id = $stu_map_id;
                        $model->subject_map_id = $_POST['sub_code' . $k];
                        $model->year = $_POST['reval_entry_year'];
                        $model->month = $_POST['reval_entry_month'];
                        $model->mark_type = $_POST['mark_type' . $k];
                        if (isset($_POST["transparency" . $k])) {
                            $transparency = "S";
                        } else {
                            $transparency = "NO";
                        }
                        $model->is_transparency = $transparency;
                        $model->created_by = Yii::$app->user->getId();
                        $model->created_at = new \yii\db\Expression('NOW()');
                        $model->updated_by = Yii::$app->user->getId();
                        $model->updated_at = new \yii\db\Expression('NOW()');
                        $model->save();
                        unset($model);
                        $model = new Revaluation();
                    }
                }
                if (isset($_POST["revaluation" . $k])) 
                {
                    $find_stu_data = Revaluation::find()->where(['student_map_id'=>$_POST['stu_map_id'.$k],'subject_map_id'=>$_POST['sub_code' . $k],'year'=>$_POST['reval_entry_year'],'month'=>$_POST['reval_entry_month'],'mark_type'=>$_POST['mark_type' . $k],'is_transparency'=>'S'])->one();
                    
                    $updated_on = date('Y-m-d-H-i-s');
                    if(!empty($find_stu_data))
                    {
                        $command1 = Yii::$app->db->createCommand('UPDATE coe_revaluation SET reval_status="YES",updated_by="'.Yii::$app->user->getId().'",updated_at="'.$updated_on.'" WHERE coe_revaluation_id="'.$find_stu_data->coe_revaluation_id.'"');
                        $command1->execute();
                    }
                }
            }
            $year = $_POST['reval_entry_year'];
            $month = $_POST['reval_entry_month'];
            $stu_reg_num = $_POST['stu_reg_num'];
            $course_batch_mapping_id = Yii::$app->db->createCommand("SELECT course_batch_mapping_id FROM coe_student_mapping as A JOIN coe_student as B ON B.coe_student_id=A.student_rel_id Where B.register_number='" . $stu_reg_num . "' and status_category_type_id NOT IN('".$det_disc_type."') ")->queryScalar();
            $semester_name = ConfigUtilities::getSemesterName($course_batch_mapping_id);
            $query = new Query();
            $query->select('a.coe_student_mapping_id,b.name,b.register_number')
                ->from('coe_student_mapping a')
                ->join('JOIN', 'coe_student b', 'a.student_rel_id=b.coe_student_id')
                ->where(['b.register_number' => $stu_reg_num])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $student_map_id = $query->createCommand()->queryOne();
            $check_trans_done = Yii::$app->db->createCommand("select * from coe_revaluation where student_map_id='" . $student_map_id['coe_student_mapping_id'] . "' and year='" . $year . "' and month='" . $month . "' and is_transparency='S' ")->queryAll();

            $check_rev_done = Yii::$app->db->createCommand("select * from coe_revaluation where student_map_id='" . $student_map_id['coe_student_mapping_id'] . "' and year='" . $year . "' and month='" . $month . "' and reval_status='YES' ")->queryAll();

            $month_name = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" . $month . "'")->queryScalar();
            $cat_paper_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'practical%'")->queryScalar();
            $cat_mod_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'moderation%'")->queryScalar();
            $cat_rev_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'revaluation%'")->queryScalar();
            $subject_result = Yii::$app->db->createCommand("select distinct subject_code as subject_code,subject_fee,subject_name,coe_subjects_id,B.semester,B.coe_subjects_mapping_id,coe_student_mapping_id,F.mark_type,F.grade_name from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry as E,coe_mark_entry_master as F where A.coe_subjects_id=B.subject_id and B.batch_mapping_id=D.course_batch_mapping_id and C.coe_student_id=D.student_rel_id and F.student_map_id=D.coe_student_mapping_id and E.student_map_id=D.coe_student_mapping_id and F.subject_map_id=B.coe_subjects_mapping_id and E.subject_map_id=B.coe_subjects_mapping_id and F.year='" . $year . "' and F.month='" . $month . "' and C.register_number='" . $stu_reg_num . "' and E.category_type_id!='" . $cat_mod_mark_type . "' and C.student_status='Active' and status_category_type_id NOT IN('".$det_disc_type."') and B.paper_type_id!='" . $cat_paper_type . "'")->queryAll();
            $table = "";
            $sn = 1;
            if (count($check_rev_done) > 0) 
            {
                $subject_total = 0;
                foreach ($check_rev_done as $check_rev) 
                {
                    $query_rev = new Query();
                    $query_rev->select('a.description')
                        ->from('coe_category_type a')
                        ->JOIN('JOIN','coe_categories b','b.coe_category_id=a.category_id')
                        ->where(['like','b.category_name','Revaluation Fees']);
                    $stu_reval_subject = $query_rev->createCommand()->queryOne();
                    $subject_total += $stu_reval_subject['description'];
                }
                require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $table .=
                    '<table border=1 class="table table-responsive table-striped" align="center" ><tbody align="center">     
                        <tr>
                            <td> 
                                <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                            </td>
                            <td colspan=10 align="center"> 
                                <center><b><font size="4px">' . $org_name . '</font></b></center>
                                <center>' . $org_address . '</center>
                                
                                <center>' . $org_tagline . '</center> 
                            </td>
                            <td>  
                                <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                            </td>
                        </tr>
                        <tr>
                            <td colspan=6 align="right"> END ' . $semester_name . ' EXAMINATIONS </td>
                            <td colspan=6 align="left"> ' . $month_name . '-' . $year . ' </td>
                            
                        </tr>
                        <tr>
                            <td colspan=12 align="center"> APPLICATION FOR REVALUATION</td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Name of the ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' </td>
                            <td colspan=8 align="left"> ' . $student_map_id['name'] . ' </td>
                            
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Register Number </td>
                            <td colspan=8 align="left"> ' . $stu_reg_num . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left">  Month / Year of Examination </td>
                            <td colspan=8 align="left"> ' . strtoupper($month_name) . '-' . $year . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Number of ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . 's Applied for Revaluation </td>
                            <td colspan=8 align="left"> ' . count($check_rev_done) . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Total Amount </td>
                            <td colspan=8 align="left"> ' . $subject_total . ' </td>
                        </tr>
                           
                        <tr>                                                                                                               
                            <th> S.NO </th> 
                            <th colspan=2 align="left"> ' . $semester_name . ' </th>
                            <th colspan=3 align="left"> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </th>
                            <th colspan=6 align="left"> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </th>';
                /*<th colspan=3 align="center"> Amount </th>*/
                $table .= '</tr>';
                foreach ($check_rev_done as $check_rev) {
                    $query_rev = new Query();
                    $query_rev->select('b.subject_code,b.subject_name,b.subject_fee,a.semester')
                        ->from('coe_subjects_mapping a')
                        ->join('JOIN', 'coe_subjects b', 'a.subject_id=b.coe_subjects_id')
                        ->where(['a.coe_subjects_mapping_id' => $check_rev['subject_map_id']]);
                    $stu_reval_subject = $query_rev->createCommand()->queryOne();
                    $table .= '<tr><td align="left">' . $sn . '</td>
                                <td colspan=2 align="left">' . $stu_reval_subject['semester'] . '</td>
                                <td colspan=3 align="left">' . $stu_reval_subject['subject_code'] . '</td>
                                <td colspan=6 align="left">' . $stu_reval_subject['subject_name'] . '</td>
                            </tr>';                    
                    $sn++;
                }
                $table .= '<tr>
                                <td height="200" colspan="12"> &nbsp; </td>
                            </tr>
                            <tr height="150">
                                <td colspan="4"> Name & Signature of the Candidate </td>
                                <td colspan="4"> Name & Signature of the Tutor </td>
                                <td colspan="4"> Head of the Department </td>
                            </tr>';
                $table .= '</tbody></table>';
                $data = ['table' => $table, 'result' => 50];
                if (isset($_SESSION['revaluation_amount'])) {
                    unset($_SESSION['revaluation_amount']);
                }
                $_SESSION['revaluation_amount'] = $table;
                $content = $_SESSION['revaluation_amount'];
                $pdf = new Pdf([
                    'mode' => Pdf::MODE_CORE,
                    'filename' => "Revaluation Total Amount.pdf",
                    'format' => Pdf::FORMAT_A4,
                    'orientation' => Pdf::ORIENT_PORTRAIT,
                    'content' => $content,
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                    'cssInline' => ' @media all{
                                table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                            }   
                        ',
                    'options' => ['title' => "Revaluation Total Amount"],
                    'methods' => [
                        'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                        'SetFooter' => ["Revaluation Total Amount " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                    ]
                ]);
                Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                $headers = Yii::$app->response->headers;
                $headers->add('Content-Type', 'application/pdf');
                return $pdf->render();
            }
       
            if (count($check_trans_done) > 0) 
            {
                $subject_total = 0;
                foreach ($check_trans_done as $check_rev) {                    
                    $query_rev = new Query();
                    $query_rev->select('a.description')
                        ->from('coe_category_type a')
                        ->JOIN('JOIN','coe_categories b','b.coe_category_id=a.category_id')
                        ->where(['like','b.category_name','Transparency Fee']);
                    $stu_reval_subject = $query_rev->createCommand()->queryOne();
                    $subject_total += $stu_reval_subject['description']; 
                }
                require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                $table .=
                    '<table border=1 class="table table-responsive table-striped" align="center" ><tbody align="center">     
                        <tr>
                            <td> 
                                <img class="img-responsive" width="80" height="80" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                            </td>
                            <td colspan=10 align="center"> 
                                <center><b><font size="4px">' . $org_name . '</font></b></center>
                                <center>' . $org_address . '</center>
                                
                                <center>' . $org_tagline . '</center> 
                            </td>
                            <td align="center">  
                                <img width="80" height="80" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                            </td>
                        </tr>
                        <tr>
                            <td colspan=6 align="right"> END ' . $semester_name . ' EXAMINATION </td>
                            <td colspan=6 align="left"> ' . strtoupper($month_name) . '-' . $year . ' </td>
                            
                        </tr>
                        <tr>
                            <td colspan=12 align="center"> APPLICATION FOR TRANSPARANCY</td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Name of the ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' </td>
                            <td colspan=8 align="left"> ' . $student_map_id['name'] . ' </td>
                            
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Register Number </td>
                            <td colspan=8 align="left"> ' . $stu_reg_num . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left">  Month / Year of Examination </td>
                            <td colspan=8 align="left"> ' . strtoupper($month_name) . '-' . $year . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Number of ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Applied for Tranparancy </td>
                            <td colspan=8 align="left"> ' . count($check_trans_done) . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> Total Amount </td>
                            <td colspan=8 align="left"> ' . $subject_total . ' </td>
                        </tr>
                           
                        <tr>                               
                            <th> S.NO </th> 
                            <th colspan=2  align="left"> ' . $semester_name . ' </th>
                            <th colspan=3  align="left"> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . 's CODE </th>
                            <th colspan=6 align="left"> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </th>';
                /*<th colspan=3 align="center"> Amount </th>*/
                $table .= '</tr>';
                foreach ($check_trans_done as $check_rev) {
                    $query_rev = new Query();
                    $query_rev->select('b.subject_code,b.subject_name,b.subject_fee,a.semester')
                        ->from('coe_subjects_mapping a')
                        ->join('JOIN', 'coe_subjects b', 'a.subject_id=b.coe_subjects_id')
                        ->where(['a.coe_subjects_mapping_id' => $check_rev['subject_map_id']]);
                    $stu_reval_subject = $query_rev->createCommand()->queryOne();
                    $table .= '<tr><td align="left">' . $sn . '</td>
                                <td colspan=2 align="left">' . $stu_reval_subject['semester'] . '</td>
                                <td colspan=3 align="left">' . $stu_reval_subject['subject_code'] . '</td>
                                <td colspan=6 align="left">' . $stu_reval_subject['subject_name'] . '</td>
                            </tr>';
                    $sn++;
                }
               
                $table .= '<tr>
                                <td height="200" colspan="12"> &nbsp; </td>
                            </tr>
                            <tr height="150">
                                <td colspan="4"> Name & Signature of the Candidate </td>
                                <td colspan="4"> Name & Signature of the Tutor </td>
                                <td colspan="4"> Head of the Department </td>
                            </tr>';
                $table .= '</tbody></table>';
                $data = ['table' => $table, 'result' => 50];
                if (isset($_SESSION['revaluation_amount'])) {
                    unset($_SESSION['revaluation_amount']);
                }
                $_SESSION['revaluation_amount'] = $table;
                $content = $_SESSION['revaluation_amount'];

                $pdf = new Pdf([
                    'mode' => Pdf::MODE_CORE,
                    'filename' => "Tranparancy Total Amount.pdf",
                    'format' => Pdf::FORMAT_A4,
                    'orientation' => Pdf::ORIENT_PORTRAIT,
                    'content' => $content,
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                    'cssInline' => ' @media all{
                                table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                            }   
                        ',
                    'options' => ['title' => "Tranparancy Total Amount"],
                    'methods' => [
                        'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                        'SetFooter' => ["Tranparancy Total Amount " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                    ]
                ]);
                Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                $headers = Yii::$app->response->headers;
                $headers->add('Content-Type', 'application/pdf');
                return $pdf->render();
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Revaluation Application Entry');
            return $this->render('revaluationentry', ['model' => $model, 'student' => $student,]);
        }
    }
    public function actionRevaluationentrysub()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $stu_reg_num = Yii::$app->request->post('stu_reg_num');

        $course_batch_mapping_id = Yii::$app->db->createCommand("SELECT course_batch_mapping_id FROM coe_student_mapping as A JOIN coe_student as B ON B.coe_student_id=A.student_rel_id Where B.register_number='" . $stu_reg_num . "' and status_category_type_id NOT IN('".$det_disc_type."') ")->queryScalar();
        if (empty($course_batch_mapping_id)) {
            return 0;
        }
        $semester_name = ConfigUtilities::getSemesterName($course_batch_mapping_id);
        $ab_locking = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REVAL_LOCKING);
        $last_exam_data = ExamTimetable::find()->where(['exam_year'=>$year,'exam_month'=>$month])->orderBy('exam_date DESC')->one();
        $today = date('Y-m-d');

        if (!empty($last_exam_data)) 
        {
            $grace_period = date_add(date_create($last_exam_data['exam_date']), date_interval_create_from_date_string($ab_locking . ' days'));
            $final_date = date_format($grace_period, 'Y-m-d');
            if(isset($final_date) && !empty($final_date) && $today>$final_date)
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Revaluation application Date has been Passed");
                return $this->redirect(['mark-entry/revaluationentry']);
            }
        }
        
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $check_trans = Yii::$app->db->createCommand("select * from coe_categories where description like '%Transparency Fee%' ")->queryOne();        
        $check_reval= Yii::$app->db->createCommand("select * from coe_categories where description like '%Revaluation Fee%' ")->queryOne();
        $stu_reg_num = Yii::$app->request->post('stu_reg_num');
        $query = new Query();
        $query->select('a.coe_student_mapping_id,b.name,b.register_number')
            ->from('coe_student_mapping a')
            ->join('JOIN', 'coe_student b', 'a.student_rel_id=b.coe_student_id')
            ->where(['b.register_number' => $stu_reg_num])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
        $student_map_id = $query->createCommand()->queryOne();

        $check_rev_done = Yii::$app->db->createCommand("select * from coe_revaluation where student_map_id='" . $student_map_id['coe_student_mapping_id'] . "' and year='" . $year . "' and month='" . $month . "' and reval_status='YES' ")->queryAll();

        $check_trans_done = Yii::$app->db->createCommand("select * from coe_revaluation where student_map_id='" . $student_map_id['coe_student_mapping_id'] . "' and year='" . $year . "' and month='" . $month . "' and is_transparency='S' ")->queryAll();

        $month_name = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='" . $month . "'")->queryScalar();

        $trans_fees = Yii::$app->db->createCommand("select description from coe_category_type where category_id='" . $check_trans['coe_category_id'] . "'")->queryScalar();

        $reval_fees = Yii::$app->db->createCommand("select description from coe_category_type where category_id='" . $check_reval['coe_category_id'] . "'")->queryScalar();

        $cat_paper_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'practical%'")->queryScalar();
        $cat_mod_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'moderation%'")->queryScalar();
        $cat_rev_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'revaluation%'")->queryScalar();

        $subject_result = Yii::$app->db->createCommand("select distinct subject_code as subject_code,subject_fee,subject_name,F.grade_name,coe_subjects_id,B.semester,C.name,B.coe_subjects_mapping_id,coe_student_mapping_id,F.mark_type from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry as E,coe_mark_entry_master as F where A.coe_subjects_id=B.subject_id and B.batch_mapping_id=D.course_batch_mapping_id and C.coe_student_id=D.student_rel_id and F.student_map_id=D.coe_student_mapping_id and E.student_map_id=D.coe_student_mapping_id and F.subject_map_id=B.coe_subjects_mapping_id and E.subject_map_id=B.coe_subjects_mapping_id and F.year='" . $year . "' and F.month='" . $month . "' and C.register_number='" . $stu_reg_num . "' and E.category_type_id!='" . $cat_mod_mark_type . "' and C.student_status='Active' and B.paper_type_id!='" . $cat_paper_type . "' and F.result NOT LIKE '%Absent%' and status_category_type_id NOT IN('".$det_disc_type."') and A.ESE_max!=0 and A.ESE_min!=0 and F.grade_name NOT IN('WH','wh','AB','ab') and F.result NOT LIKE '%Ab%' order by B.semester")->queryAll();

        $subject_trans_result = Yii::$app->db->createCommand("select distinct subject_code as subject_code,subject_fee,subject_name,coe_subjects_id,B.semester,B.coe_subjects_mapping_id,coe_student_mapping_id,F.mark_type,C.name,F.grade_name from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry as E,coe_mark_entry_master as F,coe_revaluation as G where G.student_map_id=F.student_map_id and G.subject_map_id=F.subject_map_id and A.coe_subjects_id=B.subject_id and B.batch_mapping_id=D.course_batch_mapping_id and C.coe_student_id=D.student_rel_id and F.student_map_id=D.coe_student_mapping_id and E.student_map_id=D.coe_student_mapping_id and F.subject_map_id=B.coe_subjects_mapping_id and E.subject_map_id=B.coe_subjects_mapping_id and F.year='" . $year . "' and F.month='" . $month . "' and G.month='".$month."' and G.year='".$year."' and C.register_number='" . $stu_reg_num . "' and E.category_type_id!='" . $cat_mod_mark_type . "' and C.student_status='Active' and B.paper_type_id!='" . $cat_paper_type . "' and F.result!='Absent' and status_category_type_id NOT IN('".$det_disc_type."') and F.grade_name NOT IN('WH','wh','AB','ab') and F.result NOT LIKE '%Ab%' and A.ESE_max!=0 and A.ESE_min!=0 order by B.semester")->queryAll();
       
        $table = "";
        $sn = 1;
        $a=1;
        if (count($check_rev_done) > 0) {
            $subject_total = 0;
            $reval_fees = Yii::$app->db->createCommand('SELECT description FROM coe_category_type where category_type like "%Revaluation Fees%"')->queryScalar();
            foreach ($check_rev_done as $check_rev) 
            {
                $subject_total += $reval_fees;
            }
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table .=
                '<table border=1 class="table table-responsive table-striped" align="center" ><tbody align="center">     
                        <tr>
                            <td  align="center"> 
                                <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                            </td>
                            <td colspan=10 align="center"> 
                                <center><b><font size="4px">' . strtoupper($org_name) . '</font></b></center>
                                <center>' . strtoupper($org_address) . '</center>
                                
                                <center>' . strtoupper($org_tagline) . '</center> 
                            </td>
                            <td  align="center">  
                                <img width="100" height="100" class="img-responsive" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                            </td>
                        </tr>
                        <tr>
                            <td colspan=12 align="center"> END ' . strtoupper($semester_name) . '  EXAMINATION  ' . strtoupper($month_name) . '-' . $year . ' </td>
                            
                        </tr>
                        <tr>
                            <td colspan=12 align="center"> '.strtoupper("Application for Revaluation").'</td>
                        </tr>
                       
                        <tr>
                            <td colspan=4 align="left"> '.strtoupper("Register Number ").'</td>
                            <td colspan=8 align="left"> ' . $stu_reg_num . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left">  '.strtoupper("Month / Year of Examination").' </td>
                            <td colspan=8 align="left"> ' . strtoupper($month_name) . '-' . $year . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> '.strtoupper("Number of ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) ."S Applied for Revaluation ") .' </td>
                            <td colspan=8 align="left"> ' . count($check_rev_done) . ' </td>
                        </tr>
                        <tr>
                            <td colspan=4 align="left"> TOTAL AMOUNT </td>
                            <td colspan=8 align="left"> ' . $subject_total . ' </td>
                        </tr>
                           
                        <tr>                                                                                                               
                            <th> S.NO </th> 
                            <th colspan=2 align="left"> ' . strtoupper($semester_name) . ' </th>
                            <th colspan=3 align="left"> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </th>
                            <th colspan=6 align="left"> ' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </th>';
            /*<th colspan=3 align="center"> Amount </th>*/
            $table .= '</tr>';
            foreach ($check_rev_done as $check_rev) {
                $query_rev = new Query();
                $query_rev->select('b.subject_code,b.subject_name,b.subject_fee,a.semester')
                    ->from('coe_subjects_mapping a')
                    ->join('JOIN', 'coe_subjects b', 'a.subject_id=b.coe_subjects_id')
                    ->where(['a.coe_subjects_mapping_id' => $check_rev['subject_map_id']]);
                $stu_reval_subject = $query_rev->createCommand()->queryOne();
                $table .= '<tr>
                                <td align="left">' . $sn . '</td>
                                <td colspan=2 align="left">' . $stu_reval_subject['semester'] . '</td>
                                <td colspan=3 align="left">' . $stu_reval_subject['subject_code'] . '</td>
                                <td colspan=6 align="left">' . $stu_reval_subject['subject_name'] . '</td>
                            </tr>';
                
                $sn++;
            }
            
            $table .= '
                     <tr>
                        <td height="200" colspan="12"> &nbsp; </td>
                     </tr>
                     <tr height="150">
                        <td colspan="4"> Name & Signature of the Candidate </td>
                        
                        <td colspan="4"> Name & Signature of the Tutor </td>
                        <td colspan="4"> Head of the Department </td>
                    </tr>';
            $table .= '</tbody></table>';
            $data = ['table' => $table, 'result' => 50];
            if (isset($_SESSION['revaluation_amount'])) {
                unset($_SESSION['revaluation_amount']);
            }
            $_SESSION['revaluation_amount'] = $table;
            return json_encode($data);
        } else if (count($check_trans_done) > 0) {
            $table .=
                '<table border=1 class="table table-striped" align="right" border=1>
                           <thead id="t_head">                                     
                                <th> S.NO </th> 
                                <th> ' . $semester_name . ' </th>
                                <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </th>
                                <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </th> 
                                <th> Transparency </th>
                                <th> Revaluation </th>';
            $table .= '</thead><tbody>';
            
            foreach ($subject_trans_result as $subject_result1) 
            {
                $table .="<tr>" .
                    "<td><input type='hidden' id=sn_" . $sn . " name='sn' value=" . $sn . ">" . $sn . "</td> " .
                    "<td><input type='hidden' name=sem" . $sn . " value='" . $subject_result1['semester'] . "'>" . $subject_result1['semester'] . "</td>" .
                    "<td><input type='hidden' name=sub_code" . $sn . " value='" . $subject_result1['coe_subjects_mapping_id'] . "'>" . $subject_result1['subject_code'] . "</td>" .
                    "<td><input type='hidden' name=sub_name" . $sn . " value='" . $subject_result1['coe_subjects_id'] . "'>" . $subject_result1['subject_name'] . "</td>" .
                    "<input type='hidden' class='reval_fee' id=reval_fee_" . $sn . " name=sub_fee" . $sn . " value='" . $reval_fees . "'>" .
                    "<input type='hidden' name=stu_map_id" . $sn . " value='" . $subject_result1['coe_student_mapping_id'] . "'>" .
                    "<input type='hidden' name=mark_type" . $sn . " value='" . $subject_result1['mark_type'] . "'>";
                $table .= "<td align='center'><input type='checkbox' class='transparency' onchange='revaluationentry_check(this.id)' name=transparency" . $sn . " id=transparency_" . $sn . " value='YES' checked disabled ></td>";

                $table .= "<td align='center'><input type='checkbox' class='revaluation_column' onchange='revaluationentry_checked(this.id)' name=revaluation" . $sn . " id=revaluation_" . $sn . " value='YES'></td>";
                
                $table .= "</tr>";
                $sn++;
            }
            $table .= "<tr><td colspan='6' align='right'><b>Total</b></td><td><b><center><input type='text' id='total' readonly size=4px></center></b></td></tr>";
            
            $table .= '</tbody></table>';
            $data = ['table' => $table, 'result' => 51];
            
                return json_encode($data);
            
        }
        else if (count($subject_result) > 0) {
            $table .=
                '<table border=1 class="table table-striped" align="right" border=1>     
                           <thead id="t_head">                                                                                                               
                                <th> S.NO </th> 
                                <th> ' . $semester_name . ' </th>
                                <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </th>
                                <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </th> 
                                <th> Tranparancy </th>';
            $table .= '</thead><tbody>';
            foreach ($subject_result as $subject_result1) {
               
                $table .=
                    "<tr>" .
                    "<td><input type='hidden' id=sn_" . $sn . " name='sn' value=" . $sn . ">" . $sn . "</td> " .
                    "<td><input type='hidden' name=sem" . $sn . " value='" . $subject_result1['semester'] . "'>" . $subject_result1['semester'] . "</td>" .
                    "<td><input type='hidden' name=sub_code" . $sn . " value='" . $subject_result1['coe_subjects_mapping_id'] . "'>" . $subject_result1['subject_code'] . "</td>" .
                    "<td><input type='hidden' name=sub_name" . $sn . " value='" . $subject_result1['coe_subjects_id'] . "'>" . $subject_result1['subject_name'] . "</td>" .
                    "<input type='hidden' class='fee' id=sub_fee_" . $sn . " name=sub_fee" . $sn . " value='" . $trans_fees . "'>" .
                    "<input type='hidden' name=stu_map_id" . $sn . " value='" . $subject_result1['coe_student_mapping_id'] . "'>" .
                    "<input type='hidden' name=mark_type" . $sn . " value='" . $subject_result1['mark_type'] . "'>";
               
                $table .= "<td align='center'><input type='checkbox' class='transparency' onchange='revaluationentry_check(this.id)' name=transparency" . $sn . " id=transparency_" . $sn . " value='YES'></td>";
                
                $table .= "</tr>";
                $sn++;
            }
            $table .= "<tr><td colspan='5' align='right'><b>Total</b></td><td><b><center><input type='text' id='total' readonly size=4px></center></b></td></tr>";
            
            $table .= '</tbody></table>';
            $data = ['table' => $table, 'result' => 51];

            return json_encode($data);
            
        } else {
            return 0;
        }
        /*}
        else
        {
            return 1;
        }*/
    }
    public function actionRevaluationAmountPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        
        $content = $_SESSION['revaluation_amount'];
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "Revaluation Application.pdf",
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => "Revaluation Application"],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ["Revaluation Application " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelRevaluationamount()
    {
        
        $content = $_SESSION['revaluation_amount'];
            
        $fileName = "Revaluation Data " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionViewrevaluation()
    {
        $model = new Revaluation();
        $markentry = new MarkEntry();
        if (isset($_POST['view_reval_btn'])) 
        {
            $join_in_query='where';
            $select_column = ',A.subject_code,A.subject_name ';
           
            if(isset($_POST['Revaluation']['is_transparency'][0]) && $_POST['Revaluation']['is_transparency'][0]=='yes')
            {
                $join_in_query = ' JOIN coe_dummy_number as F WHERE F.student_map_id=E.student_map_id and F.subject_map_id=E.subject_map_id and F.year="'.$_POST['mark_year'].'" and F.month="'.$_POST['month'].'" and ';
                $select_column .= ',F.dummy_number ';
            }
            $revaluation = Yii::$app->db->createCommand("select C.register_number,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and G.coe_programme_id='".$_POST['bat_map_val']."' and H.coe_programme_id='".$_POST['bat_map_val']."' and E.reval_status='YES' group by C.register_number,A.subject_code order by C.register_number")->queryAll();
            
            if (count($revaluation) > 0) {
                return $this->render('viewrevaluation', [
                    'model' => $model,
                    'markentry' => $markentry,
                    'revaluation' => $revaluation,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Revaluation");
                return $this->render('viewrevaluation', [
                    'model' => $model,
                    'markentry' => $markentry,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Revaluation');
            return $this->render('viewrevaluation', [
                'model' => $model,
                'markentry' => $markentry,
            ]);
        }
    }

    public function actionRevaluationViewPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['revaluation_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Revaluation.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => 'Revaluation Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Revaluation Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelRevaluationview()
    {
        
            $content = $_SESSION['revaluation_print'];
           
        $fileName = "Revaluation Data " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionRevaluationmarkentry()
    {
        $model = new Revaluation();
        $dummynumber = new DummyNumbers();
        $mark = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $cat_reval_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'revaluation%'")->queryScalar();
            $batch_id = DummyNumbers::find()->where(['dummy_number'=>$_POST['dummy_num']])->One();
            $stu_map_id = $batch_id['student_map_id'];

                $cia = $_POST["cia"];                
                $pre_ese = $_POST["oldese"];
                $reval_ese = $_POST["newese100"];
                //Mark Entry
                
                if (isset($_POST["newese100"]) && $_POST["newese100"] != "" && !empty($_POST["newese100"])) {
                    $reval_ese100 = $_POST["newese100"];
                } else {
                    Yii::$app->ShowFlashMessages->setMsg('Error', 'Please enter revaluation ESE mark');
                    return $this->render('revaluationmarkentry', [
                        'model' => $model,
                        'dummynumber' => $dummynumber,
                    ]);
                }
                $year = Yii::$app->request->post('reval_entry_year');
                $month = Yii::$app->request->post('reval_entry_month');
                $term = Yii::$app->request->post('term');
                $total = $_POST["newtotal"];
                $result = $_POST["newresult"];
                $updated_by = Yii::$app->user->getId();
                $updated_at = new \yii\db\Expression('NOW()');
                $mark->student_map_id = $stu_map_id;
                $mark->subject_map_id = $_POST["sub_code"];
                $mark->category_type_id = $cat_reval_id;
                $mark->category_type_id_marks = $reval_ese;   
                $mark->year = Yii::$app->request->post('reval_entry_year');
                $mark->month = Yii::$app->request->post('reval_entry_month');
                $mark->term = Yii::$app->request->post('term');
                $mark->mark_type = Yii::$app->request->post('marktype');
                $mark->status_id = 0;        
                $mark->created_by = $updated_by;
                $mark->created_at = $updated_at;
                $mark->updated_by = $updated_by;
                $mark->updated_at = $updated_at;

                $get_stu_rev = Revaluation::find()->where(['year'=>$year,'month'=>$month,'student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST['sub_code'],'reval_status'=>'YES'])->one();

                $stu_mark_data = Yii::$app->db->createCommand("select * from coe_mark_entry_master where student_map_id='".$stu_map_id."' AND subject_map_id='".$_POST['sub_code']."' and year='".$year."' and month='".$month."' and mark_type='".$get_stu_rev['mark_type']."' ")->queryOne();

                $get_sub_info = Yii::$app->db->createCommand('SELECT * FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$_POST['sub_code'].'"  ')->queryOne();

                    $ese_marks = round($reval_ese*$get_sub_info['ESE_max']/100);
                    $status_check = $ese_marks<=$get_sub_info['ESE_max'] ? 'YES' : 'NO'; 
                    $total_marks = $ese_marks+$stu_mark_data['CIA'];
                    $grade_cia_check = $stu_mark_data['CIA'];
                    $batchMapping = CoeBatDegReg::findOne($get_sub_info['batch_mapping_id']);
                    $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping['regulation_year']])->all();

                    $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                    
                    $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $_POST['sub_code'] . '" AND student_map_id="' . $stu_map_id. '" AND result not like "%pass%" ')->queryScalar();

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
                      else if ($check_attempt > $config_attempt) {
                          $ese_marks =  $reval_ese100;
                          $total_marks = $reval_ese100;
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
                                    if(!empty($_POST['reval_entry_month']) && !empty($_POST['reval_entry_year']))
                                    {
                                        $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                    }
                                    else
                                    {
                                        $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                    }
                                    
                                  }
                              } // Grade Point Caluclation
                          } // Not Empty of the Grade Point                               
                      }
                
                if ($check_attempt > $config_attempt && $org_email!='coe@skasc.ac.in') {
                          $grade_cia_check =  0;
                      }

                $year_of_passing = ($stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS") ? $_POST['reval_entry_month'] . "-" . $_POST['reval_entry_year']: '';

                $check_exists = MarkEntry::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST["sub_code"],'category_type_id'=>$cat_reval_id,'year'=>Yii::$app->request->post('reval_entry_year'),'month'=>Yii::$app->request->post('reval_entry_month'),'term'=>Yii::$app->request->post('term'),'mark_type'=>Yii::$app->request->post('marktype')])->all();

                if (empty($check_exists) && $mark->save(false) && $reval_ese > $pre_ese) 
                {
                    Yii::$app->db->createCommand("update coe_mark_entry_master set CIA='".$grade_cia_check."',ESE='" . $stu_result_data['ese_marks'] . "',total='" . $stu_result_data['total_marks'] . "',updated_by='".$updated_by."',updated_at='".$updated_at."',result='" . $stu_result_data['result'] . "',grade_point='" . $stu_result_data['grade_point'] . "',grade_name='" . $stu_result_data['grade_name']. "',year_of_passing='" . $year_of_passing . "' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $_POST['sub_code'] . "' and year='" . $_POST['reval_entry_year'] . "' and month='" . $_POST['reval_entry_month'] . "' and mark_type='".Yii::$app->request->post('marktype')."' and term='".Yii::$app->request->post('term')."' ")->execute();
                    Yii::$app->ShowFlashMessages->setMsg('Success', 'Revaluation Details Updated Successfully!!');
                        return $this->render('revaluationmarkentry', [
                            'model' => $model,
                            'dummynumber' => $dummynumber,
                    ]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error', 'Nothing has been changed');
                        return $this->render('revaluationmarkentry', [
                            'model' => $model,
                            'dummynumber' => $dummynumber,
                    ]);
                }
              
        }
        else
        {
             Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Revaluation Mark Entry');
                return $this->render('revaluationmarkentry', [
                    'model' => $model,
                    'dummynumber' => $dummynumber,
                ]);
        }
       
    }
    public function actionRevaluationmarkentryview()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $dummy_number = Yii::$app->request->post('dummy_number');
        $cat_mod_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'moderation%'")->queryScalar();
        $cat_rev_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'revaluation%'")->queryScalar();
        $cat_ese_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%ESE'")->queryScalar(); 
        $cat_dummy_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%ESE(Dummy)'")->queryScalar();
        
        $stu_mark = Yii::$app->db->createCommand("select A.subject_code,A.subject_name,E.cia,E.grade_point,E.grade_name,C.student_map_id,B.coe_subjects_mapping_id,A.ESE_max,A.ESE_min,A.total_minimum_pass,A.coe_subjects_id,E.term,E.mark_type from coe_subjects as A,coe_subjects_mapping as B,coe_dummy_number as C,coe_revaluation as D,coe_mark_entry_master as E where A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=C.subject_map_id and C.student_map_id=D.student_map_id and C.subject_map_id=D.subject_map_id and D.subject_map_id=E.subject_map_id and D.student_map_id=E.student_map_id and E.year='" . $year . "' and E.month='" . $month . "' and D.reval_status='YES' and D.is_transparency='S' and C.dummy_number='" . $dummy_number . "'")->queryOne();
        $previous_ese_100 = Yii::$app->db->createCommand("select * from coe_mark_entry where student_map_id='" . $stu_mark['student_map_id'] . "' and category_type_id IN ('" . $cat_ese_mark_type . "','".$cat_dummy_mark_type."') and subject_map_id='" . $stu_mark['coe_subjects_mapping_id'] . "'  and year='".$year."' and month='".$month."' ")->queryOne();

        $pre_ese = round(($previous_ese_100['category_type_id_marks'] / 100) * $stu_mark['ESE_max']);
        $revaluation_ese_100 = Yii::$app->db->createCommand("select * from coe_mark_entry where student_map_id='" . $stu_mark['student_map_id'] . "' and category_type_id='" . $cat_rev_mark_type . "' and subject_map_id='" . $stu_mark['coe_subjects_mapping_id'] . "'  and year='".$year."' and month='".$month."' ")->queryOne();
        $pre_total = $stu_mark['cia'] + $pre_ese;
        if ($pre_ese >= $stu_mark['ESE_min'] && $pre_total >= $stu_mark['total_minimum_pass']) {
            $pre_result = "Pass";
        } else {
            $pre_result = "Fail";
        }
        $reval_ese = round(($revaluation_ese_100['category_type_id_marks'] / 100) * $stu_mark['ESE_max']);
        $reval_total = $stu_mark['cia'] + $reval_ese;
        if ($reval_ese >= $stu_mark['ESE_min'] && $reval_total >= $stu_mark['total_minimum_pass']) {
            $reval_result = "Pass";
        } else {
            $reval_result = "Fail";
        }
        if ($pre_ese >= $reval_ese) {
            $total = $pre_total;
            $result = $pre_result;
        } else {
            $total = $reval_total;
            $result = $reval_result;
        }
        $check_rev_done = Yii::$app->db->createCommand("select * from coe_mark_entry where student_map_id='" . $stu_mark['student_map_id'] . "' and subject_map_id='" . $stu_mark['coe_subjects_mapping_id'] . "' and year='" . $year . "' and month='" . $month . "' and category_type_id='" . $cat_rev_mark_type . "' ")->queryOne();
        $table = '';
        $sn = 1;
        $checkAccess = ConfigUtilities::HasSuperAccess(Yii::$app->user->getId());
        if (!empty($stu_mark)) {
            $table .= '<table id="checkAllFeat" class="table table-striped" align="right" border=1>     
                       <thead id="t_head">                                                                                                               
                        <th> S.NO </th> 
                        <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </th>
                        <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </th>  
                        <th> CIA </th>
                        <th> Previous ESE </th>
                        <th> Previous Total </th>
                        <th> Previous Result </th>';
            $table .= '   <th> Reval ESE </br> out of 100</th>
                        <th> Reval ESE </th>
                        <th> Reval Total </th>
                        <th> Reval Result </th>
                        <th> Grade </th>
                        </thead><tbody>';
            $table .= "<tr>" .
                "<td><input type='hidden' id=sn name='sn' value=" . $sn . ">" . $sn . "</td> " .
                "<td><input type='hidden' id='sub_map_id_val' name='sub_code' value='" . $stu_mark['coe_subjects_mapping_id'] . "' >" . $stu_mark['subject_code'] . "</td>" .
                "<td><input type='hidden' name='sub_name' value='" . $stu_mark['coe_subjects_id'] . "'>" . $stu_mark['subject_name'] . "</td>" .
                "<td><input type='hidden' id=cia name='cia' value='" . $stu_mark['cia'] . "'>" . $stu_mark['cia'] . "</td>" .
                "<td><input type='hidden' id=oldese name='oldese' value='" . $previous_ese_100['category_type_id_marks'] . "'>" . $pre_ese . "</td>" .
                "<td><input type='hidden' id=oldtotal name='oldtotal' value='" . $pre_total . "' size='2px'>" . $pre_total . "</td>" .
                "<td><input type='hidden' id=oldresult name='oldresult' value='" . $pre_result . "'>" . $pre_result . "</td>";

            if (!empty($check_rev_done)) 
            {    
                if($checkAccess=='Yes')
                {
                    $table .=
                    "<td><input type='text' class='newese100' id=newese100 name=newese100 size='3px' onchange='reval_mark_ese(this.id)' title='Enter Numbers only' pattern='\d*' value='" . $revaluation_ese_100['category_type_id_marks'] . "'></td>" .
                    "<td><input type='text' id=newese name=newese readonly size='3px' value='" . $reval_ese . "'></td>" .
                    "<td><input type='text' id=newtotal name=newtotal readonly size='3px' value='" . $total . "'></td>" .
                    "<td><input type='text' id=newresult name=newresult readonly size='3px' value='" . $result . "'></td>".
                    "<td><input type='text' id=newgrade name='newgrade' size='8px' value=".$stu_mark['grade_name']." readonly></td>";
                }
                else{
                    $table .=
                    "<td><input type='text' id=newese100 name=newese100 size='3px' readonly onchange='revaluation_ese(this.id)' value='" . $revaluation_ese_100['category_type_id_marks'] . "'></td>" .
                    "<td><input type='text' id=newese name=newese readonly size='3px' value='" . $reval_ese . "'></td>" .
                    "<td><input type='text' id=newtotal name=newtotal readonly size='3px' value='" . $total . "'></td>" .
                    "<td><input type='text' id=newresult name=newresult readonly size='3px' value='" . $result . "'></td>".
                    "<td><input type='text' id=newgrade name='newgrade' size='8px' value=".$stu_mark['grade_name']." readonly></td>";
                }            
                
            } else {
               
                $table .=
                    "<td><input type='text' class='newese100' id=newese100 name=newese100 size='3px'  onchange='reval_mark_ese(this.id)' title='Enter Numbers only' pattern='\d*'></td>" .
                    "<td><input type='text' id=newese name='newese' size='3px' readonly></td>" .
                    "<td><input type='text' id=newtotal name='newtotal' size='3px' readonly></td>" .
                    "<td><input type='text' id=newresult name='newresult' size='3px' readonly></td>" .
                    "<td><input type='text' id=newgrade name='newgrade' size='8px' readonly></td>";
            }
            $table .= "  <input type='hidden' id=esemin name='esemin' value=" . $stu_mark['ESE_min'] . ">
                        <input type='hidden' id=esemax name='esemax' value=" . $stu_mark['ESE_max'] . ">
                        <input type='hidden' id=totalmin name='totalmin' value=" . $stu_mark['total_minimum_pass'] . ">
                        <input type='hidden' id=term name='term' value=" . $stu_mark['term'] . ">
                        <input type='hidden' id=marktype name='marktype' value=" . $stu_mark['mark_type'] . ">";
            $table .= "</tr>";
            $table .= "</tbody></table>";
            $stu_mark_status = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_mark_entry_master as C  where B.coe_student_mapping_id='" . $stu_mark['student_map_id'] . "' and B.student_rel_id=A.coe_student_id and B.coe_student_mapping_id=C.student_map_id and C.student_map_id='" . $stu_mark['student_map_id'] . "' and C.year='" . $year . "' and C.month='" . $month . "' and A.student_status='Active' and status_category_type_id NOT IN('".$det_disc_type."') ")->queryOne();
            if ($stu_mark_status['status_id'] == 1) 
            {
                return "status";
            } else {
                return $table;
            }
        } else {
            return 0;
        }
    }
    //check external mark entered or not
    public function actionRevaluationmarkentrygrade()
    {
        $stu_map_id = DummyNumbers::find()->where(['dummy_number'=>$_POST['dummy_number']])->one();
        $sub_map_id = Yii::$app->request->post('sub_map_id');
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $cia = Yii::$app->request->post('cia');
        $newese = Yii::$app->request->post('newese');
        $oldese = Yii::$app->request->post('oldese');
        
        $result_calc = ConfigUtilities::StudentResult($stu_map_id['student_map_id'],$sub_map_id,$cia,$newese,$year,$month);
        $result_old_calc = ConfigUtilities::StudentResult($stu_map_id['student_map_id'],$sub_map_id,$cia,$oldese,$year,$month);

        if ($result_old_calc['grade_name'] <= $result_calc['grade_name']) {
            return "No Change";
        } else {
            return "Change";
        }
    }
    public function actionRevaluationstumarks()
    {
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $stu_reg_num = Yii::$app->request->post('stu_map_id'); 
        $mark_out_of = Yii::$app->request->post('mark_out_of'); 
        $out_of = $mark_out_of==1?100:'Maximum';
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $stu_map_id = Yii::$app->db->createCommand("select coe_student_mapping_id from coe_student_mapping as A JOIN coe_student as B ON A.student_rel_id=B.coe_student_id where B.register_number='".$stu_reg_num."' and status_category_type_id NOT IN('".$det_disc_type."') ")->queryScalar();

        $cat_mod_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%moderation%'")->queryScalar();
        $cat_rev_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%revaluation%'")->queryScalar();
        $cat_ese_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like 'ese%'")->queryScalar();

        $cat_ese_dum_mark_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%ESE(Dummy)%'")->queryScalar();

        $mod_mark_entry_id = Yii::$app->db->createCommand("select subject_map_id from coe_mark_entry where student_map_id='" . $stu_map_id . "' and category_type_id='" . $cat_mod_mark_type . "'")->queryAll();
        $mod_mark_entry_id_concate = '';
        foreach ($mod_mark_entry_id as $mod_mark_entry_id1) {
            $mod_mark_entry_id_concate .= "'" . $mod_mark_entry_id1['subject_map_id'] . "',";
        }
        $mod_mark_entry_id_concate = trim($mod_mark_entry_id_concate, ",");
        $append_query = isset($mod_mark_entry_id_concate) && !empty($mod_mark_entry_id_concate) ? "and C.subject_map_id not in(" . $mod_mark_entry_id_concate . ") group by C.subject_map_id " : "  group by C.subject_map_id ";

        $stu_mark_id = Yii::$app->db->createCommand("select A.coe_subjects_id,A.subject_code,A.subject_name,C.subject_map_id,A.ESE_min,A.ESE_max,A.total_minimum_pass,C.CIA,C.ESE,C.total,C.result,C.student_map_id,C.grade_point,C.grade_name FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_mark_entry_master as C ON C.subject_map_id=B.coe_subjects_mapping_id JOIN coe_student_mapping as D ON D.coe_student_mapping_id=C.student_map_id and D.course_batch_mapping_id=B.batch_mapping_id JOIN coe_revaluation as E ON E.student_map_id=C.student_map_id and E.subject_map_id=C.subject_map_id where E.student_map_id='" . $stu_map_id . "' and E.reval_status='YES' and status_category_type_id NOT IN('".$det_disc_type."') and E.year='" . $year . "' and E.month='" . $month . "'" . $append_query)->queryAll();
       
        $table = '';
        $sn = 1;
       
            if (count($stu_mark_id) > 0) 
            {
                $table .= '<table id="checkAllFeat" class="table table-striped" align="right" border=1>     
                           <thead id="t_head">                                                                                                               
                            <th> S.NO </th> 
                            <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </th>
                            <th> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </th>  
                            <th> CIA </th>
                            <th> Previous ESE </th>
                            <th> Previous Total </th>
                            <th> Previous Result </th>';
                $table .= ' <th> Reval ESE </br> out of '.$out_of.'</th>
                            <th> Reval ESE </th>
                            <th> Reval Total </th>
                            <th> Reval Result </th>
                            </thead><tbody>';
                
                $checkAccess = ConfigUtilities::HasSuperAccess(Yii::$app->user->getId());
                foreach ($stu_mark_id as $stu_mark_id1) 
                {
                    $previous_ese_100 = Yii::$app->db->createCommand("select * from coe_mark_entry where student_map_id='" . $stu_mark_id1['student_map_id'] . "' and category_type_id IN('" . $cat_ese_mark_type . "','" . $cat_ese_dum_mark_type . "') and subject_map_id='" . $stu_mark_id1['subject_map_id'] . "' and year='".$year."' and month='".$month."'")->queryOne();
                    $pre_ese =$previous_ese_100['category_type_id_marks'];

                     $check_rev_done = Yii::$app->db->createCommand("select * from coe_mark_entry where student_map_id='" . $stu_mark_id1['student_map_id'] . "' and subject_map_id='" . $stu_mark_id1['subject_map_id'] . "' and year='" . $year . "' and month='" . $month . "' and category_type_id='" . $cat_rev_mark_type . "' ")->queryOne();

                    $checkGetDum = Yii::$app->db->createCommand("select * from coe_dummy_number where student_map_id='" . $stu_mark_id1['student_map_id'] . "' and subject_map_id='" . $stu_mark_id1['subject_map_id'] . "' and year='" . $year . "' and month='" . $month . "' ")->queryOne();
                    $print_dum = !empty($checkGetDum)?$checkGetDum['dummy_number']:'NO DATA';
                    $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                    
                      $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $stu_mark_id1['subject_map_id'] . '" AND student_map_id="' . $stu_mark_id1['student_map_id']. '" AND result not like "%pass%" ')->queryScalar();
                      $get_sub_info = Yii::$app->db->createCommand('SELECT * FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$stu_mark_id1['subject_map_id'].'"  ')->queryOne();
                    require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                      $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];
                      $ese_marks = round($pre_ese*$get_sub_info['ESE_max']/100);
                        $status_check = $ese_marks<=$get_sub_info['ESE_max'] ? 'YES' : 'NO'; 
                        $total_marks = $ese_marks+$stu_mark_id1['CIA'];
                        $grade_cia_check = $stu_mark_id1['CIA'];

                      $arts_college_grade = 'NO';
                      if($org_email=='coe@skasc.ac.in')
                      {
                        $ese_marks =  $pre_ese;
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
                      else if ($check_attempt > $config_attempt) {
                          $ese_marks =  $pre_ese;
                          $total_marks = $pre_ese;
                      }

                      if ($check_attempt > $config_attempt && $org_email!='coe@skasc.ac.in') {
                          $grade_cia_check =  0;
                      }
                      else
                      {
                        $grade_cia_check = $stu_mark_id1['CIA'];
                      }

                      $pre_total = $grade_cia_check + $ese_marks;
                    
                    if ($ese_marks >= $stu_mark_id1['ESE_min'] && $pre_total >= $stu_mark_id1['total_minimum_pass']) {
                        $pre_result = "Pass";
                    } else {
                        $pre_result = "Fail";
                    }

                    $table .= "<tr>" .
                        "<td><input type='hidden' id=sn_" . $sn . " name='sn' value=" . $sn . ">" . $sn . "</td> " .
                        "<td><input type='hidden' name=sub_code" . $sn . " value='" . $stu_mark_id1['subject_map_id'] . "'>" . $stu_mark_id1['subject_code'] . "</td>" .
                        "<td><input type='hidden' name=sub_name" . $sn . " value='" . $stu_mark_id1['coe_subjects_id'] . "'>" . $stu_mark_id1['subject_name'] . "</td>" .
                        "<td><input type='hidden' id=cia_" . $sn . " name=cia" . $sn . " value='" . $grade_cia_check . "'>" . $grade_cia_check . "</td>" .
                        "<td><input type='hidden' id=oldese_" . $sn . " name=oldese" . $sn . " value='" . $ese_marks . "'>" . $ese_marks . "</td>" .
                        "<td><input type='hidden' id=oldtotal_" . $sn . " name=oldtotal" . $sn . " value='" . $pre_total . "' size='2px'>" . $pre_total . "</td>" .
                        "<td><input type='hidden' id=oldresult_" . $sn . " name=oldresult" . $sn . " value='" . $pre_result . "'>" . $pre_result . "</td>";
                    $change_func = $mark_out_of==1?'revaluation_esereg(this.id)':'revaluation_eseregMax(this.id)';

                    $table .= "<input type='hidden' id=esemin_" . $sn . " value='" . $stu_mark_id1['ESE_min'] . "'>";
                        $table .= "<input type='hidden' id=esemax_" . $sn . " value='" . $stu_mark_id1['ESE_max'] . "'>";
                        $table .= "<input type='hidden' id=totalmin_" . $sn . " value='" . $stu_mark_id1['total_minimum_pass'] . "'>";

                        $table .= "<input type='hidden' name=esemin" . $sn . " value='" . $stu_mark_id1['ESE_min'] . "'>";
                        $table .= "<input type='hidden' name=esemax" . $sn . " value='" . $stu_mark_id1['ESE_max'] . "'>";
                        $table .= "<input type='hidden' name=totalmin" . $sn . " value='" . $stu_mark_id1['total_minimum_pass'] . "'>";
                    if (count($check_rev_done) > 0 && !empty($check_rev_done)) 
                    {
                        $check_rev_master_done = Yii::$app->db->createCommand("select * from coe_mark_entry_master where student_map_id='" . $stu_mark_id1['student_map_id'] . "' and subject_map_id='" . $stu_mark_id1['subject_map_id'] . "' and year='" . $year . "' and month='" . $month . "' and mark_type='" . $check_rev_done['mark_type'] . "' and term='".$check_rev_done['term']."' ")->queryOne();
                        $stu_res_dat = ConfigUtilities::StudentResult($stu_mark_id1['student_map_id'],$stu_mark_id1['subject_map_id'],$stu_mark_id1['CIA'] ,$check_rev_done['category_type_id_marks'],$year,$month );
                        if($checkAccess=='Yes')
                        {
                            $table .=
                            "<td><input type='text' required id=newese100_" . $sn . " name=newese100" . $sn . " size='3px'  onkeypress='numbersOnly(event); autocomplete='off' allowEntr(event,this.id);'  onchange='".$change_func."' value='" . $check_rev_done['category_type_id_marks'] . "'></td>" .
                            "<td><input type='text' required id=newese_" . $sn . " name=newese" . $sn . " readonly size='3px' value='" . $stu_res_dat['ese_marks'] . "' ></td>" .
                            "<td><input type='text' required id=newtotal_" . $sn . " name=newtotal" . $sn . " readonly size='3px' value='" . $stu_res_dat['total_marks'] . "' ></td>" .
                            "<td><input type='text' required id=newresult_" . $sn . " name=newresult" . $sn . " readonly size='3px' value='" . $stu_res_dat['result'] . "' ></td>";
                        }
                        else
                        {
                            $table .=
                            "<td><input type='text' required id=newese100_" . $sn . " name=newese100" . $sn . " size='3px'  readonly  value='" . $check_rev_done['category_type_id_marks'] . "'></td>" .
                            "<td><input type='text' required id=newese_" . $sn . " name=newese" . $sn . " readonly size='3px' value='" . $stu_res_dat['ese_marks'] . "' ></td>" .
                            "<td><input type='text' required id=newtotal_" . $sn . " name=newtotal" . $sn . " readonly size='3px' value='" . $stu_res_dat['total_marks'] . "' ></td>" .
                            "<td><input type='text' required id=newresult_" . $sn . " name=newresult" . $sn . " readonly size='3px' value='" . $stu_res_dat['result'] . "' ></td>";
                        }
                        
                       
                    } else {
                        
                        $table .=
                            "<td><input required type='text' id=newese100_" . $sn . " name=newese100" . $sn . " size='3px' onkeypress='numbersOnly(event); autocomplete='off' allowEntr(event,this.id);'  onchange='".$change_func."' ></td>" .
                            "<td><input type='text' id=newese_" . $sn . " autocomplete='off' name=newese" . $sn . " readonly size='3px' ></td>" .
                            "<td><input type='text' id=newtotal_" . $sn . " autocomplete='off' name=newtotal" . $sn . " readonly size='3px' ></td>" .
                            "<td><input type='text' id=newresult_" . $sn . " autocomplete='off' name=newresult" . $sn . " readonly size='3px' ></td>";
                    }
                    $table .= "</tr>";
                    $sn++;
                } // Foreach Ends Here
                $table .= "</tbody></table>";
                return $table;
            } 
            else 
            {
                return 0;
            }
        //check external mark entered or not
    }
    public function actionRevaluation()
    {
        $model = new MarkEntry();
        $student = new Student();
        $markentrymaster = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

            $sn = Yii::$app->request->post('sn');
            $stu_reg_num = Yii::$app->request->post('stu_reg_num');
            $stu_map_id_det = Yii::$app->db->createCommand("select * from coe_student_mapping as A JOIN coe_student as B ON A.student_rel_id=B.coe_student_id where B.register_number='".$stu_reg_num."' and status_category_type_id NOT IN('".$det_disc_type."') ")->queryOne(); 
            $stu_map_id = $stu_map_id_det['coe_student_mapping_id'];
            $batch_id = $stu_map_id_det['course_batch_mapping_id'];
            
            $cat_reval_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%revaluation%'")->queryScalar();

            for ($k = 1; $k <= $sn; $k++) 
            {
                $cia = $_POST['cia' . $k];
                if(isset($_POST['esemin' . $k]))
                {
                    $year = $_POST['reval_entry_year'];
                    $month = $_POST['reval_entry_month'];   
                    $get_stu_rev = Revaluation::find()->where(['year'=>$year,'month'=>$month,'student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST['sub_code' . $k],'reval_status'=>'YES'])->one();

                    $ese_min = $_POST['esemin' . $k];
                    $pre_ese = $_POST['oldese' . $k];
                    $reval_ese100 = $_POST['newese100' . $k];
                    $reval_ese = $_POST['newese' . $k];
                    $total = $_POST['newtotal' . $k];
                    $result = $_POST['newresult' . $k];
                    $stu_mark_data = Yii::$app->db->createCommand("select * from coe_mark_entry_master where student_map_id='".$stu_map_id."' AND subject_map_id='".$_POST['sub_code' . $k]."' and year='".$year."' and month='".$month."' and mark_type='".$get_stu_rev['mark_type']."' ")->queryOne();                    
                    $updated_by = Yii::$app->user->getId();
                    $updated_at = new \yii\db\Expression('NOW()');
                    $model->student_map_id = $stu_map_id;
                    $model->subject_map_id = $_POST['sub_code' . $k];
                    $model->category_type_id = $cat_reval_id;    
                    $model->category_type_id_marks = $reval_ese100;
                    $model->year = $year;
                    $model->month = $month;
                    $model->term = $stu_mark_data['term'];
                    $model->mark_type = $stu_mark_data['mark_type'];
                    $model->status_id = 0;
                    $model->created_by = $updated_by;
                    $model->created_at = $updated_at;
                    $model->updated_by = $updated_by;
                    $model->updated_at = $updated_at;

                    $get_sub_info = Yii::$app->db->createCommand('SELECT * FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$_POST['sub_code' . $k].'"  ')->queryOne();

                    $ese_marks = round($reval_ese100*$get_sub_info['ESE_max']/100);
                    $status_check = $ese_marks<=$get_sub_info['ESE_max'] ? 'YES' : 'NO'; 
                    $total_marks = $ese_marks+$stu_mark_data['CIA'];
                    $grade_cia_check = $stu_mark_data['CIA'];
                    $batchMapping = CoeBatDegReg::findOne($get_sub_info['batch_mapping_id']);
                    $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping['regulation_year']])->all();

                        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                      $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];
                      $arts_college_grade = 'NO';

                      $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                    
                      $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $_POST['sub_code' . $k] . '" AND student_map_id="' . $stu_map_id. '" AND result not like "%pass%"')->queryScalar();

                      if($org_email=='coe@skasc.ac.in')
                      {
                        $ese_marks = $convert_ese_marks =  $reval_ese100;
                        $insert_total = $reval_ese100+$grade_cia_check;
                        if($final_sub_total<100)
                        {
                          $total_marks = round(round((($insert_total/$final_sub_total)*10),1)*10);
                        }
                        else
                        {

                          $total_marks = $reval_ese100+$grade_cia_check;
                        }
                        $arts_college_grade = round(($insert_total/$final_sub_total)*10,1);

                      }
                      else if ($check_attempt > $config_attempt) {
                          $ese_marks =  $reval_ese100;
                          $total_marks = $reval_ese100;
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
                                    if(!empty($_POST['reval_entry_month']) && !empty($_POST['reval_entry_year']))
                                    {
                                        $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                    }
                                    else
                                    {
                                        $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                    }
                                    
                                  }
                              } // Grade Point Caluclation
                          } // Not Empty of the Grade Point                               
                      }
                     if ($check_attempt > $config_attempt && $org_email!='coe@skasc.ac.in') {
                          $grade_cia_check =  0;
                      }         
                    $year_of_passing = ($stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS") ? $_POST['reval_entry_month'] . "-" . $_POST['reval_entry_year']: '';

                    $checkMasrksAvail = MarkEntry::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST['sub_code' . $k],'year'=>$year,'month'=>$month,'category_type_id'=>$cat_reval_id,'mark_type'=>$stu_mark_data['mark_type']])->one();
                    
                    if( empty($checkMasrksAvail) && $status_check=='YES' && $model->save(false) && $reval_ese100>$pre_ese)
                    {
                        Yii::$app->db->createCommand("update coe_mark_entry_master set CIA='".$grade_cia_check."', ESE='" . $stu_result_data['ese_marks'] . "',total='" . $stu_result_data['total_marks'] . "',updated_by='".$updated_by."',updated_at='".$updated_at."',result='" . $stu_result_data['result'] . "',grade_point='" . $stu_result_data['grade_point'] . "',grade_name='" . $stu_result_data['grade_name'] . "',year_of_passing='" . $year_of_passing . "' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $_POST['sub_code' . $k] . "' and year='" . $year . "' and month='" . $month . "' and term='".$stu_mark_data['term']."' and mark_type='".$stu_mark_data['mark_type']."'")->execute();                        
                    }
                    else if(!empty($checkMasrksAvail))
                    {

                        $mark_entry_update = Yii::$app->db->createCommand("update coe_mark_entry set category_type_id_marks='".$reval_ese100."' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $_POST['sub_code' . $k] . "' and year='" . $year . "' and month='" . $month . "' and term='".$stu_mark_data['term']."' and mark_type='".$stu_mark_data['mark_type']."' and category_type_id ='".$cat_reval_id."' ")->execute();   

                        Yii::$app->db->createCommand("update coe_mark_entry_master set CIA='".$grade_cia_check."', ESE='" . $stu_result_data['ese_marks'] . "',total='" . $stu_result_data['total_marks'] . "',updated_by='".$updated_by."',updated_at='".$updated_at."',result='" . $stu_result_data['result'] . "',grade_point='" . $stu_result_data['grade_point'] . "',grade_name='" . $stu_result_data['grade_name'] . "',year_of_passing='" . $year_of_passing . "' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $_POST['sub_code' . $k] . "' and year='" . $year . "' and month='" . $month . "' and term='".$stu_mark_data['term']."' and mark_type='".$stu_mark_data['mark_type']."'")->execute();   
                    }
                    else{
                        Yii::$app->ShowFlashMessages->setMsg('ERROR', 'NO DATA FOUND'); 
                    }    
                    unset($model);
                    $model = new MarkEntry();                    
                    
                }                
                
            } // For Loop Ends Here
            Yii::$app->ShowFlashMessages->setMsg('Success', 'Revaluation Marks Updated Successfully!!'); 
            return $this->render('revaluation', [
                'model' => $model,
                'student'=>$student,
            ]);
        }
        else
        {
            return $this->render('revaluation', [
                'model' => $model,
                'student'=>$student,
            ]);
        }
}

public function actionRevaluationSubjectEntry()
    {
        $model = new MarkEntry();
        $student = new Student();
        $markentrymaster = new MarkEntryMaster();
        if(Yii::$app->request->post())
        {
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $sn = Yii::$app->request->post('sn');
            $cat_reval_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where description like '%revaluation%'")->queryScalar();

            for ($k = 1; $k <= $sn; $k++) 
            {
                if(isset($_POST['reg_nu' . $k]) && isset($_POST['esemin' . $k]))
                {
                    $stu_reg_num = $_POST['reg_nu' . $k];
                    $stu_map_id_det = StudentMapping::findOne($stu_reg_num); 
                    $stu_map_id = $stu_map_id_det['coe_student_mapping_id'];
                    $batch_id = $stu_map_id_det['course_batch_mapping_id'];
                    $cia = $_POST['cia' . $k];
                    $year = $_POST['reval_entry_year'];
                    $month = $_POST['reval_entry_month'];   
                    $get_stu_rev = Revaluation::find()->where(['year'=>$year,'month'=>$month,'student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST['sub_code' . $k],'reval_status'=>'YES'])->one();

                    $ese_min = $_POST['esemin' . $k];
                    $pre_ese = $_POST['oldese' . $k];
                    $reval_ese100 = $_POST['newese100' . $k];
                    $reval_ese = $_POST['newese' . $k];
                    $total = $_POST['newtotal' . $k];
                    $result = $_POST['newresult' . $k];
                   
                    if(!empty($get_stu_rev))
                    {
                        $checkAlreadyUpdated = MarkEntryMaster::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST['sub_code' . $k],'year'=>$year,'month'=>$month,'mark_type'=>$get_stu_rev['mark_type']])->one();
                        
                        $updated_by = Yii::$app->user->getId();
                        $updated_at = new \yii\db\Expression('NOW()');
                        $model->student_map_id = $stu_map_id;
                        $model->subject_map_id = $_POST['sub_code' . $k];
                        $model->category_type_id = $cat_reval_id;    
                        $model->category_type_id_marks = $reval_ese100;
                        $model->year = $year;
                        $model->month = $month;
                        $model->term = $checkAlreadyUpdated['term'];
                        $model->mark_type = $checkAlreadyUpdated['mark_type'];
                        $model->status_id = 0;
                        $model->created_by = $updated_by;
                        $model->created_at = $updated_at;
                        $model->updated_by = $updated_by;
                        $model->updated_at = $updated_at;

                        $get_sub_info = Yii::$app->db->createCommand('SELECT * FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$_POST['sub_code' . $k].'"  ')->queryOne();
                        $ese_marks = round($reval_ese100*$get_sub_info['ESE_max']/100);
                        $status_check = $ese_marks<=$get_sub_info['ESE_max'] ? 'YES' : 'NO'; 
                        $total_marks = $ese_marks+$checkAlreadyUpdated['CIA'];
                        $grade_cia_check = $checkAlreadyUpdated['CIA'];
                        $batchMapping = CoeBatDegReg::findOne($get_sub_info['batch_mapping_id']);
                        $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping['regulation_year']])->all();

                        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                      $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];
                      $arts_college_grade = 'NO';

                      $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
                    
                      $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $_POST['sub_code' . $k] . '" AND student_map_id="' . $stu_map_id. '" AND result not like "%pass%" ')->queryScalar();
                      
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
                      else if ($check_attempt > $config_attempt) {
                          $ese_marks =  $reval_ese100;
                          $total_marks = $reval_ese100;
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
                                    if(!empty($_POST['reval_entry_month']) && !empty($_POST['reval_entry_year']))
                                    {
                                        $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                    }
                                    else
                                    {
                                        $stu_result_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                    }
                                    
                                  }
                              } // Grade Point Caluclation
                          } // Not Empty of the Grade Point                               
                      }

                      if ($check_attempt > $config_attempt && $org_email!='coe@skasc.ac.in') {
                          $grade_cia_check =  0;
                      }
                             
                        $year_of_passing = ($stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS") ? $_POST['reval_entry_month'] . "-" . $_POST['reval_entry_year']: '';
                        $checkMasrksAvail = MarkEntry::find()->where(['student_map_id'=>$stu_map_id,'subject_map_id'=>$_POST['sub_code' . $k],'year'=>$year,'month'=>$month,'category_type_id'=>$cat_reval_id,'mark_type'=>$checkAlreadyUpdated['mark_type']])->one();
                        if( empty($checkMasrksAvail) && $status_check=='YES' && $model->save(false) && $reval_ese100>$pre_ese)
                        {
                            Yii::$app->db->createCommand("update coe_mark_entry_master set CIA='".$grade_cia_check."', ESE='" . $stu_result_data['ese_marks'] . "',total='" . $stu_result_data['total_marks'] . "',updated_by='".$updated_by."',updated_at='".$updated_at."',result='" . $stu_result_data['result'] . "',grade_point='" . $stu_result_data['grade_point'] . "',grade_name='" . $stu_result_data['grade_name'] . "',year_of_passing='" . $year_of_passing . "' where student_map_id='" . $stu_map_id . "' and subject_map_id='" . $_POST['sub_code' . $k] . "' and year='" . $year . "' and month='" . $month . "' and term='".$checkAlreadyUpdated['term']."' and mark_type='".$checkAlreadyUpdated['mark_type']."'")->execute();                        
                        }    
                        unset($model);
                        $model = new MarkEntry(); 
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('ERROR', 'NO DATA FOUND'); 
                    }
                }                
                
            } // For Loop Ends Here
            Yii::$app->ShowFlashMessages->setMsg('Success', 'Revaluation Marks Updated Successfully!!'); 
            return $this->render('revaluation-subject-entry', [
                'model' => $model,
                'student'=>$student,
            ]);
        }
        else
        {
            return $this->render('revaluation-subject-entry', [
                'model' => $model,
                'student'=>$student,
            ]);
        }
}
    /* Revaluation Ends Here */
    /**
     * Updates an existing MarkEntry model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_mark_entry_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }
    /**
     * Deletes an existing MarkEntry model.
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
     * Finds the MarkEntry model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return MarkEntry the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = MarkEntry::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    // Result Publish Starts here
    public function actionResultPublish()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $model = new MarkEntry();
        if ($model->load(Yii::$app->request->post())) {
            if (empty($_POST['bat_map_val']) && empty($model->month) && empty($model->year) && empty($_POST['exam_semester'])) {
                Yii::$app->ShowFlashMessages->setMsg('Error', 'Select the required Information');
                return $this->redirect(['mark-entry/result-publish']);
            }
            $sem = ConfigUtilities::semCaluclation($model->year, $model->month, $_POST['bat_map_val']);
            $exam_type = $sem == $_POST['exam_semester'] ? 'Regular' : 'Arrear';
            $cat_id = Categorytype::find()->where(['category_type' => $exam_type])->one();
            $section = $_POST['sec'] != 'All' ? $_POST['sec'] : '';
            $query = new Query();
            $query->select(['q.subject_map_id', 'q.student_map_id', 'a.name', 'a.register_number', 'concat(h.degree_code,".",h.degree_name) as degree_name', 'g.programme_code', 'k.semester', 'l.subject_name', 'l.subject_code', 'l.CIA_max', 'l.ESE_max', 'l.total_minimum_pass', 'l.end_semester_exam_value_mark', 'm.batch_name', 'q.year', 'q.CIA', 'q.ESE', 'q.total','q.withheld' ,'q.grade_point', 'q.grade_name', 'n.description as month', 'q.result'])
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
            $query_man->select(['F.student_map_id','H.subject_code',  'A.name', 'A.register_number', 'H.subject_name','H.ESE_max', 'H.CIA_max', 'H.end_semester_exam_value_mark', 'K.description as month',  'F.year','F.subject_map_id','F.student_map_id', 'F.ESE','F.CIA', 'F.total','F.result','F.semester' ,'F.withheld','F.grade_name', 'F.grade_point', 'concat(degree_code,".",degree_name) as degree_name','E.programme_code','total_minimum_pass','batch_name'])
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
            $countQuery->Where(['F.year' => $model->year, 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $model->month,'G.semester'=>$_POST['exam_semester']])
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
            $query_man_count->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.year' => $model->year, 'F.month' => $model->month, 'A.student_status' => 'Active','F.semester'=>$_POST['exam_semester'],'H.semester'=>$_POST['exam_semester']])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            if ($section != "") {
                $query_man_count->andWhere(['B.section_name' => $_POST['sec']]);
            }
            $query_man_count->groupBy('F.student_map_id,F.subject_map_id');
            $countOfManSubjects = $query_man_count->createCommand()->queryAll();
            if(!empty($countOfManSubjects))
            {
                $countOfSubjects = array_merge($countOfSubjects,$countOfManSubjects);
            }
            if (empty($send_result)) {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['mark-entry/result-publish']);
            }
            return $this->render('result-publish', [
                'model' => $model,
                'send_result' => $send_result,
                'countOfSubjects' => $countOfSubjects,
                'subjectsInfo' => $subjectsInfo,
                'sem'=>$sem,
            ]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Result Publish');
            return $this->render('result-publish', [
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
                            font-size: 12px !important; 
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
    // Result Publish Ends Here
    public function actionNoticeboard()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reval_status_entry = Categorytype::find()->where(['category_type'=>'Revaluation'])->orWhere(['description'=>'Revaluation'])->one();
       
        if (isset($_POST['noticeboardbutton'])) 
        {
            $reval_status = isset($_POST['MarkEntry']['mark_type'][0])?$_POST['MarkEntry']['mark_type'][0]:'';
            $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['bat_val'] . "'")->queryScalar();
            $degree_name = Yii::$app->db->createCommand("select concat(degree_name,' -  ',programme_name) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['bat_map_val'] . "'")->queryScalar();
            $year = $_POST['year'];
            $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();

            $withheld_list = Yii::$app->db->createCommand('SELECT DISTINCT student_map_id as id FROM coe_mark_entry_master WHERE month="'.$_POST['month'].'" AND year="'.$_POST['year'].'" AND withheld="w" ')->queryAll();
            $withheld = [];
            foreach ($withheld_list as $key => $value) {
                $withheld[$value['id']]=$value['id'];
            }
            $whereCondition = [                        
                        'b.course_batch_mapping_id' => $_POST['bat_map_val'], 'c.year' => $year, 'c.month' => $_POST['month']
                    ];
            $query_n = new Query();
            $query_n->select('a.register_number,d.semester,e.subject_code,e.subject_name,c.CIA,c.ESE,e.CIA_max,e.ESE_max,c.total,c.result,c.grade_name,c.withheld,c.grade_point,c.subject_map_id,c.student_map_id,c.mark_type,paper_no')
                ->from('coe_student a')
                ->join('JOIN', 'coe_student_mapping b', 'a.coe_student_id=b.student_rel_id')
                ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_student_mapping_id=c.student_map_id')
                ->join('JOIN', 'coe_subjects_mapping d', 'c.subject_map_id=d.coe_subjects_mapping_id and d.batch_mapping_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_subjects e', 'd.subject_id=e.coe_subjects_id');
                if(!empty($reval_status) && $reval_status=='yes')
                {
                    $query_n->join('JOIN', 'coe_mark_entry f', 'f.student_map_id=c.student_map_id and f.subject_map_id=c.subject_map_id and f.year=c.year and f.mark_type=c.mark_type and f.term=c.term and f.month=c.month and f.student_map_id=b.coe_student_mapping_id and f.subject_map_id=d.coe_subjects_mapping_id');
                    $whereCondition_12 = [                        
                            'f.category_type_id'=>$reval_status_entry['coe_category_type_id'],'f.year'=>$_POST['year'],'f.month' => $_POST['month'],
                        ];
                    $whereCondition = array_merge($whereCondition,$whereCondition_12);
                }
                $query_n->where($whereCondition)
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['NOT IN', 'c.student_map_id', $withheld]);
                $query_n->groupBy('a.register_number,e.subject_code')
                ->orderBy('a.register_number,d.semester');
            $noticeboard_copy = $query_n->createCommand()->queryAll();

            if($org_email!='coe@skcet.ac.in' && $reval_status=='')
            {
                $query_man = new  Query();
                $query_man->select('A.register_number,F.semester,H.subject_code,H.subject_name,F.CIA,F.ESE,H.CIA_max,H.ESE_max, F.total,F.result, F.grade_name,F.withheld, F.grade_point,F.subject_map_id,F.student_map_id,mark_type,paper_no')
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
                $query_man->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.year' => $year, 'F.month' => $_POST['month'], 'A.student_status' => 'Active','G.is_additional'=>'NO'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['NOT IN', 'student_map_id', $withheld]);
                $query_man->groupBy('register_number,subject_code')
                    ->orderBy('register_number,semester');
                   
                $mandatory_statement = $query_man->createCommand()->queryAll();
                if(!empty($mandatory_statement))
                {
                    $noticeboard_copy = array_merge($noticeboard_copy,$mandatory_statement);
                }
            }            
            array_multisort(array_column($noticeboard_copy, 'semester'),  SORT_ASC, $noticeboard_copy);
            array_multisort(array_column($noticeboard_copy, 'paper_no'),  SORT_ASC, $noticeboard_copy);
            array_multisort(array_column($noticeboard_copy, 'register_number'),  SORT_ASC, $noticeboard_copy);            
            if (count($noticeboard_copy) > 0) 
            {
                return $this->render('noticeboard', [
                    'model' => $model,
                    'noticeboard_copy' => $noticeboard_copy,
                    'year' => $year, 'month' => $month, 'batch_name' => $batch_name, 'degree_name' => $degree_name,'reval_status'=>$reval_status,

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
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => 'Noticeboard Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Noticeboard Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelNoticeboardcopy()
    {
        
           //s $content = $_SESSION['noticeboard_print'];
        $content = $_SESSION['moderation_print'];
            
        $fileName = "Noticeboard Data " . date('Y-m-d-H-i-s') . '.xls';
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
    public function actionProgrammeresultanalysis()
    {
        $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $_POST['batch'] . "'")->queryScalar();
        $section = !empty($_POST['section']) && $_POST['section']!='All'?' and section_name="'.$_POST['section'].'" ':'';
        $degree_name = Yii::$app->db->createCommand("select concat(degree_code,'  ',programme_code) as degree_name from coe_degree a,coe_programme b,coe_bat_deg_reg c where a.coe_degree_id=c.coe_degree_id and b.coe_programme_id=c.coe_programme_id and c.coe_bat_deg_reg_id='" . $_POST['batch_map_id'] . "'")->queryScalar();
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();
        $mark_type_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='27' ")->queryScalar();
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
        $query_cr->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id,description,coe_subjects_id,batch_mapping_id')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->where(['b.batch_mapping_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => 27]);
        $subject_list = $query_cr->createCommand()->queryAll();
        if (count($subject_list) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $course_result_table = '';
            $course_result_table .= '<table border=1  width="100%" class="table table-striped table-responsive table-hover table-bordered"  align="center">';
            $course_result_table .= '<tr>
                                        <td colspan=22 align="center"> 
                                            <center><b><font size="6px">' . $org_name . '</font></b></center>
                                            <center>' . $org_address . '</center>
                                            <center>' . $org_tagline . '</center> 
                                        </td>
                                       
                                    </tr>';
            $course_result_table .= '<tr><td colspan=22 align="center"><b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . ' Result Analysis</b> - ' . strtoupper($month . ' ' . $_POST['year']) . '</td></tr>';
            $course_result_table .= '<tr><td colspan=22 align="center"><b>Batch - ' . strtoupper($batch_name . ' ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . '</b> - ' . $degree_name) . '</td></tr>';
            $colspan = 22 - (count($grade_name) + 11); // is the number of columns
            $course_result_table .= '<tr>                                                                                                                                
                            <th> S. NO </th> 
                            <th>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' CODE </th>  
                            <th colspan="' . $colspan . '" >' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)) . ' NAME </th>
                            <th>ENR</th>
                            <th>APP</th>
                            <th>ABS</th>
                            <th>WIT</th>
                            <th>PA</th>
                            <th>FA</th>
                            <th>PA%</th>
                            <th>FA%</th>
                            <th>MEAN</th>
                            <th>SSD</th>
                            <th>VAR</th>
                            <th>SD</th>';
            $old_grade = '';
            foreach ($grade_name as $grade) {
                if (!empty($grade['grade_name']) && $old_grade != $grade['grade_name']) {
                    $old_grade = $grade['grade_name'];
                    $course_result_table .= '<th>' . strtoupper($grade['grade_name']) . '</th>';
                }
            }
            $course_result_table .= '</tr>';
            
            $sn = 1;
            foreach ($subject_list as $subject) {
                $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . $subject['subject_name'] . '</td>';
                $query_enroll = new Query();
                $query_enroll->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id and c.batch_mapping_id=a.course_batch_mapping_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month']]);
                if(!empty($_POST['section']) && $_POST['section']!='All')
                {
                     $query_enroll->andWhere(['=', 'section_name', $_POST['section']]);
                }
                $query_enroll->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_enrol = $query_enroll->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_enrol . '</td>';
                $query_appeared = new Query();
                $query_appeared->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month']])
                    ->andWhere(['NOT LIKE', 'b.result', 'Absent']);

                if(!empty($_POST['section']) && $_POST['section']!='All')
                {
                     $query_appeared->andWhere(['=', 'section_name', $_POST['section']]);
                }

                    $query_appeared->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_subjects_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent']);
                if(!empty($_POST['section']) && $_POST['section']!='All')
                {
                     $query_absent->andWhere(['=', 'section_name', $_POST['section']]);
                }

                $query_absent->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_absent = $query_absent->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_absent . '</td>';
                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w']);
                if(!empty($_POST['section']) && $_POST['section']!='All')
                {
                     $query_withheld->andWhere(['=', 'section_name', $_POST['section']]);
                }
                    $query_withheld->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month']])
                    ->andWhere(['NOT', ['year_of_passing' => '']]);
                if(!empty($_POST['section']) && $_POST['section']!='All')
                {
                     $query_pass->andWhere(['=', 'section_name', $_POST['section']]);
                }

                    $query_pass->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_pass = $query_pass->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                $query_fail = new Query();
                $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' and result  not like '%Absent%' $section AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) ";
                $student_fail = Yii::$app->db->createCommand($select_query)->queryScalar();
                $student_appeared = $student_appeared==0 ? 1: $student_appeared;
                $student_enrol = $student_enrol==0 ? 1: $student_enrol;
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

                $query_mean = new Query();
                $query_mean = "SELECT sum(b.total) FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' AND status_category_type_id NOT IN('".$det_disc_type."') ";
                $student_mean = Yii::$app->db->createCommand($query_mean)->queryScalar();

                $MEAN = round(($student_mean/$student_appeared),2);

                $query_SSD = new Query();
                $query_SSD = "SELECT power(sum($MEAN-b.total),2) as ssd FROM coe_student_mapping a JOIN coe_mark_entry_master as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_subjects_mapping c ON c.coe_subjects_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_subjects_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' AND status_category_type_id NOT IN('".$det_disc_type."') group by student_map_id";
                $student_ssd = Yii::$app->db->createCommand($query_SSD)->queryAll();
                $ssd_calc = 0;
                foreach ($student_ssd as $key => $ssd_val) 
                {
                    $ssd_calc +=$ssd_val['ssd'];
                }
                $variance = round(($ssd_calc/$student_appeared),2);

                $course_result_table .= '<td align="center">' . $MEAN . '</td>';
                $course_result_table .= '<td align="center">' . $ssd_calc . '</td>';
                $course_result_table .= '<td align="center">' .  $variance. '</td>';
                $course_result_table .= '<td align="center">' . round(sqrt($variance),2) . '</td>';


                foreach ($grade_name as $grade) {
                    $query_75 = new Query();
                    $query_75->select('count(student_map_id)')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mark_entry_master b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_subjects_mapping c', 'c.coe_subjects_mapping_id=b.subject_map_id')
                        ->where(['subject_map_id' => $subject['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'grade_name' => $grade['grade_name']]);
                    if(!empty($_POST['section']) && $_POST['section']!='All')
                    {
                         $query_75->andWhere(['=', 'section_name', $_POST['section']]);
                    }
                        $query_75->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_75 = $query_75->createCommand()->queryScalar();
                    $course_result_table .= '<td>' . $student_75 . '</td>';
                }
                $course_result_table .= '</tr>';
                $sn++;
            }

            $man_query_cr = new Query();
            $man_query_cr->select('subject_code,sub_cat_code,sub_cat_name,a.semester,subject_name, coe_mandatory_subcat_subjects_id , description, coe_mandatory_subjects_id, batch_mapping_id')
                ->from('coe_mandatory_subjects a')
                ->join('JOIN', 'coe_mandatory_subcat_subjects b', 'b.man_subject_id=a.coe_mandatory_subjects_id and b.batch_map_id=a.batch_mapping_id')
                ->join('JOIN', 'coe_mandatory_stu_marks c', 'c.subject_map_id=b.coe_mandatory_subcat_subjects_id')
                ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
                ->where(['b.batch_map_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => 27])->groupBy('subject_code');
            $man_subject_list = $man_query_cr->createCommand()->queryAll();
            
            if(!empty($man_subject_list))
            {
                foreach ($man_subject_list as $subject) {
                    $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code']."-".$subject['sub_cat_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . $subject['subject_name']."-".$subject['sub_cat_name'] . '</td>';
                    $query_enroll = new Query();
                    $query_enroll->select('count(DISTINCT student_map_id)')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mandatory_stu_marks b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mandatory_subcat_subjects c', 'c.coe_mandatory_subcat_subjects_id=b.subject_map_id and c.batch_map_id=a.course_batch_mapping_id')
                        ->where(['b.subject_map_id' => $subject['coe_mandatory_subcat_subjects_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month']]);
                    if(!empty($_POST['section']) && $_POST['section']!='All')
                    {
                         $query_enroll->andWhere(['=', 'section_name', $_POST['section']]);
                    }
                        $query_enroll->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_enrol = $query_enroll->createCommand()->queryScalar();
                    $course_result_table .= '<td align="center">' . $student_enrol . '</td>';
                    $query_appeared = new Query();
                    $query_appeared->select('count(DISTINCT student_map_id)')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mandatory_stu_marks b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mandatory_subcat_subjects c', 'c.coe_mandatory_subcat_subjects_id=b.subject_map_id and c.batch_map_id=a.course_batch_mapping_id')
                        ->where(['b.subject_map_id' => $subject['coe_mandatory_subcat_subjects_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month']])
                        ->andWhere(['NOT LIKE', 'b.result', 'Absent']);
                    if(!empty($_POST['section']) && $_POST['section']!='All')
                    {
                         $query_appeared->andWhere(['=', 'section_name', $_POST['section']]);
                    }
                        $query_appeared->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_appeared = $query_appeared->createCommand()->queryScalar();
                    $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                    $query_absent = new Query();
                    $query_absent->select('count(student_map_id)')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mandatory_stu_marks b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mandatory_subcat_subjects c', 'c.coe_mandatory_subcat_subjects_id=b.subject_map_id and c.batch_map_id=a.course_batch_mapping_id')
                        ->where(['b.subject_map_id' => $subject['coe_mandatory_subcat_subjects_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent']);
                    if(!empty($_POST['section']) && $_POST['section']!='All')
                    {
                         $query_absent->andWhere(['=', 'section_name', $_POST['section']]);
                    }
                        $query_absent->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_absent = $query_absent->createCommand()->queryScalar();
                    $course_result_table .= '<td align="center">' . $student_absent . '</td>';
                    $query_withheld = new Query();
                    $query_withheld->select('count(student_map_id)')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mandatory_stu_marks b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mandatory_subcat_subjects c', 'c.coe_mandatory_subcat_subjects_id=b.subject_map_id and c.batch_map_id=a.course_batch_mapping_id')
                        ->where(['subject_map_id' => $subject['coe_mandatory_subcat_subjects_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w']);
                    if(!empty($_POST['section']) && $_POST['section']!='All')
                    {
                         $query_withheld->andWhere(['=', 'section_name', $_POST['section']]);
                    }
                        $query_withheld->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_withheld = $query_withheld->createCommand()->queryScalar();
                    $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                    $query_pass = new Query();
                    $query_pass->select('count(student_map_id)')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mandatory_stu_marks b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mandatory_subcat_subjects c', 'c.coe_mandatory_subcat_subjects_id=b.subject_map_id and c.batch_map_id=a.course_batch_mapping_id')
                        ->where(['subject_map_id' => $subject['coe_mandatory_subcat_subjects_id'], 'year' => $_POST['year'], 'month' => $_POST['month']])
                        ->andWhere(['NOT', ['year_of_passing' => '']]);
                    if(!empty($_POST['section']) && $_POST['section']!='All')
                    {
                         $query_pass->andWhere(['=', 'section_name', $_POST['section']]);
                    }
                        $query_pass->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_pass = $query_pass->createCommand()->queryScalar();
                    $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                    $query_fail = new Query();
                    $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_mandatory_stu_marks as b ON b.student_map_id=a.coe_student_mapping_id JOIN coe_mandatory_subcat_subjects c ON c.coe_mandatory_subcat_subjects_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_mandatory_subcat_subjects_id']."' AND year='".$_POST['year']."' $section and month='".$_POST['month']."' and result  not like '%Absent%' AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) ";
                    $student_fail = Yii::$app->db->createCommand($select_query)->queryScalar();
                    $student_appeared = $student_appeared==0 ? 1: $student_appeared;
                    $student_enrol = $student_enrol==0 ? 1: $student_enrol;
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
                    foreach ($grade_name as $grade) {
                        $query_75 = new Query();
                        $query_75->select('count(student_map_id)')
                            ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_mandatory_stu_marks b', 'b.student_map_id=a.coe_student_mapping_id')
                        ->join('JOIN', 'coe_mandatory_subcat_subjects c', 'c.coe_mandatory_subcat_subjects_id=b.subject_map_id and c.batch_map_id=a.course_batch_mapping_id')
                            ->where(['subject_map_id' => $subject['coe_mandatory_subcat_subjects_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'grade_name' => $grade['grade_name']]);
                        if(!empty($_POST['section']) && $_POST['section']!='All')
                        {
                             $query_75->andWhere(['=', 'section_name', $_POST['section']]);
                        }
                            $query_75->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                        $student_75 = $query_75->createCommand()->queryScalar();
                        $course_result_table .= '<td>' . $student_75 . '</td>';
                    }
                    $course_result_table .= '</tr>';
                    $sn++;
                }
            }
            $course_result_table .= "<tr>";
            $course_result_table .= "<td colspan='10' > &nbsp; </td>
                                 <td colspan='6'> 
                                            APP : <br />
                                            ENR : <br />
                                           PA : <br />
                                            FA : <br />
                                            WIT :<br />
                                            MEAN :<br />
                                            SSD :<br />
                                            VAR :<br />
                                            SD :<br />
                                    </td> ";
            $course_result_table .= "<td colspan='6'> 
                                        
                                            <b>APPEARED </b><br />
                                            <b>ENROLLED </b><br />
                                            <b>PASS </b><br />
                                            <b>FAIL </b><br />
                                            <b>WITH HELD</b><br />
                                            <b>MEAN</b><br />
                                            <b>SUM OF SQUARED DEVIATIONS</b><br />
                                            <b>VARIANCE</b><br />
                                            <b>STANDARD DEVIATIONS</b><br />
                                        
                                    </td>";
            $course_result_table .= "</tr>";
            $course_result_table .= '</table>';
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
            'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
       
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelProgrammeanalysis()
    {
       
        $content = $_SESSION['programme_analysis_print'];
          
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionCrseanalysissubcode()
    {
        $subject_query = new Query();
        $subject_query->select('c.subject_code,c.coe_subjects_id')
            ->from('coe_mark_entry_master a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.subject_map_id=b.coe_subjects_mapping_id')
            ->join('JOIN', 'coe_subjects c', 'b.subject_id=c.coe_subjects_id')
            ->join('JOIN', 'coe_bat_deg_reg d', 'd.coe_bat_deg_reg_id=b.batch_mapping_id')
            ->join('JOIN', 'coe_batch e', 'd.coe_batch_id=e.coe_batch_id')
            ->where(['a.year' => $_POST['year'], 'a.month' => $_POST['month'], 'a.mark_type' => $_POST['mark_type'], 'e.coe_batch_id' => $_POST['batch_id'],'d.coe_batch_id'=>$_POST['batch_id']])->groupBy('subject_code')->orderBy('subject_code');
        $subject_list = $subject_query->createCommand()->queryAll();
        return json_encode($subject_list);
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
        $reg_year = Yii::$app->db->createCommand("select regulation_year from coe_bat_deg_reg where coe_batch_id='" . $_POST['batch'] . "' ")->queryScalar();
        $year_exam = $_POST['year'];
        $query_gr = new Query();
        $query_gr->select('grade_name')
            ->from('coe_regulation')
            ->where(['regulation_year' => $reg_year])
            ->andWhere(['NOT', ['grade_name' => '']])->groupBy('grade_name');
        $grade_name = $query_gr->createCommand()->queryAll();
        $count = count($grade_name);
        $crse_analysis_query = new Query();
        $crse_analysis_query->select('distinct(subject_code),b.semester,subject_name,coe_subjects_mapping_id,description,coe_subjects_id,batch_mapping_id,degree_code,programme_name')
            ->from('coe_subjects a')
            ->join('JOIN', 'coe_subjects_mapping b', 'a.coe_subjects_id=b.subject_id')
            ->join('JOIN', 'coe_mark_entry_master c', 'b.coe_subjects_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->join('JOIN', 'coe_bat_deg_reg e', 'b.batch_mapping_id=e.coe_bat_deg_reg_id')
            ->join('JOIN', 'coe_degree f', 'e.coe_degree_id=f.coe_degree_id')
            ->join('JOIN', 'coe_programme g', 'e.coe_programme_id=g.coe_programme_id')
            ->where(['c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type'], 'a.coe_subjects_id' =>$_POST['sub_id'],'b.subject_id'=>$_POST['sub_id']]);
        $courseanalysis = $crse_analysis_query->createCommand()->queryAll();
        $colspan = 20-$count;
        if (count($courseanalysis) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $data = '<table id="checkAllFeet" class="table table-responsive table-striped" align="center" ><tbody align="center">';
            $data .= '<tr>
                        <td colspan=2> 
                            <img class="img-responsive" width="100" height="100" src="' . Yii::getAlias("@web") . '/images/main_logo.png" alt="College Logo">
                        </td>
                        <td colspan="'.$colspan.'" align="center"> 
                            <center><b><font size="4px">' . $org_name . '</font></b></center>
                            <center>' . $org_address . '</center>
                            
                            <center>' . $org_tagline . '</center> 
                        </td>
                        <td  colspan=2>  
                            <img width="100" height="100" src="' . Yii::getAlias("@web") . '/images/skacas.png" alt="College Logo 2">
                        </td>
                    </tr>';
            $data .= '<tr>
                        <td colspan=17 align="center"><b>' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Result Analysis - ' . $month . ' ' . $_POST['year'] . '
                        </b></td>
                    </tr>';
            $data .= '<tr><td colspan=17 align="center">';
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
                    <th>Fail %</th>';
            foreach ($grade_name as $grade) {
                $data .= '<th>' . $grade['grade_name'] . '</th>';
            }
            $data .= '</tr>';
            $sn = 1;
            foreach ($courseanalysis as $crse) {
                $degree_name_l = strstr($crse['degree_code'], "MBATRISEM") ? "MBA" : $crse['degree_code'];
                $data .= '<tr><td>' . $sn . '</td><td>' . $degree_name_l . ' ' . $crse['programme_name'] . '</td>';
                if ($crse['description'] != 'Elective') {
                    $query_enroll = new Query();
                    if ($mark_type_name == "Regular") {
                        $query_enroll->select('count(student_rel_id)')
                            ->from('coe_student_mapping a')
                            ->join('JOIN', 'coe_subjects_mapping b', 'a.course_batch_mapping_id=b.batch_mapping_id')
                            ->where(['b.coe_subjects_mapping_id' => $crse['coe_subjects_mapping_id']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
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
                            ->where(['c.subject_map_id' => $crse['coe_subjects_mapping_id'], 'mark_type' => $_POST['mark_type'], 'year' => $year_exam, 'month' => $_POST['month'],'b.semester'=>$crse['semester']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    }
                    $student_enrol = $query_enroll->createCommand()->queryScalar();
                    $data .= '<td align="center">' . $student_enrol . '</td>';
                }
                $query_appeared = new Query();
                $query_appeared->select('count(student_map_id)')
                    ->from('coe_mark_entry_master')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['result' => 'Absent']]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_appeared . '</td>';

                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_mark_entry_master')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])->andWhere(['LIKE','result','Abs']);
                $student_absent = $query_absent->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_absent . '</td>';

                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_mark_entry_master')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_withheld . '</td>';

                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_mark_entry_master')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']]);
                $student_pass = $query_pass->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_pass . '</td>';

                $query_fail = new Query();
                $query_fail->select('count(student_map_id)')
                    ->from('coe_mark_entry_master')
                    ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'year_of_passing' => '','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['result' => 'Absent']]);
                $student_fail = $query_fail->createCommand()->queryScalar();
                $data .= '<td align="center">' . $student_fail . '</td>';
                $student_enrol = $student_enrol==0?1:$student_enrol;
                if ($mark_type_name == "Regular") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $data .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $pass_percent = ($student_pass / $student_enrol) * 100;
                    $data .= '<td align="center">' . round($pass_percent, 1) . '</td>';
                }
                if ($mark_type_name == "Regular") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $data .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                } else if ($mark_type_name == "Arrear") {
                    $fail_percent = ($student_fail / $student_enrol) * 100;
                    $data .= '<td align="center">' . round($fail_percent, 1) . '</td>';
                }
                foreach ($grade_name as $grade) {
                    $query_75 = new Query();
                    $query_75->select('count(student_map_id)')
                        ->from('coe_mark_entry_master')
                        ->where(['subject_map_id' => $crse['coe_subjects_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'grade_name' => $grade['grade_name'],'mark_type'=>$_POST['mark_type']]);
                    $student_75 = $query_75->createCommand()->queryScalar();
                    $data .= '<td align="center">' . $student_75 . '</td>';
                }
                $data .= '</tr>';
                $sn++;
            }
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
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionStudentmarkView()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        if (isset($_POST['markviewbutton'])) {
            $stu_general_query = new Query();
            $reg_num = $_POST['mark_view_reg_no'];
            $stu_general_query->select('a.register_number,a.name,d.batch_name,b.previous_reg_number,e.degree_code,f.programme_code,g.description as student_status,b.coe_student_mapping_id as stu_map_id')
                ->from('coe_student a')
                ->join('JOIN', 'coe_student_mapping b', 'a.coe_student_id=b.student_rel_id')
                ->join('JOIN', 'coe_category_type as g', 'g.coe_category_type_id=b.status_category_type_id')
                ->join('JOIN', 'coe_bat_deg_reg c', 'b.course_batch_mapping_id=c.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_batch d', 'c.coe_batch_id=d.coe_batch_id')
                ->join('JOIN', 'coe_degree e', 'c.coe_degree_id=e.coe_degree_id')
                ->join('JOIN', 'coe_programme f', 'c.coe_programme_id=f.coe_programme_id')
                ->where(['a.register_number' => $_POST['mark_view_reg_no']]);
            $student_detail = $stu_general_query->createCommand()->queryOne();
            if (count($student_detail) > 0) 
            {
                $stu_mark_query = new Query();
                $stu_mark_query->select('b.course_batch_mapping_id as bat_map_val,e.student_map_id, c.subject_code,c.subject_name,d.semester,e.CIA, e.ESE,e.total,e.result, e.grade_name,e.grade_point,e.mark_type, f.coe_category_type_id, e.year_of_passing,e.withheld,e.month,e.year, f.description,c.CIA_max, c.credit_points,c.ESE_max, c.total_minimum_pass as min_pass')
                    ->from('coe_student a')
                    ->join('JOIN', 'coe_student_mapping b', 'a.coe_student_id = b.student_rel_id')
                    ->join('JOIN', 'coe_subjects_mapping d', 'b.course_batch_mapping_id=d.batch_mapping_id')
                    ->join('JOIN', 'coe_subjects c', 'c.coe_subjects_id=d.subject_id')
                    ->join('JOIN', 'coe_mark_entry_master e', 'd.coe_subjects_mapping_id=e.subject_map_id and b.coe_student_mapping_id=e.student_map_id')
                    ->join('JOIN', 'coe_category_type f', 'e.month=f.coe_category_type_id')
                    ->where(['a.register_number' => $_POST['mark_view_reg_no']])
                    ->orderBy('e.year,e.month,d.semester,e.mark_type,d.paper_type_id')
                    ->groupBy('e.year,e.month,c.subject_code');
                    //(, e.year,f.coe_category_type_id,c.semester,d.paper_type_id');
                $student_mark = $stu_mark_query->createCommand()->queryAll();
                if (count($student_mark) > 0) {
                    return $this->render('studentmark-view', [
                        'model' => $model, 'student_detail' => $student_detail, 'student_mark' => $student_mark,
                        'reg_num'=>$reg_num,
                    ]);
                } else {
                    Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                    return $this->render('studentmark-view', [
                        'model' => $model,
                    ]);
                }
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('studentmark-view', [
                    'model' => $model,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' Mark View');
            return $this->render('studentmark-view', [
                'model' => $model,
            ]);
        }
    }
    public function actionStudentmarkViewPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $content = $_SESSION['studentmarkview_print'];
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Mark View Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; } 
                        
                        .maroon_identification
                        {   
                            padding: 5px !important;
                            color: #FFF; 
                            height: 10px; 
                            width: 100px; 
                            background: #890c01;     
                        }
                        .withheld_iden
                        {   
                            padding: 5px !important;
                            color: #FFF; 
                            height: 10px; 
                            width: 100px; 
                            background: #c14603;     
                        }
                        .moderation_IDEN
                        {   
                            padding: 5px !important;
                            color: #FFF; 
                            height: 10px; 
                            width: 100px; 
                            background: #cc00ff;     
                        }
                        .withdraw_IDEN
                        {   
                            padding: 5px !important;
                            color: #FFF; 
                            height: 10px; 
                            width: 100px; 
                            background: #38007a;     
                        }
                        .reval_drwaw
                        {   
                            padding: 5px !important;
                            color: #FFF; 
                            height: 10px; 
                            width: 100px; 
                            background: #0182a3;     
                        }
                        .color_identification td:nth-child(2) 
                        {
                            padding-left: 25px;
                            font-size: 16px;
                        }
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
            'options' => ['title' => strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)).' SEMESTER WISE PERFORMANCE '],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => [ ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' SEMESTER WISE PERFORMANCE ' . strtoupper(' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}')],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelStudentmarkview()
    {
        
            $content = $_SESSION['studentmarkview_print'];
           
        $fileName = "Mark View Data " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionMarkPercent()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Mark Percent ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('mark-percent', [
            'model' => $model,
        ]);
    }
    public function actionMarkpercentreport()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $det_long_absent = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Long Absent%'")->queryScalar();
        
        $month = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $_POST['month'] . "'")->queryScalar();
        $failed_stus = Yii::$app->db->createCommand('SELECT student_map_id FROM coe_mark_entry_master where year="'.$_POST['year'].'" and month="'.$_POST['month'].'" and mark_type="27" and year_of_passing="" ')->queryAll();
        $notIn = array_filter(['']);
        foreach ($failed_stus as $key => $fails) {
            $notIn[$fails['student_map_id']]=$fails['student_map_id'];
        }
        $BAT_name = Batch::findOne($_POST['batch']);
        $MON_name = Categorytype::findOne($_POST['month']);
         $notIn = array_filter($notIn);
            
            $query_map_id = new Query();
            $query_map_id->select(['count(distinct student_map_id) as count','course_batch_mapping_id','programme_code','degree_code','batch_name','degree_type'])
                ->from('coe_student_mapping a')
                ->join('JOIN', 'coe_student b', 'a.student_rel_id=b.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg c', 'c.coe_bat_deg_reg_id=a.course_batch_mapping_id')
                ->join('JOIN', 'coe_batch d', 'd.coe_batch_id=c.coe_batch_id')
                ->join('JOIN', 'coe_degree f', 'f.coe_degree_id=c.coe_degree_id')
                ->join('JOIN', 'coe_programme g', 'g.coe_programme_id=c.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master e', 'e.student_map_id=a.coe_student_mapping_id')
                ->where(['d.coe_batch_id' => $_POST['batch'],'c.coe_batch_id' => $_POST['batch'], 'b.student_status' => 'Active','e.month'=>$_POST['month'],'e.mark_type'=>27,'e.year'=>$_POST['year'],'result'=>'Pass'])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['<>', 'status_category_type_id', $det_long_absent])
                ->andWhere(['NOT IN', 'student_map_id', $notIn])
                ->groupBy('course_batch_mapping_id')
                ->orderBy('degree_code,programme_code');
            $students_map_id_pass = $query_map_id->createCommand()->queryAll();

            $query_map_id_fail = new Query();
            $query_map_id_fail->select(['count(distinct student_map_id) as count','course_batch_mapping_id','programme_code','degree_code','batch_name','degree_type'])
                ->from('coe_student_mapping a')
                ->join('JOIN', 'coe_student b', 'a.student_rel_id=b.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg c', 'c.coe_bat_deg_reg_id=a.course_batch_mapping_id')
                ->join('JOIN', 'coe_batch d', 'd.coe_batch_id=c.coe_batch_id')
                ->join('JOIN', 'coe_degree f', 'f.coe_degree_id=c.coe_degree_id')
                ->join('JOIN', 'coe_programme g', 'g.coe_programme_id=c.coe_programme_id')
                ->join('JOIN', 'coe_mark_entry_master e', 'e.student_map_id=a.coe_student_mapping_id')
                ->where(['d.coe_batch_id' => $_POST['batch'],'c.coe_batch_id' => $_POST['batch'], 'b.student_status' => 'Active','e.month'=>$_POST['month'],'e.mark_type'=>27,'e.year'=>$_POST['year']])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'result', 'Pass'])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['<>', 'status_category_type_id', $det_long_absent])
                ->groupBy('course_batch_mapping_id')
                ->orderBy('degree_code,programme_code');
            $students_map_id_fail = $query_map_id_fail->createCommand()->queryAll();
            
            $fail_array = array();
            if (count($students_map_id_pass) > 0) 
            {
                require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                
                $data = '<table width="100%" id="checkAllFeet" border=1  align="center" ><tbody align="center">';
                $data .= '<tr>
                            <td colspan=7 align="center"> 
                                <center><b><font size="4px">'.$org_name.'</font></b></center>
                                <center><b><font size="4px">Office of the Controller of the Examinations</font></b></center>
                                <center>Consolidated Results '.$MON_name->description."-".$BAT_name->batch_name.' Examinations</center>
                                <center>' . $BAT_name->batch_name . ' BATCH</center> 
                            </td>
                        </tr>';
                $data .= '<tr>
                            <td colspan=7 align="center"><b>Mark Percent : ' . $_POST['year'] . ' ' . $month . '
                            </b></td>
                        </tr>';
               

                $data .= '<tr>
                            <td align="center"><b>SNO</b></td>
                            <td align="center"><b>' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH) . '</b></td>
                            <td align="center"><b>' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . '</b></td>
                            <td align="center">TOTAL STRENGTH</td>
                            <td align="center">REGISTERED</td>
                            <td align="center">PASS COUNT</td>
                            <td align="center">PASS %</td>
                        </tr>';

                $var_change = $students_map_id_pass;
                $sn_no = 1;
                $i = $total_pass = $total_strength_active = $ug_strength = $ug_reg = $ug_pass = $pg_strength = $pg_reg = $pg_pass_count =  $total_strength = $total_fail =0;
                $prev_deg_name = '';
                foreach ($var_change as $key => $deg) 
                {               
                    $batch_anme = $students_map_id_pass[$i]['batch_name']; 
                    if($i!=0)
                    {
                        $batch_anme = ',,';
                    }

                    $pass_count_disp = isset($students_map_id_pass[$i]['count'])?$students_map_id_pass[$i]['count']:'--';
                    if($prev_deg_name!='' && $prev_deg_name!=$deg['degree_type'])
                    {   
                        $data .='<tr><td colspan=3 align="center"> <b>TOTAL UG</b></td>';
                    }
                    else
                    {
                        $data .='<tr><td>'.$sn_no.'</td>';
                        $data .='<td>'.$batch_anme.'</td>'; 
                        $data .='<td align="center">'.$deg['degree_code']."-".$deg['programme_code'].'</td>';
                    }
                    
                    $STRENGHT = StudentMapping::find()->where(['course_batch_mapping_id'=>$deg['course_batch_mapping_id']])->count();
                    $STRENGHT_NOT = StudentMapping::find()->where(['course_batch_mapping_id'=>$deg['course_batch_mapping_id']])->andWhere(['<>', 'status_category_type_id', $det_cat_type])->andWhere(['<>', 'status_category_type_id', $det_disc_type])->andWhere(['<>', 'status_category_type_id', $det_long_absent])->count();

                    $total_strength_active +=$STRENGHT_NOT;
                    $total_strength +=$STRENGHT;

                    if($deg['degree_type']=='UG')
                    {
                        $ug_strength += $STRENGHT;
                        $ug_reg += $STRENGHT_NOT;
                        $ug_pass += $pass_count_disp;
                    }
                    else
                    {
                        $pg_strength +=  $STRENGHT;
                        $pg_reg += $STRENGHT_NOT;
                        $pg_pass_count += $pass_count_disp;
                    }
                    if($prev_deg_name!='' && $prev_deg_name!=$deg['degree_type'])
                    {   
                        
                         $data .='<td align="center"><b>'.$ug_strength.'</b></td>';
                         $data .='<td align="center"><b>'.$ug_reg.'</b></td>';
                         $data .='<td align="center"><b>'.$ug_pass.'</b></td>';
                         $data .='<td align="center"><b>'.round( (($ug_pass / $ug_reg) * 100),2).'</b></td></tr>';

                         $data .='<tr><td>'.$sn_no.'</td>';
                         $data .='<td>'.$batch_anme.'</td>'; 
                         $data .='<td align="center">'.$deg['degree_code']."-".$deg['programme_code'].'</td>';
                         $data .='<td align="center">'.$STRENGHT.'</td>';
                         $data .='<td align="center">'.$STRENGHT_NOT.'</td>';
                         $data .='<td align="center">'.$pass_count_disp.'</td>';
                         $pass_percent = ($pass_count_disp / $STRENGHT_NOT) * 100;
                         $data .='<td align="center">'.round($pass_percent,2).'</td></tr>';
                         
                    }
                    else 
                    {
                         $data .='<td align="center">'.$STRENGHT.'</td>';
                         $data .='<td align="center">'.$STRENGHT_NOT.'</td>';
                         $data .='<td align="center">'.$pass_count_disp.'</td>';
                         $pass_percent = ($pass_count_disp / $STRENGHT_NOT) * 100;
                         $data .='<td align="center">'.round($pass_percent,2).'</td></tr>';
                    }
                    $sn_no++;$i++;
                    if($pass_count_disp!='--')
                    {
                        $total_pass +=$pass_count_disp;
                    }
                    $prev_deg_name = $deg['degree_type'];  
                }
                // Display PG Data
                $pg_reg = $pg_reg==0 ? 0: $pg_reg;
                 $data .='<tr><td colspan=3 align="center"> <b> TOTAL PG</b></td>';
                 $data .='<td align="center"><b>'.$pg_strength.'</b></td>';
                 $data .='<td align="center"><b>'.$pg_reg.'</b></td>';
                 $data .='<td align="center"><b>'.$pg_pass_count.'</b></td>';
                  $pg_reg = $pg_reg==0 ? 1: $pg_reg;
                 $data .='<td align="center"><b>'.round( (($pg_pass_count / $pg_reg) * 100),2).'</b></td></tr>';
                    
                $data .='
                <tr>
                    <td colspan=3><b> TOTAL UG & PG </b></td>
                    <td align="center"><b>'.$total_strength.'</b></td>
                    <td align="center"><b>'.$total_strength_active.'</b></td>
                    <td align="center"><b>'.$total_pass.'</b></td>
                    <td><b>'.round((($total_pass/$total_strength_active)*100),2).'</b></td>
                </tr>';
               
                $data .= '</tbody></table>';
                if (isset($_SESSION['mark_percent_print'])) {
                    unset($_SESSION['mark_percent_print']);
                }
                $_SESSION['mark_percent_print'] = $data;
                return $data;
            } else {
                return 0;
            }
    }
    public function actionMarkPercentPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['mark_percent_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Mark Percent Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }',
            'options' => ['title' => 'Mark Percent Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Mark Percent Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelMarkpercent()
    {
        
            $content = $_SESSION['mark_percent_print'];
            
        $fileName = "Mark Percent Data " . date('Y-m-d-H-i-s') . '.xls';
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
       // $year = Yii::$app->request->post('year');
        $batch = Yii::$app->request->post('batch');
        $batch_mapping_id = Yii::$app->request->post('batch_mapping_id');
        $batch_name = Yii::$app->db->createCommand("select batch_name from coe_batch where coe_batch_id='" . $batch . "'")->queryScalar();
        //$month = Yii::$app->request->post('month');
        //$month_name = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='" . $month . "'")->queryScalar();
//$sem_count = ConfigUtilities::SemCaluclation($year,$month,$batch_mapping_id);
        //print_r($batch_name);exit;

        $man_query = new Query();
        $man_query->select('F.batch_name,C.degree_code,B.programme_name,B.programme_code,D.semester,D.subject_code,D.subject_name,D.CIA_max,D.ESE_max,D.total_minimum_pass,credit_points,D.CIA_min,D.ESE_min,G.description as paper_type,H.description as subject_type,I.description as course_type,paper_no,part_no,D.semester')
            ->from('coe_bat_deg_reg A')
            ->join('JOIN', 'coe_programme B', 'A.coe_programme_id=B.coe_programme_id')
            ->join('JOIN', 'coe_degree C', 'A.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_mandatory_subcat_subjects E', 'A.coe_bat_deg_reg_id=E.batch_map_id')
            ->join('JOIN', 'coe_mandatory_subjects D', 'D.coe_mandatory_subjects_id=E.man_subject_id')
            ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
            ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=E.subject_type_id')
            ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=E.course_type_id')
            ->join('JOIN', 'coe_batch F', 'A.coe_batch_id=F.coe_batch_id')
            ->where([ 'F.batch_name' => $batch_name])->orderBy('semester,programme_code,degree_code');
        $man_subjects = $man_query->createCommand()->queryAll();
        
        $table = "";
        $sn = 1;
        $query = new Query();
        $query->select('F.batch_name,C.degree_code,B.programme_name,B.programme_code,E.semester,D.subject_code,D.subject_name,D.CIA_max,D.ESE_max,D.total_minimum_pass,D.credit_points,D.CIA_min,D.ESE_min,G.description as paper_type,H.description as subject_type,I.description as course_type,paper_no,part_no,E.semester')
            ->from('coe_bat_deg_reg A')
            ->join('JOIN', 'coe_programme B', 'A.coe_programme_id=B.coe_programme_id')
            ->join('JOIN', 'coe_degree C', 'A.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_subjects_mapping E', 'A.coe_bat_deg_reg_id=E.batch_mapping_id')
            ->join('JOIN', 'coe_subjects D', 'E.subject_id=D.coe_subjects_id')
            ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
            ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=E.subject_type_id')
            ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=E.course_type_id')
            ->join('JOIN', 'coe_batch F', 'A.coe_batch_id=F.coe_batch_id')
            ->where(['F.batch_name' => $batch_name])->orderBy('semester,programme_code,degree_code');
        $subject = $query->createCommand()->queryAll();

        if(!empty($man_subjects))
        {
            $subject =array_merge($subject,$man_subjects);
            array_multisort(array_column($subject, 'paper_no'),  SORT_ASC, $subject);
        }
        //print_r($subject);exit;

        if (count($subject) > 0) {

            foreach ($subject as $subject12) 
            {
                $prg_name =  $subject12['programme_name'];     
            }

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
                            
                         </tr>';
                       


            $table .= '<tr>
                        <td><b> S.NO </b></td>
                        <td><b>Degree </td>
                        <td><b>Branch </td>
                        
                        <td><b> Semester </td>
                        <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </td>
                        <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </td>
                        <td><b> Paper No </td>
                        <td><b> Part No </td>
                        <td><b> CIA Min </td>
                        <td><b> CIA Max </td>
                        <td><b> ESE Min </td>
                        <td><b> ESE Max </td>
                        <td><b> Total Min </td>
                        <td><b> Credits </td>
                        
                        <td><b> Paper Type </td>
                        <td><b> Course Type </td>
                    </tr>';
            foreach ($subject as $subject1) {
                $table .= '
                    <tr>
                        <td> ' . $sn . ' </td>
                        <td> ' . $subject1['degree_code']. ' </td>
                         <td> ' . $subject1['programme_code']. ' </td>
                        
                        <td> ' . $subject1['semester']. ' </td>
                        <td> ' . $subject1['subject_code'] . ' </td>
                        <td> ' . $subject1['subject_name'] . ' </td>
                        <td> ' . $subject1['paper_no'] . ' </td>
                        <td> ' . $subject1['part_no'] . ' </td>
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
            'orientation' => Pdf::ORIENT_LANDSCAPE,
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
    //Subject Information Ends here
    public function actionInternetCopy()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $reval_status_entry = Categorytype::find()->where(['category_type'=>'Revaluation'])->orWhere(['description'=>'Revaluation'])->one();
        //$term_1=$_POST['term'];
        //print_r($term_1);exit;

        if (isset($_POST['internetcopybutton'])) 
        {
            $internLa = Categorytype::find()->where(['category_type'=>'Internal Final'])->orWhere(['category_type'=>'CIA'])->one();
            $disc_stu = Categorytype::find()->where(['category_type'=>'Discontinued'])->orWhere(['description'=>'Discontinued'])->one();
            $externAl = Categorytype::find()->where(['category_type'=>'ESE'])->orWhere(['category_type'=>'External'])->one();
            $dummy_entry = Categorytype::find()->where(['category_type'=>'ESE(Dummy)'])->orWhere(['category_type'=>'ESE(Dummy)'])->one();
            $absent_term = Categorytype::find()->where(['category_type'=>'end'])->orWhere(['category_type'=>'End'])->one();
            $exam_type = Categorytype::find()->where(['category_type'=>'Regular'])->orWhere(['category_type'=>'Regular'])->one();
            $getDiscList = Yii::$app->db->createCommand("select A.* from coe_absent_entry as A JOIN coe_student_mapping as B ON B.coe_student_mapping_id=A.absent_student_reg where exam_year='".$_POST['year']."' and exam_month='".$_POST['month']."' and status_category_type_id!='".$disc_stu['coe_category_type_id']."'")->queryAll();
            $getAbsentList = AbsentEntry::find()->where(['exam_year'=>$_POST['year'],'exam_month'=>$_POST['month']])->all();
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId();
            $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
            $has_absent = 0; $missing_records = '';

            if(!empty($getAbsentList))
            {
                foreach ($getAbsentList as $key => $absentList) 
                {         
                    $subject_map_id =   $absentList['exam_subject_id'];
                    $student_map_id =   $absentList['absent_student_reg']; 
                    $year = $absentList['exam_year']=='' ? $_POST['year']:$absentList['exam_year'];
                    $month = $absentList['exam_month']=='' ? $_POST['month']:$absentList['exam_month'];
                    $term = $absentList['absent_term']=='' ? $absent_term->coe_category_type_id:$absentList['absent_term'];

                    $mark_type = $absentList['exam_type']=='' ? $exam_type->coe_category_type_id:$absentList['exam_type'];
                    
                    

                    $stuCiaMarks = MarkEntry::find()->where(['category_type_id'=>$internLa->coe_category_type_id,'subject_map_id'=>$subject_map_id,'student_map_id'=>$student_map_id])->orderBy('coe_mark_entry_id desc')->one();
                    //print_r($stuCiaMarks);exit;

                    $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id . '" AND student_map_id="' . $student_map_id . '" AND result not like "%pass%"')->queryScalar();

                   $check_mark_entry = MarkEntry::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$student_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orWhere(['IN','category_type_id',$dummy_entry->coe_category_type_id,$externAl->coe_category_type_id])->one();

                   $stuCiaMarksMaster = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$student_map_id])->orderBy('coe_mark_entry_master_id desc')->one();

                    $absen_model_save = new MarkEntry();
                    $absen_model_save->student_map_id = $student_map_id;
                    $absen_model_save->subject_map_id = $subject_map_id;
                    $absen_model_save->category_type_id =$externAl->coe_category_type_id;
                    $absen_model_save->category_type_id_marks =0;
                    $absen_model_save->year = $year;
                    $absen_model_save->month = $month;
                    $absen_model_save->term = $term;
                    $absen_model_save->attendance_remarks = 'NOT ALLOWED';
                    $absen_model_save->mark_type = $mark_type;
                    $absen_model_save->created_at = $created_at;
                    $absen_model_save->created_by = $updateBy;
                    $absen_model_save->updated_at = $created_at;
                    $absen_model_save->updated_by = $updateBy;
                    $student_cia_marks = 0;
                    $status_insert = 0;
                    if(empty($stuCiaMarks) && empty($stuCiaMarksMaster))
                    {                      
                        $status_insert = 0;
                    }
                    if(empty($stuCiaMarks) && !empty($stuCiaMarksMaster))
                    {          
                        $student_cia_marks = $stuCiaMarksMaster['CIA'];          
                        $status_insert = 1;
                    }
                    else if($check_attempt>$config_attempt)
                    {
                        $status_insert = 1;
                        $student_cia_marks = 0;
                    }
                    else if(!empty($stuCiaMarks))
                    {
                        $status_insert = 1;
                        $student_cia_marks = $stuCiaMarks['category_type_id_marks'];
                    }
                   /* else
                     {
                        $has_absent = 1;
                        $stuMap = StudentMapping::findOne($student_map_id);
                        $stuMapDe = Student::findOne($stuMap['student_rel_id']);
                        $subMap = SubjectsMapping::findOne($subject_map_id);
                        $subMapId = Subjects::findOne($subMap['subject_id']);
                        $missing_records .=$stuMapDe['register_number']."-".$subMapId['subject_code']."<br />";

                        Yii::$app->ShowFlashMessages->setMsg('Error','NO CIA MARKS FOUND KINDLY CHECK THE PENDING STATUS AND RE-GENRATE THE INTERNET COPY!!!');

                        
                    }*/
                    else{

                        
                    }
                    $check_mark_entry_master = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$student_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->one();

                    if(empty($check_mark_entry) && $status_insert==1)
                    {
                        $ab_MarkEntryMaster = new MarkEntryMaster();
                        $ab_MarkEntryMaster->student_map_id = $student_map_id;
                        $ab_MarkEntryMaster->subject_map_id =$subject_map_id;
                        $ab_MarkEntryMaster->CIA = $student_cia_marks;
                        $ab_MarkEntryMaster->ESE = 0;
                        $ab_MarkEntryMaster->total = $student_cia_marks;
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
                        $check_mark_entry_master = MarkEntryMaster::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$student_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->one();

                        if(empty($check_mark_entry_master) && $absen_model_save->save(false))
                        {
                            unset($absen_model_save);
                            $ab_MarkEntryMaster->save();
                        }
                        unset($ab_MarkEntryMaster);    
                    }
                    else if(!empty($check_mark_entry) && empty($check_mark_entry_master) && $status_insert==1)
                    {
                        unset($absen_model_save);
                        $ab_MarkEntryMaster = new MarkEntryMaster();
                        $ab_MarkEntryMaster->student_map_id = $student_map_id;
                        $ab_MarkEntryMaster->subject_map_id =$subject_map_id;
                        $ab_MarkEntryMaster->CIA = $student_cia_marks;
                        $ab_MarkEntryMaster->ESE = 0;
                        $ab_MarkEntryMaster->total = $student_cia_marks;
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
            if($has_absent==1)
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','NO CIA MARKS FOUND FOR '.$missing_records.' KINDLY CHECK THE PENDING STATUS AND RE-GENRATE THE INTERNET COPY!!!');
                return $this->redirect(['mark-entry/internet-copy']);
            }
            $reval_status = isset($_POST['MarkEntry']['mark_type'][0])?$_POST['MarkEntry']['mark_type'][0]:'';
            $internet_copy_query = new Query();
            $withheld_list = Yii::$app->db->createCommand('SELECT DISTINCT student_map_id as id FROM coe_mark_entry_master WHERE month="'.$_POST['month'].'" AND year="'.$_POST['year'].'" AND withheld="w" ')->queryAll();
            $withheld = [];
            foreach ($withheld_list as $key => $value) {
                $withheld[$value['id']]=$value['id'];
            }

            $whereCondition = [                        
                        'a.year' => $_POST['year'], 'a.month' => $_POST['month'],
                    ];
            $internet_copy_query->select('c.register_number,paper_no,c.name,c.dob,e.subject_code,e.ESE_max,e.ESE_min,e.CIA_max,e.CIA_min,a.subject_map_id,a.CIA,a.ESE,a.result,a.withheld,a.grade_name,a.student_map_id,a.year,a.month,degree_code,programme_name,d.semester')
                ->from('coe_mark_entry_master a')
                ->join('JOIN', 'coe_student_mapping b', 'a.student_map_id=b.coe_student_mapping_id')
                ->join('JOIN', 'coe_student c', 'b.student_rel_id=c.coe_student_id')
                ->join('JOIN', 'coe_subjects_mapping d', 'a.subject_map_id=d.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_subjects e', 'd.subject_id=e.coe_subjects_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id = d.batch_mapping_id and g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');

            if(!empty($reval_status) && $reval_status=='yes')
            {
                $internet_copy_query->join('JOIN', 'coe_mark_entry f', 'f.student_map_id=a.student_map_id and f.subject_map_id=a.subject_map_id and f.year=a.year and f.month=a.month and f.mark_type=a.mark_type and f.term=a.term');
                $whereCondition_12 = [                        
                        'f.category_type_id'=>$reval_status_entry['coe_category_type_id'],'f.year'=>$_POST['year'],'f.month' => $_POST['month'],
                    ];
                $whereCondition = array_merge($whereCondition,$whereCondition_12); 
            }
			$internet_copy_query->where($whereCondition);           
            $internet_copy_query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->orderBy('c.register_number,paper_no');
            $internet_copy = $internet_copy_query->createCommand()->queryAll();

            $query_man = new  Query();
            $query_man->select('H.subject_code,paper_no,  A.name, A.register_number, A.dob,  H.ESE_min,H.ESE_max, H.CIA_max,H.CIA_min, F.subject_map_id, F.ESE,F.CIA, F.result,F.withheld,F.grade_name,F.student_map_id,F.year, F.month,D.degree_code,E.programme_name,F.semester')
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
            $query_man->Where(['F.year' => $_POST['year'], 'F.month' =>  $_POST['month'], 'A.student_status' => 'Active','G.is_additional'=>'NO'])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query_man->orderBy('A.register_number,paper_no');
               
            $mandatory_statement = $query_man->createCommand()->queryAll();
            if(!empty($mandatory_statement))
            {
                $internet_copy = array_merge($internet_copy,$mandatory_statement);
            }
            array_multisort(array_column($internet_copy, 'paper_no'),  SORT_ASC, $internet_copy);
            array_multisort(array_column($internet_copy, 'register_number'),  SORT_ASC, $internet_copy);

            if (count($internet_copy) > 0) {
                return $this->render('internet-copy', [
                    'model' => $model, 'galley' => $galley, 'internet_copy' => $internet_copy,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('internet-copy', [
                    'model' => $model, 'galley' => $galley,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Internet Copy');
            return $this->render('internet-copy', [
                'model' => $model, 'galley' => $galley,
            ]);
        }
    }
    public function actionInternetCopyPdf()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        $publish_date = date('Y-m-d');
        $check_data = Yii::$app->db->createCommand("SELECT * FROM coe_mark_entry_master where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "' and status_id=1")->queryScalar();
        if(empty($check_data))
        {
            Yii::$app->db->createCommand("update coe_mark_entry_master set result_published_date='".$publish_date."',status_id=1,updated_by='".$updated_by."',updated_at='".$updated_at."' where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "'")->execute();
            Yii::$app->db->createCommand("update coe_mark_entry set status_id=1,updated_by='".$updated_by."',updated_at='".$updated_at."' where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "'")->execute();    
        }        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['internetcopy_print'];
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Mark Data.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
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
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                           
                        }
                    }   
                ',
            'options' => ['title' => 'Mark Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Mark Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelInternetCopyPdf()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();

        $publish_date = date('Y-m-d');
        $check_data = Yii::$app->db->createCommand("SELECT * FROM coe_mark_entry_master where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "' and status_id=1")->queryScalar();
        if(empty($check_data))
        {
            Yii::$app->db->createCommand("update coe_mark_entry_master set result_published_date='".$publish_date."',status_id=1,updated_by='".$updated_by."',updated_at='".$updated_at."' where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "'")->execute();
            Yii::$app->db->createCommand("update coe_mark_entry set status_id=1,updated_by='".$updated_by."',updated_at='".$updated_at."' where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "'")->execute();    
        }
        
        $content = $_SESSION['internetcopy_print'];           
        $fileName = "INTERNET COPY - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    //Hallticket export starts here
    public function actionHallticketexport()
    {
        $model = new MarkEntry();
        $galley = new HallAllocate();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Hallticket Export');
        return $this->render('hallticketexport', [
            'model' => $model, 'galley' => $galley,
        ]);
    }
    public function actionHallticketexportdata()
    {
        
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
        $stu_elective = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Elective%'")->queryScalar();

        $practical_check = Yii::$app->request->post('check_val');
        $year = Yii::$app->request->post('year');
        $batch_id = Yii::$app->request->post('batch_id');
        $semester = Yii::$app->request->post('semester');
        if(isset($semester) && !empty($semester))
        {
            $semester = $semester+1;
        }
        $month = Yii::$app->request->post('month');
        $table = "";
        $a="400";
        $pract="Practical";
        $sn = 1;
        if(isset($batch_id) && !empty($batch_id))
        {
            $bath_name = Batch::findOne($batch_id);
            $batch_name_id = $bath_name->batch_name;
        }
        if($practical_check==1)
        {
            $add_in_theQuery = isset($batch_id) && !empty($batch_id) ? " and A.coe_batch_id='".$batch_id."' and A.coe_batch_id='".$batch_id."' and batch_name='".$batch_name_id."' ":'';

            $add_in_theQuerySem = isset($semester) && !empty($semester) ? " and G.semester='".$semester."' ":'';

            $query_2 = "SELECT L.batch_name,D.register_number,D.name,D.dob,B.degree_code,C.programme_name,G.semester,F.subject_code,F.subject_name,$a as exam_date, $a as category_type, $a as hall_name ,$a as row, $a as row_column,$a as seat_no  FROM coe_bat_deg_reg as A JOIN coe_degree as B ON A.coe_degree_id=B.coe_degree_id JOIN coe_programme C ON A.coe_programme_id=C.coe_programme_id JOIN coe_student_mapping E ON  A.coe_bat_deg_reg_id=E.course_batch_mapping_id JOIN coe_student D ON E.student_rel_id=D.coe_student_id JOIN coe_subjects_mapping G ON A.coe_bat_deg_reg_id=G.batch_mapping_id JOIN coe_subjects F ON G.subject_id=F.coe_subjects_id JOIN coe_nominal as I ON I.coe_subjects_id=F.coe_subjects_id AND I.coe_student_id=D.coe_student_id and G.subject_id=I.coe_subjects_id and I.course_batch_mapping_id=G.batch_mapping_id JOIN coe_category_type H ON H.coe_category_type_id=G.paper_type_id JOIN coe_batch L ON A.coe_batch_id=L.coe_batch_id WHERE H.description LIKE '%$pract%' AND G.subject_type_id='".$stu_elective."' AND status_category_type_id not in('".$det_disc_type."' ) $add_in_theQuery $add_in_theQuerySem group by D.register_number,F.subject_code ORDER BY G.semester,D.register_number";
            $practical_elective = Yii::$app->db->createCommand($query_2)->queryAll();

            $query_3 = "SELECT L.batch_name,D.register_number,D.name,D.dob,B.degree_code,C.programme_name,G.semester,F.subject_code,F.subject_name,$a  as exam_date, $a  as category_type, $a  as hall_name ,$a  as row, $a  as row_column,$a  as seat_no  FROM coe_bat_deg_reg as A JOIN coe_degree as B ON A.coe_degree_id=B.coe_degree_id JOIN coe_programme C ON A.coe_programme_id=C.coe_programme_id JOIN coe_student_mapping E ON  A.coe_bat_deg_reg_id=E.course_batch_mapping_id JOIN coe_student D ON E.student_rel_id=D.coe_student_id JOIN coe_subjects_mapping G ON A.coe_bat_deg_reg_id=G.batch_mapping_id JOIN coe_subjects F ON G.subject_id=F.coe_subjects_id JOIN coe_category_type H ON H.coe_category_type_id=G.paper_type_id JOIN coe_batch L ON A.coe_batch_id=L.coe_batch_id WHERE H.description LIKE '%$pract%' AND G.subject_type_id!='".$stu_elective."' AND status_category_type_id not in('".$det_disc_type."' ) $add_in_theQuery $add_in_theQuerySem group by D.register_number,F.subject_code ORDER BY G.semester,D.register_number";
            $practical_common = Yii::$app->db->createCommand($query_3)->queryAll();
            if(!empty($practical_elective))
            {
                if(!empty($practical_common))
                {
                    $practical_common = array_merge($practical_common,$practical_elective);
                }
            }
            $query_4 = "SELECT L.batch_name,D.register_number,D.name,D.dob,B.degree_code,C.programme_name,G.semester,F.subject_code,F.subject_name,$a  as exam_date, $a  as category_type, $a  as hall_name ,$a  as row, $a  as row_column,$a  as seat_no  FROM coe_bat_deg_reg as A JOIN coe_degree as B ON A.coe_degree_id=B.coe_degree_id JOIN coe_programme C ON A.coe_programme_id=C.coe_programme_id JOIN coe_student_mapping E ON  A.coe_bat_deg_reg_id=E.course_batch_mapping_id JOIN coe_student D ON E.student_rel_id=D.coe_student_id JOIN coe_subjects_mapping G ON A.coe_bat_deg_reg_id=G.batch_mapping_id and E.course_batch_mapping_id=G.batch_mapping_id JOIN coe_subjects F ON G.subject_id=F.coe_subjects_id JOIN coe_category_type H ON H.coe_category_type_id=G.paper_type_id JOIN coe_batch L ON A.coe_batch_id=L.coe_batch_id JOIN coe_mark_entry_master as M ON M.student_map_id=E.coe_student_mapping_id and M.subject_map_id=G.coe_subjects_mapping_id WHERE H.description LIKE '%$pract%' AND status_category_type_id not in('".$det_disc_type."' ) $add_in_theQuery $add_in_theQuerySem group by D.register_number,F.subject_code ORDER BY G.semester,D.register_number";
            $prac_arrear = Yii::$app->db->createCommand($query_4)->queryAll();

            if(!empty($prac_arrear))
            {
                if(!empty($practical_common))
                {
                    $practical_common = array_merge($practical_common,$prac_arrear);
                }
            }
            $subject = $practical_common;
            $subject = array_map("unserialize", array_unique(array_map("serialize", $subject)));

        }
        else
        {
            $examsIdsMer = array_filter(['']);
            $examSIds = ExamTimetable::find()->where(['exam_year'=>$year,'exam_month'=>$month])->all();
            foreach ($examSIds as $key => $exams) 
            {
               $examsIdsMer[$exams['coe_exam_timetable_id']]=$exams['coe_exam_timetable_id'];
            }
            $examsIdsMer = array_filter($examsIdsMer);
            $query = new Query();
            $query->select('L.batch_name,D.register_number,D.name,D.dob,B.degree_code,C.programme_name,G.semester,F.subject_code,F.subject_name,H.exam_date,K.category_type,I.hall_name,J.row,J.row_column,J.seat_no')
                ->from('coe_bat_deg_reg A')
                ->join('JOIN', 'coe_degree B', 'A.coe_degree_id=B.coe_degree_id')
                ->join('JOIN', 'coe_programme C', 'A.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_student_mapping E', 'E.course_batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_student D', 'D.coe_student_id=E.student_rel_id')
                ->join('JOIN', 'coe_subjects_mapping G', 'G.batch_mapping_id=E.course_batch_mapping_id and G.batch_mapping_id=A.coe_bat_deg_reg_id')
                ->join('JOIN', 'coe_subjects F', 'F.coe_subjects_id=G.subject_id')
                ->join('JOIN', 'coe_exam_timetable H', 'H.subject_mapping_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_hall_allocate J', 'J.exam_timetable_id=H.coe_exam_timetable_id and J.register_number=D.register_number and J.year=H.exam_year and J.month=H.exam_month')
                ->join('JOIN', 'coe_hall_master I', 'I.coe_hall_master_id=J.hall_master_id')
                ->join('JOIN', 'coe_category_type K', 'K.coe_category_type_id=H.exam_session')
                ->join('JOIN', 'coe_batch L', 'A.coe_batch_id=L.coe_batch_id')
                ->where(['J.year' => $year,'J.month' => $month,'H.exam_year'=> $year,'H.exam_month'=>$month,'L.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id]);
                /*if(isset($batch_id) && !empty($batch_id))
                {
                    $query->where(['L.coe_batch_id'=>$batch_id,'A.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id]);
                }*/
            $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['<>', 'G.subject_type_id', $stu_elective])
                ->andWhere(['IN', 'coe_exam_timetable_id', $examsIdsMer])
                ->andWhere(['IN', 'exam_timetable_id', $examsIdsMer])
                ->groupBy('D.register_number,F.subject_code')
                ->orderBy('G.semester,D.register_number,H.exam_date');
            $subject = $query->createCommand()->queryAll();

            $query_1 = new Query();
            $query_1->select('L.batch_name,D.register_number,D.name,D.dob,B.degree_code,C.programme_name,G.semester,F.subject_code,F.subject_name,H.exam_date,K.category_type,I.hall_name,J.row,J.row_column,J.seat_no')
                ->from('coe_bat_deg_reg A')
                ->join('JOIN', 'coe_degree B', 'A.coe_degree_id=B.coe_degree_id')
                ->join('JOIN', 'coe_programme C', 'A.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_batch L', 'A.coe_batch_id=L.coe_batch_id')
                ->join('JOIN', 'coe_student_mapping E', 'A.coe_bat_deg_reg_id=E.course_batch_mapping_id')
                ->join('JOIN', 'coe_student D', 'E.student_rel_id=D.coe_student_id')
                ->join('JOIN', 'coe_subjects_mapping G', 'A.coe_bat_deg_reg_id=G.batch_mapping_id')
                ->join('JOIN', 'coe_subjects F', 'G.subject_id=F.coe_subjects_id')
                ->join('JOIN', 'coe_nominal N', 'N.coe_subjects_id=F.coe_subjects_id AND N.coe_student_id=D.coe_student_id and N.course_batch_mapping_id=E.course_batch_mapping_id and N.semester=G.semester and N.course_batch_mapping_id=G.batch_mapping_id')
                ->join('JOIN', 'coe_exam_timetable H', 'H.subject_mapping_id=G.coe_subjects_mapping_id')
                ->join('JOIN', 'coe_hall_allocate J', 'J.exam_timetable_id=H.coe_exam_timetable_id and D.register_number=J.register_number and J.year=H.exam_year and J.month=H.exam_month')
                ->join('JOIN', 'coe_hall_master I', 'I.coe_hall_master_id=J.hall_master_id')
                ->join('JOIN', 'coe_category_type K', 'K.coe_category_type_id=H.exam_session')
                ->where(['J.year' => $year, 'J.month' => $month,'H.exam_year'=> $year,'H.exam_month'=>$month,'L.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id]);
               /* if(isset($batch_id) && !empty($batch_id))
                {
                    $query->where(['L.coe_batch_id'=>$batch_id,'A.coe_batch_id'=>$batch_id,'batch_name'=>$batch_name_id]);
                }*/
                $query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['=', 'G.subject_type_id', $stu_elective])
                ->andWhere(['IN', 'coe_exam_timetable_id', $examsIdsMer])
                ->andWhere(['IN', 'exam_timetable_id', $examsIdsMer])
                ->groupBy('D.register_number,F.subject_code')
                ->orderBy('G.semester,D.register_number,H.exam_date');
            $subject_elec = $query_1->createCommand()->queryAll();
           
            if(!empty($subject_elec))
            {
                $subject = array_merge($subject,$subject_elec);
            }
        }
        $subject = array_map("unserialize", array_unique(array_map("serialize", $subject)));
        if (count($subject) > 0) 
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table width="100%" ><tbody align="center">';
            
            $table .= '
                        <tr>
                            <td><b> S.NO </b></td>
                            <td><b> Batch </b></td>
                            <td><b> Register number </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' Name </b></td>
                            <td><b> DOB </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' </b></td>
                            <td><b> Semester </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Code </b></td>
                            <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Name </b></td>
                            <td><b> Exam date </b></td>
                            <td><b> Session </b></td>
                            <td><b> Hallname </b></td>
                            
                            <td><b> Seat no </b></td>
                        </tr>';
            foreach ($subject as $subject1) 
            {
                $dergreee_code = strstr($subject1['degree_code'], "MBATRISEM") ? "MBA" : $subject1['degree_code'];
                $exam_date = $subject1['exam_date']=='400' ? '-' : date('d-M-Y',strtotime($subject1['exam_date']));
                $hall_name = $subject1['hall_name']=='400' ? '-' : $subject1['hall_name'];
                $seat_no = $subject1['seat_no']=='400' ? '-' : $subject1['seat_no'];
                $category_type = $subject1['category_type']=='400' ? '-' : $subject1['category_type'];
                $table .= '
                    <tr>
                        <td> ' . $sn . ' </td>
                        <td> ' . $subject1['batch_name'] . ' </td>
                        <td> ' . $subject1['register_number'] . ' </td>
                        <td> ' . $subject1['name'] . ' </td>
                        <td> ' . $subject1['dob'] . ' </td>
                        <td> ' . $dergreee_code . ' ' . $subject1['programme_name'] . ' </td>
                        <td> ' . $subject1['semester'] . ' </td>
                        <td> ' . $subject1['subject_code'] . ' </td>
                        <td> ' . $subject1['subject_name'] . ' </td>
                        <td> ' . $exam_date. ' </td>
                        <td> ' . $category_type . ' </td>
                        <td> ' . $hall_name . ' </td>
                        <td> ' . $seat_no . ' </td>
                    </tr>';
                $sn++;
            }
            $table .= '</table>';
        } else {
            $table .= 0;
        }
        if (isset($_SESSION['hallticket_export_print'])) {
            unset($_SESSION['hallticket_export_print']);
        }
        $_SESSION['hallticket_export_print'] = $table;
        return $table;
    }
    public function actionHallticketexportPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['hallticket_export_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Hall Ticket Export.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; }
                    }   
                ',
            'options' => ['title' => 'Hallticket export Information'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Hallticket ' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelHallticketexport()
    {
        
            $content = $_SESSION['hallticket_export_print'];
            
        $fileName = "Hallticket export Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    //Hallticket export ends here
    //University report starts here
    public function actionUniversityreport()
    {
        $model = new MarkEntry();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to University ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('universityreport', [
            'model' => $model,
        ]);
    }
    /*public function actionUniversityreportdata()
    {
        $batch = Yii::$app->request->post('batch');
        $bat_map_id = Yii::$app->request->post('bat_map_id');
        $passed_stu = array();
        $failed_stu = array();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $passed_student = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_subjects_mapping as C,coe_mark_entry_master as D where A.coe_student_id=B.student_rel_id and B.course_batch_mapping_id=C.batch_mapping_id and B.coe_student_mapping_id=D.student_map_id and C.coe_subjects_mapping_id=D.subject_map_id and B.course_batch_mapping_id='" . $bat_map_id . "' and C.batch_mapping_id='" . $bat_map_id . "' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and year_of_passing!='' order by A.register_number")->queryAll();
        
        $failed_student = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_subjects_mapping as C,coe_mark_entry_master as D where A.coe_student_id=B.student_rel_id and B.course_batch_mapping_id=C.batch_mapping_id and B.coe_student_mapping_id=D.student_map_id and C.coe_subjects_mapping_id=D.subject_map_id and B.course_batch_mapping_id='" . $bat_map_id . "' and C.batch_mapping_id='" . $bat_map_id . "' and year_of_passing='' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') order by A.register_number")->queryAll();
        
        foreach ($passed_student as $passed_student1) {
            $passed_stu[] = $passed_student1['register_number'];
        }
        $a = array_unique($passed_stu);
        
        foreach ($failed_student as $failed_student1) {
            $failed_stu[] = $failed_student1['register_number'];
        }
        $b = array_unique($failed_stu);
        
        $final = array_diff($a, $b);
        

        $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,E.programme_name,H.subject_code,C.regulation_year,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.year,F.month,B.course_batch_mapping_id,F.year_of_passing,paper_no,F.result,max(F.year_of_passing) as last_appearance,round (sum(H.credit_points*F.grade_point)/sum(H.credit_points),5) as total FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id where G.batch_mapping_id='".$bat_map_id."' AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%')) and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') AND F.grade_name not IN ('U','wh','AB','ab','WH','ra','wd','WD')  group by A.register_number order by A.register_number, G.semester, paper_no";
        $final_student = Yii::$app->db->createCommand($get_stu_query)->queryAll();

        $last_appearance_of = Yii::$app->db->createCommand("SELECT max(F.year_of_passing) as last_appearance FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$bat_map_id."' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='') AND F.grade_name not IN ('U','wh','AB','ab','WH','ra','wd','WD')  group by A.register_number")->queryScalar();
        //print_r($last_appearance_of);exit;
        if(!empty($last_appearance_of))
        {
            $ex_plode = explode('-', $last_appearance_of);
            $month_name = Categorytype::findOne($ex_plode[0]);
            $month_and_year = strtoupper($month_name['description']."-".$ex_plode[1]);
        }
        else
        {
            $month_and_year = DATE('Y');
        }
        
        
        $table = "";
        $sn = 1;
        $query = new Query();
        if (count($final_student) > 0) 
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table=$body=$footer='';
            $table = '<table border=1 id="checkAllFeet" class="table table-responsive table-striped" align="center" >
                <tbody align="center">';
               $table .='<table  border="1"  class="table table-bordered table-responsive dum_edit_table table-hover" >';
        $table .='<tr><td colspan="12">SRI KRISHNA ARTS AND SCIENCE COLLEGE, (AUTONOMOUS), COIMBATORE - 641 008.</td></tr>';
        $table .='<tr><td colspan="12">MONTH AND YEAR OF EXAMINATIONS :  '.$month_and_year.'</td></tr>';
        foreach ($final_student as $key => $name) 

        {
             
        }
        $table .='<tr><td colspan="6" align="center">DEGREE :  '.$name['degree_code'].'</td><td colspan="6" align="center">BRANCH :  '.$name['programme_name'].'</td></tr>';
        foreach ($final_student as $valuesa) 
        {
            for ($a=1; $a <=5 ; $a++) { 
                $part_max_marks[] = ConfigUtilities::getPartDetails($valuesa['register_number'],$a);
            }
            break;
        }
         $table .='<tr>
                   <td>SNO</td>
                   <td>REGNO</td>
                   <td>NAME OF THE CANDIDATE</td>
                   <td>SEX</td>
                   ';

        $table .='<td colspan=3>PART I
                    <table border=1>
                    <tr>
                        <td>SUBJECT NAME</td>
                        <td>MARKS SECURED OUT OF '.$part_max_marks[0]['part_total_marks'].'</td>
                        <td>CLASS</td>
                    </tr>
                   </table></td>';
        $table .='<td colspan=2>PART II
                    <table border=1>
                    <tr>
                        <td>MARKS SECURED OUT OF '.$part_max_marks[1]['part_total_marks'].'</td>
                        <td>CLASS</td>
                    </tr>
                   </table></td>';
        $table .='<td colspan=2>PART III
                    <table border=1>
                    <tr>
                        <td>MARKS SECURED OUT OF '.$part_max_marks[2]['part_total_marks'].'</td>
                        <td>CLASS</td>
                    </tr>
                   </table></td>';
        $table .='<td>FOR COE USE</td>';
        $SNO = 1;
         //print_r($valuesa);exit;
         foreach ($final_student as $value) 
        {           
            
            $part_info = array_filter(['']);
            for ($i=1; $i <=5 ; $i++) { 
                $part_info[] = ConfigUtilities::getPartDetails($value['register_number'],$i);
                //print_r($part_info);exit;
            }
              if(($part_info[0]['part_class']=='SECOND CLASS' || $part_info[1]['part_class']=='SECOND CLASS')  && $part_info[2]['part_class']=='FIRST CLASS WITH DISTINCTION')
      {
        $part_info[2]['part_class']='FIRST CLASS';
      }
      else
      {
     $part_info[2]['part_class'];
      }
            $table .='<tr>
                        <td>'.$SNO.'</td>
                        <td>'.strtoupper($value['register_number']).'</td>
                        <td>'.strtoupper($value['name']).'</td>
                        <td>'.$value['gender'].'</td>';
                        $table .='<td colspan=3>
                         <table border=1>
                            
                            <tr>
                           <td> '.strtoupper($value['subject_name']).' </td>
                                <td> '.$part_info[0]['part_marks'].' </td>
                                <td> '.strtoupper($part_info[0]['part_class']).' </td>
                                </tr>
                        </table>
                    </td>
                    <td colspan=2>
                        <table border=1>
                            <tr>
                                <td> '.$part_info[1]['part_marks'].' </td>
                                <td> '.strtoupper($part_info[1]['part_class']).' </td>
                            </tr>
                        </table>
                    </td>
                    <td colspan=2>
                        <table border=1>
                            <tr>
                                <td> '.$part_info[2]['part_marks'].' </td>
                                <td> '.strtoupper($part_info[2]['part_class']).' </td>
                            </tr>
                        </table>
                    </td>
                    <td>&nbsp;</td>';
            $table .='</tr>';
            $SNO++;


                    }
                    $table .='<tr>
                    <td height="50" colspan=12>&nbsp;</td>
                </tr>';
        $table .='<tr>
                    <td colspan=4> DATE  </td>
                    <td colspan=4> CONTROLLER OF EXAMINATIONS   </td>
                    <td colspan=4> PRINCIPAL AND CHIEF CONTROLLER  </td>
                </tr></table>';

       
           /* $table .= '
                        <tr>                           
                            <td colspan=13 align="center"> 
                                <center><b><font size="4px">' . strtoupper($org_name) . '</font></b></center>
                                <center>' . strtoupper($org_address) . '</center>                                
                                <center>DETAILS OF CANDIDATES ELIGIBLE FOR THE AWARD OF DEGREE</center> 
                                <center> MONTH AND YEAR OF EXAMINATION '.$month_and_year.' </center>
                            </td>                           
                            
                        </tr>';
            $table .= '
                    <tr>
                        <td align="center"><b> S.NO </b></td>
                        <td align="center"><b> Reg No </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' name </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . ' CODE </b></td>
                        <td align="center"><b> BRANCH / SPECILIZATION </b></td>
                        <td align="center"><b> Gender </b></td>
                        <td align="center"><b> Total CGPA Secured </b></td>
                        <td align="center"><b> Total CGPA Maximum </b></td>
                        <td align="center"><b> ATTEMPT </b></td>
                        <td align="center"><b> Classification </b></td>
                        <td align="center"><b> Ref.No. of the University approving the admission of the student </b></td>
                        <td align="center"><b> Marks </b></td>
                        <td align="center"><b> Year Of Passing </b></td>
                        

                    </tr>';
            
            foreach ($final_student as $final_student1) 
            {
                    $attempt_check = MarkEntryMaster::find()->where(['year_of_passing'=>'','student_map_id'=>$final_student1['student_map_id']])->all();

                    $withdraw_check = MarkEntryMaster::find()->where(['grade_name'=>'WD','student_map_id'=>$final_student1['student_map_id']])->all();
                    $with_dr_count = 0;
                    if(!empty($withdraw_check) && count($withdraw_check)>0)
                    {
                        foreach ($withdraw_check as $key => $value) 
                        {
                            $withdraw_count = Yii::$app->db->createCommand('SELECT count(*) as count FROM coe_mark_entry_master WHERE student_map_id="'.$final_student1['student_map_id'].'" and result like "%pass%" and grade_name not like "%WD%" AND subject_map_id="'.$value['subject_map_id'].'"')->queryScalar();
                            $with_dr_count = !empty($withdraw_count) ? $with_dr_count+$withdraw_count : $with_dr_count;
                        }                        
                        $attempt = count($withdraw_check)==$with_dr_count?'WITH DRAW IN '.count($withdraw_check)." CLEARED IN SINLE ATTEMPTS" :'WITH DRAW IN '.count($withdraw_check)." CLEARED IN MULTIPLE ATTEMPTS";
                    }
                    else
                    {
                        $attempt = !empty($attempt_check) && count($attempt_check)>0 ?'YES':'NO';
                    }
                    $add_css = $attempt!='NO' ? 'style="background: #f24400; color: #fff; padding: 5px 0 5px 0;" ' : '';
                    $cgpa_calculation = ConfigUtilities::getCgpaCaluclation($final_student1['year'],$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id']);

                    $checkPrev_reg_num = StudentMapping::findOne($final_student1['student_map_id']);
                    $prev_reg_num = empty($checkPrev_reg_num->previous_reg_number)?0:$checkPrev_reg_num->previous_reg_number;
                    
                    if($prev_reg_num!=0)
                    {
                       $getoldStu = Student::findOne(['register_number'=>$prev_reg_num]);
                       $oldMap = StudentMapping::findOne(['student_rel_id'=>$getoldStu->coe_student_id]);
                       $prev_stu_map = $oldMap->coe_student_mapping_id; 
                       $cgpa_calculation = ConfigUtilities::getCgpaCaluclationRejoin($final_student1['course_batch_mapping_id'],$final_student1['student_map_id']);
                    }
                    
                    $table .= '
                        <tr '.$add_css.'>
                            <td align="center"> ' . $sn . ' </td>
                            <td align="center"> ' . $final_student1['register_number'] . ' </td>
                            <td align="center"> ' . $final_student1['name'] . ' </td>
                            <td align="center"> ' . $final_student1['degree_code'] . ' </td>
                            <td align="center"> ' . $final_student1['programme_name'] . ' </td>
                            <td align="center"> ' . $final_student1['gender'] . ' </td>
                            <td align="center"> ' . round($cgpa_calculation['final_cgpa'], 2) . ' </td>';
                    $Percentage = round($cgpa_calculation['final_cgpa'], 2);
                    $classification = ConfigUtilities::getClassification($Percentage,$final_student1['regulation_year'],$final_student1['register_number']);
                    $part_info = ConfigUtilities::getPartDetails($final_student1['register_number'],3);
                
         //$display_marks = $part_info['part_marks']=='-'?'-':$part_info['part_marks'].;
                    $year_of_passing = ConfigUtilities::getYearOfPassing($final_student1['year_of_passing']);
                    $table .= '<td align="center"> 10 </td>';                    

                    $table .= '<td align="center"> '.$attempt.' </td>';            
                    $table .= '<td align="center"> ' . $classification . ' </td>';
                    $table .= '<td align="center"> &nbsp; </td>';
                    $table .= '<td align="center"> '.$part_info['part_marks'] .' </td>';
                    $table .= '<td align="center"> '.$year_of_passing.' </td>';
                    $table .= '</tr>';
                    $sn++;
            }
            */


      /*  } else {
            return 0;
        }
        $table .= "<tr><td colspan=6> COE </td><td colspan=6> SIGNATURE OF PRINCIPAL </td></tr>";
        $table .= '</table>';
        if (isset($_SESSION['university_report_print'])) 
        {
            unset($_SESSION['university_report_print']);
        }
        $_SESSION['university_report_print'] = $table;
        return $table;
    }*/

    public function actionUniversityreportdata()
    {
         $model = new MarkEntry();
        $batch = Yii::$app->request->post('batch');
        $bat_map_id = Yii::$app->request->post('bat_map_id');
        $exam_term =Yii::$app->request->post('exam_term') ;
        //print_r($exam_term);exit;
        $passed_stu = array();
        $failed_stu = array();
        $get_batc_map = CoeBatDegReg::findOne($bat_map_id);

       $deg_info = Degree::findOne($get_batc_map->coe_degree_id);

        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $passed_student = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_subjects_mapping as C,coe_mark_entry_master as D where A.coe_student_id=B.student_rel_id and B.course_batch_mapping_id=C.batch_mapping_id and B.coe_student_mapping_id=D.student_map_id and C.coe_subjects_mapping_id=D.subject_map_id and B.course_batch_mapping_id='" . $bat_map_id . "' and C.batch_mapping_id='" . $bat_map_id . "' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and year_of_passing!='' and D.term='" . $exam_term . "'   order by A.register_number")->queryAll();

      //print_r( $passed_student);exit;
        
        $failed_student = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_subjects_mapping as C,coe_mark_entry_master as D where A.coe_student_id=B.student_rel_id and B.course_batch_mapping_id=C.batch_mapping_id and B.coe_student_mapping_id=D.student_map_id and C.coe_subjects_mapping_id=D.subject_map_id and B.course_batch_mapping_id='" . $bat_map_id . "' and C.batch_mapping_id='" . $bat_map_id . "' and year_of_passing='' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') order by A.register_number")->queryAll();
        
        foreach ($passed_student as $passed_student1) 
        {
            $passed_stu[] = $passed_student1['register_number'];
        }
        $a = array_unique($passed_stu);
        
        foreach ($failed_student as $failed_student1) {
            $failed_stu[] = $failed_student1['register_number'];
        }
        $b = array_unique($failed_stu);
        
        $final = array_diff($a, $b);

          $reg_num_in = '';
           $ug_maximum_marks = 3500;
           // $ug_maximum_marks = 3200;
            $pg_maximum_marks = 2250;
            $ug_maximum_credits = 140;
            $pg_maximum_credits = 90;

             $get_regnum = StuInfo::find()->where(['batch_map_id'=>$bat_map_id])->all();

            // print_r($get_regnum);exit;
           if(!empty($get_regnum) && count($get_regnum)>0 && $deg_info['degree_type']=='PG')
            {   
                foreach ($passed_student as $val) 
                {
                    $get_data = ConfigUtilities::getCreditDetails($val['register_number']);
                    if($get_data['part_credits']==$pg_maximum_credits && $pg_maximum_marks==$get_data['part_total_marks'])
                    {
                        $reg_num_in .="'".$val['register_number']."',";
                    }
                }
                $trim_reg = trim($reg_num_in,',');
            }
            else if(!empty($get_regnum) && count($get_regnum)>0 && $deg_info['degree_type']=='UG')
            {

                 foreach ($passed_student as $val) 
                {
                    $get_data = ConfigUtilities::getCreditDetails($val['register_number']);
                //print_r( $get_data);exit;

                    
                    if($get_data['part_credits']==$ug_maximum_credits && $ug_maximum_marks==$get_data['part_total_marks'])
                    {
                        $reg_num_in .="'".$val['register_number']."',";
                    }
                }
                $trim_reg = trim($reg_num_in,',');
            }
             else{
                 
            }
            if(empty($trim_reg))
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
               
            }

           
            
        

       $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,E.programme_name,H.subject_code,C.regulation_year,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.year,F.month,B.course_batch_mapping_id,F.year_of_passing,paper_no,F.result,max(F.year_of_passing) as last_appearance,round (sum(H.credit_points*F.grade_point)/sum(H.credit_points),5) as total FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id where G.batch_mapping_id='".$bat_map_id."' AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%')) and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') AND F.grade_name not IN ('U','wh','AB','ab','WH','ra','wd','WD') AND A.register_number IN ('".$trim_reg."')       group by A.register_number order by A.register_number, G.semester, paper_no";
        $final_student = Yii::$app->db->createCommand($get_stu_query)->queryAll();

        $last_appearance_of = Yii::$app->db->createCommand("SELECT max(F.year_of_passing) as last_appearance FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$bat_map_id."' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='') AND F.grade_name not IN ('U','wh','AB','ab','WH','ra','wd','WD')  group by A.register_number")->queryScalar();
        //print_r($last_appearance_of);exit;
        if(!empty($last_appearance_of))
        {
            $ex_plode = explode('-', $last_appearance_of);
            $month_name = Categorytype::findOne($ex_plode[0]);
            $month_and_year = strtoupper($month_name['description']."-".$ex_plode[1]);
        }
        else
        {
            $month_and_year = DATE('Y');
        }
        
        
        $table = "";
        $sn = 1;
        $query = new Query();
        if (count($final_student) > 0) 
        {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table=$body=$footer='';
            $table = '<table border=1 id="checkAllFeet" class="table table-responsive table-striped" align="center" >
                <tbody align="center">';
               $table .='<table  border="1"  class="table table-bordered table-responsive dum_edit_table table-hover" >';
        $table .='<tr><td colspan="12">SRI KRISHNA ARTS AND SCIENCE COLLEGE, (AUTONOMOUS), COIMBATORE - 641 008.</td></tr>';
        $table .='<tr><td colspan="12">MONTH AND YEAR OF EXAMINATIONS :  '.$month_and_year.'</td></tr>';
        foreach ($final_student as $key => $name) 

        {
             
        }
        $table .='<tr><td colspan="6" align="center">DEGREE :  '.$name['degree_code'].'</td><td colspan="6" align="center">BRANCH :  '.$name['programme_name'].'</td></tr>';
        foreach ($final_student as $valuesa) 
        {
            for ($a=1; $a <=5 ; $a++) { 
                $part_max_marks[] = ConfigUtilities::getPartDetails($valuesa['register_number'],$a);
            }
            break;
        }
         $table .='<tr>
                   <td>SNO</td>
                   <td>REGNO</td>
                   <td>NAME OF THE CANDIDATE</td>
                   <td>SEX</td>
                   ';

        $table .='<td colspan=3>PART I
                    <table border=1>
                    <tr>
                        <td>SUBJECT NAME</td>
                        <td>MARKS SECURED OUT OF '.$part_max_marks[0]['part_total_marks'].'</td>
                        <td>CLASS</td>
                    </tr>
                   </table></td>';
        $table .='<td colspan=2>PART II
                    <table border=1>
                    <tr>
                        <td>MARKS SECURED OUT OF '.$part_max_marks[1]['part_total_marks'].'</td>
                        <td>CLASS</td>
                    </tr>
                   </table></td>';
        $table .='<td colspan=2>PART III
                    <table border=1>
                    <tr>
                        <td>MARKS SECURED OUT OF '.$part_max_marks[2]['part_total_marks'].'</td>
                        <td>CLASS</td>
                    </tr>
                   </table></td>';
        $table .='<td>FOR COE USE</td>';
        $SNO = 1;
         //print_r($valuesa);exit;
          $temp_part1=''; $temp_part2='';
         foreach ($final_student as $value) 
        {           
            //print_r($final_student);exit;
            $part_info = array_filter(['']);
             $temp_part1=''; $temp_part2='';
           
            for ($i=1; $i <=5 ; $i++) 
            { 
                $part_info[] = ConfigUtilities::getPartDetails($value['register_number'],$i);
                //print_r($part_info);exit;
               
           
      

//print_r($temp_part1);exit;
 


            
    
      
            }

            if(($part_info[0]['part_class']=='SECOND CLASS' || $part_info[1]['part_class']=='SECOND CLASS')  && $part_info[2]['part_class']=='FIRST CLASS WITH DISTINCTION')
      {
        $part_info[2]['part_class']='FIRST CLASS';
      }
      else
      {
     $part_info[2]['part_class'];
      }
            $table .='<tr>
                        <td>'.$SNO.'</td>
                        <td>'.strtoupper($value['register_number']).'</td>
                        <td>'.strtoupper($value['name']).'</td>
                        <td>'.$value['gender'].'</td>';
                        $table .='<td colspan=3>
                         <table border=1>
                            
                            <tr>
                           <td> '.strtoupper($value['subject_name']).' </td>
                                <td> '.$part_info[0]['part_marks'].' </td>
                                <td> '.strtoupper($part_info[0]['part_class']).' </td>
                                </tr>
                        </table>
                    </td>
                    <td colspan=2>
                        <table border=1>
                            <tr>
                                <td> '.$part_info[1]['part_marks'].' </td>
                                <td> '.strtoupper($part_info[1]['part_class']).' </td>
                            </tr>
                        </table>
                    </td>
                    <td colspan=2>
                        <table border=1>
                            <tr>
                                <td> '.$part_info[2]['part_marks'].' </td>
                                <td> '.strtoupper($part_info[2]['part_class']).' </td>
                            </tr>
                        </table>
                    </td>
                    <td>&nbsp;</td>';
            $table .='</tr>';
            $SNO++;
    
            


                    }
                    $table .='<tr>
                    <td height="50" colspan=12>&nbsp;</td>
                </tr>';
        $table .='<tr>
                    <td colspan=4> DATE  </td>
                    <td colspan=4> CONTROLLER OF EXAMINATIONS   </td>
                    <td colspan=4> PRINCIPAL AND CHIEF CONTROLLER  </td>
                </tr></table>';

       
           /* $table .= '
                        <tr>                           
                            <td colspan=13 align="center"> 
                                <center><b><font size="4px">' . strtoupper($org_name) . '</font></b></center>
                                <center>' . strtoupper($org_address) . '</center>                                
                                <center>DETAILS OF CANDIDATES ELIGIBLE FOR THE AWARD OF DEGREE</center> 
                                <center> MONTH AND YEAR OF EXAMINATION '.$month_and_year.' </center>
                            </td>                           
                            
                        </tr>';
            $table .= '
                    <tr>
                        <td align="center"><b> S.NO </b></td>
                        <td align="center"><b> Reg No </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' name </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . ' CODE </b></td>
                        <td align="center"><b> BRANCH / SPECILIZATION </b></td>
                        <td align="center"><b> Gender </b></td>
                        <td align="center"><b> Total CGPA Secured </b></td>
                        <td align="center"><b> Total CGPA Maximum </b></td>
                        <td align="center"><b> ATTEMPT </b></td>
                        <td align="center"><b> Classification </b></td>
                        <td align="center"><b> Ref.No. of the University approving the admission of the student </b></td>
                        <td align="center"><b> Marks </b></td>
                        <td align="center"><b> Year Of Passing </b></td>
                        

                    </tr>';
            
            foreach ($final_student as $final_student1) 
            {
                    $attempt_check = MarkEntryMaster::find()->where(['year_of_passing'=>'','student_map_id'=>$final_student1['student_map_id']])->all();

                    $withdraw_check = MarkEntryMaster::find()->where(['grade_name'=>'WD','student_map_id'=>$final_student1['student_map_id']])->all();
                    $with_dr_count = 0;
                    if(!empty($withdraw_check) && count($withdraw_check)>0)
                    {
                        foreach ($withdraw_check as $key => $value) 
                        {
                            $withdraw_count = Yii::$app->db->createCommand('SELECT count(*) as count FROM coe_mark_entry_master WHERE student_map_id="'.$final_student1['student_map_id'].'" and result like "%pass%" and grade_name not like "%WD%" AND subject_map_id="'.$value['subject_map_id'].'"')->queryScalar();
                            $with_dr_count = !empty($withdraw_count) ? $with_dr_count+$withdraw_count : $with_dr_count;
                        }                        
                        $attempt = count($withdraw_check)==$with_dr_count?'WITH DRAW IN '.count($withdraw_check)." CLEARED IN SINLE ATTEMPTS" :'WITH DRAW IN '.count($withdraw_check)." CLEARED IN MULTIPLE ATTEMPTS";
                    }
                    else
                    {
                        $attempt = !empty($attempt_check) && count($attempt_check)>0 ?'YES':'NO';
                    }
                    $add_css = $attempt!='NO' ? 'style="background: #f24400; color: #fff; padding: 5px 0 5px 0;" ' : '';
                    $cgpa_calculation = ConfigUtilities::getCgpaCaluclation($final_student1['year'],$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id']);

                    $checkPrev_reg_num = StudentMapping::findOne($final_student1['student_map_id']);
                    $prev_reg_num = empty($checkPrev_reg_num->previous_reg_number)?0:$checkPrev_reg_num->previous_reg_number;
                    
                    if($prev_reg_num!=0)
                    {
                       $getoldStu = Student::findOne(['register_number'=>$prev_reg_num]);
                       $oldMap = StudentMapping::findOne(['student_rel_id'=>$getoldStu->coe_student_id]);
                       $prev_stu_map = $oldMap->coe_student_mapping_id; 
                       $cgpa_calculation = ConfigUtilities::getCgpaCaluclationRejoin($final_student1['course_batch_mapping_id'],$final_student1['student_map_id']);
                    }
                    
                    $table .= '
                        <tr '.$add_css.'>
                            <td align="center"> ' . $sn . ' </td>
                            <td align="center"> ' . $final_student1['register_number'] . ' </td>
                            <td align="center"> ' . $final_student1['name'] . ' </td>
                            <td align="center"> ' . $final_student1['degree_code'] . ' </td>
                            <td align="center"> ' . $final_student1['programme_name'] . ' </td>
                            <td align="center"> ' . $final_student1['gender'] . ' </td>
                            <td align="center"> ' . round($cgpa_calculation['final_cgpa'], 2) . ' </td>';
                    $Percentage = round($cgpa_calculation['final_cgpa'], 2);
                    $classification = ConfigUtilities::getClassification($Percentage,$final_student1['regulation_year'],$final_student1['register_number']);
                    $part_info = ConfigUtilities::getPartDetails($final_student1['register_number'],3);
                
         //$display_marks = $part_info['part_marks']=='-'?'-':$part_info['part_marks'].;
                    $year_of_passing = ConfigUtilities::getYearOfPassing($final_student1['year_of_passing']);
                    $table .= '<td align="center"> 10 </td>';                    

                    $table .= '<td align="center"> '.$attempt.' </td>';            
                    $table .= '<td align="center"> ' . $classification . ' </td>';
                    $table .= '<td align="center"> &nbsp; </td>';
                    $table .= '<td align="center"> '.$part_info['part_marks'] .' </td>';
                    $table .= '<td align="center"> '.$year_of_passing.' </td>';
                    $table .= '</tr>';
                    $sn++;
            }
            */


        } else {
            return 0;
        }
        $table .= "<tr><td colspan=6> COE </td><td colspan=6> SIGNATURE OF PRINCIPAL </td></tr>";
        $table .= '</table>';
        if (isset($_SESSION['university_report_print'])) 
        {
            unset($_SESSION['university_report_print']);
        }
        $_SESSION['university_report_print'] = $table;
        return $table;
    }
    public function actionUniversityreportPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['university_report_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Universityreport.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #CCC;
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
            'options' => ['title' => 'University Report Information'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['University Report Information' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelUniversityreport()
    {        
        $content = $_SESSION['university_report_print'];           
        $fileName = "University Report Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    //University report ends here

    //University report starts here
    public function actionUniversityreportCompleted()
    {
        $model = new MarkEntry();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to University ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('universityreport-completed', [
            'model' => $model,
        ]);
    }
    public function actionUniversityreportdatacomp()
    {
        $batch = Yii::$app->request->post('batch');
        $bat_map_id = Yii::$app->request->post('bat_map_id');
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $passed_stu = array();
        $failed_stu = array();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $passed_student = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_subjects_mapping as C,coe_mark_entry_master as D where A.coe_student_id=B.student_rel_id and B.course_batch_mapping_id=C.batch_mapping_id and B.coe_student_mapping_id=D.student_map_id and C.coe_subjects_mapping_id=D.subject_map_id and B.course_batch_mapping_id='" . $bat_map_id . "' and C.batch_mapping_id='" . $bat_map_id . "' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and year_of_passing!='' order by A.register_number")->queryAll();
       
        $failed_student = Yii::$app->db->createCommand("select * from coe_student as A,coe_student_mapping as B,coe_subjects_mapping as C,coe_mark_entry_master as D where A.coe_student_id=B.student_rel_id and B.course_batch_mapping_id=C.batch_mapping_id and B.coe_student_mapping_id=D.student_map_id and C.coe_subjects_mapping_id=D.subject_map_id and B.course_batch_mapping_id='" . $bat_map_id . "' and C.batch_mapping_id='" . $bat_map_id . "' and year_of_passing='' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') order by A.register_number")->queryAll();
        
        foreach ($passed_student as $passed_student1) {
            $passed_stu[] = $passed_student1['register_number'];
        }
        $a = array_unique($passed_stu);
        
        foreach ($failed_student as $failed_student1) {
            $failed_stu[] = $failed_student1['register_number'];
        }
        $b = array_unique($failed_stu);
        $final = array_diff($a, $b);
        

        $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,E.programme_name,H.subject_code,C.regulation_year,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.year,F.month,B.course_batch_mapping_id,F.year_of_passing,paper_no,F.result,max(F.year_of_passing) as last_appearance, round (sum(H.credit_points*F.grade_point)/sum(H.credit_points),5) as total  FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id where G.batch_mapping_id='".$bat_map_id."' AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='' and subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where student_map_id=F.student_map_id and result like '%pass%')) and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') AND F.grade_name not IN ('U','wh','AB','ab','WH','ra','wd','WD') and F.year='".$year."' and F.month='".$month."'  group by A.register_number order by A.register_number, G.semester, paper_no";
        $final_student = Yii::$app->db->createCommand($get_stu_query)->queryAll();

        $last_appearance_of = Yii::$app->db->createCommand("SELECT max(F.year_of_passing) as last_appearance FROM coe_student as A JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id JOIN coe_mark_entry_master as F ON F.student_map_id=B.coe_student_mapping_id JOIN coe_subjects_mapping as G ON G.coe_subjects_mapping_id=F.subject_map_id JOIN coe_subjects as H ON H.coe_subjects_id=G.subject_id JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  where G.batch_mapping_id='".$bat_map_id."' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') AND B.coe_student_mapping_id NOT IN(select student_map_id from coe_mark_entry_master where year_of_passing='') AND F.grade_name not IN ('U','wh','AB','ab','WH','ra','wd','WD') and F.year<='".$year."'   group by A.register_number")->queryScalar();
        $month_name_old= Categorytype::findOne($month);
        $month_and_year = DATE('Y')." - ".$month_name_old->description;
        if(!empty($last_appearance_of))
        {
            $ex_plode = explode('-', $last_appearance_of);
            $month_name = Categorytype::findOne($ex_plode[0]);
            $month_and_year = strtoupper($month_name['description']."-".$ex_plode[1]);
        }

        
        $table = "";
        $sn = 1;
        $query = new Query();
        if (count($final_student) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table border=1 id="checkAllFeet" class="table table-responsive table-striped" align="center" >
                <tbody align="center">';
            $table .= '
                        <tr>                           
                            <td colspan=13 align="center"> 
                                <center><b><font size="4px">' . strtoupper($org_name) . '</font></b></center>
                                <center>' . strtoupper($org_address) . '</center>                                
                                <center>DETAILS OF CANDIDATES ELIGIBLE FOR THE AWARD OF DEGREE</center> 
                                <center> MONTH AND YEAR OF EXAMINATION '.$month_and_year.' </center>
                            </td>                           
                            
                        </tr>';
            $table .= '
                    <tr>
                        <td align="center"><b> S.NO </b></td>
                        <td align="center"><b> Reg No </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT) . ' name </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . ' CODE </b></td>
                        <td align="center"><b> BRANCH / SPECILIZATION </b></td>
                        <td align="center"><b> Gender </b></td>
                        <td align="center"><b> Total CGPA Secured </b></td>
                        <td align="center"><b> Total CGPA Maximum </b></td>
                        <td align="center"><b> ATTEMPT </b></td>
                        <td align="center"><b> Classification </b></td>
                        <td align="center"><b> Ref.No. of the University approving the admission of the student </b></td>
                        <td align="center"><b> Remarks </b></td>
                        <td align="center"><b> Year of Passing </b></td>
                    </tr>';
            
            foreach ($final_student as $final_student1) 
            {
                    $attempt_check = MarkEntryMaster::find()->where(['year_of_passing'=>'','student_map_id'=>$final_student1['student_map_id']])->all();

                    $withdraw_check = MarkEntryMaster::find()->where(['grade_name'=>'WD','student_map_id'=>$final_student1['student_map_id']])->all();
                    $with_dr_count = 0;
                    if(!empty($withdraw_check) && count($withdraw_check)>0)
                    {
                        foreach ($withdraw_check as $key => $value) 
                        {
                            $withdraw_count = Yii::$app->db->createCommand('SELECT count(*) as count FROM coe_mark_entry_master WHERE student_map_id="'.$final_student1['student_map_id'].'" and result like "%pass%" and grade_name not like "%WD%" AND subject_map_id="'.$value['subject_map_id'].'"')->queryScalar();
                            $with_dr_count = !empty($withdraw_count) ? $withdraw_count : $with_dr_count;
                        }
                        $attempt = count($withdraw_check)==$with_dr_count?'WITH DRAW IN '.count($withdraw_check)." CLEARED IN SINLE ATTEMPTS" :'WITH DRAW IN '.count($withdraw_check)." CLEARED IN MULTIPLE ATTEMPTS";
                    }
                    else
                    {
                        $attempt = !empty($attempt_check) && count($attempt_check)>0 ?'YES':'NO';
                    }
                    $add_css = $attempt!='NO' ? 'style="background: #f24400; color: #fff; padding: 5px 0 5px 0;" ' : '';

                    $cgpa_calculation = ConfigUtilities::getCgpaCaluclation($final_student1['year'],$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id']);

                    $table .= '
                        <tr '.$add_css.'>
                            <td align="center"> ' . $sn . ' </td>
                            <td align="center"> ' . $final_student1['register_number'] . ' </td>
                            <td align="center"> ' . $final_student1['name'] . ' </td>
                            <td align="center"> ' . $final_student1['degree_code'] . ' </td>
                            <td align="center"> ' . $final_student1['programme_name'] . ' </td>
                            <td align="center"> ' . $final_student1['gender'] . ' </td>
                            <td align="center"> ' . $cgpa_calculation['final_cgpa'] . ' </td>';
                    $Percentage = round($cgpa_calculation['final_cgpa'], 2);
                    $classification = ConfigUtilities::getClassification($Percentage,$final_student1['regulation_year'],$final_student1['register_number']);
                    $year_of_passing = ConfigUtilities::getYearOfPassing($final_student1['year_of_passing']);
                    $table .= '<td align="center"> 10 </td>';                    

                    $table .= '<td align="center"> '.$attempt.' </td>';            
                    $table .= '<td align="center"> ' . $classification . ' </td>';
                    $table .= '<td align="center"> &nbsp; </td>';
                    $table .= '<td align="center"> &nbsp; </td>';
                    $table .= '<td align="center">'.$year_of_passing.' </td>';
                    $table .= '</tr>';
                    $sn++;
            }
        } else {
            return 0;
        }
        $table .= "<tr><td colspan=6> COE </td><td colspan=6> SIGNATURE OF PRINCIPAL </td></tr>";
        $table .= '</table>';
        if (isset($_SESSION['university_report_print'])) {
            unset($_SESSION['university_report_print']);
        }
        $_SESSION['university_report_print'] = $table;
        return $table;
    }
    public function actionUniversityreportCompletedPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['university_report_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Universityreport.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #CCC;
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
            'options' => ['title' => 'University Report Information'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['University Report Information' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelUniversityCompletedreport()
    {        
        $content = $_SESSION['university_report_print'];           
        $fileName = "University Report Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    //AdditionalCredits Starts Here
    public function actionAdditionalcredits()
    {
        $model = new MarkEntry();
        $add_credits = new AdditionalCredits();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (isset($_POST['add_submit_btn'])) 
        {
            
            $sn = Yii::$app->request->post('sn');
            $subject_code = $_POST['add_sub_code'];
            $subject_name = $_POST['add_sub_name'];
            $credit = $_POST['add_credits'];
            $created_by = Yii::$app->user->getId();
            $created_at = date("Y-m-d H:i:s");            
            $exam_year = $_POST['MarkEntry']['year'];
            $exam_month = $_POST['month'];
            for ($k = 1; $k <= $sn; $k++) 
            {
                if (isset($_POST['add' . $k]) && $_POST['add' . $k]=='on') 
                {
                    $query = new Query();
                    $query->select('a.coe_student_mapping_id')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_student b', 'a.student_rel_id=b.coe_student_id')
                        ->where(['b.register_number' => $_POST['reg_num' . $k]])
                        ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                        ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_map_id = $query->createCommand()->queryScalar();
                    $marks = $_POST['actxt_' . $k];
                    $result = $_POST['acresult_' . $k];
                    $grade_name = $result=='Pass' || $result=='PASS' || $result=='PASS' ? $_POST['grade_' . $k]:'U';
                    $grade_point = $result=='Pass' || $result=='PASS' || $result=='PASS' ? $_POST['grade_point_' . $k]:'0';
                    $result = $_POST['acresult_' . $k];
                    $year_of_passing = $result=='Pass' || $result=='PASS' || $result=='PASS' ? $exam_month."-".$exam_year: "";

                    $check_query = new Query();
                    $check_query->select('*')
                        ->from('coe_additional_credits')
                        ->where(['student_map_id' => $student_map_id, 'subject_code' => $subject_code]);
                    $check_exist = $check_query->createCommand()->queryAll();
                    if (empty($check_exist)) 
                    {
                        $insert_additional_credit = Yii::$app->db->createCommand("insert into coe_additional_credits values('','" . $exam_year . "','" . $exam_month . "','" . $student_map_id . "','" . $subject_code . "','" . $subject_name . "','" . $credit . "','" . $marks . "','" . $grade_point . "','" . $grade_name . "','" . $result . "','" . $year_of_passing . "','" . $created_at . "','" . $created_by . "','" . $created_at . "','" . $created_by . "') ")->execute();
                    }
                }
            }
            Yii::$app->ShowFlashMessages->setMsg('Success', 'Additional Credits Successfully Inserted!!!');
            return $this->redirect(['additionalcredits',]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Mark Entry');
            return $this->render('additionalcredits', [
                'model' => $model, 'add_credits' => $add_credits,
            ]);
        }
    }
    public function actionAdditionalcreditsArts()
    {
        $model = new MarkEntry();
        $add_credits = new AdditionalCredits();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        if (isset($_POST['add_submit_btn'])) 
        {
            $sn = Yii::$app->request->post('sn');
            $subject_code = $_POST['add_sub_code'];
            $subject_name = $_POST['add_sub_name'];
            $credit = $_POST['add_credits'];
            $cia_min = $_POST['AdditionalCredits']['cia_minimum'];
            $cia_max = $_POST['AdditionalCredits']['cia_maximum'];
            $ese_min = $_POST['AdditionalCredits']['ese_minimum'];
            $ese_max = $_POST['AdditionalCredits']['ese_maximum'];
            $total_min = $_POST['AdditionalCredits']['total_minimum_pass'];
            $created_by = Yii::$app->user->getId();
            $created_at = date("Y-m-d H:i:s");            
            $exam_year = $_POST['MarkEntry']['year'];
            $exam_month = $_POST['month'];
            $bat_val = $_POST['bat_val'];
            $bat_map_val = $_POST['bat_map_val'];
            $semester= $_POST['semester'];

            $final_sub_total= $cia_max+$ese_max;
            $coe_batch_id = Batch::findOne($bat_val);
              $regulation = CoeBatDegReg::find()->where(['coe_batch_id'=>$coe_batch_id->coe_batch_id,'coe_bat_deg_reg_id'=>$bat_map_val])->one();
              $grade_details = Regulation::find()->where(['regulation_year'=>$regulation->regulation_year])->all();
              require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
             // $sem_verify = ConfigUtilities::SemCaluclation($exam_year,$exam_month,$bat_map_val);
            for ($k = 1; $k <= $sn; $k++) 
            {
                if (isset($_POST['add' . $k]) && $_POST['add' . $k]=='on') 
                {
                    $query = new Query();
                    $query->select('a.coe_student_mapping_id')
                        ->from('coe_student_mapping a')
                        ->join('JOIN', 'coe_student b', 'a.student_rel_id=b.coe_student_id')
                        ->where(['b.register_number' => $_POST['reg_num' . $k]])
                        ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                        ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    $student_map_id = $query->createCommand()->queryScalar();
                    $out_of_maximum = $_POST['actxt_' . $k];
                    $cia_marks = $grade_cia_check = $cia_max==0?0:$_POST['actxttotal_' . $k];
                    $ese_marks = $ese_max==0?0:$_POST['actxttotal_' . $k];
                    $total_marks = $_POST['actxttotal_' . $k];
                    $result = $_POST['acresult_' . $k];
                  $arts_college_grade = round(($out_of_maximum/$final_sub_total)*10,1);
                  
                    //$arts_college_grade = round(($total_marks/$final_sub_total)*10,1);
                  
                      foreach($grade_details as $value) 
                      {
                          if($value['grade_point_to']!='')
                          {                            
                              if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                              {
                                  if($grade_cia_check<$cia_min || $ese_marks<$ese_min || $total_marks<$total_min)
                                  {
                                    $stu_result_data = ['result'=>'Fail','total_marks'=>$total_marks,'grade_name'=>'U','grade_point'=>0,'year_of_passing'=>'','ese_marks'=>$ese_marks];        
                                  }      
                                  else
                                  {
                                    $grade_name_prit = $value['grade_name'];
                                    $grade_point_arts = $arts_college_grade;
                                    
                                    $stu_result_data = ['result'=>'Pass','total_marks'=>$total_marks,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'ese_marks'=>$ese_marks,'year_of_passing'=>$exam_month."-".$exam_year];
                                    
                                    
                                  }
                              } // Grade Point Caluclation
                          } // Not Empty of the Grade Point                               
                      }
                    
                    $check_query = new Query();
                    $check_query->select('*')
                        ->from('coe_additional_credits')
                        ->where(['student_map_id' => $student_map_id, 'subject_code' => $subject_code]);
                    $check_exist = $check_query->createCommand()->queryAll();

                    if (empty($check_exist)) 
                    {
                        $model_save = new AdditionalCredits();
                        $model_save->exam_year = $exam_year;
                        $model_save->exam_month = $exam_month;
                        $model_save->student_map_id = $student_map_id;
                        $model_save->subject_code = $subject_code;
                        $model_save->subject_name = $subject_name;
                        $model_save->credits = $credit;
                        $model_save->semester = $semester;
                        $model_save->out_of_maximum = $out_of_maximum;
                        $model_save->CIA = $cia_marks;
                        $model_save->ESE = $stu_result_data['ese_marks'];
                        $model_save->total = $stu_result_data['total_marks'];
                        $model_save->result = $stu_result_data['result'];
                        $model_save->grade_point = $stu_result_data['grade_point'];
                        $model_save->grade_name = $stu_result_data['grade_name'];
                        $model_save->year_of_passing = $stu_result_data['year_of_passing'];
                        $model_save->cia_minimum = $cia_min;                        
                        $model_save->cia_maximum = $cia_max;
                        $model_save->ese_minimum = $ese_min;
                        $model_save->ese_maximum = $ese_max;
                        $model_save->total_minimum_pass = $total_min;
                        $model_save->created_at = $created_at;
                        $model_save->created_by = $created_by;
                        $model_save->updated_at = $created_at;
                        $model_save->updated_by = $created_by;
                        if($model_save->save(false))
                        {
                            Yii::$app->ShowFlashMessages->setMsg('SUCCESS', 'ADDITIONAL CREDITS INSERTED SUCCESSFULLY!!!');
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('ERROR', 'SOMETHING WRONG');
                        }
                    }
                }
            }
            return $this->redirect(['additionalcredits-arts']);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Mark Entry');
            return $this->render('additionalcredits-arts', [
                'model' => $model, 'add_credits' => $add_credits,
            ]);
        }
    }
    public function actionInternetCopyAdditionalCredits()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        

        if (isset($_POST['internetcopybutton'])) 
        {
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId();
           
            $internet_copy_query = new Query();
            
            $whereCondition = [                        
                        'a.exam_year' => $_POST['year'], 'a.exam_month' => $_POST['month']
                    ];
            $internet_copy_query->select('c.register_number,c.name,c.dob,subject_code,ese_maximum,ese_minimum,cia_maximum,cia_minimum,a.CIA,a.ESE,a.result,a.grade_name,a.student_map_id,a.exam_year as year,a.exam_month as month,degree_code,programme_name')
                ->from('coe_additional_credits a')
                ->join('JOIN', 'coe_student_mapping b', 'a.student_map_id=b.coe_student_mapping_id')
                ->join('JOIN', 'coe_student c', 'b.student_rel_id=c.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');
           
            $internet_copy_query->where($whereCondition);           
            $internet_copy_query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->orderBy('c.register_number');
            $internet_copy = $internet_copy_query->createCommand()->queryAll();
            array_multisort(array_column($internet_copy, 'register_number'),  SORT_ASC, $internet_copy);

            if (count($internet_copy) > 0) {
                return $this->render('internet-copy-additional-credits', [
                    'model' => $model, 'galley' => $galley, 'internet_copy' => $internet_copy,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('internet-copy-additional-credits', [
                    'model' => $model, 'galley' => $galley,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Internet Copy');
            return $this->render('internet-copy-additional-credits', [
                'model' => $model, 'galley' => $galley,
            ]);
        }
    }
    public function actionExcelInternetCopyAdditionalCreditsPdf()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();        
        $content = $_SESSION['internetcopy_additional_print'];
        $fileName = "ADDITIONAL CREDITS INTERNET COPY - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionAdditionalcreditsUpdate()
    {
        $model = new MarkEntry();
        $add_credits = new AdditionalCredits();       
        if (isset($_POST['add_submit_btn'])) 
        {
            return $this->redirect(['additionalcredits-update']);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Subject Name Update');
            return $this->render('additionalcredits-update', [
                'model' => $model, 'add_credits' => $add_credits,
            ]);
        }
    }
    //AdditionalCredits Ends Here
    //Withdraw starts here
    public function actionWithdraw()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();
        $sn = Yii::$app->request->post('sn');
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        for ($k = 1; $k <= $sn; $k++) {
            if (isset($_POST["withdraw" . $k])) {
                Yii::$app->db->createCommand("update coe_mark_entry_master set withdraw='wd',updated_by='".$updated_by."',updated_at='".$updated_at."',grade_name='WD' where student_map_id='" . $_POST['stu_map_id'] . "' and subject_map_id='" . $_POST['sub_code' . $k] . "' and year='" . $_POST['withdraw_year'] . "' and month='" . $_POST['withdraw_month'] . "'")->execute();
            } else {
                Yii::$app->db->createCommand("update coe_mark_entry_master set withdraw='NULL', updated_by='".$updated_by."',updated_at='".$updated_at."' where student_map_id='" . $_POST['stu_map_id'] . "' and subject_map_id='" . $_POST['sub_code' . $k] . "' and year='" . $_POST['withdraw_year'] . "' and month='" . $_POST['withdraw_month'] . "'")->execute();
            }
            Yii::$app->ShowFlashMessages->setMsg('Success', 'With Draw Status Updated Successfully!!');
        }
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Withdraw Entry');
        return $this->render('withdraw', ['model' => $model, 'student' => $student, 'subject' => $subject]);
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
            return $this->redirect(['withdraw']);
        }

        $mark_check_fail = Yii::$app->db->createCommand("select coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry_master as E where A.coe_subjects_id=B.subject_id and C.coe_student_id=D.student_rel_id and B.coe_subjects_mapping_id=E.subject_map_id and D.coe_student_mapping_id=E.student_map_id and C.register_number='" . $reg . "' and B.semester<='" . $sem . "' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and E.year<='" . $year . "' and E.month<='" . $month . "' and result like '%fail%' ")->queryAll();

        $check_withdraw = Yii::$app->db->createCommand("select coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry_master as E where A.coe_subjects_id=B.subject_id and C.coe_student_id=D.student_rel_id and B.coe_subjects_mapping_id=E.subject_map_id and D.coe_student_mapping_id=E.student_map_id and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and C.register_number='" . $reg . "' and E.withdraw like '%WD%' ")->queryAll();

        if(!empty($mark_check_fail) || !empty($check_withdraw))
        {
            Yii::$app->ShowFlashMessages->setMsg('Error', '<b> '.$reg. ' NOT <b>ELIGIBLE</b> for WITHDRAWAL');
            return $this->redirect(['withdraw']);
        }
        $sub_list = Yii::$app->db->createCommand("select coe_subjects_mapping_id,subject_code,subject_name,coe_student_mapping_id from coe_subjects as A,coe_subjects_mapping as B,coe_student as C,coe_student_mapping as D,coe_mark_entry_master as E where A.coe_subjects_id=B.subject_id and C.coe_student_id=D.student_rel_id and B.coe_subjects_mapping_id=E.subject_map_id and D.coe_student_mapping_id=E.student_map_id and C.register_number='" . $reg . "' and B.semester='" . $sem . "' and status_category_type_id NOT IN('".$det_cat_type."','".$det_disc_type."') and E.year='" . $year . "' and E.month='" . $month . "'")->queryAll();
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
    public function actionViewTransparencySubject()
    {
        $model = new Revaluation();
        $markentry = new MarkEntry();
        if (isset($_POST['view_reval_btn'])) 
        {
            $join_in_query='where';
            $select_column = ',A.subject_code,A.subject_name ';
            $add_where_condition = '';
            if(isset($_POST['Revaluation']['is_transparency'][0]) && $_POST['Revaluation']['is_transparency'][0]=='yes')
            {
                $join_in_query = ' JOIN coe_dummy_number as F WHERE F.student_map_id=E.student_map_id and F.subject_map_id=E.subject_map_id and F.year=E.year and F.month=E.month and  ';
                $select_column .= ',F.dummy_number ';
                $add_where_condition = ' and F.year="'.$_POST['mark_year'].'" and F.month="'.$_POST['month'].'"  ';
            }
            
            $revaluation = Yii::$app->db->createCommand("select C.register_number,E.year,DF.description as month,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id JOIN coe_category_type as DF ON DF.coe_category_type_id=E.month ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and  A.subject_code='" . $_POST['subject_code'] . "' $add_where_condition group by C.register_number,A.subject_code order by C.register_number")->queryAll();
            
            if (count($revaluation) > 0) {
                return $this->render('view-transparency-subject', [
                    'model' => $model,
                    'markentry' => $markentry,
                    'revaluation' => $revaluation,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Tranparancy");
                return $this->render('view-transparency-subject', [
                    'model' => $model,
                    'markentry' => $markentry,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Tranparancy');
            return $this->render('view-transparency-subject', [
                'model' => $model,
                'markentry' => $markentry,
            ]);
        }
    }
    public function actionTransparencyViewPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['transparency_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Tranparancy.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => 'Tranparancy Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Tranparancy Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelTransparencyview()
    {
        
        $content = $_SESSION['transparency_print'];   
        $fileName = "Tranparancy Data " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionViewTransparency()
    {
        $model = new Revaluation();
        $markentry = new MarkEntry();
        if (isset($_POST['view_reval_btn'])) 
        {
            $join_in_query='where';
            $select_column = ',A.subject_code,A.subject_name ';
            $add_where_condition = '';
            if(isset($_POST['Revaluation']['is_transparency'][0]) && $_POST['Revaluation']['is_transparency'][0]=='yes')
            {
                $join_in_query = ' JOIN coe_dummy_number as F WHERE F.student_map_id=E.student_map_id and F.subject_map_id=E.subject_map_id and F.year=E.year and F.month=E.month and ';
                $select_column .= ',F.dummy_number ';
                $add_where_condition = ' and F.year="'.$_POST['mark_year'].'" and F.month="'.$_POST['month'].'"  ';
            }
            $revaluation = Yii::$app->db->createCommand("select C.register_number,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and G.coe_programme_id='".$_POST['bat_map_val']."' $add_where_condition and H.coe_programme_id='".$_POST['bat_map_val']."' group by C.register_number,A.subject_code order by C.register_number")->queryAll();
            
            if (count($revaluation) > 0) {
                return $this->render('view-transparency', [
                    'model' => $model,
                    'markentry' => $markentry,
                    'revaluation' => $revaluation,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Tranparancy");
                return $this->render('view-transparency', [
                    'model' => $model,
                    'markentry' => $markentry,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Tranparancy');
            return $this->render('view-transparency', [
                'model' => $model,
                'markentry' => $markentry,
            ]);
        }
    }

    public function actionViewTransparencyDept()
    {
        $model = new Revaluation();
        $markentry = new MarkEntry();
        if (isset($_POST['view_reval_btn'])) 
        {
            $join_in_query='where';
            $select_column = ',A.subject_code,A.subject_name ';
            $add_where_condition = '';
            if(isset($_POST['Revaluation']['is_transparency'][0]) && $_POST['Revaluation']['is_transparency'][0]=='yes')
            {
                $join_in_query = ' JOIN coe_dummy_number as F WHERE F.student_map_id=E.student_map_id and F.subject_map_id=E.subject_map_id and F.year=E.year and F.month=E.month and ';
                $select_column .= ',F.dummy_number ';
                $add_where_condition = ' and F.year="'.$_POST['mark_year'].'" and F.month="'.$_POST['month'].'"  ';
            }
            if(isset($_POST['bat_val']) && !empty($_POST['bat_val']) && isset($_POST['month']) && isset($_POST['bat_map_val']) && !empty($_POST['bat_map_val']))
            {
                $revaluation = Yii::$app->db->createCommand("select C.register_number,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_batch as abc ON abc.coe_batch_id=G.coe_batch_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and G.coe_batch_id='".$_POST['bat_val']."' $add_where_condition and abc.coe_batch_id='".$_POST['bat_val']."' and G.coe_programme_id='".$_POST['bat_map_val']."' and H.coe_programme_id='".$_POST['bat_map_val']."' group by C.register_number,A.subject_code order by A.subject_code")->queryAll();

            }
            else if(isset($_POST['bat_val']) && !empty($_POST['bat_val']) && isset($_POST['month']))
            {
                $revaluation = Yii::$app->db->createCommand("select C.register_number,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_batch as abc ON abc.coe_batch_id=G.coe_batch_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and G.coe_batch_id='".$_POST['bat_val']."' $add_where_condition and abc.coe_batch_id='".$_POST['bat_val']."' group by C.register_number,A.subject_code order by A.subject_code")->queryAll();

            }
            else if(isset($_POST['bat_map_val']) && !empty($_POST['bat_map_val'])  && isset($_POST['month']))
            {
                $revaluation = Yii::$app->db->createCommand("select C.register_number,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and G.coe_programme_id='".$_POST['bat_map_val']."' $add_where_condition and H.coe_programme_id='".$_POST['bat_map_val']."' group by C.register_number,A.subject_code order by A.subject_code")->queryAll();
            }
            else if(isset($_POST['month']))
            {
                $revaluation = Yii::$app->db->createCommand("select C.register_number,C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' $add_where_condition group by C.register_number,A.subject_code order by A.subject_code")->queryAll();
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', 'NO DATA FOUND');
                return $this->render('view-transparency-dept', [
                    'model' => $model,
                    'markentry' => $markentry,
                ]);
            }


            
            
            if (count($revaluation) > 0) {
                return $this->render('view-transparency-dept', [
                    'model' => $model,
                    'markentry' => $markentry,
                    'revaluation' => $revaluation,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Tranparancy");
                return $this->render('view-transparency-dept', [
                    'model' => $model,
                    'markentry' => $markentry,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Tranparancy');
            return $this->render('view-transparency-dept', [
                'model' => $model,
                'markentry' => $markentry,
            ]);
        }
    }
    public function actionUpdateTransparencyDept()
    {
        $model = new Revaluation();
        $markentry = new MarkEntry();
        $checkAccess = ConfigUtilities::HasSuperAccess(Yii::$app->user->getId());
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();

        if($checkAccess=='Yes')
        {

            if (isset($_POST['view_reval_btn'])) 
            {
                $join_in_query='where';
                $select_column = ',A.subject_code,A.subject_name ';
                $add_where_condition = '';
                $stu_details = Student::findOne(['register_number'=>$_POST['bat_map_val']]);
                $stuMapDet = StudentMapping::findOne(['student_rel_id'=>$stu_details->coe_student_id]);
                if(isset($_POST['Revaluation']['is_transparency'][0]) && $_POST['Revaluation']['is_transparency'][0]=='yes')
                {
                    $join_in_query = ' JOIN coe_dummy_number as F WHERE F.student_map_id=E.student_map_id and F.subject_map_id=E.subject_map_id and F.year=E.year and F.month=E.month and ';
                    $select_column .= ',F.dummy_number ';
                    $add_where_condition = ' and F.year="'.$_POST['mark_year'].'" and F.month="'.$_POST['month'].'"  ';
                }
                $revaluation = Yii::$app->db->createCommand("select C.register_number,E.student_map_id,E.subject_map_id,E.year,E.month,1 as is_checked,E.mark_type, C.name ".$select_column."  from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id JOIN coe_revaluation as E JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id and A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and E.student_map_id='".$stuMapDet->coe_student_mapping_id."' and coe_student_mapping_id='".$stuMapDet->coe_student_mapping_id."' and register_number='".$_POST['bat_map_val']."' $add_where_condition and register_number='".$_POST['bat_map_val']."' group by E.student_map_id,E.subject_map_id order by A.subject_code")->queryAll();

                $revaluation_1 = Yii::$app->db->createCommand("select C.register_number,E.student_map_id,E.subject_map_id,E.year,0 as is_checked,E.month,E.mark_type, C.name ".$select_column." from coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=B.batch_mapping_id  JOIN coe_student as C ON C.coe_student_id=D.student_rel_id  JOIN coe_bat_deg_reg as G ON G.coe_bat_deg_reg_id=D.course_batch_mapping_id and G.coe_bat_deg_reg_id=B.batch_mapping_id JOIN coe_programme as H ON H.coe_programme_id=G.coe_programme_id JOIN coe_mark_entry_master as E ON A.coe_subjects_id=B.subject_id and B.coe_subjects_mapping_id=E.subject_map_id and E.student_map_id=D.coe_student_mapping_id and D.student_rel_id=C.coe_student_id ".$join_in_query."  E.year='" . $_POST['mark_year'] . "' and E.month='" . $_POST['month'] . "' and register_number='".$_POST['bat_map_val']."' $add_where_condition and register_number='".$_POST['bat_map_val']."' and subject_map_id NOT IN(select subject_map_id FROM coe_revaluation where year='".$_POST['mark_year']."' and month='".$_POST['month']."' and student_map_id='".$stuMapDet->coe_student_mapping_id."' ) and E.grade_name NOT IN('AB','WH','wh','ab') and E.result NOT LIKE '%AB%'  group by E.student_map_id,E.subject_map_id order by A.subject_code")->queryAll();

                if(!empty($revaluation_1))
                {
                    $revaluation = array_merge($revaluation,$revaluation_1);
                }
                
                if (count($revaluation) > 0) {
                    return $this->render('update-transparency-dept', [
                        'model' => $model,
                        'markentry' => $markentry,
                        'revaluation' => $revaluation,
                    ]);
                } else {
                    Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Tranparancy");
                    return $this->render('update-transparency-dept', [
                        'model' => $model,
                        'markentry' => $markentry,
                    ]);
                }
            }
            else if (isset($_POST['view_reval_btn_UPDATE']) && $_POST['view_reval_btn_UPDATE']=='UPDATE') 
            {

                $TOTAL_subs_stu = ($_POST['total_subs']-1);
                $succesS = 0;
                for ($i=1; $i <=$TOTAL_subs_stu ; $i++) 
                {                    
                    if(isset($_POST['checkbox'.$i]) && $_POST['checkbox'.$i]=='YES')
                    {

                        $reval = Revaluation::findOne(['student_map_id'=>$_POST['student_map_id'.$i],'subject_map_id'=>$_POST['subject_map_id'.$i],'year'=>$_POST['year'.$i],'month'=>$_POST['month'.$i],'mark_type'=>$_POST['mark_type'.$i],'is_transparency'=>'S']);

                        $reval_remove = Revaluation::findOne(['student_map_id'=>$_POST['student_map_id'.$i],'subject_map_id'=>$_POST['subject_map_id'.$i],'year'=>$_POST['year'.$i],'month'=>$_POST['month'.$i],'mark_type'=>$_POST['mark_type'.$i],'is_transparency'=>'S','reval_status'=>'YES']);
                        
                        if(empty($reval))
                        {                           
                            $revalInsert = new Revaluation();
                            $revalInsert->student_map_id = $_POST['student_map_id'.$i];
                            $revalInsert->subject_map_id = $_POST['subject_map_id'.$i];
                            $revalInsert->year = $_POST['year'.$i];
                            $revalInsert->month = $_POST['month'.$i];
                            $revalInsert->mark_type = $_POST['mark_type'.$i];
                            $revalInsert->is_transparency = 'S';
                            $revalInsert->reval_status = 'NO';
                            $revalInsert->updated_by = $updated_by;
                            $revalInsert->created_by = $updated_by;
                            $revalInsert->created_at = $updated_at;
                            $revalInsert->updated_at = $updated_at;
                            $revalInsert->save();
                            unset($revalInsert);
                            $succesS++;
                        }
                        if( !empty($reval) && isset($_POST['checkbox_REVAL'.$i]) && $_POST['checkbox_REVAL'.$i]=='YES')
                        {
                            $command1 = Yii::$app->db->createCommand('UPDATE coe_revaluation SET reval_status="YES",updated_by="'.$updated_by.'",updated_at="'.$updated_at.'" WHERE coe_revaluation_id="'.$reval->coe_revaluation_id.'"');
                            $command1->execute();
                            $succesS++;
                        }
                        else if( !empty($reval_remove) && !isset($_POST['checkbox_REVAL'.$i]) && empty($_POST['checkbox_REVAL'.$i]))
                        {
                            $command1 = Yii::$app->db->createCommand('UPDATE coe_revaluation SET reval_status="NO",updated_by="'.$updated_by.'",updated_at="'.$updated_at.'" WHERE coe_revaluation_id="'.$reval_remove->coe_revaluation_id.'"');
                            $command1->execute();
                            $succesS++;
                        }

                    }
                    else
                    {
                        
                        $reval = Revaluation::findOne(['student_map_id'=>$_POST['student_map_id'.$i],'subject_map_id'=>$_POST['subject_map_id'.$i],'year'=>$_POST['year'.$i],'month'=>$_POST['month'.$i],'mark_type'=>$_POST['mark_type'.$i],'is_transparency'=>'S','reval_status'=>'NO']);
                        $reval_yes = Revaluation::findOne(['student_map_id'=>$_POST['student_map_id'.$i],'subject_map_id'=>$_POST['subject_map_id'.$i],'year'=>$_POST['year'.$i],'month'=>$_POST['month'.$i],'mark_type'=>$_POST['mark_type'.$i],'is_transparency'=>'S','reval_status'=>'YES']);

                        if(!empty($reval))
                        {
                            $del = Yii::$app->db->createCommand('delete from coe_revaluation where coe_revaluation_id="'.$reval->coe_revaluation_id.'" ');
                            if($del->execute())
                            {
                                $succesS++;
                            }
                            else
                            {
                                 Yii::$app->ShowFlashMessages->setMsg('WARNING', 'SOMETHING WRONG WITH DELETE');
                            }
                        }
                        else if (!empty($reval_yes)) 
                        {
                           $command1 = Yii::$app->db->createCommand('UPDATE coe_revaluation SET reval_status="NO",updated_by="'.$updated_by.'",updated_at="'.$updated_at.'" WHERE coe_revaluation_id="'.$reval_yes->coe_revaluation_id.'"');
                            if($command1->execute())
                            {
                                $succesS++;
                            }
                            else
                            {
                                 Yii::$app->ShowFlashMessages->setMsg('WARNING', 'SOMETHING WRONG WITH DELETE');
                            }
                        }
                        
                    }

                }
                Yii::$app->ShowFlashMessages->setMsg('Success', 'Tranparancy / Revaluation Removed / Updated for '.$succesS);
                return $this->redirect(['update-transparency-dept']);

            }
             else {
                Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Tranparancy');
                return $this->render('update-transparency-dept', [
                    'model' => $model,
                    'markentry' => $markentry,
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
    public function actionMyclassroom()
    {
        $model = new MarkEntry();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to My Class Room ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('myclassroom', [
            'model' => $model,
        ]);
    }
public function actionMyclassroomdata()
    {
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        //$degree = Yii::$app->request->post('degree');
        $batch_id = Yii::$app->request->post('batch_id');
        $batch_map_id = Yii::$app->request->post('batch_map_id');

        $passed_stu = array();
        $failed_stu = array();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $query = new  Query();

        if($batch_map_id!='')
        {
            $query->select('F.student_map_id,A.name, A.register_number, G.semester, A.dob,B.course_batch_mapping_id,F.year,F.month ,K.description as month_name , F.term, C.regulation_year, D.degree_code, D.degree_name, E.programme_name, part_no, mark_type, result_published_date')
            ->from('coe_student as A')
            ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
            ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
            ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
            ->join('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
            ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
            ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
            ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
            ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['C.coe_bat_deg_reg_id'=>$batch_map_id,'I.coe_batch_id'=>$batch_id,'C.coe_batch_id'=>$batch_id,'F.year' => $year, 'F.month' => $month, 'A.student_status' => 'Active'])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.student_map_id')
            ->orderBy('A.register_number,G.semester');
        }
        else
        {
            $query->select('F.student_map_id,A.name, A.register_number, G.semester, A.dob,B.course_batch_mapping_id,F.year,F.month ,K.description as month_name , F.term, C.regulation_year, D.degree_code, D.degree_name, E.programme_name, part_no, mark_type, result_published_date')
            ->from('coe_student as A')
            ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
            ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
            ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
            ->join('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
            ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
            ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
            ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
            ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['I.coe_batch_id'=>$batch_id,'C.coe_batch_id'=>$batch_id,'F.year' => $year, 'F.month' => $month, 'A.student_status' => 'Active'])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.student_map_id')
            ->orderBy('A.register_number,G.semester');
        }
   // echo "<br>".$query->createCommand()->getRawSql();  exit;
        $final_student = $query->createCommand()->queryAll();
       
        $table = "";
        $sn = 1;
        $query = new Query();
        if (count($final_student) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table border=1 id="checkAllFeet" class="table table-responsive table-striped" align="center" >
                <tbody align="center">';
           
            $table .= '
                    <tr>
                        <td align="center"><b> S.NO </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . ' CODE </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' NAME </b></td>
                        <td align="center"><b> DATE OF RESULT </b></td>
                        <td align="center"><b> YEAR & MONTH </b></td>
                        <td align="center"><b> NAME </b></td>
                        <td align="center"><b> DATE OF BIRTH </b></td>
                        <td align="center"><b> ROLL NUMBER </b></td>
                        <td align="center"><b> PART  DETAILS</b><table width="100%" border=1 ><tr>';
$semester_array = ['1'=>'I','2'=>'II','3'=>'III','4'=>'IV','5'=>'V','6'=>'VI','7'=>'VII','8'=>'VIII','9'=>'IX','10'=>'X','11'=>'XI','12'=>'XII','13'=>'XIII','14'=>'XIV'];
            for ($i=1; $i <5 ; $i++) 
            { 
                $table .= '<td width="30px"><h4>'.$semester_array[$i].'</h4></td>
                        <td width="40px">CREDITS</td>
                        <td width="40px">SGPA</td>
                        <td width="40px">CGPA</td>';
            }   
            $table .= '</tr></table></td></tr>'; //exit;        
            foreach ($final_student as $final_student1) 
            {       
               // $cgpa_calculation = ConfigUtilities::getCgpaCaluclation($final_student1['year'],$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id'],$final_student1['semester']);
                $date_of_result = isset($final_student1['result_published_date']) && !empty($final_student1['result_published_date']) && $final_student1['result_published_date']!='0000-00-00'?date('d-M-Y',strtotime($final_student1['result_published_date'])):date('d-m-Y');
                $table .= '
                    <tr>
                        <td align="center"> ' . $sn . ' </td>
                        <td align="center"> ' . $final_student1['degree_code'] . ' </td>
                        <td align="center"> ' . $final_student1['programme_name'] . ' </td>
                        <td align="center"> ' . $date_of_result . ' </td>
                        <td align="center"> ' . $final_student1['year']." - ".$final_student1['month_name']. ' </td>
                        <td align="center"> ' . $final_student1['name'] . ' </td>
                        <td align="center"> ' . date('d-M-Y' ,strtotime($final_student1['dob'])) . ' </td>
                        <td align="center"> ' . $final_student1['register_number'] . '</td> 
                        <td align="center"> <table border=1 >
                                <tr>';

                $getRegInfo = MarkEntryMaster::find()->where(['student_map_id'=>$final_student1['student_map_id'],'year'=>$final_student1['year'],'month'=>$final_student1['month'],'mark_type'=>27])->one();
                $semester_number = ConfigUtilities::semCaluclation($year, $final_student1['month'], $final_student1['course_batch_mapping_id']);
                $cgpa_calc = ConfigUtilities::getCgpaCaluclation($year,$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id'],$final_student1['term'],$semester_number);
              // print_r($cgpa_calc);exit;
                
                for ($a=1; $a <5 ; $a++) 
                { 
                    
                    $table .= '<td width="30px"><h4>'.$semester_array[$a].'</h4></td>
                                <td width="40px">'.$cgpa_calc['part_'.$a.'_earned'].'</td>
                                <td width="40px">'.$cgpa_calc['part_'.$a.'_gpa'].'</td>
                                <td width="40px">'.$cgpa_calc['part_'.$a.'_cgpa'].'</td>
                    ';
                } 
                $table .= '</tr>
                            </table></td></tr>';
                $sn++;
            }
        } else {
            return 0;
        }
        
        $table .= '</table>';
        if (isset($_SESSION['my_class_university_report_print'])) 
        {
            unset($_SESSION['my_class_university_report_print']);
        }
        $_SESSION['my_class_university_report_print'] = $table;
        return $table;
    }

   /*public function actionMyclassroomdata()
    {
        $year = Yii::$app->request->post('year');
        $month = Yii::$app->request->post('month');
        $degree = Yii::$app->request->post('degree');
        $batch_id = Yii::$app->request->post('batch_id');
        $batch_map_id = Yii::$app->request->post('batch_map_id');

        $passed_stu = array();
        $failed_stu = array();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        $query = new  Query();

        if($batch_map_id!='')
        {
            $query->select('F.student_map_id,A.name, A.register_number, G.semester, A.dob,B.course_batch_mapping_id,F.year,F.month ,K.description as month_name , F.term, C.regulation_year, D.degree_code, D.degree_name, E.programme_name, part_no, mark_type, result_published_date')
            ->from('coe_student as A')
            ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
            ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
            ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
            ->join('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
            ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
            ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
            ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
            ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['C.coe_bat_deg_reg_id'=>$batch_map_id,'I.coe_batch_id'=>$batch_id,'C.coe_batch_id'=>$batch_id,'F.year' => $year, 'F.month' => $month, 'A.student_status' => 'Active','D.degree_type'=>$degree])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.student_map_id')
            ->orderBy('A.register_number,G.semester');
        }
        else
        {
            $query->select('F.student_map_id,A.name, A.register_number, G.semester, A.dob,B.course_batch_mapping_id,F.year,F.month ,K.description as month_name , F.term, C.regulation_year, D.degree_code, D.degree_name, E.programme_name, part_no, mark_type, result_published_date')
            ->from('coe_student as A')
            ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
            ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
            ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
            ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
            ->join('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
            ->join('JOIN', 'coe_mark_entry_master as F', 'F.student_map_id=B.coe_student_mapping_id')
            ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.month')
            ->join('JOIN', 'coe_subjects_mapping as G', 'G.coe_subjects_mapping_id=F.subject_map_id')
            ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['I.coe_batch_id'=>$batch_id,'C.coe_batch_id'=>$batch_id,'F.year' => $year, 'F.month' => $month, 'A.student_status' => 'Active','D.degree_type'=>$degree])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->groupBy('F.student_map_id')
            ->orderBy('A.register_number,G.semester');
        }
   // echo "<br>".$query->createCommand()->getRawSql();  exit;
        $final_student = $query->createCommand()->queryAll();
       
        $table = "";
        $sn = 1;
        $query = new Query();
        if (count($final_student) > 0) {
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
            $table = '<table border=1 id="checkAllFeet" class="table table-responsive table-striped" align="center" >
                <tbody align="center">';
           
            $table .= '
                    <tr>
                        <td align="center"><b> S.NO </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE) . ' CODE </b></td>
                        <td align="center"><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' NAME </b></td>
                        <td align="center"><b> DATE OF RESULT </b></td>
                        <td align="center"><b> YEAR & MONTH </b></td>
                        <td align="center"><b> NAME </b></td>
                        <td align="center"><b> DATE OF BIRTH </b></td>
                        <td align="center"><b> ROLL NUMBER </b></td>
                        <td align="center"><b> PART  DETAILS</b><table width="100%" border=1 ><tr>';
$semester_array = ['1'=>'I','2'=>'II','3'=>'III','4'=>'IV','5'=>'V','6'=>'VI','7'=>'VII','8'=>'VIII','9'=>'IX','10'=>'X','11'=>'XI','12'=>'XII','13'=>'XIII','14'=>'XIV'];
            for ($i=1; $i <5 ; $i++) 
            { 
                $table .= '<td width="30px"><h4>'.$semester_array[$i].'</h4></td>
                        <td width="40px">CREDITS</td>
                       
                        <td width="40px">CGPA</td>
                         <td width="40px">Grade</td>';
            }   
            $table .= '</tr></table></td></tr>'; //exit;        
            foreach ($final_student as $final_student1) 
            {       
               // $cgpa_calculation = ConfigUtilities::getCgpaCaluclation($final_student1['year'],$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id'],$final_student1['semester']);
                $date_of_result = isset($final_student1['result_published_date']) && !empty($final_student1['result_published_date']) && $final_student1['result_published_date']!='0000-00-00'?date('d-M-Y',strtotime($final_student1['result_published_date'])):date('d-m-Y');
                $table .= '
                    <tr>
                        <td align="center"> ' . $sn . ' </td>
                        <td align="center"> ' . $final_student1['degree_code'] . ' </td>
                        <td align="center"> ' . $final_student1['programme_name'] . ' </td>
                        <td align="center"> ' . $date_of_result . ' </td>
                        <td align="center"> ' . $final_student1['year']." - ".$final_student1['month_name']. ' </td>
                        <td align="center"> ' . $final_student1['name'] . ' </td>
                        <td align="center"> ' . date('d-M-Y' ,strtotime($final_student1['dob'])) . ' </td>
                        <td align="center"> ' . $final_student1['register_number'] . '</td> 
                        <td align="center"> <table border=1 >
                                <tr>';

                $getRegInfo = MarkEntryMaster::find()->where(['student_map_id'=>$final_student1['student_map_id'],'year'=>$final_student1['year'],'month'=>$final_student1['month'],'mark_type'=>27])->one();
                $semester_number = ConfigUtilities::semCaluclation($year, $final_student1['month'], $final_student1['course_batch_mapping_id']);
                $cgpa_calc = ConfigUtilities::getgpaCaluclationObe($year,$final_student1['month'],$final_student1['course_batch_mapping_id'],$final_student1['student_map_id'],$final_student1['term'],$semester_number);
                 $max_parts = SubInfo::find()->where(['sub_batch_id'=>$final_student1['course_batch_mapping_id']])->orderBy('part_no desc')->limit(1)->one();
              // print_r($cgpa_calc);exit;
                
                for ($a=1; $a <5 ; $a++) 
                { 
                   
                    $table .= '<td width="30px"><h4>'.$semester_array[$a].'</h4></td>
                                <td width="40px">'.$cgpa_calc['part_'.$a.'_earned'].'</td>
                               
                                <td width="40px">'.$cgpa_calc['part_'.$a.'_cgpa'].'</td>

                                
                                <td width="40px">'.$cgpa_calc['part_'.$a.'_grade'].'</td>
                    ';
                } 

                $table .= '</tr>
                            </table></td></tr>';

                           
                $sn++;
            }
        } else {
            return 0;
        }
        
        $table .= '</table>';
        if (isset($_SESSION['my_class_university_report_print'])) 
        {
            unset($_SESSION['my_class_university_report_print']);
        }
        $_SESSION['my_class_university_report_print'] = $table;
        return $table;
    }
*/
    public function actionMyclassroomPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['my_class_university_report_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Universityreport.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #CCC;
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
            'options' => ['title' => 'MyClassRoom Report Information'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['MyClassRoom Report Information' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelMyclassroom()
    {        
        $content = $_SESSION['my_class_university_report_print'];           
        $fileName = "MyClassRoom Report Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    
    
    //Withdraw ends here


    public function actionSubAdd()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
       

        if (isset($_POST['internetcopybutton'])) 
        {
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId();
            


            $internet_copy_query = new Query();
             $whereCondition = [                        
                        'a.exam_year' => $_POST['year'], 'a.exam_month' => $_POST['month'],'b.course_batch_mapping_id' => $_POST['bat_map_val']
                    ];

            $internet_copy_query->select('c.register_number,c.name,c.dob,subject_code,subject_name,ese_maximum,ese_minimum,cia_maximum,cia_minimum,a.CIA,a.ESE,a.result,a.total,a.grade_name,a.grade_point,a.student_map_id,a.exam_year as year,a.exam_month as month,degree_code,programme_name')
                ->from('coe_additional_credits a')
                ->join('JOIN', 'coe_student_mapping b', 'a.student_map_id=b.coe_student_mapping_id')
                ->join('JOIN', 'coe_student c', 'b.student_rel_id=c.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_batch k','k.coe_batch_id=g.coe_batch_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');
           
            $internet_copy_query->where($whereCondition);  
        // print_r($internet_copy_query);exit;            
            $internet_copy_query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->orderBy('c.register_number' );


            $internet_copy = $internet_copy_query->createCommand()->queryAll();
            array_multisort(array_column($internet_copy, 'register_number'),  SORT_ASC, $internet_copy);

            if (count($internet_copy) > 0) {
                return $this->render('sub-add', [
                    'model' => $model, 'galley' => $galley, 'internet_copy' => $internet_copy,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('sub-add', [
                    'model' => $model, 'galley' => $galley,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Reports');
            return $this->render('sub-add', [
                'model' => $model, 'galley' => $galley,
            ]);
        }
    }

    public function actionExcelSubAdd()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();        
        $content = $_SESSION['internetcopy_additional_print'];
        $fileName = " Notice Board Additional Credits Copy - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }  

   
    public function actionSubAddPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['internetcopy_additional_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => ' Notice Board Additional Credits Copy.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_LANDSCAPE,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; } 
                        
                        table td{
                            border: 1px solid #CCC;
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
            'options' => ['title' => ' Notice Board Additional Credits Copy'],
            'methods' => [
                //'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
               // 'SetFooter' => ['MyClassRoom Report Information' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }

   public function actionSubAdditional()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
       

        if (isset($_POST['internetcopybutton'])) 
        {
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId();
            


            $internet_copy_query = new Query();
             $whereCondition = [                        
                        'a.exam_year' => $_POST['year'], 'a.exam_month' => $_POST['month'],'b.course_batch_mapping_id' => $_POST['bat_map_val']
                    ];

            $internet_copy_query->select('c.register_number,c.name,c.dob,subject_code,subject_name,ese_maximum,ese_minimum,cia_maximum,cia_minimum,a.CIA,a.ESE,a.result,a.total,a.grade_name,a.grade_point,a.student_map_id,a.exam_year as year,a.semester,a.exam_month as month,degree_code,programme_name')
                ->from('coe_additional_credits a')
                ->join('JOIN', 'coe_student_mapping b', 'a.student_map_id=b.coe_student_mapping_id')
                ->join('JOIN', 'coe_student c', 'b.student_rel_id=c.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_batch k','k.coe_batch_id=g.coe_batch_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');
           
            $internet_copy_query->where($whereCondition);  
        // print_r($internet_copy_query);exit;            
            $internet_copy_query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->orderBy('c.register_number' );


            $internet_copy = $internet_copy_query->createCommand()->queryAll();
            array_multisort(array_column($internet_copy, 'register_number'),  SORT_ASC, $internet_copy);

            if (count($internet_copy) > 0) {
                return $this->render('sub-additional', [
                    'model' => $model, 'galley' => $galley, 'internet_copy' => $internet_copy,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('sub-additional', [
                    'model' => $model, 'galley' => $galley,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Reports');
            return $this->render('sub-additional', [
                'model' => $model, 'galley' => $galley,
            ]);
        }
    }

   public function actionExcelSubAdditional()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();        
        $content = $_SESSION['internetcopy_additional_print'];
        $fileName = "ADDITIONAL CREDITS REPORTS - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }  

   
   public function actionSubAdditionalPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['internetcopy_additional_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'Additional Credits Reports.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }   
                ',
            'options' => ['title' => 'Additional Credits Reports'],
            'methods' => [
                //'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                //'SetFooter' => ['Tranparancy Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }





    public function actionAdd()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        

        if (isset($_POST['internetcopybutton'])) 
        {
            $created_at = date("Y-m-d H:i:s");
            $updateBy = Yii::$app->user->getId();
            


            $internet_copy_query = new Query();
             $whereCondition = [                        
                        'a.exam_year' => $_POST['year'], 'a.exam_month' => $_POST['month']
                    ];

            $internet_copy_query->select('c.register_number,c.name,c.dob,subject_code,subject_name,ese_maximum,ese_minimum,cia_maximum,cia_minimum,a.CIA,a.ESE,a.result,a.total,a.grade_name,a.grade_point,a.student_map_id,a.exam_year as year,a.out_of_maximum as secured,a.exam_month as month,degree_code,programme_name,k.batch_name')
                ->from('coe_additional_credits a')
                ->join('JOIN', 'coe_student_mapping b', 'a.student_map_id=b.coe_student_mapping_id')
                ->join('JOIN', 'coe_student c', 'b.student_rel_id=c.coe_student_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_batch k','k.coe_batch_id=g.coe_batch_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');
           
            $internet_copy_query->where($whereCondition);  
        // print_r($internet_copy_query);exit;            
            $internet_copy_query->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->orderBy('c.register_number' );


            $internet_copy = $internet_copy_query->createCommand()->queryAll();
            array_multisort(array_column($internet_copy, 'register_number'),  SORT_ASC, $internet_copy);

            if (count($internet_copy) > 0) {
                return $this->render('add', [
                    'model' => $model, 'galley' => $galley, 'internet_copy' => $internet_copy,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('add', [
                    'model' => $model, 'galley' => $galley,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Additional Credits Reports');
            return $this->render('add', [
                'model' => $model, 'galley' => $galley,
            ]);
        }
    }

    
}
