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
use app\models\Configuration;
use yii\db\Query;
use app\models\Categorytype;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
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
           $section = $_POST['MarkEntry']['section'] != 'All' ? $_POST['MarkEntry']['section'] : '';

            $type = Yii::$app->db->createCommand("select category_type from coe_category_type where coe_category_type_id='".$mark_type."' ")->queryScalar();
       // print_r($section );exit;
             $totalSuccess = '';
            
            $query = new  Query();
            $query->select(' distinct (H.subject_code) as subject_code, H.subject_name, H.CIA_max, H.ESE_max,E.programme_name,D.degree_name,D.degree_code,E.programme_code,I.batch_name,G.coe_subjects_mapping_id,G.subject_type_id,G.semester')
                ->from('coe_student as A')
                ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
                ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
                ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
                ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
                ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
                              
                ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id');
            $query->Where(['B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc, 'A.student_status' => 'Active','G.paper_type_id'=>$category_type_id,'I.coe_batch_id'=>$_POST['bat_val']]);
             if ($section != "")
             {
                 $query->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
           
            $query->orderBy('A.register_number,H.subject_code');
            $getSubsInfoDet = $query->createCommand()->queryAll();

if(!empty($getSubsInfoDet))     
{

$increment=1;
foreach ($getSubsInfoDet as $value)
{
    
require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
$table='';
$get_month_name=Categorytype::findOne($month);
$header = $footer = $final_html = $body = $html='';
$header = '<table  style="overflow-x:auto;" width="100%" cellspacing="0" cellpadding="0" border="0" id="student_bulk_edit" class="table table-bordered table-responsive bulk_edit_table table-hover"  >
 <tr>
                                        
<td><img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo"></td>
<td colspan=7 align="center"  style=font-size:"14px"> 
 <center><b><font size="5px">'.$org_name.'</font></b></center>
 <center>'.$org_address.'</center>
 <center>'.$org_tagline.'</center> 
</td>
<td align="right">  
<img width="100" colspan=9 height="100" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
 </td>
 </tr>
 <tr>

<td align="center" colspan=9><h4>PROFORMA FOR UG/PG PRACTICAL EXAMINATIONS '.strtoupper($get_month_name['description']).' - '.$year.' </h4>
</td></tr>
<tr>
<td align="center"  colspan=9><h4><b>PROGRAMME: '.$value["degree_code"].'-'.$value["programme_code"].'</h4></b>
 </td></tr>
 <tr>
<td align="center" colspan=9><h4><b>BATCH :'.$value['batch_name'].'</h4></b>
</td></tr>
<tr>
<td colspan=3 align="left" ><b><h4>SEMESTER :'.$value['semester'].'<h4></b></td>
<td colspan=6 align="right"><b><h4>EXAM TYPE :'.$type.'<h4></b></td>
 
</td></tr>

<tr>
<td ><b>   <center> SNO</center></b></td> 
<td><b> <center> Course Code</center></b></td> 
<td><b> <center>  Course Title</center></b></td>
<td width="100px"><b> <center> Date&Session</center></b></td>
<td> <b><center>  Reg No</center></b></td>
<td> <b> <center> Total</center></b></td>
<td width="150px"> <b><center >  InternalExaminer</center></b></td>
<td width="150px">  <b><center>SkilledExaminer</center></b></td>
<td>  <b><center> Venue/Lab</center></b></td>
</tr> ';

$Num_30_nums = 0; $ext2_ph='';$footer1='';
$increment=1;
foreach ($getSubsInfoDet as $value)
{
if($value['subject_type_id']==15)
{

//echo "hi";
$reg = new  Query();
$reg->select('A.register_number')
    ->from('coe_student as A')
    ->join('JOIN', 'coe_student_mapping as B', 'B.student_rel_id=A.coe_student_id ')
    ->join('JOIN', 'coe_bat_deg_reg as C', 'C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
    ->join('JOIN', 'coe_degree as D', 'D.coe_degree_id=C.coe_degree_id')
    ->join('JOIN', 'coe_programme as E', 'E.coe_programme_id=C.coe_programme_id')
    ->join('JOIN', 'coe_subjects_mapping as G', 'G.batch_mapping_id=B.course_batch_mapping_id')
    ->JOIN ('JOIN', 'coe_batch as I', 'I.coe_batch_id=C.coe_batch_id')
     ->join('JOIN', 'coe_subjects H', 'H.coe_subjects_id=G.subject_id')
    ->join('JOIN','coe_nominal x','x.coe_subjects_id=H.coe_subjects_id and A.coe_student_id=x.coe_student_id  and B.course_batch_mapping_id=x.course_batch_mapping_id ');
     $reg->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val'],'G.coe_subjects_mapping_id'=>$value['coe_subjects_mapping_id']])
     ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
     ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
    if ($section != "")
    {
     $reg->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
    } 
    $query->orderBy('A.register_number,H.subject_code');
 
    $reg_total = $reg->createCommand()->queryAll();

    //print_r($reg_total);exit;

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
            $countQuery->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val'],'G.coe_subjects_mapping_id'=>$value['coe_subjects_mapping_id']])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                
              if ($section != "")
             {
                 $countQuery->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
               
                  $countQuery->orderBy('A.register_number,H.subject_code');
            $countOfSubjects = $countQuery->createCommand()->queryAll();
        //print_r($countOfSubjects);exit;
            
}
else
{
//echo "gg";
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
    if ($section != "")
    {
     $reg->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
    } 
    $query->orderBy('A.register_number,H.subject_code');

    $reg_total = $reg->createCommand()->queryAll();
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
            $countQuery->Where([ 'B.course_batch_mapping_id' => $_POST['bat_map_val'], 'G.semester' => $sem_valc,'I.coe_batch_id'=>$_POST['bat_val'],'G.coe_subjects_mapping_id'=>$value['coe_subjects_mapping_id']])
            ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                
              if ($section != "")
             {
                 $countQuery->andWhere(['B.section_name' => $_POST['MarkEntry']['section']]);
            } 
               
                  $countQuery->orderBy('A.register_number,H.subject_code');
            $countOfSubjects = $countQuery->createCommand()->queryAll();
            //rint_r($countOfSubjects);exit;

}

//print_r($reg_total);exit;
foreach ($countOfSubjects as  $va) 
{

$abs_reg='';
  
if($va['count']<='45')
{
  $total=$va['count'];
  $counter = 0;
  $loop=0; $rsl=1; $firstregno=array();
  array_multisort(array_column($reg_total, 'register_number'),  SORT_ASC, $reg_total);
  foreach ($reg_total as $value1) 
  {
     
    $sub=substr($value1['register_number'], 5,7);
   if($value['subject_type_id']==15)
   {


 if($loop<4)
    {
        if($rsl==$sub)
        {
            $abs_reg.=$value1['register_number'].", ";
        }
        else
        {
        $abs_reg.=$value1['register_number'].",";
        }
     }
    else if($loop==4)
    {
         $abs_reg.=$value1['register_number'].", ";
     $loop=0;
    }
   }
   else
   {

      if($rsl!=$sub)
        {
            if(($rsl!=$sub) && $rsl==1)
            {
                $rsl=$rsl+2;
                $firstregno[]=$value1['register_number'];
            }
            else
            {
                $n=count($firstregno)-1; 
                //$abs_reg.=$firstregno[0]."-".$firstregno[$n].",<br>";
                if($firstregno[0]==$firstregno[$n])
            {
                $abs_reg.=$firstregno[$n]."<br>";// exit;
            }
            else
            {
                $abs_reg.=$firstregno[0]."-".$firstregno[$n]."<br>";// exit;
            }

                $firstregno=array();
                $firstregno[]=$value1['register_number'];
                $rsl=$sub+1;
            }
            
           //print_r($abs_reg); exit;
        }
        else 
        {
            $firstregno[]=$value1['register_number'];
             $rsl++;
            
        }


        if(($total-1)==$loop)
        {
             $n=count($firstregno)-1;
            $abs_reg.=$firstregno[0]."-".$firstregno[$n];// exit;
        }
     

   }

    // if($loop<4)
    // {
    //     if($rsl==$sub)
    //     {
    //         $abs_reg.=$value1['register_number'].", ";
    //     }
    //     else
    //     {
    //         $abs_reg.=$value1['register_number'].",";
    //     }
    // }
    // else if($loop==4)
    // {
    //     $abs_reg.=$value1['register_number'].", ";
    //     $loop=0;
    // }
        //echo $rsl."!=".$sub."<br>";
       
    $loop++;
 }

   //$total=($total==0)?"*":$total;
    if(!empty($total==0))
    {
  
    }
      else
      {
             $body .='<tr><td align="center" style="line-height: 1.6em;">'.$increment.'</td><td align="center" style="line-height: 1.6em;">'.$value["subject_code"].'</td><td align="center" style="line-height: 1.6em;">'.strtoupper($value["subject_name"]).'</td><td  width: 25%; align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;">'.$abs_reg.'</td><td align="center" style="line-height: 1.6em;">'.$total.'</td><td  align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;"></td></tr>';
              $increment++;

     }


 

 
}


else
{

 $total_perbatch=round(($va['count']/2),0);
 $second_total=$va['count']-$total_perbatch;
 $arrperbatch[]=$total_perbatch;
 $arrperbatch[]=$second_total;
 $count=$va['count'];
 $last_1='';
 $counter = 0;
 $second=0;
 $kk=0;
 $abs_reg='';
 $loopbatch=round(($va['count'])/$total_perbatch);
//print_r($total_perbatch);exit; //exit;
 $arryloop=array();
 $loop=1; $s=0;
 $loop1=0; $rsl=1; $firstregno=array();
 array_multisort(array_column($reg_total, 'register_number'),  SORT_ASC, $reg_total);
  foreach ($reg_total as $value1) 
  {
    // if($loop<4)
    // {
    //     $abs_reg.=$value1['register_number'].",";
    // }
    // else if($loop==4)
    // {
    //     $abs_reg.=$value1['register_number'].", ";
    //     $loop=0;
    // }

    //$abs_reg.=$value1['register_number'].", "; 
     $sub=substr($value1['register_number'], 5,7);
     if($sub!='001')
     {
         $sub=substr($value1['register_number'], 6,8);
     }
     else
     {
         $sub=substr($value1['register_number'], 5,7);
     }
     //print_r( $reg_total);exit;

  

    //print_r($rsl);exit;
     

        if($rsl!=$sub)
        {
            if(($rsl!=$sub) && $rsl==1)
            {
                $rsl=$rsl+2;
                $firstregno[]=$value1['register_number'];
            }
            else
            {
                $n=count($firstregno)-1; 
              //  $abs_reg.=$firstregno[0]."-".$firstregno[$n].",<br>";
                //$abs_reg.=$value1['register_number'];
                $firstregno[]=$value1['register_number'];
                
                $firstregno[]=$value1['register_number'];
                $rsl=$sub+1;
            }
            
           //print_r($abs_reg); exit;
        }
        else 
        {
            $firstregno[]=$value1['register_number'];
             $rsl++;
            
        }

        if(($count-1)==$loop1 && $second_total==$loop)
        {
            //print_r($firstregno); exit;

            $n=count($firstregno)-1;
           // $abs_reg.=$firstregno[0]."-".$firstregno[$n];
            if($firstregno[0]==$firstregno[$n])
            {
                $abs_reg.=$firstregno[$n];// exit;
            }
            else
            {
                $abs_reg.=$firstregno[0]."-".$firstregno[$n];// exit;
            }
            
        }
        else if($total_perbatch==$loop)
        {
           // print_r($firstregno); exit;
            $n=count($firstregno)-1;
           
            //$abs_reg.=$firstregno[0]."-".$firstregno[$n];
            if($firstregno[0]==$firstregno[$n])
            {
                $abs_reg.=$firstregno[$n];// exit;
            }
            else
            {
                $abs_reg.=$firstregno[0]."-".$firstregno[$n];// exit;
            }

            $firstregno=array();
        }
     
     
    
    //echo $loop."==".$total_perbatch."<br>"; //exit;  
    if($loop==$total_perbatch)
    {
       // print_r($abs_reg);exit;
        $arryloop[$s]=$abs_reg;
       
         $s++;
         $loop=1;
         $abs_reg='';

        $total_perbatch= $second_total;
       
    }
    else 
    {
        
        $loop++;
    }
    
 $loop1++;
}
 //echo $rsl."!=".$sub."<br>";exit;
//exit;
//echo count($arryloop); exit;
for ($al=0; $al < count($arryloop); $al++) 
{ 
   // $tot=($arrperbatch[$al]==0)?"-":$arrperbatch[$al];
    
    $body .='<tr><td align="center" style="line-height: 1.6em;">'.$increment.'</td><td align="center" style="line-height: 1.6em;">'.$value["subject_code"].'</td><td align="center" style="line-height: 1.6em;">'.strtoupper($value["subject_name"]).'</td><td  width: 25%; align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;">'.$arryloop[$al].'</td><td align="center" style="line-height: 1.6em;">'.$arrperbatch[$al].'</td><td  align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;"></td></tr>';
$increment++;
   // print_r($increment);exit;

}


}

}



$html = $header.$body.'</table>';
 

}

//print_r( $html);exit;


    $html = $header.$body.'</table>'; 
    
    $footer.='</tbody></table><br><br><br>';

    $footer1 .='<table  style="overflow-x:auto;" width="100%"  border=0 class="table table-bordered table-responsive bulk_edit_table table-hover">
                    <tr>
                        <td width=30% align="left" style=font-weight:bold><br/><br/> DATE: </td> 
                        <td width=40% align="center"  style=font-weight:bold><br/><br/> HOD</td>
                        <td width=30% align="right" style=font-weight:bold;text-align: right;><br/><br/> DEAN</td>
                     </tr>
                 </table><br><br><br>';

    $footer1 .='<table  style="overflow-x:auto;" width="100%"  border=0 class="table table-bordered table-responsive bulk_edit_table table-hover">
                    <tr>
                        <td width=30% align="left" style=font-weight:bold><br/><br/> APPROVED BY  </td> 
                        <td width=40% align="center"  style=font-weight:bold><br/><br/> COE</td>
                        <td width=30% align="right" style=font-weight:bold;text-align: right;><br/><br/> PRINCIPAL</td>
                     </tr>
                 </table>'; 
                 $html = $header.$body. $footer. $footer1.'</table>'; 
                 $final_html .=$html;
 
            
$content = $final_html;  
$pdf = new Pdf([                   
                'mode' => Pdf::MODE_CORE,                 
                'filename' => 'PRACTICAL PROFORMA REPORT.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content, 
                 'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',                    
               'cssInline' => ' @media all{
                        table{
                          border-collapse:collapse; 
                          border: none; 
                          font-family:"Roboto, sans-serif"; 
                          width:100%; 
                          font-size: 14px; 

                        }td,th{border:1px solid #999; padding: 4px; font-size: 15px; 
                             } 
                             th, td 
                             {
        
                     }
                    }   
                   
                ', 
                                               
                                    'options' => ['title' => strtoupper('PRACTICAL').' EXTERNAL EXAMINER '],
                                    'methods' => [ 
                                        'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                                       
                                    ],
                                    
                                ]);
                                
                               
                                Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                                $headers = Yii::$app->response->headers;
                                $headers->add('Content-Type', 'application/pdf');
                                return $pdf->render(); 
                        } // Successfull data Available

                // print_r($content);exit;


}    

           

  else
                        {
                             Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                             return $this->redirect(['practical']);
                        }
  
                 


} 
else 
{
            
  Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Practical Performa');
            return $this->render('practical', [
                'model' => $model,
            ]);
        }
    }

}