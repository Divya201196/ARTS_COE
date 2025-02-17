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
use PHPExcel_Writer_CSV;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
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
    // echo $rsl."!=".$sub."<br>";
     

        if($rsl!=$sub)
        {
            if(($rsl!=$sub) && $rsl==1)
            {
                $rsl=$rsl+2;
                $firstregno[]=$value1['register_number'];
            }
            else
            {
                if(empty($firstregno)) 
                {
                     $rsl=$sub+1;
                     $firstregno[]=$value1['register_number'];
                }
                else
                {
                    $n=count($firstregno)-1; 
                   // $abs_reg.=$firstregno[0]."-".$firstregno[$n].",<br>";
                    if($firstregno[0]==$firstregno[$n])
            {
                $abs_reg.=$firstregno[$n].",";// exit;
            }
            else
            {
                $abs_reg.=$firstregno[0]."-".$firstregno[$n].",<br>";// exit;
            }

                    //$abs_reg.=$value1['register_number'];
                    //$firstregno[]=$value1['register_number'];
                    $firstregno=array();
                    
                    $firstregno[]=$value1['register_number'];
                    $rsl=$sub+1;
                }
                //print_r($firstregno); exit;
                    
            }
            
           
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
                        <td width=20% align="left" style=font-weight:bold><br/><br/> DATE: </td> 
                        <td width=40% align="center"  style=font-weight:bold><br/><br/> HOD</td>
                        <td width=40% align="right" style=font-weight:bold;text-align: right;><br/><br/> DEAN</td>
                     </tr>
                 </table><br><br><br>';

   $footer1 .=' <tr>
                        
                    <td width=10% align="center" style=font-weight:bold><br/><br/> APPROVED BY  </td> 
                       
                  </tr>';
                
    $footer1 .='<table  style="overflow-x:auto;" width="100%"  border=0 class="table table-bordered table-responsive bulk_edit_table table-hover">
                    <tr>
                       
                        <td width=50% align="center"  style=font-weight:bold><br/><br/> COE</td>
                        <td width=50% align="center" style=font-weight:bold;text-align: right;><br/><br/> PRINCIPAL</td>
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


     public function actionProject()
    {
        $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        
       $category_type_id = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Project and Viva Voce%' ")->queryScalar();

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
$get_month_name=Categorytype::findOne($month);

foreach ($getSubsInfoDet as $value)
{
    
require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
$table='';
/*if($year='2024' && $month='30')
{
    $month_name ='JULY';
}
else
{
    $month_name=$get_month_name['description'];
}*/
//print_r($year);exit;
$month_name=$get_month_name['description'];
$header = $footer = $final_html = $body = $html='';
$header = '<table  style="overflow-x:auto;" width="100%" cellspacing="0" cellpadding="0" border="0" id="student_bulk_edit" class="table table-bordered table-responsive bulk_edit_table table-hover"  >
 <tr>
                                        
<td><img width="100"   colspan=6  height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo"></td>
<td colspan=6 align="center"  style=font-size:"14px"> 
 <center><b><font size="5px">'.$org_name.'</font></b></center>
 <center>'.$org_address.'</center>
 <center>'.$org_tagline.'</center> 
</td>
<td align="right">  
<img width="100" colspan=8 height="100" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
 </td>
 </tr>
 <tr>
<td align="center" colspan=9><h4>PROFORMA FOR UG/PG PRACTICAL EXAMINATIONS '.strtoupper($get_month_name['description']).' - '.$year.' </h4>
</td></tr>
<tr>
<td align="center"  colspan=8><h4><b>PROGRAMME: '.$value["degree_code"].'-'.$value["programme_code"].'</h4></b>
 </td></tr>
 <tr>
<td align="center" colspan=8><h4><b>BATCH :'.$value['batch_name'].'</h4></b>
</td></tr>
<tr>
<td colspan=3 align="left" ><b><h4>SEMESTER :'.$value['semester'].'<h4></b></td>
<td colspan=4 align="right"><b><h4>EXAM TYPE :'.$type.'<h4></b></td>
 
</td></tr>

<tr>
<td ><b>   <center> SNO</center></b></td> 
<td><b> <center> Course Code</center></b></td> 
<td><b> <center>  Course Title</center></b></td>
<td width="100px"><b> <center> Date&Session</center></b></td>
<td> <b><center>  Reg No</center></b></td>
<td> <b> <center> Total</center></b></td>
<td width="150px"><b><center>  InternalExaminer</center></b></td>
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
             $body .='<tr><td align="center" style="line-height: 1.6em;">'.$increment.'</td><td align="center" style="line-height: 1.6em;">'.$value["subject_code"].'</td><td align="center" style="line-height: 1.6em;">'.strtoupper($value["subject_name"]).'</td><td  width: 25%; align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;">'.$abs_reg.'</td><td align="center" style="line-height: 1.6em;">'.$total.'</td>
             <td  align="center" style="line-height: 1.6em;"></td><td  align="center" style="line-height: 1.6em;"></td></tr>';
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
    // echo $rsl."!=".$sub."<br>";
     

        if($rsl!=$sub)
        {
            if(($rsl!=$sub) && $rsl==1)
            {
                $rsl=$rsl+2;
                $firstregno[]=$value1['register_number'];
            }
            else
            {
                if(empty($firstregno)) 
                {
                     $rsl=$sub+1;
                     $firstregno[]=$value1['register_number'];
                }
                else
                {
                    $n=count($firstregno)-1; 
                   // $abs_reg.=$firstregno[0]."-".$firstregno[$n].",<br>";
                    if($firstregno[0]==$firstregno[$n])
            {
                $abs_reg.=$firstregno[$n].",";// exit;
            }
            else
            {
                $abs_reg.=$firstregno[0]."-".$firstregno[$n].",<br>";// exit;
            }

                    //$abs_reg.=$value1['register_number'];
                    //$firstregno[]=$value1['register_number'];
                    $firstregno=array();
                    
                    $firstregno[]=$value1['register_number'];
                    $rsl=$sub+1;
                }
                //print_r($firstregno); exit;
                    
            }
            
           
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
    
    $body .='<tr><td align="center" style="line-height: 1.6em;">'.$increment.'</td><td align="center" style="line-height: 1.6em;">'.$value["subject_code"].'</td><td align="center" style="line-height: 1.6em;">'.strtoupper($value["subject_name"]).'</td><td  width: 25%; align="center" style="line-height: 1.6em;"></td><td align="center" style="line-height: 1.6em;">'.$arryloop[$al].'</td><td align="center" style="line-height: 1.6em;">'.$arrperbatch[$al].'</td><td  align="center" style="line-height: 1.6em;"><td  align="center" style="line-height: 1.6em;"></td></tr>';
$increment++;
   // print_r($increment);exit;

}


}

}



$html = $header.$body.'</table>';
 

}

//print_r( $html);exit;


    $html = $header.$body.'</table>'; 
    
    $footer.='</tbody></table><br><br>';

    $footer1 .='<table  style="overflow-x:auto;" width="100%"  border=0 class="table table-bordered table-responsive bulk_edit_table table-hover">
                    <tr>
                        <td width=30% align="left" style=font-weight:bold><br/><br/> DATE: </td> 
                        <td width=40% align="center"  style=font-weight:bold><br/><br/> HOD</td>
                        <td width=30% align="right" style=font-weight:bold;text-align: right;><br/><br/> DEAN</td>
                     </tr>
                 </table><br><br>';

   
   $footer1 .=' <tr>
                        
                    <td width=10% align="center" style=font-weight:bold><br/><br/> APPROVED BY  </td> 
                       
                  </tr>';
                
    $footer1 .='<table  style="overflow-x:auto;" width="100%"  border=0 class="table table-bordered table-responsive bulk_edit_table table-hover">
                    <tr>
                       
                        <td width=50% align="center"  style=font-weight:bold><br/><br/> COE</td>
                        <td width=50% align="center" style=font-weight:bold;text-align: right;><br/><br/> PRINCIPAL</td>
                     </tr>
                 </table>';
                 $html = $header.$body. $footer. $footer1.'</table>'; 
                 $final_html .=$html;
 
            
