<?php

namespace app\controllers;

use Yii;
use app\models\CoeValueMarkEntry;
use app\models\CoeValueMarkEntrySearch;
use app\models\MarkEntry;
use app\models\AbsentEntry;
use app\models\MarkEntryMaster;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Student;
use app\models\HallAllocate;
use app\models\CoeBatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Batch;
use app\models\StuInfo;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use yii\helpers\Html;
use yii\db\Query;
use kartik\mpdf\Pdf;
/**
 * CoeValueMarkEntryController implements the CRUD actions for CoeValueMarkEntry model.
 */
class CoeValueMarkEntryController extends Controller
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
     * Lists all CoeValueMarkEntry models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CoeValueMarkEntrySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single CoeValueMarkEntry model.
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
     * Creates a new CoeValueMarkEntry model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new CoeValueMarkEntry();
        $markEntry = new MarkEntry();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_value_mark_entry_id]);
        } else {
            return $this->render('create', [
                'model' => $model,'markEntry'=>$markEntry
            ]);
        }
    }

     public function actionConsolidateMarkSheet()
    {
        $model = new CoeValueMarkEntry();
        $markEntry = new MarkEntry();
        $student = new Student();
        $galley = new HallAllocate();

        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        $det_disc_rejoin_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%rejoin%'")->queryScalar();
        
        if (Yii::$app->request->post() && Yii::$app->request->post('from_date')!='' && Yii::$app->request->post('to_date')!='' && Yii::$app->request->post('semester')!='' && Yii::$app->request->post('publication_date')!='') 
        {
            $get_batc_map = CoeBatDegReg::findOne($_POST['bat_map_val']);
            $deg_info = Degree::findOne($get_batc_map->coe_degree_id);
            $getBacthName = Batch::findOne($get_batc_map->coe_batch_id);
            $getBacthYear = $getBacthName['batch_name'];
            $reg_num_in = '';
            $year=$_POST['year'];
            $month=$_POST['month'];

            //print_r($month);exit;

            $get_regnum = StuInfo::find()->where(['batch_map_id'=>$_POST['bat_map_val']])->andWhere(['between','reg_num', $_POST['Student']['register_number_from'],$_POST['Student']['register_number_to']] )->all();

            if(!empty($get_regnum) && count($get_regnum)>0)
            {   
                foreach($get_regnum as $val)
                {
                    
                    $reg_num_in .="'".$val['reg_num']."',";
                    
                }
                $trim_reg = trim($reg_num_in,',');
            }

            $semester = Yii::$app->request->post('semester');
            //$month = Yii::$app->request->post('month');
            //$year = Yii::$app->request->post('year');
          
            $from_date = date("d-m-Y",strtotime(Yii::$app->request->post('from_date')));
            $to_date = date("d-m-Y",strtotime(Yii::$app->request->post('to_date')));
            $publication_date = date("d-m-Y",strtotime(Yii::$app->request->post('publication_date')));
         
            $add_sem = !empty($semester) ? " AND  G.semester<='".$semester."' ":'';
            $add_year= !empty($year) ? " AND  F.year<='".$year."' ":'';
            //normal student query
   
            //rejoin student taking marksheet
           $get_stu_query = "SELECT F.student_map_id,A.register_number,A.dob,A.name,A.gender,D.degree_code,D.degree_name,G.batch_mapping_id,E.programme_name,H.subject_code,F.subject_map_id,C.regulation_year,mark_type,H.subject_name,G.semester,H.credit_points,H.CIA_max,H.ESE_max,F.grade_point,F.grade_name,F.CIA,F.ESE,H.ESE_max,H.CIA_max,F.year,F.month,status_category_type_id,B.course_batch_mapping_id,F.year_of_passing,part_no,paper_no,F.total,F.year as exam_year,F.month as exam_month,F.result,max(F.year_of_passing) as last_appearance ,F.term FROM coe_student as A 
                JOIN coe_student_mapping as B ON B.student_rel_id=A.coe_student_id 
                JOIN coe_bat_deg_reg as C ON C.coe_bat_deg_reg_id=B.course_batch_mapping_id 
                JOIN coe_degree as D ON D.coe_degree_id=C.coe_degree_id 
                JOIN coe_programme as E ON E.coe_programme_id=C.coe_programme_id 
                JOIN coe_value_mark_entry as F ON F.student_map_id=B.coe_student_mapping_id 
                JOIN sub as G ON G.coe_sub_mapping_id=F.subject_map_id 
                JOIN coe_value_subjects as H ON H.coe_val_sub_id=G.val_subject_id 
                JOIN coe_batch as I ON I.coe_batch_id=C.coe_batch_id  
                WHERE G.batch_mapping_id='".$_POST['bat_map_val']."' and  F.year='".$_POST['year']."' and F.month='".$_POST['month']."' and  year_of_passing!='' AND A.register_number IN (".$trim_reg.") group by A.register_number,H.subject_code order by A.register_number,G.semester,paper_no";
                //echo Yii::$app->db->createCommand($get_stu_query)->getRawSql(); exit;
            $get_console_list = Yii::$app->db->createCommand($get_stu_query)->queryAll();
        
            require(Yii::getAlias('@webroot/includes/use_institute_info.php'));

            if(!empty($get_console_list))
            {
                if($file_content_available=="Yes")
                {
                    return $this->render('consolidate-mark-sheet', [                        
                        'model' => $model,
                        'get_console_list' => $get_console_list,
                        'markEntry'=>$markEntry,
                        'student' => $student,
                        'from_date'=>$from_date,
                        'to_date'=>$to_date,
                        'publication_date'=>$publication_date,
                        'semester'=>$semester,
                        'galley'=>$galley
                    ]);
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error',"No Institute Information Found");
                    return $this->redirect(['coe-value-mark-entry/consolidate-mark-sheet']); 
                }
                 
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found ");
                return $this->redirect(['coe-value-mark-entry/consolidate-mark-sheet']); 
            }


            return $this->render('consolidate-mark-sheet', [
                'model' => $model,
                'get_console_list' => $get_console_list,
                'markEntry'=>$markEntry,
                'student' => $student,
                'galley' =>$galley,
            ]);

            //return $this->redirect(['view', 'id' => $model->coe_mark_entry_master_id]);
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Consolidate Mark Sheet');
            return $this->render('consolidate-mark-sheet', [
                'model' => $model,
                'markEntry'=>$markEntry,
                'student' => $student,
                'galley' =>$galley,
            ]);
        }
    }

    public function actionConsolidateMarkSheetVaddPdf()
    {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));         
        $content = $_SESSION['get_valueadded_pdf'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => 'VALUE ADDED MARK STATEMENT.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                //'cssFile' => 'css/consolidate-markstatement-vadd.css',
                'options' => ['title' => 'VALUE ADDED MARK STATEMENT'],
                
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

    /**
     * Updates an existing CoeValueMarkEntry model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_value_mark_entry_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing CoeValueMarkEntry model.
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
     * Finds the CoeValueMarkEntry model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CoeValueMarkEntry the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CoeValueMarkEntry::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

     public function actionValueAdd()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        //$model = new CoeValueMarkEntry();
        //$markEntry = new MarkEntry();
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
                ->join('JOIN', 'coe_value_mark_entry c', 'b.coe_student_mapping_id=c.student_map_id')
                ->join('JOIN', 'sub d', 'c.subject_map_id=d.coe_sub_mapping_id and d.batch_mapping_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_value_subjects e', 'd.val_subject_id=e.coe_val_sub_id');
                /*if(!empty($reval_status) && $reval_status=='yes')
                {
                    $query_n->join('JOIN', 'internal_mark_entry f', 'f.student_map_id=c.student_map_id and f.subject_map_id=c.subject_map_id and f.year=c.year and f.mark_type=c.mark_type and f.term=c.term and f.month=c.month and f.student_map_id=b.coe_student_mapping_id and f.subject_map_id=d.coe_sub_mapping_id');
                    $whereCondition_12 = [                        
                            'f.category_type_id'=>$reval_status_entry['coe_category_type_id'],'f.year'=>$_POST['year'],'f.month' => $_POST['month'],
                        ];
                    $whereCondition = array_merge($whereCondition,$whereCondition_12);
                }*/
                $query_n->where($whereCondition)
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['NOT IN', 'c.student_map_id', $withheld]);
                $query_n->groupBy('a.register_number,e.subject_code')
                ->orderBy('a.register_number,d.semester');
            $noticeboard_copy = $query_n->createCommand()->queryAll();
