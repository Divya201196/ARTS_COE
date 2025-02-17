<?php

namespace app\controllers;
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');
use Yii;
use app\models\Batch;
use app\models\BatchSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Regulation;
use app\models\MarkEntry;
use app\models\Student;
use app\models\Programme;
use app\models\Degree;
use app\models\CoeBatDegReg;
use app\models\SubjectsMapping;
use app\models\StudentMapping;
use app\models\Categorytype;
use app\models\Configuration;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use yii\db\Query;
use kartik\widgets\Growl;
use kartik\mpdf\Pdf;
use yii\helpers\Url;

class BatchController extends Controller
{
    
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

   
    public function actionIndex()
    {
        $searchModel = new BatchSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH).' ');
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }


    public function actionView($id)
    {
        $model = $this->findModel($id);
        $regulation = new Regulation();  

        if ($model->load(Yii::$app->request->post()) && $model->save()) {            
            return $this->redirect(['view', 'id' => $model->coe_batch_id]);
        } else {
            return $this->render('update', [
                'model' => $model,'regulation' => $regulation,
            ]);
        }
    }

   
    public function actionCreate()
    {
        $model = new Batch();
        $regulation = new Regulation();
        $degree = new Degree();        
        $programme = new Programme();        
        $coebatdegreg = new CoeBatDegReg();
        if ($model->load(Yii::$app->request->post()) && $regulation->load(Yii::$app->request->post()))
        {  
            $reg_year = $regulation->regulation_year;                        
            $check_batch = Batch::find()->where(['batch_name' => $model->batch_name])->one();            
            $check_regulation = Regulation::find()->where(['regulation_year' => $regulation->regulation_year])->one();                          
            if(!empty($check_batch->batch_name)){
                Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH).' is Already Exists');
                return $this->render('create', [
                    'model' => $model,
                    'regulation' => $regulation,
                    'degree' => $degree,
                    'programme' => $programme,
                ]);                
            }else{
                $model->attributes;
                $model->created_at = new \yii\db\Expression('NOW()');
                $model->created_by = Yii::$app->user->getId();
                $model->updated_at = new \yii\db\Expression('NOW()');
                $model->updated_by = Yii::$app->user->getId();                
                if(isset($_POST['deg'])){
                    $model->save();                              
                    if(isset($_POST['from']) && isset($_POST['to']) && isset($_POST['name']) && isset($_POST['point'])){
                        $from = $_POST['from'];
                        $to = $_POST['to'];
                        $name = $_POST['name'];
                        $point = $_POST['point'];
                        for($i=0;$i<count($from);$i++){                                
                            $regulation = new Regulation();
                            $regulation->coe_batch_id=$model->coe_batch_id; 
                            $regulation->regulation_year=$reg_year;
                            $regulation->grade_point_from=$from[$i];
                            $regulation->grade_point_to=$to[$i];
                            $regulation->grade_name=$name[$i];
                            $regulation->grade_point=$point[$i];
                            $regulation->created_at = new \yii\db\Expression('NOW()');
                            $regulation->created_by = Yii::$app->user->getId();
                            $regulation->updated_at = new \yii\db\Expression('NOW()');
                            $regulation->updated_by = Yii::$app->user->getId();
                            $regulation->save();
                            unset($regulation);
                        } //for                            
                    }else{
                        $regulation->coe_batch_id=$model->coe_batch_id;                    
                        $regulation->regulation_year=$reg_year;
                        $regulation->created_at = new \yii\db\Expression('NOW()');
                        $regulation->created_by = Yii::$app->user->getId();                            
                        $regulation->updated_by = Yii::$app->user->getId();
                        $regulation->save();
                    }
                    $regulation = new Regulation();                    
                    if(isset($_POST['deg']) && isset($_POST['pgm']) && isset($_POST['sec'])){
                        $deg = $_POST['deg'];
                        $pgm = $_POST['pgm'];
                        $sec = $_POST['sec']; 
                        for($i=0;$i<count($deg);$i++){   
                            $section = $sec[$i]==0?1:$sec[$i];                             
                            $deg_id = Degree::find()->where(['degree_code' => $deg[$i]])->one();
                            $prgm_id = Programme::find()->where(['programme_code' => $pgm[$i]])->one();
                            $getProg = Yii::$app->db->createCommand('SELECT * FROM coe_programme WHERE programme_code like "%'.$pgm[$i].'%" ')->queryOne();

                            $coebatdegreg = new CoeBatDegReg();
                            $coebatdegreg->coe_batch_id=$model->coe_batch_id;
                            $coebatdegreg->coe_degree_id=$deg_id->coe_degree_id;
                            $coebatdegreg->coe_programme_id=$getProg['coe_programme_id'];
                            $coebatdegreg->regulation_year=$reg_year;
                            $coebatdegreg->no_of_section=$section;
                            $coebatdegreg->created_at = new \yii\db\Expression('NOW()');
                            $coebatdegreg->created_by = Yii::$app->user->getId();
                            $coebatdegreg->updated_by = Yii::$app->user->getId();
                            $coebatdegreg->save();
                            unset($coebatdegreg);
                        } //for                                               
                    } //pgm sec
                    Yii::$app->ShowFlashMessages->setMsg('Success','Record Saved Successfully!!!');
                    return $this->redirect(['create',]);  
                }else{
                        Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH).' cannot be assigned without '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE).'!!!');
                        return $this->redirect(['create',]); 
                }                                                                                 
            } //else                      
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH).' Creation');
            return $this->render('create', [
                'model' => $model,
                'regulation' => $regulation,
                'degree' => $degree, 
                'programme' => $programme,               
            ]);
        }        
    }

    public function actionUpdated()
    {
        $coebatdegreg = new CoeBatDegReg(); 
        $batch = Batch::find()->where(['batch_name' => $_POST['batch']])->one();
        $programme = Programme::find()->where(['programme_code' => $_POST['programme']])->one();
        //echo $batch->coe_batch_id."---".$_POST['degree']."---".$programme->coe_programme_id."---".$_POST['section'];exit;
        $exist_degree_details = Yii::$app->db->createCommand("select coe_bat_deg_reg_id from coe_bat_deg_reg where coe_batch_id='".$batch->coe_batch_id."' and coe_degree_id='".$_POST['degree']."' and coe_programme_id='".$programme->coe_programme_id."'")->queryScalar();
        if(empty($exist_degree_details)){
            $section = $_POST['section']==0?1:$_POST['section'];
            $coebatdegreg->coe_batch_id=$batch->coe_batch_id;
            $coebatdegreg->coe_degree_id=$_POST['degree'];
            $coebatdegreg->coe_programme_id=$programme->coe_programme_id;
            $coebatdegreg->no_of_section=$section;
            $coebatdegreg->created_at = new \yii\db\Expression('NOW()');
            $coebatdegreg->created_by = Yii::$app->user->getId();
            $coebatdegreg->updated_by = Yii::$app->user->getId();
            return $coebatdegreg->save()?"Updated":"No";     
        }else{
           return 0;
        }
                        
    }
    
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $regulation = new Regulation();  

        if ($model->load(Yii::$app->request->post()) && $model->save()) {            
            return $this->redirect(['view', 'id' => $model->coe_batch_id]);
        } else {
            return $this->render('update', [
                'model' => $model,'regulation' => $regulation,
            ]);
        }
    }

   
    public function actionDelete($id)
    {
        $batch_mapping = CoeBatDegReg::find()->where(['coe_batch_id'=>$id])->one();
        $degree_name = Batch::findOne($id);
        $name_of_degree = $degree_name->batch_name;
        if(empty($batch_mapping))
        {
            $regulation = Regulation::findOne(['coe_batch_id'=>$id]);
            $regulation_del = Regulation::findModel($regulation->coe_regulation_id)->delete();
            if($regulation_del)
            {
                $this->findModel($id)->delete();
                Yii::$app->ShowFlashMessages->setMsg('Success',$name_of_degree.' Has Deleted Successfully!! ');   
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Delete ".$name_of_degree.' Technical Error ');   
            }
            
        }
        else
        {
            $SubjectsMapping = SubjectsMapping::findOne(['batch_mapping_id'=>$batch_mapping->coe_bat_deg_reg_id]); 
            $StudentMapping = StudentMapping::findOne(['course_batch_mapping_id'=>$batch_mapping->coe_bat_deg_reg_id]);
            if(empty($SubjectsMapping) && empty($StudentMapping))
            {   
                
                $batch_mapping_del = CoeBatDegReg::deleteAll(['coe_batch_id'=>$id]);                
                if($batch_mapping_del)
                {
                    $regulation = Regulation::findOne(['coe_batch_id'=>$id]);
                    $regulation_del = Regulation::deleteAll(['coe_batch_id'=>$id]);

                    if($regulation_del)
                    {
                        $this->findModel($id)->delete();
                        Yii::$app->ShowFlashMessages->setMsg('Success',$name_of_degree.' Has Deleted Successfully!! ');   
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Delete ".$name_of_degree.' Technical Error ');   
                    }
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error','Error while deleting record of '.$name_of_degree);
                }
                
            }
            else
            {
               
                Yii::$app->ShowFlashMessages->setMsg('Error',' You can not delete this <b>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_BATCH).'</b> Because already <b>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)."</b> are Assigned OR <b>".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)."</b> are Available");
                
            }
            
        }
        
        return $this->redirect(['index']);
    }

   
    protected function findModel($id)
    {
        if (($model = Batch::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    public function actionViewtable()
    {
        $degree = new Degree();
        $programme = new Programme();
        $degree_list = Degree::find()->all(); 
        $programme_list = Programme::find()->all();    
        return $programme_list;
    }
    public function actionViewWithdraw()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();       
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to View Withdraw Entry');
        return $this->render('view-withdraw', ['model' => $model, 'student' => $student, 'subject' => $subject]);
    }
    public function actionRevalReport()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();       
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Revaluation Report');
        return $this->render('reval-report', ['model' => $model, 'student' => $student, 'subject' => $subject]);
    }
    public function actionCourseMarksInfo()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();       
        Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to <b>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).'</b> Marks Info');
        return $this->render('course-marks-info', ['model' => $model, 'student' => $student, 'subject' => $subject]);
    }
    public function actionCiaNotZero()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();  
        return $this->render('cia-not-zero', ['model' => $model, 'student' => $student, 'subject' => $subject]);
    }
    public function actionRevalPrintPdf()
    {
      require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['reval_report'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' =>"REVAL RESULTS.pdf",                
                'format' => Pdf::FORMAT_A4,                 
                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                    'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; }td,th{border:1px solid #999; padding: 4px;}
                    }   
                ', 
                'options' => ['title' =>"REVAL RESULTS"],
                'methods' => [
                    'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                    'SetFooter' => ["REVAL RESULTS " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();      
    }
    public function actionRevalPrintExcel()
    {
        $content = $_SESSION['reval_report'];
        $fileName = "Reval Print" . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    public function actionSubMinMaxInfoPdf(){
      require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['sub_max_min_info'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' =>strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." MIN MAX.pdf",                
                'format' => Pdf::FORMAT_A4,                
                'orientation' => Pdf::ORIENT_POTRAIT,                
                'destination' => Pdf::DEST_BROWSER,                
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; }td,th{border:1px solid #999; padding: 4px;}
                    }   
                ', 
               
                'options' => ['title' =>strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." MIN MAX "],
                'methods' => [
                    'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                    'SetFooter' => [strtoupper(ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT))." MIN MAX " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();      
    }
    public function actionExcelSubMinMaxInfoPdf()
    {
        $content = $_SESSION['sub_max_min_info'];
        $fileName = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." MIN MAX " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionGetCiaNotZeroPdf(){
      require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['get_count_not_zero'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' =>"CIA NOT ZERO LIST.pdf",                
                'format' => Pdf::FORMAT_A4,                
                'orientation' => Pdf::ORIENT_POTRAIT,                
                'destination' => Pdf::DEST_BROWSER,                
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; }td,th{border:1px solid #999; padding: 4px;}
                    }   
                ', 
               
                'options' => ['title' =>"CIA NOT ZERO LIST"],
                'methods' => [
                    'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                    'SetFooter' => ["CIA NOT ZERO LIST" . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();      
    }
    public function actionGetCiaNotZeroExcel()
    {
        $content = $_SESSION['get_count_not_zero'];
        $fileName = "CIA NOT ZERO LIST " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
     public function actionPractical()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
       $category_type_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Practical%' ")->queryScalar();

        $model = new MarkEntry();
        if (Yii::$app->request->post()) 
        {
            $sem_valc = ConfigUtilities::SemCaluclation($_POST['mark_year'],$_POST['month'],$_POST['bat_map_val']);
            $month=$_POST['month'];
            $year=$_POST['mark_year'];
            $mark_type=$_POST['mark_type'];
           
            $type = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='".$mark_type."' ")->queryScalar();
            //print_r($sem_valc );exit;
             $totalSuccess = '';
            
            $query = new  Query();
            $query->select(' distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,E.programme_name,D.degree_name,D.degree_code,E.programme_code,I.batch_name,G.coe_subjects_mapping_id')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                              
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc, 'A.student_status' => 'Active','G.paper_type_id'=>$category_type_id]);
            $query->orderBy('A.register_number,H.subject_code');
            $getSubsInfoDet = $query->createCommand()->queryAll();


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
            $countQuery->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val']])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                 $query->orderBy('A.register_number,H.subject_code');
            $countOfSubjects = $countQuery->createCommand()->queryAll();
            //print_r( $countOfSubjects);exit;

        if(!empty($getSubsInfoDet))     
  {
                       
                         foreach ($getSubsInfoDet as $value)
                        {

                                     require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
                            $table='';
                            $get_month_name=Categorytype::findOne($month);
                            $header = $footer = $final_html = $body = $body_1='';
                            $header = '<table width="100%" class="table table-bordered table-responsive bulk_edit_table table-hover"  >
                                <tr>
                                        
                                          <td> 
                            <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                        </td>
                        <td colspan=27 align="center"> 
                            <center><b><font size="4px">'.$org_name.'</font></b></center>
                            <center>'.$org_address.'</center>
                            
                            <center>'.$org_tagline.'</center> 
                        </td>
                        <td align="right">  
                            <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                        </td>
                                        </tr>
                                        
                                        
                                        <tr>
                                        <td align="center" colspan=29><h2>PROFORMA FOR UG/PG PRACTICAL EXAMINATIONS '.$year.' - '.strtoupper($get_month_name['description']).'</h2>
                                        </td></tr>
                                         <tr>
                                        <td align="center"  colspan=29><h3><b>PROGRAMME: '.$value["degree_code"].'-'.$value["programme_code"].'</h3></b>
                                        </td></tr>
                                        <tr>
                                        <td align="center" colspan=29><h3><b>BATCH :'.$value['batch_name'].'</h3></b>
                                        </td></tr>
                                        <tr>
                                       
                                        <tr  class="table-danger">
                                            <th>SNO</th> 
                                            <th>Sem</th>
                                             <th>Exam Type</th>  
                                          
                                            <th>Course Title</th>
                                            <th>Course Name</th>
                                            <th colspan=15 >Date</th>
                                             <th>Session</th>
                                              <th >Reg No</th>
                                            <th>Total</th>
                                            <th>Internal Examiner</th>
                                             <th>Skilled Examiner</th>
                                              <th colspan=15>Venue/Lab</th>
                                        </tr>               
                                        
                                        ';
                             

                              $increment = 1;
                              $Num_30_nums = 0; $ext2_ph='';
            $reg = new  Query();
            $reg->select('A.register_number')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                 ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
               
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $reg->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val'],'G.coe_subjects_mapping_id'=>$value['coe_subjects_mapping_id']])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                 $query->orderBy('A.register_number,H.subject_code');
            $reg_total = $reg->createCommand()->queryAll();
            //print_r($reg_total);exit;
  
                                                    
foreach ($countOfSubjects as  $va) 
{
    if($va['count']<='45')
    {
      
      $total=$va['count'];
       //$first=1;
       $counter = 0;
      foreach ($reg_total as $item) 
      {
     
    
    if( $counter == 0 ) {
         
       
       $first=$item['register_number'];
    }
     
    
    if( $counter == $total - 1)
    {
         
        
        $last=$item['register_number'];
    }
         
    
    $counter = $counter + 1;

 

   
}
}
else
{

 $total=$va['count']/2;

$second_total=$va['count']-$total;




}

                                 
                                   $body .='<tr><td>'.$increment.'</td><td>'.$sem_valc.'</td><td>'.$type .'</td><td>'.$value["subject_code"].'</td><td>'.strtoupper($value["subject_name"]).'</td><td colspan=15></td><td></td><td>
                                    '.$first.'-'.$last.'</td><td>'.$total.' </td><td></td><td></td><td colspan=15></td></tr>';

                                     $body_1 .='<tr><td>'.$increment.'</td><td>'.$sem_valc.'</td><td>'.$type .'</td><td>'.$value["subject_code"].'</td><td>'.strtoupper($value["subject_name"]).'</td><td colspan=15></td><td></td><td>
                                    '.$first.'-'.$last.'</td><td>'.$second_total.'</td><td></td><td></td><td colspan=15></td></tr>';
                                    $increment++;
                                    if($increment%31==0)
                                    {
                                        $Num_30_nums =1;
                                        $html = $header.$body.$body_1.'</table>';
                                        $final_html .=$html;
                                        $html = $body = $body_1='';
                                    }
                                }
                            
                        }
                             $footer ='<br><tr class ="alternative_border">
                            <td align="left" colspan=4>
                                DATE <br /><br />
                                
                             <td align="left" colspan=4>
                                HOD <br /><br />
                               <br />
                            </td>
                            <td align="left" colspan=29>
                               DEAN <br /><br />
                            <br />
                            </td>
                            
                        </br></tr></tbody></table>';

                                
                              
                                if($Num_30_nums<=30)
                                  {
                                    $html = $header.$body.$body_1.$footer.'</table>';     
                                  }                  
                              $final_html .=$html;               
                              $content = $final_html;


                                $pdf = new Pdf([                   
                                    'mode' => Pdf::MODE_CORE,                 
                                    'filename' => 'PRACTICAL EXTERNAL EXAMINER VERIFICATION REPORT.pdf',                
                                    'format' => Pdf::FORMAT_A4,                 
                                    'orientation' => Pdf::ORIENT_PORTRAIT,                 
                                    'destination' => Pdf::DEST_BROWSER,                 
                                    'content' => $content,                     
                                    'cssInline' => ' @media all{
                                        table{border-collapse: collapse;  text-align: left;  font-family:"Roboto, sans-serif"; width:100%; font-size: 17px; } 
                                        
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
                                               
                                    'options' => ['title' => strtoupper('PRACTICAL').' EXTERNAL EXAMINER '],
                                    'methods' => [ 
                                        'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                                        'SetFooter'=>[strtoupper('PRACTICAL').' PROFORMA FOR UG/PG PRACTICAL EXAMINATIONS '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
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
                        } // Successfull data Available
                        else
                        {
                             Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                             return $this->redirect(['practical']);
                        }
            
          /*  if (!empty($cia_list)) {
                return $this->render('practical', [
                    'model' => $model,
                    'cia_list' => $cia_list,
                    'subjectsInfo' => $subjectsInfo,
                    'countOfSubjects' => $countOfSubjects,
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['batch/practical']);
            }*/
        } 
        else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to CIA Mark List');
            return $this->render('practical', [
                'model' => $model,
            ]);
        }
    }

}