$content = $final_html;  
$pdf = new Pdf([                   
                'mode' => Pdf::MODE_CORE,                 
                'filename' => 'PROJECT PROFORMA REPORT.pdf',                
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
                                               
                                    'options' => ['title' => strtoupper('PROJECT').' EXTERNAL EXAMINER '],
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
         return $this->redirect(['project']);
    }

} 
else 
{
            
  Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to Project Performa');
            return $this->render('project', [
                'model' => $model,
            ]);
        }
    }
     public function actionCoverNumberPrac()
    {
        
        $model = new MarkEntry();
      Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Answer Covers Register Number');
        return $this->render('cover-number-prac', [
            'model' => $model,
           
        ]);
    }

 public function actionPrintRegisterNumbersPdf(){
      require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['get_print_reg'];
        //print_r($content);exit;        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' =>'Register Number Covers.pdf',                
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
                          font-size: 18px; 
                        }td,th{border:1px solid #999; padding: 4px;}
                    }   
                ', 
                'options' => ['title' =>"Register Number Covers"],                
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();        
        unset($_SESSION['get_print_reg']);
    }

    public function actionNadReport()
    {
        $model = new MarkEntry();
        $student = new Student();
        $subject = new SubjectsMapping();
        if (Yii::$app->request->post()) 
        {

        $batch= $_POST['bat_val'];
        $year=$_POST['withdraw_year'];
        $programme=$_POST['bat_map_val'];
        $month=$_POST['consolidate_month'];

      // print_r($programme);exit;
        
       $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
       
        $query_map_id = new Query();
        $query_map_id->select(['register_number','A.year','H.description as month_name','A.grade_name','degree_code','programme_code','subject_code','subject_name','A.CIA','CIA_max','A.ESE','ESE_max','A.total','A.student_map_id','A.subject_map_id','course_batch_mapping_id','coe_subjects_id','A.mark_type','A.term','A.month','x.batch_name','semester','A.result','stu.name','stu.gender','stu.dob','programme_name','C.batch_mapping_id','A.grade_name','A.grade_point','A.year_of_passing','sub.credit_points','A.result','stu.abc_number_id','stu.admission_year','stu.aadhar_number',"concat(G.degree_code,E.programme_code) as degree_name",'G.degree_type'])
                ->from('coe_mark_entry_master A')
                ->join('JOIN', 'coe_student_mapping B', 'B.coe_student_mapping_id=A.student_map_id')
                ->join('JOIN', 'coe_student stu', 'stu.coe_student_id=B.student_rel_id')
                
                ->join('JOIN', 'coe_subjects_mapping C', 'C.coe_subjects_mapping_id=A.subject_map_id and C.batch_mapping_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_subjects sub', 'sub.coe_subjects_id=C.subject_id')
                ->join('JOIN', 'coe_bat_deg_reg D', 'D.coe_bat_deg_reg_id=C.batch_mapping_id and D.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                ->join('JOIN', 'coe_programme E', 'E.coe_programme_id=D.coe_programme_id')
                ->join('JOIN', 'coe_degree G', ' G.coe_degree_id=D.coe_degree_id ')
                ->join('JOIN', 'coe_batch x', ' x.coe_batch_id=D.coe_batch_id ')
                ->join('JOIN', 'coe_category_type H', 'H.coe_category_type_id=A.month')
                ->join('JOIN', 'coe_mark_entry I', 'I.student_map_id=A.student_map_id and I.subject_map_id=A.subject_map_id and I.month=A.month and I.year=A.year and I.mark_type=A.mark_type')
                ->where(['student_status' => 'Active','A.year'=>$year,'A.month'=>$month,'I.year'=>$year,'I.month'=>$month,'C.batch_mapping_id'=> $programme,'x.coe_batch_id'=>$batch])
                 ->andWhere(['NOT IN', 'abc_number_id', ''])
                ->groupBy('register_number');
        $query_map_id->orderby('register_number asc');
        $nad = $query_map_id->createCommand()->queryAll();




        
    
       // $colspan = 20-$count;

        if (!empty($nad)) 
            {
                return $this->render('nad-report', [
                    'model' => $model,
                    'student' => $student, 'subject' => $subject,'nad'=>$nad,'year'=>$year,'month'=>$month,'programme' =>$programme
                ]);
            } else {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['batch/nad-report']);
            }
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome', 'Welcome to ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT) . ' Wise Arrear ' . ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('nad-report', [
                'model' => $model,'student' => $student, 'subject' => $subject

            ]);
        }

}