//print_r($noticeboard_copy);exit;
           
            array_multisort(array_column($noticeboard_copy, 'semester'),  SORT_ASC, $noticeboard_copy);
            array_multisort(array_column($noticeboard_copy, 'paper_no'),  SORT_ASC, $noticeboard_copy);
            array_multisort(array_column($noticeboard_copy, 'register_number'),  SORT_ASC, $noticeboard_copy);            
            if (count($noticeboard_copy) > 0) 
            {
                return $this->render('value-add', [
                    'model' => $model,
                    'noticeboard_copy' => $noticeboard_copy,
                    'year' => $year, 'month' => $month, 'batch_name' => $batch_name, 'degree_name' => $degree_name,'reval_status'=>$reval_status,

                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Notice Board Copy");
                return $this->render('value-add', [
                    'model' => $model,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Noticeboard Copy');
            return $this->render('value-add', [
                'model' => $model,
            ]);
        }
    }
    public function actionValueAddPdf()
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
            'options' => ['title' => 'Value Added Noticeboard Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Value Added Noticeboard Data' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelValueAdd()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();        
        $content = $_SESSION['noticeboard_print'];
        $fileName = " Value Added Noticeboard Copy - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

     public function actionValueAddedReports()
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
            $query_n->select('a.register_number,d.semester,e.subject_code,e.subject_name,c.CIA,c.ESE,e.CIA_max,e.ESE_max,c.total,c.result,c.grade_name,c.withheld,c.grade_point,c.subject_map_id,c.student_map_id,c.mark_type,paper_no,k.batch_name,h.degree_code,i.programme_name' )
                ->from('coe_student a')
                ->join('JOIN', 'coe_student_mapping b', 'a.coe_student_id=b.student_rel_id')
                ->join('JOIN', 'coe_value_mark_entry c', 'b.coe_student_mapping_id=c.student_map_id')
                ->join('JOIN', 'sub d', 'c.subject_map_id=d.coe_sub_mapping_id and d.batch_mapping_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_value_subjects e', 'd.val_subject_id=e.coe_val_sub_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_batch k','k.coe_batch_id=g.coe_batch_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');




                $query_n->where($whereCondition)
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['NOT IN', 'c.student_map_id', $withheld]);
                $query_n->groupBy('a.register_number,e.subject_code')
                ->orderBy('a.register_number,d.semester');
            $noticeboard_copy = $query_n->createCommand()->queryAll();
//print_r($noticeboard_copy);exit;
           
            array_multisort(array_column($noticeboard_copy, 'semester'),  SORT_ASC, $noticeboard_copy);
            array_multisort(array_column($noticeboard_copy, 'paper_no'),  SORT_ASC, $noticeboard_copy);
            array_multisort(array_column($noticeboard_copy, 'register_number'),  SORT_ASC, $noticeboard_copy);            
            if (count($noticeboard_copy) > 0) 
            {
                return $this->render('value-added-reports', [
                    'model' => $model,
                    'noticeboard_copy' => $noticeboard_copy,
                    'year' => $year, 'month' => $month,  'degree_name' => $degree_name,'reval_status'=>$reval_status,

                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available for Value Added Subject REPORTS");
                return $this->render('value-added-reports', [
                    'model' => $model,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Value Added Subject Reports');
            return $this->render('value-added-reports', [
                'model' => $model,
            ]);
        }
    }

    public function actionValueAddedReportsPdf()
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
            'options' => ['title' => 'Value Added Subjects Reports Data'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Value Added Subject Reports' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    public function actionExcelValueAddedReports()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();        
        $content = $_SESSION['noticeboard_print'];
        $fileName = " Value Added Subject Reports - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionValueaddedInternetCopy()
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

            /*if(!empty($getAbsentList))
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

                  // $check_mark_entry = MarkEntry::find()->where(['subject_map_id'=>$subject_map_id,'student_map_id'=>$student_map_id,'year'=>$year,'month'=>$month,'term'=>$term,'mark_type'=>$mark_type])->orWhere(['IN','category_type_id',$dummy_entry->coe_category_type_id,$externAl->coe_category_type_id])->one();

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
                   /* else{

                        
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
                return $this->redirect(['coe-value-mark-entry/valueadded-internet-copy']);
            }
            $reval_status = isset($_POST['MarkEntry']['mark_type'][0])?$_POST['MarkEntry']['mark_type'][0]:'';
            $internet_copy_query = new Query();
            $withheld_list = Yii::$app->db->createCommand('SELECT DISTINCT student_map_id as id FROM coe_mark_entry_master WHERE month="'.$_POST['month'].'" AND year="'.$_POST['year'].'" AND withheld="w" ')->queryAll();
            $withheld = [];
            foreach ($withheld_list as $key => $value) {
                $withheld[$value['id']]=$value['id'];
            }*/

            $whereCondition = [                        
                        'a.year' => $_POST['year'], 'a.month' => $_POST['month'],
                    ];
                    $internet_copy_query = new Query();
            $internet_copy_query->select('c.register_number,paper_no,c.name,c.dob,e.subject_code,e.ESE_max,e.ESE_min,e.CIA_max,e.CIA_min,a.subject_map_id,a.CIA,a.ESE,a.result,a.withheld,a.grade_name,a.student_map_id,a.year,a.month,degree_code,programme_name,d.semester,a.total')
                ->from('coe_value_mark_entry a')
                ->join('JOIN', 'coe_student_mapping b', 'a.student_map_id=b.coe_student_mapping_id')
                ->join('JOIN', 'coe_student c', 'b.student_rel_id=c.coe_student_id')
                ->join('JOIN', 'sub d', 'a.subject_map_id=d.coe_sub_mapping_id')
                ->join('JOIN', 'coe_value_subjects e', 'd.val_subject_id=e.coe_val_sub_id')
                ->join('JOIN', 'coe_bat_deg_reg g','g.coe_bat_deg_reg_id = d.batch_mapping_id and g.coe_bat_deg_reg_id=b.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree h','h.coe_degree_id=g.coe_degree_id')
                ->join('JOIN', 'coe_programme i','i.coe_programme_id=g.coe_programme_id');

           /* if(!empty($reval_status) && $reval_status=='yes')
            {
                $internet_copy_query->join('JOIN', 'internal_mark_entry f', 'f.student_map_id=a.student_map_id and f.subject_map_id=a.subject_map_id and f.year=a.year and f.month=a.month and f.mark_type=a.mark_type and f.term=a.term');
                $whereCondition_12 = [                        
                        'f.category_type_id'=>$reval_status_entry['coe_category_type_id'],'f.year'=>$_POST['year'],'f.month' => $_POST['month'],
                    ];
                $whereCondition = array_merge($whereCondition,$whereCondition_12); 
            }*/
           $internet_copy_query->where($whereCondition);           
            $internet_copy_query->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
              $internet_copy_query->andWhere(['<>', 'course_type_id', 122])
                ->orderBy('c.register_number,paper_no');
            $internet_copy = $internet_copy_query->createCommand()->queryAll();

            /*$query_man = new  Query();
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
            $query_man->Where(['F.year' => $_POST['year'], 'F.month' =>  $_POST['month'], 'A.student_status' => 'Active','G.is_additional'=>'YES'])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query_man->orderBy('A.register_number,paper_no');
               
            $mandatory_statement = $query_man->createCommand()->queryAll();
            if(!empty($mandatory_statement))
            {
                $internet_copy = array_merge($internet_copy,$mandatory_statement);
            }
            array_multisort(array_column($internet_copy, 'paper_no'),  SORT_ASC, $internet_copy);
            array_multisort(array_column($internet_copy, 'register_number'),  SORT_ASC, $internet_copy);*/

            if (count($internet_copy) > 0) {
                return $this->render('valueadded-internet-copy', [
                    'model' => $model, 'galley' => $galley, 'internet_copy' => $internet_copy,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Available");
                return $this->render('valueadded-internet-copy', [
                    'model' => $model, 'galley' => $galley,
                ]);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Internet Copy');
            return $this->render('valueadded-internet-copy', [
                'model' => $model, 'galley' => $galley,
            ]);
        }
    }
    public function actionValueaddedInternetCopyPdf()
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
     public function actionExcelValueaddedInternetCopyPdf()
    {
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();

        $publish_date = date('Y-m-d');
        $check_data = Yii::$app->db->createCommand("SELECT * FROM coe_value_mark_entry where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "' and status_id=1")->queryScalar();
        if(empty($check_data))
        {
            Yii::$app->db->createCommand("update coe_value_mark_entry set result_published_date='".$publish_date."',status_id=1,updated_by='".$updated_by."',updated_at='".$updated_at."' where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "'")->execute();
            Yii::$app->db->createCommand("update internal_mark_entry set status_id=1,updated_by='".$updated_by."',updated_at='".$updated_at."' where year='" . $_SESSION['mark_year'] . "' and month='" . $_SESSION['mark_month'] . "'")->execute();    
        }
        
        $content = $_SESSION['internetcopy_print'];           
        $fileName = "INTERNET COPY - " . $_SESSION['mark_year'] . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


   public function actionValueaddedProgrammeanalysis()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
        return $this->render('valueadded-programmeanalysis', [
            'model' => $model,
        ]);
    }



    public function actionValueaddedProgrammeresultanalysis()
   
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
        $query_cr->select('distinct(subject_code),b.semester,subject_name,coe_sub_mapping_id, description,coe_val_sub_id,batch_mapping_id,a.CIA_max,ESE_max')
            ->from('coe_value_subjects a')
            ->join('JOIN', 'sub b', 'a.coe_val_sub_id=b.val_subject_id')
            ->join('JOIN', 'coe_value_mark_entry c', 'b.coe_sub_mapping_id=c.subject_map_id')
            ->join('JOIN', 'coe_category_type d', 'b.subject_type_id=d.coe_category_type_id')
            ->where(['b.batch_mapping_id' => $_POST['batch_map_id'], 'c.year' => $_POST['year'], 'c.month' => $_POST['month'], 'c.mark_type' => $_POST['mark_type']]);
        $subject_list = $query_cr->createCommand()->queryAll();
// print_r($subject_list);exit;
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

            <tr><td colspan=18 align="center"><b>' . strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)) . strtoupper(' Result Analysis(Value Added subjects)</b> - ') . strtoupper($month . ' ' . $_POST['year']) . ' (FEB 2022)</td></tr>';
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
                $course_result_table .= '<tr><td align="left">' . $sn . '</td><td align="left">' . $subject['subject_code'] . '</td><td colspan="' . $colspan . '"  align="left">' . strtoupper($subject['subject_name']) . '</td>';
                $query_enroll = new Query();
                $query_enroll->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_sub_mapping_id'],'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_enrol = $query_enroll->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_enrol . '</td>';

                $query_appeared = new Query();
                $query_appeared->select('count(DISTINCT student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_sub_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT LIKE', 'b.result', 'Absent'])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_appeared = $query_appeared->createCommand()->queryScalar();
                $course_result_table .= '<td align="center">' . $student_appeared . '</td>';
                $query_absent = new Query();
                $query_absent->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['b.subject_map_id' => $subject['coe_sub_mapping_id'], 'b.year' => $_POST['year'], 'b.month' => $_POST['month'], 'b.result' => 'Absent','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    //echo $query_absent->createCommand()->getrawsql();
                $student_absent = $query_absent->createCommand()->queryScalar();

                
                $student_absent = $student_absent==0?'-':$student_absent;
                $course_result_table .= '<td align="center">' . $student_absent . '</td>';

                $query_withheld = new Query();
                $query_withheld->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_sub_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'], 'withheld' => 'w','mark_type'=>$_POST['mark_type']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_withheld = $query_withheld->createCommand()->queryScalar();
                $student_withheld = $student_withheld==0?'-':$student_withheld;
                $course_result_table .= '<td align="center">' . $student_withheld . '</td>';
                $query_pass = new Query();
                $query_pass->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_sub_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type']])
                    ->andWhere(['NOT', ['year_of_passing' => '']])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $student_pass = $query_pass->createCommand()->queryScalar();

                $course_result_table .= '<td align="center">' . $student_pass . '</td>';
                $query_fail = new Query();
                $select_query = "SELECT count(student_map_id) FROM coe_student_mapping a JOIN coe_value_mark_entry as b ON b.student_map_id=a.coe_student_mapping_id JOIN sub c ON c.coe_sub_mapping_id=b.subject_map_id WHERE subject_map_id='".$subject['coe_sub_mapping_id']."' AND year='".$_POST['year']."' and month='".$_POST['month']."' and result  not like '%Absent%' AND status_category_type_id NOT IN('".$det_disc_type."') and (year_of_passing is NULL or year_of_passing='' ) and grade_name NOT IN('W','WH','w','wh') ";
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
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_sub_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['>=', 'total', $total_75])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                    //echo "<br>".$query_75->createCommand()->getRawSql();
                $student_75 = $query_75->createCommand()->queryScalar();
                $student_75 = $student_75==0?'-':$student_75;
                $course_result_table .= '<td>' . $student_75 . '</td>';

                $query_74 = new Query();
                $query_74->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_sub_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_60,$total_74])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_74 = $query_74->createCommand()->queryScalar();
                $query_74 = $query_74==0?'-':$query_74;
                $course_result_table .= '<td>' . $query_74 . '</td>';

                $query_59 = new Query();
                $query_59->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_sub_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
                    ->andWhere(['BETWEEN', 'total', $total_50,$total_59])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query_59 = $query_59->createCommand()->queryScalar();
                $query_59 = $query_59==0?'-':$query_59;
                $course_result_table .= '<td>' . $query_59 . '</td>';

                $query_49 = new Query();
                $query_49->select('count(student_map_id)')
                    ->from('coe_student_mapping a')
                    ->join('JOIN', 'coe_value_mark_entry b', 'b.student_map_id=a.coe_student_mapping_id')
                    ->join('JOIN', 'sub c', 'c.coe_sub_mapping_id=b.subject_map_id')
                    ->where(['subject_map_id' => $subject['coe_sub_mapping_id'], 'year' => $_POST['year'], 'month' => $_POST['month'],'mark_type'=>$_POST['mark_type'],'result'=>'Pass'])
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
    public function actionValueaddedProgrammeAnalysisPdf()
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
                'SetFooter' => [ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . ' PAGE :{PAGENO}'],
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
    public function actionExcelValueaddedProgrammeanalysis()
    {
        
        $content = $_SESSION['programme_analysis_print'];
         
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME) . ' Result Analysis Data' . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


     public function actionSubvalue()
    {
        $model = new MarkEntry();
        $markentrymaster = new MarkEntryMaster();
        $galley = new HallAllocate();
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Information');
        return $this->render('subvalue', [
            'model' => $model, 'galley' => $galley,
        ]);
    }
    public function actionSubadded()
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
            ->join('JOIN', 'sub E', 'A.coe_bat_deg_reg_id=E.batch_mapping_id')
            ->join('JOIN', 'coe_value_subjects D', 'E.val_subject_id=D.coe_val_sub_id')
            ->join('JOIN', 'coe_category_type G', 'G.coe_category_type_id=E.paper_type_id')
            ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=E.subject_type_id')
            ->join('JOIN', 'coe_category_type I', 'I.coe_category_type_id=E.course_type_id')
            ->join('JOIN', 'coe_batch F', 'A.coe_batch_id=F.coe_batch_id')
            ->join('JOIN', 'coe_value_mark_entry MN', 'MN.subject_map_id=E.coe_sub_mapping_id')
            ->where(['MN.year'=>$year,'MN.month'=>$month])->groupBy('D.subject_code')->orderBy('semester');
        $subject = $query->createCommand()->queryAll();
       // print_r($query);exit;
       
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
                        <td colspan=14>  '.$year.'-'.strtoupper($month_name).' -VALUE ADDED SUBJECT INFORMATION  </b> </td>
                        </tr>';


            $table .= '<tr>
                        <td><b> S.NO </b></td>
                        <td><b> Year </b></td>
                         <td><b> ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH) . '  </b></td>
                        <th>'.strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE)." Name").'</th>
                        
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
                        <td> ' . $subject1['batch_name'] . ' </td>
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
    public function actionSubvaluePdf()
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
    public function actionExcelSubvalue()
    {
        
            $content = $_SESSION['subject_information_print'];
            
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . " Information " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    
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
                ->join('JOIN', 'sub as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'internal_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_sub_mapping_id')                
                ->join('JOIN', 'coe_value_subjects H', 'H.coe_val_sub_id=G.val_subject_id');
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
                ->join('JOIN', 'sub as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'internal_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_sub_mapping_id')
                ->join('JOIN', 'coe_value_subjects H', 'H.coe_val_sub_id=G.val_subject_id');
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
                ->join('JOIN', 'sub as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'internal_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id and F.subject_map_id=G.coe_sub_mapping_id')
                ->join('JOIN', 'coe_value_subjects H', 'H.coe_val_sub_id=G.val_subject_id');
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
                return $this->redirect(['coe-value-mark-entry/ciamarklist']);
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
            'options' => ['title' => ' VALUE ADDED SUBJECT CIA MARK LIST'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Conslidate CIA Mark List(value added subjects)' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
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
            $query->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,A.register_number,F.CIA,F.ESE,F.grade_name,F.year,J.category_type as month,K.category_type as exam_type,E.programme_name,D.degree_name,B.coe_student_mapping_id,G.coe_sub_mapping_id,F.month as exam_month')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_value_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'coe_category_type as J', 'J.coe_category_type_id=F.month')
                ->join('JOIN', 'coe_category_type as K', 'K.coe_category_type_id=F.mark_type')
                ->join('JOIN', 'sub as G', 'G.coe_sub_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_value_subjects H', 'H.coe_val_sub_id=G.val_subject_id');
            $query->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term'], 'A.student_status' => 'Active'])
                 ->andWhere(['<>', 'course_type_id', 122])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
            $query->orderBy('A.register_number,H.subject_code');
            $ese_list = $query->createCommand()->queryAll();
           // print_r($ese_list);exit;
            $subject_get_data = new  Query();
            $subject_get_data->select('distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_value_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'sub as G', 'G.coe_sub_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_value_subjects H', 'H.coe_val_sub_id=G.val_subject_id');
            $subject_get_data->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term']])
                 ->andWhere(['<>', 'course_type_id', 122])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
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
                ->join('JOIN', 'coe_value_mark_entry as F', 'F.student_map_id=B.coe_student_mapping_id')
                ->join('JOIN', 'sub as G', 'G.coe_sub_mapping_id=F.subject_map_id')
                ->join('JOIN', 'coe_value_subjects H', 'H.coe_val_sub_id=G.val_subject_id');
            $countQuery->Where(['F.year' => $_POST['mark_year'], 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'F.month' => $_POST['month'], 'F.mark_type' => $_POST['mark_type'], 'F.term' => $_POST['term']])
                ->andWhere(['<>', 'course_type_id', 122])
                ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
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
                return $this->redirect(['coe-value-mark-entry/esemarklist']);
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
                'SetFooter' => ['Conslidate ESE Mark List(value added)' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
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

}