/*public function actionNadWebExcel()
{
        
          
        $content = $_SESSION['get_excel_query'];
        $sub_max= $_SESSION['get_excel_subsmax'];
        $sem_verify=$_SESSION['sem'];

        //$stusublist= $_SESSION['get_excel_stusublist'];
        //print_r($stusublist);exit;
        $objPHPExcel = new \PHPExcel();
         $objPHPExcel->createSheet(0); //Setting index when creating
         $objPHPExcel->setActiveSheetIndex(0);
         $objWorkSheet = $objPHPExcel->getActiveSheet();
         $objWorkSheet->setTitle('NAD Website');

         $objWorkSheet->setCellValue('A1','ORG_NAME');
         $objWorkSheet->setCellValue('B1','ACADEMIC_COURSE_ID');
         $objWorkSheet->setCellValue('C1','COURSE_NAME');
         $objWorkSheet->setCellValue('D1','STREAM');
         $objWorkSheet->setCellValue('E1','SESSION');
         $objWorkSheet->setCellValue('F1','REMARKS');
          $objWorkSheet->setCellValue('G1','ADMISSION_YEAR');
         $objWorkSheet->setCellValue('H1','REGN_NO');
         $objWorkSheet->setCellValue('I1','RROLL');
         $objWorkSheet->setCellValue('J1','CNAME');
         $objWorkSheet->setCellValue('K1','AADHAAR_NAME');
          $objWorkSheet->setCellValue('L1','ABC_ACCOUNT_ID');
         $objWorkSheet->setCellValue('M1','GENDER');
         $objWorkSheet->setCellValue('N1','DOB');
         $objWorkSheet->setCellValue('o1','PHOTO');
         $objWorkSheet->setCellValue('P1','MRKS_REC_STATUS');
         $objWorkSheet->setCellValue('Q1','YEAR');
         $objWorkSheet->setCellValue('R1','MONTH');
         $objWorkSheet->setCellValue('S1','SEM');
         $objWorkSheet->setCellValue('T1','CGPA');
         $objWorkSheet->setCellValue('U1','SGPA');
         $objWorkSheet->setCellValue('V1','TOT_CREDIT');
         $objWorkSheet->setCellValue('W1','TOT_CREDIT_POINTS');


                        $count=$sub_max*8;
                    //print_r($count);exit;
                $objPHPExcel->setActiveSheetIndex(0);                
                $sheet = $objPHPExcel->getSheet();
                $objSheet = $objPHPExcel->getActiveSheet();
                $firstColumn = $sheet->getHighestColumn();
                $row = 1;
                
                
                 $strt=23;

                  $char_change = $strt+$count;

                 $endColumn = PHPExcel_Cell::stringFromColumnIndex($char_change);//exit;
            
            for ($s=1; $s <= $sub_max; $s++) 
            { 
                
                $name="SUB".$s."NM";
                $subcode="SUB".$s."";
                $grade="SUB".$s."_GRADE";
                $gradepoints="SUB".$s."_GRADE_POINTS";
                $credit="SUB".$s."_CREDIT";
                $credit_points="SUB".$s."_CREDIT_POINTS";
                $eligibility="SUB".$s."_CREDIT_ELIGIBILITY";
                $remarks="SUB".$s."_REMARKS";

                if($s==1)
                {
                    
                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo ($strt+1).$column2;

                    $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;


                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);

                      $strt=$strt+1;

                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   
                }
                else
                {
                 
                    $strt=$strt+1;
                     

                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo ($strt).$column2;

                     $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                      $strt=$strt+1;

                    $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    
                    $strt=$strt+1;

                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                }

             
                
   
                $objPHPExcel->getActiveSheet()->setCellValue($column1.'1',$name);
                $objPHPExcel->getActiveSheet()->setCellValue($column2.'1',$subcode);
                $objPHPExcel->getActiveSheet()->setCellValue($column3.'1',$grade);
                $objPHPExcel->getActiveSheet()->setCellValue($column4.'1',$gradepoints);
                $objPHPExcel->getActiveSheet()->setCellValue($column5.'1', $credit);
                $objPHPExcel->getActiveSheet()->setCellValue($column6.'1', $credit_points);
                $objPHPExcel->getActiveSheet()->setCellValue($column7.'1',$eligibility);
                $objPHPExcel->getActiveSheet()->setCellValue($column8.'1', $remarks);
                
                

            }//exit;

                    // $objWorkSheet->setCellValue('V1',$name);
                         // $objWorkSheet->setCellValue('W1',$subcode);
                         // $objWorkSheet->setCellValue('X1',$grade);
                         //  $objWorkSheet->setCellValue('Y1',$gradepoints);  $objPHPExcel->getActiveSheet()->setCellValue($column6.'1', $credit_points);
                           // $objWorkSheet->setCellValue('AA1',$credit_points);
                            // $objWorkSheet->setCellValue('AB1',$remarks);
                           
  $sno=1;$row=2;
 foreach ($content as  $rows)
 {
    
    $sub_max= $_SESSION['get_excel_subsmax'];
    if($rows['month_name']=="Oct/Nov")
                      {



                 // /$month="Nov/Dec";
                        $month="NOV";
                      }
                      else
                      {

                    //$month=$rows["month_name"];
                        $month="APRIL";


                         }
//$sem_verify = ConfigUtilities::SemCaluclation(  $year,$month,$rows['course_batch_mapping_id']);
 $sem = ConfigUtilities::getSemesterRoman($sem_verify); 
  

     
 require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
    
$p='CHOICE BASED CREDIT SYSTEM';
$batch='BATCH 2021-2025 (Regulations 2021)';
$org='SRI KRISHNA COLLEGE OF TECHNOLOGY';
$m='O';

$get_total_1 = 'SELECT sum(A.grade_point*D.credit_points) as cgpa FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"   and student_map_id="'.$rows['student_map_id'].'" and month="'.$rows['month'].'"'; 
          $gettotalcredits = Yii::$app->db->createCommand($get_total_1)->queryScalar();

  $get_credits_1 = 'SELECT  sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE    student_map_id="'.$rows['student_map_id'].'" and  year="'.$rows['year'].'" and month="'.$rows['month'].'" ';
     $passcredits_1 = Yii::$app->db->createCommand($get_credits_1)->queryScalar();
          //print_r($passcredits_1);exit;
 if($sem_verify%2==0)
 
 {

    $get_total = 'SELECT sum(A.grade_point*D.credit_points) as cgpa FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year<="'.$rows['year'].'"   and student_map_id="'.$rows['student_map_id'].'" and result like "%pass%"  AND subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where year="'.$rows['year'].'" and student_map_id="'.$rows['student_map_id'].'" and month=30 and result like "%pass%"  ) '; 
          $totalcredits = Yii::$app->db->createCommand($get_total)->queryScalar();
         

$get_credits = 'SELECT sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year<="'.$rows['year'].'"   and student_map_id="'.$rows['student_map_id'].'" and result like "%pass%"  AND subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where year="'.$rows['year'].'" and student_map_id="'.$rows['student_map_id'].'" and month=30 and result like "%pass%"  ) '; 
          $passcredits = Yii::$app->db->createCommand($get_credits)->queryScalar();
    
 }

 else
 {

$get_total = 'SELECT  sum(A.grade_point*D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE   student_map_id="'.$rows['student_map_id'].'"   and A.result="Pass"  and B.semester<="'.$sem_verify.'"';
     $totalcredits = Yii::$app->db->createCommand($get_total)->queryScalar();

   $get_credits = 'SELECT  sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE    student_map_id="'.$rows['student_map_id'].'" and A.result="Pass"  and B.semester<="'.$sem_verify.'"';
     $passcredits = Yii::$app->db->createCommand($get_credits)->queryScalar();


 }


// print_r($passcredits);exit;

     if(!empty($totalcredits) && !empty($passcredits))
     {
  $cgpa=round($totalcredits/$passcredits,2);
    }
    else
    {

        $cgpa=0;
    }
//print_r( $cgpa);exit;


  $get_total_gpa = 'SELECT  sum(A.grade_point*D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'" and semester="'.$sem_verify.'" ';
     $totalcreditsgpa = Yii::$app->db->createCommand($get_total_gpa)->queryScalar();

     $get_credits_gpa = 'SELECT  sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'" and A.result="Pass" and semester="'.$sem_verify.'"';
     $passcreditsgpa = Yii::$app->db->createCommand($get_credits_gpa)->queryScalar();
      

      if(!empty($totalcreditsgpa) &&!empty($passcreditsgpa))
     {
         $gpa=round($totalcreditsgpa/$passcreditsgpa,2);
    }
    else
    {

        $gpa=0;
    }

     $date=date_create($rows['dob']);
     $dob= date_format($date,"d-m-Y ");
   


      $objWorkSheet->setCellValue('A'.$row,$org);
       $objWorkSheet->setCellValue('B'.$row,$rows["programme_code"]);
       $objWorkSheet->setCellValue('C'.$row,$rows["degree_code"].'-'.$rows["programme_name"]);
       $objWorkSheet->setCellValue('D'.$row,'');
       $objWorkSheet->setCellValue('E'.$row,'');
       $objWorkSheet->setCellValue('F'.$row,$batch);
        $objWorkSheet->setCellValue('G'.$row,$rows["admission_year"]);
        $objWorkSheet->setCellValue('H'.$row,$rows["register_number"]);
        $objWorkSheet->setCellValue('I'.$row,$rows["register_number"]);
        $objWorkSheet->setCellValue('J'.$row,$rows["name"]);
        $objWorkSheet->setCellValue('K'.$row,'');
        $objWorkSheet->setCellValue('L'.$row,$rows["abc_number_id"]);
        $objWorkSheet->setCellValue('M'.$row,$rows["gender"]);
        $objWorkSheet->setCellValue('N'.$row,$dob);
        $objWorkSheet->setCellValue('O'.$row,'');
        $objWorkSheet->setCellValue('P'.$row,$m);
        $objWorkSheet->setCellValue('Q'.$row,$rows['year']);
        $objWorkSheet->setCellValue('R'.$row,$month);
        $objWorkSheet->setCellValue('S'.$row,$sem);
        $objWorkSheet->setCellValue('T'.$row,$cgpa);
        $objWorkSheet->setCellValue('U'.$row,$gpa);
        $objWorkSheet->setCellValue('V'.$row, $passcredits_1);
        $objWorkSheet->setCellValue('W'.$row,$gettotalcredits);
         



             $stusublist = Yii::$app->db->createCommand("select C.subject_name,C.subject_code,B.grade_name,B.grade_point,C.credit_points,B.result,B.subject_map_id,C.CIA_max,C.ESE_max,A.semester from coe_subjects_mapping as A JOIN coe_mark_entry_master as B ON B.subject_map_id=A.coe_subjects_mapping_id JOIN coe_subjects as C ON C.coe_subjects_id=A.subject_id where batch_mapping_id='".$rows['batch_mapping_id']."' and B.year='".$rows["year"]."' and B.month='".$rows["month"]."' and B.student_map_id='".$rows['student_map_id']."' ")->queryAll();
             //print_r($stusublist);exit;

                 $strt=23;

                  $char_change = $strt+$count;

                 $endColumn = PHPExcel_Cell::stringFromColumnIndex($char_change);

                 $sll=1;

               foreach ($stusublist as  $value1) 
              {
                $sem_fail = ConfigUtilities::getSemesterRoman( $value1['semester']); 

                             if($sll==1)
                {
                    
                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo ($strt+1).$column2;

                    $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;


                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;


                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   
                }
                else
                {
                 
                    $strt=$strt+1;
                     

                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo ($strt).$column2;

                     $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                      $strt=$strt+1;

                    $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                }


                             
                             //print_r($stusublist);exit;
                              $get_cgpa_grades = 'SELECT A.grade_point*D.credit_points as totalcredits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'" and subject_map_id="'.$value1['subject_map_id'].'"';
                               $credits = Yii::$app->db->createCommand($get_cgpa_grades)->queryScalar();


                          

                               if($value1['grade_name'] =='S')
                               {

                               $grade_name=0;
                               $result=0;
                               }

                               else
                               {

                       $grade_name=$value1["grade_name"];
                         $result=$value1["result"];
                               }

                               if($value1['grade_name']=='S' && $value1['result']=="Pass")
                               {
                                  $grade_point="Completed";

                               }

                               elseif ($value1['grade_name']=='S' && $value1['result']=="Fail") 
                               {
                                   $grade_point="Not Completed";
                               }

                               else
                               {
                                  $grade_point=$value1["grade_point"];

                               }

                               $subject_name=$value1["subject_name"];
                               $subject_code=$value1["subject_code"];
                               $grade=$grade_name;
                               $point=$grade_point;
                               $credit_points=$value1["credit_points"];
                               $cr=$credits;
                               $res=$result;
                               if($res=="Pass")
                               {
                                  $res_1= $res."($sem_fail SEM COURSE)";

                               }
                               else
                               {
                                 $res_1=$res."($sem_fail SEM COURSE)";

                               }
                               //print_r($grade);exit;

                               //$objWorkSheet->setCellValue('V'.$row, $grade);
 $objPHPExcel->getActiveSheet()->setCellValue($column1.$row,$subject_name);
 $objPHPExcel->getActiveSheet()->setCellValue($column2.$row,$subject_code);
 $objPHPExcel->getActiveSheet()->setCellValue($column3.$row,$grade);
 $objPHPExcel->getActiveSheet()->setCellValue($column4.$row,$point);
 $objPHPExcel->getActiveSheet()->setCellValue($column5.$row,$credit_points);
 $objPHPExcel->getActiveSheet()->setCellValue($column6.$row,$cr);
 $objPHPExcel->getActiveSheet()->setCellValue($column7.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column8.$row,$res_1);

$sll++;
}

  $row++;
$sno++;
}
      

      header('Content-type: application/.csv');
        header('Content-Disposition: attachment; filename="report.csv"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

        }*/
 public function actionNadWebExcel()
{
        
          
        $content = $_SESSION['get_excel_query'];
        $sub_max= $_SESSION['get_excel_subsmax'];
        $sem_verify=$_SESSION['sem'];

        //$stusublist= $_SESSION['get_excel_stusublist'];
        //print_r($stusublist);exit;
         $objPHPExcel = new \PHPExcel();
         $objPHPExcel->createSheet(0); //Setting index when creating
         $objPHPExcel->setActiveSheetIndex(0);
         $objWorkSheet = $objPHPExcel->getActiveSheet();
         $objWorkSheet->setTitle('NAD Website');

         $objWorkSheet->setCellValue('A1','ORG_NAME');
         $objWorkSheet->setCellValue('B1','AADHAAR_NAME');
         $objWorkSheet->setCellValue('C1','ADMISSION_YEAR');
         $objWorkSheet->setCellValue('D1','ACADEMIC_COURSE_ID');
         $objWorkSheet->setCellValue('E1','COURSE_NAME');
         $objWorkSheet->setCellValue('F1','STREAM');
         $objWorkSheet->setCellValue('G1','SESSION');
         $objWorkSheet->setCellValue('H1','REGN_NO');
         $objWorkSheet->setCellValue('I1','RROLL');
         $objWorkSheet->setCellValue('J1','CNAME');
         $objWorkSheet->setCellValue('K1','GENDER');
         $objWorkSheet->setCellValue('L1','DOB');
         $objWorkSheet->setCellValue('M1','FNAME');
         $objWorkSheet->setCellValue('N1','MNAME');
         $objWorkSheet->setCellValue('O1','PHOTO');
         $objWorkSheet->setCellValue('P1','MRKS_REC_STATUS');
         $objWorkSheet->setCellValue('Q1','RESULT');
         $objWorkSheet->setCellValue('R1','YEAR');
         $objWorkSheet->setCellValue('S1','MONTH');
         $objWorkSheet->setCellValue('T1','PERCENT');
         $objWorkSheet->setCellValue('U1','DOI');
         $objWorkSheet->setCellValue('V1','CERT_NO');
         $objWorkSheet->setCellValue('W1','SEM');
         $objWorkSheet->setCellValue('X1','EXAM_TYPE');
         $objWorkSheet->setCellValue('Y1','TOT');
         $objWorkSheet->setCellValue('Z1','TOT_MIN');
         $objWorkSheet->setCellValue('AA1','TOT_MRKS');
         $objWorkSheet->setCellValue('AB1','TOT_TH_MAX');
         $objWorkSheet->setCellValue('AC1','TOT_TH_MIN');
         $objWorkSheet->setCellValue('AD1','TOT_TH_MRKS');
         $objWorkSheet->setCellValue('AE1','TOT_PR_MAX');
         $objWorkSheet->setCellValue('AF1','TOT_PR_MIN');
         $objWorkSheet->setCellValue('AG1','TOT_PR_MRKS');
         $objWorkSheet->setCellValue('AH1','TOT_CE_MAX');
         $objWorkSheet->setCellValue('AI1','TOT_CE_MIN');
         $objWorkSheet->setCellValue('AJ1','TOT_CE_MRKS');
         $objWorkSheet->setCellValue('AK1','TOT_VV_MAX');
         $objWorkSheet->setCellValue('AL1','TOT_VV_MIN');
         $objWorkSheet->setCellValue('AM1','TOT_VV_MRKS');
         $objWorkSheet->setCellValue('AN1','TOT_CREDIT');
         $objWorkSheet->setCellValue('AO1','TOT_CREDIT_POINTS');
         $objWorkSheet->setCellValue('AP1','TOT_GRADE_POINTS');
         $objWorkSheet->setCellValue('AQ1','PREV_TOT_MRKS');
         $objWorkSheet->setCellValue('AR1','GRAND_TOT_MAX');
         $objWorkSheet->setCellValue('AS1','GRAND_TOT_MIN');
         $objWorkSheet->setCellValue('AT1','GRAND_TOT_MRKS');
         $objWorkSheet->setCellValue('AU1','GRAND_TOT_CREDIT');
         $objWorkSheet->setCellValue('AV1','CGPA');
         $objWorkSheet->setCellValue('AW1','REMARKS');
         $objWorkSheet->setCellValue('AX1','SGPA');
         $objWorkSheet->setCellValue('AY1','ABC_ACCOUNT_ID');
         $objWorkSheet->setCellValue('AZ1','TERM_TYPE');
         $objWorkSheet->setCellValue('BA1','TOT_GRADE');
        // $objWorkSheet->setCellValue('BB2','DEPARTMENT');

                $count=$sub_max*8;
                    //print_r($count);exit;
                $objPHPExcel->setActiveSheetIndex(0);                
                $sheet = $objPHPExcel->getSheet();
                $objSheet = $objPHPExcel->getActiveSheet();
                $firstColumn = $sheet->getHighestColumn();
                $row = 1;
                $strt=53;

                  $char_change = $strt+$count;

                 $endColumn = PHPExcel_Cell::stringFromColumnIndex($char_change);//exit;
            
            for ($s=1; $s <= $sub_max; $s++) 
            { 
                
                $name="SUB".$s."NM";
                $subcode="SUB".$s."";
                $submax="SUB".$s."MAX";
                $submin="SUB".$s."MIN";
                $submaxth="SUB".$s."_TH_MAX";
                $subvv="SUB".$s."_VV_MRKS";
                $subpr="SUB".$s."_PR_CE_MRKS";
                $subthmin="SUB".$s."_TH_MIN";
                $subprmax="SUB".$s."_PR_MAX";
                $subprmin="SUB".$s."_PR_MIN";
                $subcemax="SUB".$s."_CE_MAX";
                $subcemin="SUB".$s."_CE_MIN";
                $subthmrks="SUB".$s."_TH_MRKS";
                $subprmrks="SUB".$s."_PR_MRKS";
                $subcemrks="SUB".$s."_CE_MRKS";
                $subtot="SUB".$s."_TOT";
                $subgrade="SUB".$s."_GRADE";
                $subgradepoint="SUB".$s."_GRADE_POINTS";
                $subcredit="SUB".$s."_CREDIT";
                $credit_points="SUB".$s."_CREDIT_POINTS";
                $remarks="SUB".$s."_REMARKS";
                $subvvmin="SUB".$s."_VV_MIN";
                $subvvmax="SUB".$s."_VV_MAX";
                $subthcemrks="SUB".$s."_TH_CE_MRKS";
                $eligibility="SUB".$s."_CREDIT_ELIGIBILITY";
               if($s==1)
                {
                    
                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo ($strt+1).$column2;

                    $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;


                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    
                    $column9 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;
                    
                    $column10 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column11 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column12 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column13 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column14 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column15 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column16 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                     $column17 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column18 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column19 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column20 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column21 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column22 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column23 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column24 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column25 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   
                }
                else
                {
                 
                    $strt=$strt+1;
                     

                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo ($strt).$column2;

                     $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                      $strt=$strt+1;

                    $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    
                    $strt=$strt+1;

                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column9 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;
                    
                    $column10 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column11 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column12 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column13 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column14 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column15 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column16 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                     $column17 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column18 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column19 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column20 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column21 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column22 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column23 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column24 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column25 = PHPExcel_Cell::stringFromColumnIndex($strt);



                }
                 

                $objPHPExcel->getActiveSheet()->setCellValue($column1.'1',$name);
                $objPHPExcel->getActiveSheet()->setCellValue($column2.'1',$subcode);
                $objPHPExcel->getActiveSheet()->setCellValue($column3.'1',$submax);
                $objPHPExcel->getActiveSheet()->setCellValue($column4.'1',$submin);
                $objPHPExcel->getActiveSheet()->setCellValue($column5.'1', $submaxth);
                $objPHPExcel->getActiveSheet()->setCellValue($column6.'1', $subvv);
                $objPHPExcel->getActiveSheet()->setCellValue($column7.'1',$subpr);
                $objPHPExcel->getActiveSheet()->setCellValue($column8.'1',$subthmin);
                $objPHPExcel->getActiveSheet()->setCellValue($column9.'1',$subprmax);
                $objPHPExcel->getActiveSheet()->setCellValue($column10.'1', $subprmin);
                $objPHPExcel->getActiveSheet()->setCellValue($column11.'1', $subcemax);
                $objPHPExcel->getActiveSheet()->setCellValue($column12.'1', $subcemin);
                $objPHPExcel->getActiveSheet()->setCellValue($column13.'1', $subthmrks);
                $objPHPExcel->getActiveSheet()->setCellValue($column14.'1', $subprmrks);
                $objPHPExcel->getActiveSheet()->setCellValue($column15.'1', $subcemrks);
                $objPHPExcel->getActiveSheet()->setCellValue($column16.'1', $subtot);
                $objPHPExcel->getActiveSheet()->setCellValue($column17.'1', $subgrade);
                $objPHPExcel->getActiveSheet()->setCellValue($column18.'1', $subgradepoint);
                $objPHPExcel->getActiveSheet()->setCellValue($column19.'1', $subcredit);
                $objPHPExcel->getActiveSheet()->setCellValue($column20.'1', $credit_points);
                $objPHPExcel->getActiveSheet()->setCellValue($column21.'1', $remarks);
                $objPHPExcel->getActiveSheet()->setCellValue($column22.'1', $subvvmin);
                $objPHPExcel->getActiveSheet()->setCellValue($column23.'1', $subvvmax);
                $objPHPExcel->getActiveSheet()->setCellValue($column24.'1', $subthcemrks);
                $objPHPExcel->getActiveSheet()->setCellValue($column25.'1', $eligibility);
               
               }//exit;

               
                   // $objWorkSheet->setCellValue('V1',$name);
                         // $objWorkSheet->setCellValue('W1',$subcode);
                         // $objWorkSheet->setCellValue('X1',$grade);
                         //  $objWorkSheet->setCellValue('Y1',$gradepoints);  $objPHPExcel->getActiveSheet()->setCellValue($column6.'1', $credit_points);
                           // $objWorkSheet->setCellValue('AA1',$credit_points);
                            // $objWorkSheet->setCellValue('AB1',$remarks);
                           
  $sno=1;$row=2;
 foreach ($content as  $rows)
 {
    //print_r($rows);exit;
    //print_r($rows['year_of_passing']);exit;
    $sub_max= $_SESSION['get_excel_subsmax'];
    if($rows['month_name']=="Oct/Nov")
  {



// /$month="Nov/Dec";
    $month="NOV";
  }

elseif($rows['year_of_passing']=='29-2022')
  {  
    
    $month="JUNE";


  }
  else
  {

//$month=$rows["month_name"];
    $month="MAY";


     }

//$sem_verify = ConfigUtilities::SemCaluclation(  $year,$month,$rows['course_batch_mapping_id']);
 $sem = ConfigUtilities::getSemesterRoman($sem_verify); 
  

     
 require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
    
$p='CHOICE BASED CREDIT SYSTEM';
$batch='BATCH 2021-2025 (Regulations 2021)';
$org='SRI KRISHNA ARTS AND SCIENCE COLLEGE';
$m='O';
$exam_type='REGULAR';

$get_total_1 = 'SELECT sum(A.grade_point*D.credit_points) as cgpa FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"   and student_map_id="'.$rows['student_map_id'].'" and month="'.$rows['month'].'"'; 
          $gettotalcredits = Yii::$app->db->createCommand($get_total_1)->queryScalar();

  $get_credits_1 = 'SELECT  sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE    student_map_id="'.$rows['student_map_id'].'" and  year="'.$rows['year'].'" and month="'.$rows['month'].'" ';
     $passcredits_1 = Yii::$app->db->createCommand($get_credits_1)->queryScalar();
          //print_r($passcredits_1);exit;
 if($sem_verify%2==0)
 
 {

$get_total = 'SELECT sum(A.grade_point*D.credit_points) as cgpa FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year<="'.$rows['year'].'"   and student_map_id="'.$rows['student_map_id'].'" and result like "%pass%"  AND subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where year="'.$rows['year'].'" and student_map_id="'.$rows['student_map_id'].'" and month=30 and result like "%pass%"  ) AND D.part_no=3'; 
          $totalcredits = Yii::$app->db->createCommand($get_total)->queryScalar();
         

$get_credits = 'SELECT sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year<="'.$rows['year'].'"   and student_map_id="'.$rows['student_map_id'].'" and result like "%pass%"  AND subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where year="'.$rows['year'].'" and student_map_id="'.$rows['student_map_id'].'" and month=30 and result like "%pass%"  )  AND D.part_no=3'; 
     $passcredits = Yii::$app->db->createCommand($get_credits)->queryScalar();
    
 }

 else
 {

$get_total = 'SELECT  sum(A.grade_point*D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE   student_map_id="'.$rows['student_map_id'].'"   and A.result="Pass" and  year<="'.$rows['year'].'" and D.part_no=3';
     $totalcredits = Yii::$app->db->createCommand($get_total)->queryScalar();

   $get_credits = 'SELECT  sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE    student_map_id="'.$rows['student_map_id'].'" and A.result="Pass" and  year<="'.$rows['year'].'"  and D.part_no=3';
     $passcredits = Yii::$app->db->createCommand($get_credits)->queryScalar();

 }


// print_r($passcredits);exit;

     if(!empty($totalcredits) && !empty($passcredits))
     {

     $cgpa_1=($totalcredits/$passcredits);
     $cgpa_2=round($cgpa_1,2);
     $cgpa = empty($cgpa_2)?"--":round($cgpa_2,1);

    }
    else
    {

        $cgpa=0;
    }
//print_r( $cgpa);exit;

   
  

    $get_total_gpa = 'SELECT  sum(A.grade_point*D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'"  ';
    $totalcreditsgpa = Yii::$app->db->createCommand($get_total_gpa)->queryScalar();
     
     $totalcreditsgpa = round($totalcreditsgpa,1);


    $get_total_gpa1 = 'SELECT  sum(A.grade_point*D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'"  and D.part_no=3 ';
    $totalcreditsgpa1=Yii::$app->db->createCommand($get_total_gpa1)->queryScalar();
    

     $get_credits_gpa = 'SELECT  sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'" and A.result="Pass"  and D.part_no=3';
     $passcreditsgpa = Yii::$app->db->createCommand($get_credits_gpa)->queryScalar();
      

      if(!empty($totalcreditsgpa1) &&!empty($passcreditsgpa))
     {
         $gpa_1=($totalcreditsgpa1/$passcreditsgpa);
         $gpa_2=round($gpa_1,2);
         $gpa = empty($gpa_2)?"--":round($gpa_2,1);
         
     
        // print_r($gpa_result_send_part3);exit;

    }
    else
    {

        $gpa=0;
    }



     $date=date_create($rows['dob']);
     $dob= date_format($date,"d-m-Y ");
    
     //print_r($exam_type);exit;
    $stusublist_1 = Yii::$app->db->createCommand("select sum(C.end_semester_exam_value_mark) as max,sum(C.total_minimum_pass) as min,C.subject_name,C.subject_code ,sum(B.total) as total,sum(C.ESE_max) as esemax,sum(C.ESE_min) as esemin,B.grade_name,B.grade_point,
        C.credit_points,B.result,B.subject_map_id,sum(C.CIA_max) as CIA,sum(C.CIA_min) as CIAmin,C.CIA_max,C.ESE_max,sum(B.ESE) as ESE,sum(B.CIA) as ciamarks,sum(C.credit_points) as credit,A.semester from coe_subjects_mapping as A JOIN coe_mark_entry_master as B ON B.subject_map_id=A.coe_subjects_mapping_id JOIN coe_subjects as C ON C.coe_subjects_id=A.subject_id where batch_mapping_id='".$rows['batch_mapping_id']."' and B.year='".$rows["year"]."' and B.month='".$rows["month"]."' and B.student_map_id='".$rows['student_map_id']."' and result like '%pass%' " )->queryAll();


       $credit = Yii::$app->db->createCommand("select sum(C.credit_points) as credit from coe_subjects_mapping as A JOIN coe_mark_entry_master as B ON B.subject_map_id=A.coe_subjects_mapping_id JOIN coe_subjects as C ON C.coe_subjects_id=A.subject_id where batch_mapping_id='".$rows['batch_mapping_id']."' and B.year='".$rows["year"]."' and B.month='".$rows["month"]."' and B.student_map_id='".$rows['student_map_id']."' and year_of_passing!='' ")->queryAll();
   //   print_r($sem_verify);exit;


  /* if($sem_verify%2==0)
        {*/
         

          $tot_sem_credits = 'SELECT sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE  student_map_id="'.$rows['student_map_id'].'" and B.semester<='.$sem_verify.'  and year_of_passing!="" and A.result like "%pass%"  and A.result NOT like "%Absent%" and  subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where year="'.$rows["year"].'" and student_map_id="'.$rows['student_map_id'].'" and month=30 and result like "%pass%"  and B.semester<='.$sem_verify.')';
           $semester_credits = Yii::$app->db->createCommand($tot_sem_credits)->queryScalar();


        /*$tot_sem_credits = 'SELECT sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE  student_map_id="'.$rows['student_map_id'].'" and  A.year<='.$rows["year"].'  and B.semester<='.$sem_verify.' and year_of_passing!="" and A.result like "%pass%"   and  subject_map_id NOT IN(select subject_map_id FROM coe_mark_entry_master where year="'.$rows["year"].'"   and student_map_id="'.$rows['student_map_id'].'" and month=30 and result like "%pass%"  and B.semester<='.$sem_verify.')';
         $semester_credits = Yii::$app->db->createCommand($tot_sem_credits)->queryScalar();*/
        

      /*  }
    else
       {

        $tot_sem_credits = 'SELECT sum(D.credit_points) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE  student_map_id="'.$rows['student_map_id'].'" and semester<="'.$sem_verify.'" and  A.year<='.$rows["year"].' and A.month='.$rows["month"].'   and year_of_passing!="" and A.result like "%pass%"';
        $semester_credits = Yii::$app->db->createCommand($tot_sem_credits)->queryScalar();

     
        }*/
        //print_r($semester_credits);exit;

    $get_total_grade_points = 'SELECT  sum(A.grade_point) as credits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'" ';
    $totalgradepoints = Yii::$app->db->createCommand($get_total_grade_points )->queryScalar();
    $totalgradepoints = round($totalgradepoints,2);

    $total_sem_submarks = Yii::$app->db->createCommand("select sum(C.end_semester_exam_value_mark) as totalmax,sum(C.total_minimum_pass) as totalmin,C.subject_name,C.subject_code ,sum(B.total) as grandtotal,sum(C.ESE_max) as esemax,sum(C.ESE_min) as esemin,B.grade_name,B.grade_point,C.credit_points,B.result,B.subject_map_id,sum(C.CIA_max) as CIA,sum(C.CIA_min) as CIAmin,C.CIA_max,C.ESE_max,sum(B.ESE) as ESE,sum(B.CIA) as ciamarks,sum(C.credit_points) as totcredit,A.semester from coe_subjects_mapping as A JOIN coe_mark_entry_master as B ON B.subject_map_id=A.coe_subjects_mapping_id JOIN coe_subjects as C ON C.coe_subjects_id=A.subject_id where batch_mapping_id='".$rows['batch_mapping_id']."' and   B.year='".$rows['year']."'   and  B.month='".$rows['month']."' and B.student_map_id='".$rows['student_map_id']."' and result like '%Pass%'")->queryAll();

      // $degree_name=concat($rows["degree_code"], ' ' , $rows["programme_code"]);
       //print_r($rows['degree_name']);exit;

        $sem = ConfigUtilities::getSemesterRoman($sem_verify); 
        if($rows['degree_name']=='B.SC.CS')
        {
            $name="BSCCS";
        }
        elseif($rows['degree_name']=='B.A.EL')
        {
             $name="BAENG";
        }
        elseif ($rows['degree_name']=='B.SC.MATHS') 
        {
             $name="BSCMA";
          
        }
        else if($rows['degree_name']=='B.SC.CSA')
        {
            $name="BSCCA";
        }
        else if($rows['degree_name']=='B.SC.IT')
        {
            $name="BSCIT";
        }
        else if($rows['degree_name']=='B.SC.CT')
        {
            $name="BSCCT";
        }
        else if($rows['degree_name']=='B.SC.CGS')
        {
            $name="BSCCG";
        }
        else if($rows['degree_name']=='B.SC.SS')
        {
            $name="BSCSS";
        }
         else if($rows['degree_name']=='B.SC.DS')
        {
            $name="BSCDS";
        }
         else if($rows['degree_name']=='B.C.ACA')
        {
            $name="BCAOO";
        }
        else if($rows['degree_name']=='B.SC.AI')
        {
            $name="BSCAI";
        }
        else if($rows['degree_name']=='B.SC.ECS')
        {
            $name="BSCEC";
        }
        else if($rows['degree_name']=='B.SC.BT')
        {
            $name="BSCBT";
        }
        else if($rows['degree_name']=='B.SC.MB')
        {
            $name="BSCMB";
        }
        else if($rows['degree_name']=='B.SC.CDF')
        {
            $name="BSCCD";
        }
         else if($rows['degree_name']=='B.SC.CSHM')
        {
            $name="BSCCH";
        }
        else if($rows['degree_name']=='B.COMCOMMERCE')
        {
            $name="BCOMO";
        }
         else if($rows['degree_name']=='B.COMCA')
        {
            $name="BCOCA";
        }
        else if($rows['degree_name']=='B.COMIT')
        {
            $name="BCOIT";
        }
        else if($rows['degree_name']=='B.COMPA')
        {
            $name="BCOPA";
        }
         else if($rows['degree_name']=='B.COMBPS')
        {
            $name="BCOBP";
        }
         else if($rows['degree_name']=='B.COMCM')
        {
            $name="BCOCM";
        }
        else if($rows['degree_name']=='B.COMAF')
        {
            $name="BCOAF";
        }
        else if($rows['degree_name']=='B.COMBI')
        {
            $name="BCOBI";
        }
        else if($rows['degree_name']=='B.COMEC')
        {
            $name="BCOEC";
        }
        else if($rows['degree_name']=='B.COMCSH')
        {
            $name="BCOCS";
        }
         else if($rows['degree_name']=='B.COMBA')
        {
            $name="BCOBA";
        }
         else if($rows['degree_name']=='B.B.ABBA')
        {
            $name="BBAOO";
        }
        else if($rows['degree_name']=='B.B.ACA')
        {
            $name="BBACA";
        }
         else if($rows['degree_name']=='B.SC.ISM')
        {
            $name="BSCIM";
        }
        else if($rows['degree_name']=='B.B.ALOG')
        {
            $name="BBALG";
        }
        else if($rows['degree_name']=='B.SC.PH')
        {
            $name="BSCPH";
        }
        else if($rows['degree_name']=='M.AEL')
        {
            $name="MAENG";
        }
         else if($rows['degree_name']=='M.SCCS')
        {
            $name="MSCSS";
        }
        else if($rows['degree_name']=="M.SCMATH'S")
        {
            $name="MSCMA";
        }
        else if($rows['degree_name']=="M.SCIT")
        {
            $name="MSCIT";
        }
         else if($rows['degree_name']=="M.SCSS")
        {
            $name="MSCSS";
        }
        else if($rows['degree_name']=="M.SCECS")
        {
            $name="MSCEC";
        }
        else if($rows['degree_name']=="M.SCBT")
        {
            $name="MSCBT";
        }
        else if($rows['degree_name']=="M.SCBIO")
        {
            $name="MSCBI";
        }
        else if($rows['degree_name']=="M.COMCOMMERCE")
        {
            $name="MCOMO";
        }
        else if($rows['degree_name']=="M.COMIB")
        {
            $name="MCOIB";
        }
         else if($rows['degree_name']=="M.S.WSOCIAL")
        {
            $name="MSWOO";
        }
        else
        {
             $name="MAPAP";

        }


                

    $credits_1=$credit[0]['credit'];
        if(empty($credits_1))
        {
            $d=0;
        }
        else
        {

            $d=$credits_1;
        }
     $total_sem_submarks_1=$total_sem_submarks[0]['totalmax'];
       if(empty($total_sem_submarks_1))
       {

        $totalsemsubmarks=0;

       }
       else
       {

        $totalsemsubmarks=$total_sem_submarks_1;

       }
      // print_r($totalsemsubmarks);exit;
    $totalmin=$total_sem_submarks[0]['totalmin'];
       if(empty($totalmin))
       {

         $totalsemsubmin=0;

       }
       else
       {
           
        $totalsemsubmin=$totalmin;

       }
    $totalgrand=$total_sem_submarks[0]['grandtotal'];
       if(empty($totalgrand)) 
       {

        $totalsemsubgrand=0;
       }    
       else
       {
         
         $totalsemsubgrand=$totalgrand;

       }
       //print_r($totalsemsubgrand);exit;
    $totcredits=$total_sem_submarks[0]['totcredit'];
      if(empty($totcredits))
      {

       $totcredits_1=0;
      }
      else
      {

        $totcredits_1=$totcredits;
      }  


       if($rows['batch_name']=="2021")
        {
            $batchname="2021-2024";
            $admission_year="2021";
        }
        elseif($rows['batch_name']=="2022" && $rows['degree_type']=="PG")
        {
             $batchname="2022-2024";
             $admission_year="2022";
        }
        elseif($rows['batch_name']=="2022" && $rows['degree_type']=="UG")
        {
             $batchname="2022-2025";
             $admission_year="2022";
        }
       
        elseif($rows['batch_name']=="2023" && $rows['degree_type']=="PG")
        {
            $batchname="2023-2025";
            $admission_year="2023";

        }
         elseif($rows['batch_name']=="2023" && $rows['degree_type']=="UG")
        {
          $batchname="2023-2026";
            $admission_year="2023";

        }
        else
        {

            
        }



        $objWorkSheet->setCellValue('A'.$row,$org);
        $objWorkSheet->setCellValue('B'.$row,'');
        $objWorkSheet->setCellValue('C'.$row,$admission_year);
        $objWorkSheet->setCellValue('D'.$row,$name);
        $objWorkSheet->setCellValue('E'.$row,$rows["degree_code"]. "  " .$rows["programme_name"]);
        $objWorkSheet->setCellValue('F'.$row,'');
        $objWorkSheet->setCellValue('G'.$row,$batchname);
        $objWorkSheet->setCellValue('H'.$row,$rows["register_number"]);
        $objWorkSheet->setCellValue('I'.$row,$rows["register_number"]);
        $objWorkSheet->setCellValue('J'.$row,$rows["name"]);
        
        $objWorkSheet->setCellValue('K'.$row,$rows["gender"]);
        $objWorkSheet->setCellValue('L'.$row,$dob);
        $objWorkSheet->setCellValue('M'.$row,'');
        $objWorkSheet->setCellValue('N'.$row,'');
        $objWorkSheet->setCellValue('O'.$row,'');
        $objWorkSheet->setCellValue('P'.$row,$m);
        $objWorkSheet->setCellValue('Q'.$row,'');
        $objWorkSheet->setCellValue('R'.$row,$rows['year']);
        $objWorkSheet->setCellValue('S'.$row,$month);
        $objWorkSheet->setCellValue('T'.$row,'');
        $objWorkSheet->setCellValue('U'.$row,'');
        $objWorkSheet->setCellValue('V'.$row,'');
        $objWorkSheet->setCellValue('W'.$row,$sem );
        $objWorkSheet->setCellValue('X'.$row,$exam_type);
        $objWorkSheet->setCellValue('Y'.$row,$stusublist_1[0]['max']);
        $objWorkSheet->setCellValue('Z'.$row,$stusublist_1[0]['min']);
        $objWorkSheet->setCellValue('AA'.$row,$stusublist_1[0]['total']);
        $objWorkSheet->setCellValue('AB'.$row,$stusublist_1[0]['esemax']);
        $objWorkSheet->setCellValue('AC'.$row,$stusublist_1[0]['esemin']);
        $objWorkSheet->setCellValue('AD'.$row,$stusublist_1[0]['ESE']);
        $objWorkSheet->setCellValue('AE'.$row,$stusublist_1[0]['CIA']);
        $objWorkSheet->setCellValue('AF'.$row,$stusublist_1[0]['CIAmin']);
        $objWorkSheet->setCellValue('AG'.$row,$stusublist_1[0]['ciamarks']);
        $objWorkSheet->setCellValue('AH'.$row,$stusublist_1[0]['credit']);
        $objWorkSheet->setCellValue('AI'.$row,'');
        $objWorkSheet->setCellValue('AJ'.$row, $d);
        $objWorkSheet->setCellValue('AK'.$row,'');
        $objWorkSheet->setCellValue('AL'.$row,'');
        $objWorkSheet->setCellValue('AM'.$row,'');
        //$objWorkSheet->setCellValue('AN'.$row,$semester_credits);
        $objWorkSheet->setCellValue('AN'.$row, $d);
        $objWorkSheet->setCellValue('AO'.$row, $totalcreditsgpa);
        $objWorkSheet->setCellValue('AP'.$row,$totalgradepoints );
        $objWorkSheet->setCellValue('AQ'.$row,'');
        //$objWorkSheet->setCellValue('AR'.$row,$total_sem_submarks[0]['totalmax']);
        $objWorkSheet->setCellValue('AR'.$row,$totalsemsubmarks);    
        //$objWorkSheet->setCellValue('AS'.$row,$total_sem_submarks[0]['totalmin']);
        $objWorkSheet->setCellValue('AS'.$row,$totalsemsubmin);
        //$objWorkSheet->setCellValue('AT'.$row,$total_sem_submarks[0]['grandtotal']);
        $objWorkSheet->setCellValue('AT'.$row,$totalsemsubgrand);
        //$objWorkSheet->setCellValue('AU'.$row,$total_sem_submarks[0]['totcredit']);
        $objWorkSheet->setCellValue('AU'.$row,$totcredits_1);
        $objWorkSheet->setCellValue('AV'.$row,$cgpa);
        $objWorkSheet->setCellValue('AW'.$row,'');
        $objWorkSheet->setCellValue('AX'.$row,$gpa);
        $objWorkSheet->setCellValue('AY'.$row,$rows["abc_number_id"]);
       
        $objWorkSheet->setCellValue('AZ'.$row,'');
        $objWorkSheet->setCellValue('BA'.$row,'');
        //$objWorkSheet->setCellValue('BB'.$row,'');


       $stusublist = Yii::$app->db->createCommand("select C.subject_name,C.subject_code,B.grade_name,B.grade_point,C.credit_points,B.result,B.subject_map_id,C.CIA_max,C.ESE_max,C.ESE_min,C.end_semester_exam_value_mark,C.total_minimum_pass,C.CIA_min,A.semester,B.CIA,B.ESE,B.total,(B.grade_point*C.credit_points) as stucredits from coe_subjects_mapping as A JOIN coe_mark_entry_master as B ON B.subject_map_id=A.coe_subjects_mapping_id JOIN coe_subjects as C ON C.coe_subjects_id=A.subject_id where batch_mapping_id='".$rows['batch_mapping_id']."' and B.year='".$rows["year"]."' and B.month='".$rows["month"]."' and B.student_map_id='".$rows['student_map_id']."' order by A.paper_no  ")->queryAll();
             //print_r($stusublist);exit;

                 $strt=53;

                  $char_change = $strt+$count;

                 $endColumn = PHPExcel_Cell::stringFromColumnIndex($char_change);

                 $sll=1;

               foreach ($stusublist as  $value1) 
              {
                $sem_fail = ConfigUtilities::getSemesterRoman( $value1['semester']); 

                             if($sll==1)
                {
                    
                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    //echo ($strt+1).$column2;

                    $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                     $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;


                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;


                   $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    
                    $column9 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;
                    
                    $column10 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column11 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column12 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column13 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column14 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column15 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column16 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                     $column17 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column18 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column19 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column20 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column21 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column22 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column23 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column24 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column25 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   
                }
                else
                {
                 
                    $strt=$strt+1;
                     

                    $column1 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo "<br>".$strt.$column1;

                    $strt=$strt+1;

                    $column2 = PHPExcel_Cell::stringFromColumnIndex($strt);

                   // echo ($strt).$column2;

                     $strt=$strt+1;

                    $column3 = PHPExcel_Cell::stringFromColumnIndex($strt);

                      $strt=$strt+1;

                    $column4 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    $column5 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column6 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column7 = PHPExcel_Cell::stringFromColumnIndex($strt);

                     $strt=$strt+1;

                    $column8 = PHPExcel_Cell::stringFromColumnIndex($strt);

                    $strt=$strt+1;

                    
                    $column9 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;
                    
                    $column10 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column11 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column12 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column13 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column14 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column15 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                    $column16 = PHPExcel_Cell::stringFromColumnIndex($strt);
                    $strt=$strt+1;

                     $column17 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column18 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column19 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column20 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column21 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column22 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column23 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                     $column24 = PHPExcel_Cell::stringFromColumnIndex($strt);
                     $strt=$strt+1;

                    $column25 = PHPExcel_Cell::stringFromColumnIndex($strt);

                }
                


                             
                             //print_r($stusublist);exit;
                              $get_cgpa_grades = 'SELECT A.grade_point*D.credit_points as totalcredits FROM coe_mark_entry_master as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id=A.subject_map_id JOIN coe_student_mapping as C ON C.coe_student_mapping_id=A.student_map_id JOIN coe_subjects as D ON D.coe_subjects_id=B.subject_id WHERE year="'.$rows['year'].'"  and month="'.$rows['month'].'"  and student_map_id="'.$rows['student_map_id'].'" and subject_map_id="'.$value1['subject_map_id'].'"';
                               $credits = Yii::$app->db->createCommand($get_cgpa_grades)->queryScalar();
                               if($value1['grade_name'] =='S')
                               {

                               $grade_name=0;
                               $result=0;

                               }

                               else
                               {

                         $grade_name=$value1["grade_name"];
                         $result=$value1["result"];
                               }

                               if($value1['grade_name']=='S' && $value1['result']=="Pass")
                               {
                                  $grade_point="Completed";

                               }

                               elseif ($value1['grade_name']=='S' && $value1['result']=="Fail") 
                               {
                                   $grade_point="Not Completed";
                               }

                               else
                               {
                                  $grade_point=$value1["grade_point"];

                               }

                               $subject_name=strtoupper($value1["subject_name"]);
                               $subject_code=$value1["subject_code"];
                               $ese_max_total=$value1["end_semester_exam_value_mark"];
                               $ese_min_total=$value1["total_minimum_pass"];
                               $ese_max=$value1["ESE_max"];
                               $ese_min=$value1["ESE_min"];
                               $cia_max=$value1["CIA_max"];
                               $cia_min=$value1["CIA_min"];
                               $cia=$value1["CIA"];
                               $ese=$value1["ESE"];
                               $total=$value1["total"];

                               $grade_1=$grade_name;
                            if($grade_1=="VERY GOOD" || $grade_1=="EXEMPLARY" ||$grade_1=="GOOD" || $grade_1=="FAIR")
                            {

                               $grade="-";
                            }

                            else
                            {

                                $grade=$grade_name;
                            }
                               $point=$grade_point;
                               
                               $cr=$credits;
                                //$subgrade=$value1["stucredits"];
                               $subgrade = round($value1["stucredits"],2);
                               $res=$result;
                               //$res = $result == "Fail" || $result == "fail" || $result == "Fail" ? "RA" : $result;
                               //print_r($res);exit;
                               if($res=="Pass")
                               {
                                  $res_1= strtoupper($res)."(SEM $sem_fail)";
                                  $cre=$value1["credit_points"];
                                  $credit_points=$value1["credit_points"];

                               }
                                                  
                               else
                               {
                                 $res_1=strtoupper($res)."(SEM $sem_fail)";
                                  $cre=0;
                                  $credit_points=0;

                               }



                               //print_r($grade);exit;

                               //$objWorkSheet->setCellValue('V'.$row, $grade);
 /*$objPHPExcel->getActiveSheet()->setCellValue($column1.$row,$subject_name);
 $objPHPExcel->getActiveSheet()->setCellValue($column2.$row,$subject_code);
 $objPHPExcel->getActiveSheet()->setCellValue($column3.$row,$grade);
 $objPHPExcel->getActiveSheet()->setCellValue($column4.$row,$point);
 $objPHPExcel->getActiveSheet()->setCellValue($column5.$row,$credit_points);
 $objPHPExcel->getActiveSheet()->setCellValue($column6.$row,$cr);
 $objPHPExcel->getActiveSheet()->setCellValue($column7.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column8.$row,$res_1);*/

 $objPHPExcel->getActiveSheet()->setCellValue($column1.$row,$subject_name);
 $objPHPExcel->getActiveSheet()->setCellValue($column2.$row,$subject_code);
 $objPHPExcel->getActiveSheet()->setCellValue($column3.$row,$ese_max_total);
 $objPHPExcel->getActiveSheet()->setCellValue($column4.$row,$ese_min_total);
 $objPHPExcel->getActiveSheet()->setCellValue($column5.$row,$ese_max);
 $objPHPExcel->getActiveSheet()->setCellValue($column6.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column7.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column8.$row,$ese_min);
 $objPHPExcel->getActiveSheet()->setCellValue($column9.$row,$cia_max);
 $objPHPExcel->getActiveSheet()->setCellValue($column10.$row,$cia_min);
 $objPHPExcel->getActiveSheet()->setCellValue($column11.$row,$credit_points);
 $objPHPExcel->getActiveSheet()->setCellValue($column12.$row,$cre);
 $objPHPExcel->getActiveSheet()->setCellValue($column13.$row,$ese);
 $objPHPExcel->getActiveSheet()->setCellValue($column14.$row,$cia);
 $objPHPExcel->getActiveSheet()->setCellValue($column15.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column16.$row,$total);
 $objPHPExcel->getActiveSheet()->setCellValue($column17.$row,$grade);
 $objPHPExcel->getActiveSheet()->setCellValue($column18.$row,$point);
 $objPHPExcel->getActiveSheet()->setCellValue($column19.$row,$credit_points);
 $objPHPExcel->getActiveSheet()->setCellValue($column20.$row, $subgrade);
 $objPHPExcel->getActiveSheet()->setCellValue($column21.$row,$res_1);
 $objPHPExcel->getActiveSheet()->setCellValue($column22.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column23.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column24.$row,'');
 $objPHPExcel->getActiveSheet()->setCellValue($column25.$row,'');



$sll++;
}


  $row++;
  
$sno++;
}

      

      header('Content-type: application/.csv');
        header('Content-Disposition: attachment; filename="report.csv"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');

        }

public function actionNadPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
       
            $content = $_SESSION['nad'];
           // print_r($content);exit;
            
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => "Nad pdf",
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
              
            'options' => ['title' => "NAD"],
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
    
}
