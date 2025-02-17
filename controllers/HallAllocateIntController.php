<?php

namespace app\controllers;

use Yii;
use app\models\HallAllocateInt;
use app\models\HallAllocateIntSearch;
use app\models\AbsentEntryInt;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\HallMaster;
use app\models\Student;
use app\models\SubjectsMapping;
use app\models\StudentMapping;
use app\models\MarkEntry;
use app\models\MarkEntryMaster;
use app\models\Categories;
use app\models\Categorytype;
use app\models\ExamTimetableInt;
use yii\helpers\Url;
use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\data\SqlDataProvider;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use yii\helpers\ArrayHelper;
use kartik\mpdf\Pdf;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use PHPExcel_Style_Fill;
use PHPExcel_Style_Alignment;
use yii\db\Query;
use app\models\FacultyHallArrangeInt;
use app\models\ValuationFacultyAllocate;
use app\models\ValuationFaculty;
/**
 * HallAllocateIntController implements the CRUD actions for HallAllocateInt model.
 */
class HallAllocateIntController extends Controller
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
     * Lists all HallAllocateInt models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new HallAllocateIntSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $this->redirect(['create']);
        // return $this->render('index', [
        //     'searchModel' => $searchModel,
        //     'dataProvider' => $dataProvider,
        // ]);
    }

    /**
     * Displays a single HallAllocateInt model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        //echo "sldfg;lgbfs";exit;
        $this->redirect(['create']);
        // return $this->render('view', [
        //     'model' => $this->findModel($id),
        // ]);
    }

    /**
     * Displays a single HallAllocateInt model.
     * @param integer $id
     * @return mixed
     */
    public function actionAnswerPackets()
    {
        $model = new HallAllocateInt();
        $ExamTimetableInt = new ExamTimetableInt();
        $absentModel = new AbsentEntryInt();
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Answer Paper Packets Alloted');
        return $this->render('answer-packets', [
            'model' => $model,
            'ExamTimetableInt' => $ExamTimetableInt,
            'absentModel' =>$absentModel,
        ]);
    }

    public function actionAnswerScriptsPdf(){
      require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   

        $content = $_SESSION['get_answer_packet'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' =>'Answer Script.pdf',                
                'format' => Pdf::FORMAT_A4,                
                'orientation' => Pdf::ORIENT_LANDSCAPE,                
                'destination' => Pdf::DEST_BROWSER,                
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 14px; }td,th{border:1px solid #999; padding: 4px;}
                        
                    }   
                ', 
               
                'options' => ['title' =>"Answer Script"],
                'methods' => [
                    'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                    'SetFooter' => [ "Answer Script " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();        
        unset($_SESSION['get_answer_packet']);
    }
    public function actionAnswerScriptsExcel()
    {
        $content = $_SESSION['get_answer_packet'];
        $fileName = "Answer Script " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    /**
     * Displays a single HallAllocateInt model.
     * @param integer $id
     * @return mixed
     */
    public function actionPrintRegisterNumbers()
    {
        $model = new HallAllocateInt();
        $ExamTimetableInt = new ExamTimetableInt();
        $absentModel = new AbsentEntryInt();
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Answer Paper Packets Alloted');
        return $this->render('print-register-numbers', [
            'model' => $model,
            'ExamTimetableInt' => $ExamTimetableInt,
            'absentModel' =>$absentModel,
        ]);
    }

    public function actionPrintRegisterNumbersPdf(){
      require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['get_print_reg'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' =>'Register Number Packets.pdf',                
                'format' => Pdf::FORMAT_A4,                
                'orientation' => Pdf::ORIENT_PORTRAIT,                
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
                        }td,th{border:1px solid #999; padding: 4px;}
                    }   
                ', 
                'options' => ['title' =>"Register Number Packets"],
                'methods' => [
                    'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                    'SetFooter' => [ "Register Number Packets " . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();        
        unset($_SESSION['get_print_reg']);
    }
    public function actionPrintRegisterNumbersExcel()
    {
        $content = $_SESSION['get_print_reg'];
        $fileName = "Register Numbers " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    /**
     * Creates a new HallAllocateInt model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
  public function actionCreate()
  {        
      require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
      $model = new HallAllocateInt();
      $hallmaster = new HallMaster();
      $categorytype = new Categorytype();
      $exam = new ExamTimetableInt();
      $cia=''; 
      
      $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();
      $bar_code_gen = array_filter(['']);
      $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if ($model->load(Yii::$app->request->post()) && $exam->load(Yii::$app->request->post()) && $hallmaster->load(Yii::$app->request->post()) ) 
        {
      if($_POST['countTo']==$_POST['hallCount']){
        $exam_month = $_POST['HallAllocateInt']['month'];
        $namearr = explode("&",trim($_POST['hallName'],"&"));
        $internal_number = $_POST['HallAllocateInt']['internal_number'];
        $missing_entry = isset($model->seat_no) && !empty($model->seat_no[0]) && $model->seat_no[0]=='yes'? $model->seat_no[0]:'no';
        if($_POST['arrangement_type'] == 'Subject Wise')
        {
            $total_subject_galley = $_POST['galley_subject_wise'];            
            $sub_name_check=array();    
            $exam_date =  date("Y-m-d",strtotime($exam->exam_date));
            for($s=0;$s<count($total_subject_galley);$s++){             

              $query_0 = new Query();              
              $sub_name_check[] = $query_0->select("distinct (a.coe_subjects_mapping_id),c.exam_type,d.description")
                ->from('coe_subjects_mapping a')
                ->join('JOIN','coe_subjects b','a.subject_id=b.coe_subjects_id')
                ->join('JOIN','coe_exam_timetable_int c','a.coe_subjects_mapping_id=c.subject_mapping_id')
                ->join('JOIN','coe_category_type d','c.exam_type=d.coe_category_type_id')
                ->where(['b.subject_code' => $total_subject_galley[$s],'c.exam_date'=>$exam_date,'c.exam_session'=>$exam->exam_session,'c.time_slot'=>$exam->time_slot,'c.exam_month'=>$exam_month,'c.internal_number'=>$internal_number])  ->createCommand()->queryAll(); 
            }
            
            $sub_stu = [];
            $arr_sub_stu = [];            
            $total_sub_map_id = 0;
            $exam_date =  date("Y-m-d",strtotime($exam->exam_date));
            foreach($sub_name_check as $sub_name)
            {  
              foreach($sub_name as $subs){
                $total_sub_map_id += count($subs['coe_subjects_mapping_id']);                
                
                  $query_2 = new Query();
                  $subject_type = $query_2->select("a.description")
                    ->from('coe_category_type a')
                    ->join('JOIN','coe_subjects_mapping b','a.coe_category_type_id=b.subject_type_id')
                    ->where(['coe_subjects_mapping_id'=>$subs['coe_subjects_mapping_id']])->createCommand()->queryScalar(); 
                   $getExamTimet = ExamTimetableInt::find()->where(['exam_date'=>$exam_date,'exam_month'=>$exam_month,'exam_session'=>$exam->exam_session,'subject_mapping_id' =>$subs['coe_subjects_mapping_id'],'time_slot'=>$exam->time_slot,'internal_number'=>$internal_number])->all();
                    $examTTIds = array_filter([]);
                    foreach ($getExamTimet as $key => $examIds) {
                      $examTTIds[$examIds['coe_exam_timetable_id']]=$examIds['coe_exam_timetable_id'];
                    }
                    
                    $getHallStu = HallAllocateInt::find()->where(['month'=>$model->month,'year'=>$model->year])->andWhere(['IN','exam_timetable_id',$examTTIds])->all();
                    $removeReg_num = array_filter([]);
                    if(!empty($getHallStu))
                    {
                        foreach ($getHallStu as $key => $regRemo) {
                         $removeReg_num[$regRemo['register_number']]=$regRemo['register_number'];
                        }  
                    }

                  if($subject_type!='Elective')
                  {
                    $query_3 = new Query();
                    $subject_student = $query_3->select("f.coe_student_id as stu_id,c.coe_exam_timetable_id")
                      ->from('coe_student_mapping a')
                      ->join('JOIN','coe_student f','f.coe_student_id=a.student_rel_id')
                      ->join('JOIN','coe_subjects_mapping b','a.course_batch_mapping_id=b.batch_mapping_id')
                      ->join('JOIN','coe_exam_timetable_int c','c.subject_mapping_id=b.coe_subjects_mapping_id')
                      ->where(['c.subject_mapping_id' =>$subs['coe_subjects_mapping_id'],'f.student_status'=>'Active','c.exam_date'=>$exam_date,'c.exam_month'=>$exam_month,'c.exam_session'=>$exam->exam_session,'c.time_slot'=>$exam->time_slot,'c.internal_number'=>$internal_number])->andWhere(['NOT IN', 'status_category_type_id', [$det_cat_type,$det_disc_type]])->andWhere(['NOT IN', 'f.register_number', $removeReg_num])->orderBy('register_number ASC')->createCommand()->queryAll(); 

                    if(count($subject_student)>0)
                    {
                        array_push($sub_stu, $subject_student);
                    }
                    
                  }else{

                    $query_3 = new Query();
                    $sub_id = $query_3->select("subject_id")
                      ->from('coe_subjects_mapping')
                      ->where(['coe_subjects_mapping_id' =>$subs['coe_subjects_mapping_id']])->createCommand()->queryScalar();
                    $getSemester = SubjectsMapping::findOne($subs['coe_subjects_mapping_id']);

                    $query_4 = new Query();
                    $subject_student = $query_4->select("f.coe_student_id as stu_id,d.coe_exam_timetable_id")
                      ->from('coe_nominal a')
                      ->join('JOIN','coe_student f','f.coe_student_id=a.coe_student_id')
                      ->join('JOIN','coe_student_mapping abc','abc.student_rel_id=f.coe_student_id and abc.course_batch_mapping_id=a.course_batch_mapping_id')
                      ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.coe_subjects_id')
                      ->join('JOIN','coe_subjects_mapping c','c.subject_id=b.coe_subjects_id')
                      ->join('JOIN','coe_exam_timetable_int d','d.subject_mapping_id=c.coe_subjects_mapping_id 
                        and c.batch_mapping_id=a.course_batch_mapping_id 
                        and c.subject_id=a.coe_subjects_id')
                      ///->where(['a.coe_subjects_id' =>$sub_id])->createCommand()->queryAll();
                      ->where(['c.coe_subjects_mapping_id' =>$subs['coe_subjects_mapping_id'],'c.semester'=>$getSemester->semester,'a.semester'=>$getSemester->semester,'f.student_status'=>'Active','d.exam_date'=>$exam_date,'d.exam_month'=>$exam_month,'d.exam_session'=>$exam->exam_session,'d.time_slot'=>$exam->time_slot,'d.internal_number'=>$internal_number])->andWhere(['NOT IN', 'status_category_type_id', [$det_cat_type,$det_disc_type]])->orderBy('register_number ASC')->andWhere(['NOT IN', 'f.register_number', $removeReg_num])->createCommand()->queryAll();

                  if(count($subject_student)>0)
                    {
                        array_push($sub_stu, $subject_student);
                    }


                    
                  }           
                    $inarrr[]=$sub_name_check;
                
              }      
            } //sub_name_check foreach

            // Regular Students 
            $mer= array_reduce($sub_stu, 'array_merge', array());     
              //print_r($mer);
              //exit();
              

                $total_merge_student=$mer;
                $total_stu_division=count($total_merge_student)/2; // 200/2 =100           
                $count_merge_student=count($total_merge_student); //=200
                $jumbling_student=array_slice($total_merge_student,0,$total_stu_division);
                $jumbling_remaining_student=array_slice($total_merge_student,$total_stu_division,$count_merge_student);

                if(count($total_subject_galley)==1)
                {
                  $check_arrange_single = HallAllocateInt::find()->select(['exam_timetable_id'])->where(['month'=>$model->month,'year'=>$model->year])->distinct()->all();
                  if(count($check_arrange_single)==1)
                  {
                    shuffle($jumbling_student);
                  }
                  else if (count($check_arrange_single)==2) {
                    shuffle($jumbling_remaining_student);
                  }
                  else if (count($check_arrange_single)==3) {
                    shuffle($jumbling_student);
                  }
                  else if (count($check_arrange_single)==4) {
                    shuffle($jumbling_remaining_student);
                  }
                  else if (count($check_arrange_single)==5) {
                    shuffle($jumbling_student);
                  }
                  else if (count($check_arrange_single)==6) {
                    shuffle($jumbling_remaining_student);
                  }
                  else if (count($check_arrange_single)==7) {
                    shuffle($jumbling_student);
                  }
                  else if (count($check_arrange_single)==8) {
                    shuffle($jumbling_remaining_student);
                  }else if (count($check_arrange_single)==9) {
                    shuffle($jumbling_student);
                  }else if (count($check_arrange_single)==10) {
                    shuffle($jumbling_remaining_student);
                  }
                  else
                  {
                    shuffle($jumbling_remaining_student);
                  }                  
                }
                else
                {
                  $jumbling_student=$jumbling_student;
                  $jumbling_remaining_student=$jumbling_remaining_student;
                }
                
                //print_r($jumbling_student);exit;
                
                if($_POST['seat_arrr'] == 'Straight Arrangement'){

                  for($s=0;$s<$total_stu_division;$s++)
                  {
                    if(isset($jumbling_student[$s]))
                    {
                      $z[]=$jumbling_student[$s];
                    }
                    if(isset($jumbling_remaining_student[$s]))
                    {
                      $z[]=$jumbling_remaining_student[$s];
                    } 
                  }
                }else{
                  $config_seat = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_GALLEY_HALL_COLUMN_SIZE);
                  $count = $config_seat;
                  $value = 1;
                  $check_val=$config_seat/2;
                  for($s=0;$s<$total_stu_division;$s++){
                    //echo $s%$config_seat."<br />";
                    if($s%$config_seat==$check_val) 
                    {
                        if(isset($jumbling_remaining_student[$s]))
                        {
                          $z[]=$jumbling_remaining_student[$s];
                        } 
                        if(isset($jumbling_student[$s]))
                        {
                          $z[]=$jumbling_student[$s];
                        }

                        $value = "Arranged";
                      
                    }
                    else{
                      
                      if($value == "Arranged" )
                      {
                          if(isset($jumbling_remaining_student[$s]))
                          {
                            $z[]=$jumbling_remaining_student[$s];
                          } 
                          if(isset($jumbling_student[$s]))
                          {
                            $z[]=$jumbling_student[$s];
                          }
                          
                          $value = "Start Fresh";
                          
                      }
                      else if ($value == "Start Fresh") { 
                        
                        if(isset($jumbling_remaining_student[$s]))
                          {
                            $z[]=$jumbling_remaining_student[$s];
                          } 
                          if(isset($jumbling_student[$s]))
                          {
                            $z[]=$jumbling_student[$s];
                          }
                        $value = "Don't Arrange";
                      }
                      else
                      {
                        if(isset($jumbling_student[$s]))
                          {
                            $z[]=$jumbling_student[$s];
                          }
                          if(isset($jumbling_remaining_student[$s]))
                          {
                            $z[]=$jumbling_remaining_student[$s];
                          }                           
                        $value = "Don't Arrange";                            
                      }                      
                        
                    }                      
                     $count++;
                  }
                }

              //}//total_sub_map_id else condition             

              if(!empty($z)){
                $regular=$z;  
              }else if(empty($z)){
                $regular='';  
              }
                          
              //Regular Students
               
              $printData12 = [];

             $printData12 = $regular;            
              
              $hall_type_id = new Query();
              $seat_capacity = $hall_type_id->select("a.description")
                              ->from('coe_category_type a')
                              ->where(['a.coe_category_type_id' =>$_POST['HallMaster']['hall_type_id']])->createCommand()->queryScalar(); 
              
              $seat_column = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_GALLEY_HALL_COLUMN_SIZE);              
              $hall_type = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='".$hallmaster->hall_type_id."'")->queryScalar();
              $row = $hall_type/$seat_column;
              $row_system = ceil($row);              
              
              $hall=0;
              $k=0;
              $rowsss=1;
              $seatsss=1;
              $id=0;
              $sql5='insert into coe_hall_allocate_int(hall_master_id,exam_timetable_id,year,month,register_number,row,row_column,seat_no,created_by,created_at,updated_by,updated_at,internal_number) values'; 
              $bar_insert='insert into coe_dummy_number(student_map_id,subject_map_id,dummy_number,year,month,exam_date,exam_session, created_by, created_at,updated_by,updated_at) values'; 
              $insert_data  = $insert_bar_data = "";
              $all_hall_name = [];
              $row = [];
              $all_reg_num = [];
              $column = [];
              $seat_no = [];
              $created_at = date("Y-m-d H:i:s");
              $createdBy = Yii::$app->user->getId();

              for ($l=0; $l< count($namearr) ; $l++) {  // No of Students                 
                if($l==count($namearr))
                {
                  break;
                }
                else
                {
                  
                  if($rowsss==$row_system && $k==$seat_capacity)   
                  {
                    $rowsss=1;
                    $seatsss=1;                    
                    continue;
                  }
                  else{   
                  
                      
                    for ($m=1; $m <= $row_system ; $m++) 
                    {                     
                        for($i=1; $i <=$seat_column ; $i++) 
                        {
                          //if(isset($mer[$id]['stu_id'])){ 
                      
                          if(isset($printData12[$id]['stu_id']))
                          {
                                           
                            $hall_id = HallMaster::find()->where(['hall_name'=>$namearr[$l]])->one();
                            $exam_id = $printData12[$id]['coe_exam_timetable_id'];
                            $ex_data = ExamTimetableInt::findOne($exam_id);

                            $register_number = Student::findOne(['coe_student_id'=>$printData12[$id]['stu_id']]);
                            $stu_map_idDe = StudentMapping::findOne(['student_rel_id'=>$register_number['coe_student_id']]);
                            $str = $register_number->register_number;
                            $reg_num = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
            
                            $uniq_dumm_num = $stu_map_idDe['coe_student_mapping_id'].$ex_data['subject_mapping_id'].$m.$i.$seatsss;

                            $check_data = HallAllocateInt::find()->where(['exam_timetable_id'=>$exam_id,'year'=>$model->year,'month'=>$model->month,'register_number'=>$register_number->register_number])->all();

                            //$check_sameSeat = HallAllocateInt::find()->where(['exam_timetable_id'=>$exam_id,'year'=>$model->year,'month'=>$model->month,'row'=>$row,'row_column'=>$column,'seat_no'=>$seat_no])->all();
                           
                            // This is aTemporary Fix for Missing Arrangements 

                            if($missing_entry==='yes')
                            {
                              $getExamTimet = ExamTimetableInt::find()->where(['exam_date'=>$exam_date,'exam_month'=>$exam_month,'exam_session'=>$exam->exam_session,'internal_number'=>$internal_number])->all();
                              $examTTIds = array_filter([]);
                              foreach ($getExamTimet as $key => $examIds) {
                                $examTTIds[$examIds['coe_exam_timetable_id']]=$examIds['coe_exam_timetable_id'];
                              }
                              
                              $getHallList = HallAllocateInt::find()->where(['year'=>$model->year,'month'=>$model->month,'hall_master_id'=>$hall_id->coe_hall_master_id])->andWhere(['IN','exam_timetable_id',$examTTIds])->orderBy('seat_no desc')->one();

                              $totalSeats = $getHallList['seat_no'];

                              if($seatsss<=$totalSeats)
                              {
                                $seatsss = $totalSeats+1;
                              }
                            }

                            // This is aTemporary Fix ends here
                            
                            if(count($check_data)>0 && !empty($check_data))
                            {
                                
                            }else{

                              $check_multiple = Yii::$app->db->createCommand('SELECT * FROM coe_hall_allocate_int AS A JOIN coe_exam_timetable_int as B ON B.coe_exam_timetable_id=A.exam_timetable_id WHERE register_number="'.$register_number->register_number.'" AND A.month="'.$model->month.'" AND A.year="'.$model->year.'" AND B.exam_date="'.$exam_date.'" AND B.exam_session="'.$exam->exam_session.'" AND B.internal_number="'.$internal_number.'"')->queryAll();

                              if(count($check_multiple)>0 && !empty($check_multiple))
                              {
                                  Yii::$app->ShowFlashMessages->setMsg('ERROR',"UNABLE TO ARRANGE GALLEY DUE TO MULTIPLE EXAMS FOR SINGLE STUDENT ON ".date('d-m-Y',strtotime($exam_date)) );
                                  return $this->redirect(['create']);
                              }
                              else
                              {
                                  if($seatsss == $seat_capacity)
                                  {
                                    $insert_bar_data .='("'.$stu_map_idDe['coe_student_mapping_id'].'","'.$ex_data['subject_mapping_id'].'","'.$uniq_dumm_num.'","'.$model->year.'","'.$model->month.'","'.$ex_data['exam_date'].'","'.$ex_data['exam_session'].'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'"),';

                                    $insert_data .='("'.$hall_id->coe_hall_master_id.'","'.$exam_id.'","'.$model->year.'","'.$model->month.'","'.$register_number->register_number.'","'.$m.'","'.$i.'","'.$seatsss.'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'","'.$internal_number.'"),';

                                    $id++;
                                    $seatsss++;
                                    break;
                                  }else{

                                   $insert_bar_data .='("'.$stu_map_idDe['coe_student_mapping_id'].'","'.$ex_data['subject_mapping_id'].'","'.$uniq_dumm_num.'","'.$model->year.'","'.$model->month.'","'.$ex_data['exam_date'].'","'.$ex_data['exam_session'].'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'"),';

                                    $insert_data .='("'.$hall_id->coe_hall_master_id.'","'.$exam_id.'","'.$model->year.'","'.$model->month.'","'.$register_number->register_number.'","'.$m.'","'.$i.'","'.$seatsss.'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'","'.$internal_number.'"),';

                                    $id++;
                                    $seatsss++;
                                  }

                                  array_push($all_hall_name, $namearr[$l]);
                                  array_push($all_reg_num,$register_number->register_number);
                                  array_push($row, $m);
                                  array_push($column, $i);
                                  array_push($seat_no, $seatsss);
                              }
                            }
                              
                            
                          }//checking student id has a value
                          else{                            
                            $id++;
                            $seatsss++;
                          }
                        }
                    }// for row_system
   
                    $seatsss=1;                    
                    $rowsss++;                    
                    $k++;
   
                  } //else above for loop $row_system
                  
                } //else above break
               
              } //students loop
             
        }
        else if($_POST['arrangement_type'] == 'Non-Subject Wise')
        {     

          $query_1 = new Query();
          $exam_date =  date("Y-m-d",strtotime($exam->exam_date));
          $sub_name_check = $query_1->select("a.subject_mapping_id,a.exam_type,b.description")
            ->from('coe_exam_timetable_int a')
            ->join('JOIN','coe_category_type b','a.exam_type=b.coe_category_type_id')
            ->where(['a.exam_date' =>$exam_date,'a.exam_month'=>$exam_month,'a.exam_session'=>$exam->exam_session,'a.time_slot'=>$exam->time_slot,'a.internal_number'=>$internal_number])->createCommand()->queryAll(); 

            $sub_stu = [];
            $arr_sub_stu = [];
            $total_sub_map_id = 0;
            foreach($sub_name_check as $subs)
            {
              $total_sub_map_id += count($subs['subject_mapping_id']);
              
                $query_2 = new Query();
                $subject_type = $query_2->select("a.description")
                  ->from('coe_category_type a')
                  ->join('JOIN','coe_subjects_mapping b','a.coe_category_type_id=b.subject_type_id')
                  ->where(['coe_subjects_mapping_id' =>$subs['subject_mapping_id']])->createCommand()->queryScalar(); 

                if($subject_type!='Elective'){

                  $query_3 = new Query();
                  $subject_student = $query_3->select("DISTINCT (a.student_rel_id) as stu_id,c.coe_exam_timetable_id,f.register_number,c.qp_code")
                    ->from('coe_student_mapping a')
                    ->join('JOIN','coe_student f','f.coe_student_id=a.student_rel_id')
                    ->join('JOIN','coe_subjects_mapping b','a.course_batch_mapping_id=b.batch_mapping_id')
                    ->join('JOIN','coe_exam_timetable_int c','c.subject_mapping_id=b.coe_subjects_mapping_id')
                    ->where(['c.subject_mapping_id' =>$subs['subject_mapping_id'],'b.coe_subjects_mapping_id' =>$subs['subject_mapping_id'],'f.student_status'=>'Active','c.exam_date'=>$exam_date,'c.exam_month'=>$exam_month,'c.exam_session'=>$exam->exam_session,'c.time_slot'=>$exam->time_slot,'c.internal_number'=>$internal_number])
                    ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->orderBy('register_number ASC')
                    ->createCommand()->queryAll(); 

                  array_multisort(array_column($subject_student, 'register_number'),  SORT_ASC, $subject_student);

                  array_push($sub_stu, $subject_student);
                }else{

                  $query_3 = new Query();
                  $sub_id = $query_3->select("subject_id")
                    ->from('coe_subjects_mapping')
                    ->where(['coe_subjects_mapping_id' =>$subs['subject_mapping_id']])->createCommand()->queryScalar();
                  $getSemester = SubjectsMapping::findOne($subs['subject_mapping_id']);
                  $query_4 = new Query();
                  $subject_student = $query_4->select("DISTINCT(f.coe_student_id) as stu_id,d.coe_exam_timetable_id,f.register_number,d.qp_code")
                    ->from('coe_nominal a')
                    ->join('JOIN','coe_student f','f.coe_student_id=a.coe_student_id')
                    ->join('JOIN','coe_student_mapping abcd','abcd.student_rel_id=f.coe_student_id and abcd.course_batch_mapping_id=a.course_batch_mapping_id')
                    ->join('JOIN','coe_subjects b','b.coe_subjects_id=a.coe_subjects_id')
                    ->join('JOIN','coe_subjects_mapping c','c.subject_id=b.coe_subjects_id')
                    //->join('JOIN','coe_exam_timetable_int d','d.subject_mapping_id=c.coe_subjects_mapping_id')
                    ->join('JOIN','coe_exam_timetable_int d','d.subject_mapping_id=c.coe_subjects_mapping_id 
                        and c.batch_mapping_id=a.course_batch_mapping_id 
                        and c.subject_id=a.coe_subjects_id')
                      ///->where(['a.coe_subjects_id' =>$sub_id])->createCommand()->queryAll();
                      ->where(['d.subject_mapping_id' =>$subs['subject_mapping_id'],'a.semester'=>$getSemester->semester,'c.semester'=>$getSemester->semester,'c.coe_subjects_mapping_id' =>$subs['subject_mapping_id'],'f.student_status'=>'Active','d.exam_date'=>$exam_date,'d.exam_month'=>$exam_month,'d.exam_session'=>$exam->exam_session,'c.time_slot'=>$exam->time_slot,'d.internal_number'=>$internal_number])
                      ->andWhere(['<>', 'status_category_type_id', $det_cat_type])
                      ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                      ->orderBy('register_number ASC')
                      ->createCommand()->queryAll();
                    
                    array_multisort(array_column($subject_student, 'register_number'),  SORT_ASC, $subject_student);

                  array_push($sub_stu, $subject_student);
                }           
                  $inarrr[]=$sub_name_check;
                   
            }//sub_name_check foreach   
             // print_r($sub_stu); exit();
            $mer= array_reduce($sub_stu, 'array_merge', array());
        

              //array_multisort(array_column($mer, 'register_number'),  SORT_ASC, $mer);
                $total_merge_student=$mer;


                $total_stu_division=count($total_merge_student)/2; // 200/2 =100           
                $count_merge_student=count($total_merge_student); //=200
                $jumbling_student=array_slice($total_merge_student,0,$total_stu_division);
                $jumbling_remaining_student=array_slice($total_merge_student,$total_stu_division,$count_merge_student);
                
                 if($_POST['seat_arrr'] == 'Straight Arrangement'){

                  for($s=0;$s<$total_stu_division;$s++)
                  {
                    if(isset($jumbling_student[$s]))
                    {
                      $z[]=$jumbling_student[$s];
                    }
                    if(isset($jumbling_remaining_student[$s]))
                    {
                      $z[]=$jumbling_remaining_student[$s];
                    } 
                  }
                }else{
                  $config_seat = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_GALLEY_HALL_COLUMN_SIZE);
                  $count = $config_seat;
                  $value = 1;
                  $check_val=$config_seat/2;
                  for($s=0;$s<$total_stu_division;$s++){
                    //echo $s%$config_seat."<br />";
                    if($s%$config_seat==$check_val) 
                    {
                        if(isset($jumbling_remaining_student[$s]))
                        {
                          $z[]=$jumbling_remaining_student[$s];
                        } 
                        if(isset($jumbling_student[$s]))
                        {
                          $z[]=$jumbling_student[$s];
                        }

                        $value = "Arranged";
                      
                    }
                    else{
                      
                      if($value == "Arranged" )
                      {
                          if(isset($jumbling_remaining_student[$s]))
                          {
                            $z[]=$jumbling_remaining_student[$s];
                          } 
                          if(isset($jumbling_student[$s]))
                          {
                            $z[]=$jumbling_student[$s];
                          }
                          
                          $value = "Start Fresh";
                          
                      }
                      else if ($value == "Start Fresh") { 
                        
                        if(isset($jumbling_remaining_student[$s]))
                          {
                            $z[]=$jumbling_remaining_student[$s];
                          } 
                          if(isset($jumbling_student[$s]))
                          {
                            $z[]=$jumbling_student[$s];
                          }
                        $value = "Don't Arrange";
                      }
                      else
                      {
                        if(isset($jumbling_student[$s]))
                          {
                            $z[]=$jumbling_student[$s];
                          }
                          if(isset($jumbling_remaining_student[$s]))
                          {
                            $z[]=$jumbling_remaining_student[$s];
                          }                           
                        $value = "Don't Arrange";                            
                      }                      
                        
                    }                      
                     $count++;
                  }
                }
                
             // }//

              if(!empty($z)){
                $regular=$z;  
              }else if(empty($z)){
                $regular='';  
              }
              //Regular students
                            
               
              $printData12 = [];

              $printData12 = $regular;   
              // echo "<pre>";
              // print_r($printData12);exit;

              $hall_type_id = new Query();
              $seat_capacity = $hall_type_id->select("a.description")
                              ->from('coe_category_type a')
                              ->where(['a.coe_category_type_id' =>$_POST['HallMaster']['hall_type_id']])->createCommand()->queryScalar(); 
              
              //$seat_column = 6;
              $seat_column = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_GALLEY_HALL_COLUMN_SIZE);                
              $hall_type = Yii::$app->db->createCommand("select description from coe_category_type where coe_category_type_id='".$hallmaster->hall_type_id."'")->queryScalar();
              $row = $hall_type/$seat_column;
              $row_system = ceil($row);
              // $namearr is Number of Halls

              $hall=0;
              $k=0;
              $rowsss=1;
              $seatsss=1;
              $id=0;
              $sql5='insert into coe_hall_allocate_int(hall_master_id,exam_timetable_id,year,month,register_number,row,row_column,seat_no,created_by,created_at,updated_by,updated_at,internal_number) values';
              $bar_insert='insert into coe_dummy_number(student_map_id,subject_map_id,dummy_number,year,month,exam_date,exam_session, created_by, created_at,updated_by,updated_at) values'; 
              $insert_data = "";
              $insert_bar_data = "";
              $all_hall_name = [];
              $row = [];
              $all_reg_num = [];
              $column = [];
              $seat_no = [];
              $created_at = date("Y-m-d H:i:s");
              $createdBy = Yii::$app->user->getId();

              for ($l=0; $l< count($namearr) ; $l++) {  // No of Students                 
                if($hall==count($namearr))
                {
                  break;
                }
                else
                {
                  if($rowsss==$row_system && $k==$seat_capacity)   
                  {
                    $rowsss=1;
                    $seatsss=1;
                    $hall++;
                    continue;
                  }
                  else{                  
                    for ($m=1; $m <= $row_system ; $m++) {                     
                        for($i=1; $i <=$seat_column ; $i++) {
                          //if(isset($mer[$id]['stu_id'])){ 
                          if(isset($printData12[$id]['stu_id'])){                        
                            $hall_id = HallMaster::find()->where(['hall_name'=>$namearr[$l]])->one();
                            //$exam_id = $mer[$id]['coe_exam_timetable_id'];
                            $exam_id = $printData12[$id]['coe_exam_timetable_id'];
                            $ex_data = ExamTimetableInt::findOne($exam_id);
                            //$register_number = Student::findOne(['coe_student_id'=>$mer[$id]['stu_id']]);
                            $register_number = Student::findOne(['coe_student_id'=>$printData12[$id]['stu_id']]);
                            $stu_map_idDe = StudentMapping::findOne(['student_rel_id'=>$register_number['coe_student_id']]);
                            $str = $register_number->register_number;
                            $reg_num = filter_var($str, FILTER_SANITIZE_NUMBER_INT);
                         
                            $uniq_dumm_num = $ex_data['subject_mapping_id'].$m.$i.$seatsss.$stu_map_idDe['coe_student_mapping_id'];
                            
                            array_push($all_hall_name, $namearr[$l]);
                            array_push($all_reg_num,$register_number->register_number);
                            array_push($row, $m);
                            array_push($column, $i);
                            array_push($seat_no, $seatsss);

                            $check_data = HallAllocateInt::find()->where(['exam_timetable_id'=>$exam_id,'year'=>$model->year,'month'=>$model->month,'register_number'=>$register_number->register_number])->all();
                            
                            if(count($check_data)>0 && !empty($check_data))
                            {
                                
                            }else{
                             
                              $check_multiple = Yii::$app->db->createCommand('SELECT * FROM coe_hall_allocate_int AS A JOIN coe_exam_timetable_int as B ON B.coe_exam_timetable_id=A.exam_timetable_id WHERE register_number="'.$register_number->register_number.'" AND A.month="'.$model->month.'" AND A.year="'.$model->year.'" AND B.exam_date="'.$exam_date.'" AND B.exam_session="'.$exam->exam_session.'" AND B.internal_number="'.$internal_number.'" AND B.time_slot='.$exam->time_slot)->queryAll();

                              if(count($check_multiple)>0 && !empty($check_multiple))
                              {
                                  Yii::$app->ShowFlashMessages->setMsg('ERROR',"UNABLE TO ARRANGE GALLEY DUE TO MULTIPLE EXAMS FOR SINGLE STUDENT ON ".date('d-m-Y',strtotime($exam_date)) );
                                 return $this->redirect(['create']);
                              }
                              else
                              {
                                if($seatsss == $seat_capacity){
                                  $insert_bar_data .='("'.$stu_map_idDe['coe_student_mapping_id'].'","'.$ex_data['subject_mapping_id'].'","'.$uniq_dumm_num.'","'.$model->year.'","'.$model->month.'","'.$ex_data['exam_date'].'","'.$ex_data['exam_session'].'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'"),';

                                  $insert_data .='("'.$hall_id->coe_hall_master_id.'","'.$exam_id.'","'.$model->year.'","'.$model->month.'","'.$register_number->register_number.'","'.$m.'","'.$i.'","'.$seatsss.'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'","'.$internal_number.'"),';

                                  $id++;
                                  $seatsss++;
                                  break;
                                }else{
                                  $insert_bar_data .='("'.$stu_map_idDe['coe_student_mapping_id'].'","'.$ex_data['subject_mapping_id'].'","'.$uniq_dumm_num.'","'.$model->year.'","'.$model->month.'","'.$ex_data['exam_date'].'","'.$ex_data['exam_session'].'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'"),';

                                  $insert_data .='("'.$hall_id->coe_hall_master_id.'","'.$exam_id.'","'.$model->year.'","'.$model->month.'","'.$register_number->register_number.'","'.$m.'","'.$i.'","'.$seatsss.'","'.$createdBy.'","'.$created_at.'","'.$createdBy.'","'.$created_at.'","'.$internal_number.'"),';
                                  $id++; 
                                  $seatsss++;
                                }
                              } 
                            }
                            
                          }//checking student id has a value
                          else{                            
                            $id++;
                            $seatsss++;
                          }
                        }
                    }// for row_system
   
                    $seatsss=1;
                    $rowsss++;
                    $k++;
   
                  } //else above for loop $row_system
                  
                } //else above break
               
              } //students loop
           } 
           
            if(!empty($insert_data) && !empty($insert_bar_data))
            {
              $insert_data=substr($insert_data, 0, -1);

              $insert_query = $sql5.$insert_data;
              $insert_bar_data=substr($insert_bar_data, 0, -1);
              $insert_bar_query = $bar_insert.$insert_bar_data; 
              
              if($org_email=='info@skct.edu.in' || $org_email=='coe@skct.edu.in')
              {
                //Yii::$app->db->createCommand($insert_bar_query)->query();
              }

            if(Yii::$app->db->createCommand($insert_query)->query() ) {
              
              $exam_sn = Yii::$app->db->createCommand("select distinct(a.description) from coe_category_type a,coe_exam_timetable_int b where a.coe_category_type_id=b.exam_session and b.exam_session='".$exam->exam_session."'")->queryScalar(); 
              $exam_date =  date("Y-m-d",strtotime($exam->exam_date));

               $time_slot = Yii::$app->db->createCommand("select distinct(a.description) from coe_category_type a,coe_exam_timetable_int b where a.coe_category_type_id=b.time_slot and b.time_slot='".$exam->time_slot."'")->queryScalar();

              $query_allocate = new Query();
              $allocated_value = $query_allocate->select("hall_name,a.register_number,row,row_column,seat_no, c.subject_mapping_id as subject_map_id,coe_student_mapping_id as student_map_id,subject_code,c.exam_date,c.exam_session,c.exam_month,c.exam_year")
                              ->from('coe_hall_allocate_int a')
                              ->join('JOIN','coe_hall_master b','a.hall_master_id=b.coe_hall_master_id')
                              ->join('JOIN','coe_exam_timetable_int c','c.coe_exam_timetable_id=a.exam_timetable_id')
                              ->join('JOIN','coe_subjects_mapping d','d.coe_subjects_mapping_id=c.subject_mapping_id')
                              ->join('JOIN','coe_subjects sub','sub.coe_subjects_id=d.subject_id')
                              ->join('JOIN','coe_student_mapping e','e.course_batch_mapping_id=d.batch_mapping_id')
                              ->join('JOIN','coe_student f','f.coe_student_id=e.student_rel_id and f.register_number=a.register_number')
                              ->where(['c.exam_date' =>$exam_date,'c.exam_month'=>$exam_month,'c.exam_session'=>$exam->exam_session ,'c.time_slot'=>$exam->time_slot,'c.internal_number'=>$internal_number])
                              ->orderBy('hall_name,seat_no')->createCommand()->queryAll();

                $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT'); 
            $cia= $array[$internal_number];              
          Yii::$app->ShowFlashMessages->setMsg('Success',"Halls Allocated Successfully!!" );
          return $this->render('view', [
                'model' => $model,
                'hallmaster' => $hallmaster,
                'categorytype' => $categorytype,
                'exam' => $exam,                
                'exam_date' => $exam_date,
                'exam_session' => $exam_sn,
                'internal_number'=>$cia,
                'time_slot'=>$time_slot,
                'allocated_value' => $allocated_value,
            ]);

        }
        else{
          $hallmaster = new HallMaster();
            $categorytype = new Categorytype();
            $exam = new ExamTimetableInt();
          Yii::$app->ShowFlashMessages->setMsg('error',"Un Known Error" );
           return $this->render('create', [
                'model' => $model,'hallmaster' => $hallmaster,'categorytype' => $categorytype,'exam' => $exam,
            ]);
        }
      }
      else{
          $hallmaster = new HallMaster();
          $categorytype = new Categorytype();
          $exam = new ExamTimetableInt();
          Yii::$app->ShowFlashMessages->setMsg('error',"Halls Arrangement Already Completed" );
            return $this->redirect(['create']);
        }
      }//equal hall count if condition
      else{
        $hallmaster = new HallMaster();
        $categorytype = new Categorytype();
        $exam = new ExamTimetableInt();
        Yii::$app->ShowFlashMessages->setMsg('error',"Halls are not sufficient to assign" );
            return $this->redirect(['create']);
      }
    } else {
      Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Galley Arrangement');
            return $this->render('create', [
                'model' => $model,'hallmaster' => $hallmaster,'categorytype' => $categorytype,'exam' => $exam,
            ]);
        }
  }

    /**
     * Updates an existing HallAllocateInt model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_hall_allocate_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }


    // Excel Printing
    public function actionExcelprint(){
        
        $content = $_SESSION['questionpaper_print'];
        $fileName ='Question-Paper-Count-'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionHallpdf(){
      require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   

        $content = $_SESSION['hall_arrange'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' => 'Internal_Galley_Arrangement.pdf',                
                'format' => Pdf::FORMAT_LEGAL,                
                'orientation' => Pdf::ORIENT_PORTRAIT,                
                'destination' => Pdf::DEST_BROWSER,                
                'content' => $content,  
                'options' => ['title' => 'Internal Galley Arrangement'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>["Internal - Galley Arrangement Report Sheet PRINTED ON : ".date("d-m-Y h:i:s A")." PAGE :{PAGENO}"],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();        
        unset($_SESSION['hall_arrange']);
    }



    /**
     * Deletes an existing HallAllocateInt model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }


    public function actionDeletehallarrangement()
    {
      $exam_date =  date("Y-m-d",strtotime($_POST['date']));
      $month =  Yii::$app->request->post('month');
      $year =  Yii::$app->request->post('year');
      $time_slot =  Yii::$app->request->post('time_slot');

      $internal_number =  Yii::$app->request->post('internal_number');
      $entered_marks = array();
      $subject_query = new Query();
      $check_subject = $subject_query->select("subject_mapping_id,coe_exam_timetable_id")
                              ->from('coe_exam_timetable_int')                              
                              ->where(['exam_date' =>$exam_date,'exam_session'=>$_POST['session'],'time_slot'=>$time_slot,'exam_year'=>$year,'exam_month'=>$month,'internal_number'=>$internal_number])->createCommand()->queryAll();
      
      $absent_query = new Query();
      $check_absent = $absent_query->select('*')
                              ->from('coe_absent_entry_int')                              
                              ->where(['exam_date' =>$exam_date,'exam_session'=>$_POST['session'],'exam_year'=>$year,'exam_month'=>$month,'internal_number'=>$internal_number])->createCommand()->queryAll();

      $check_faculty_allocate = $subject_query->select("fh_arrange_id")
                              ->from('coe_faculty_hall_arrange_int')                              
                              ->where(['exam_date' =>$exam_date,'exam_session'=>$_POST['session'],'time_slot'=>$time_slot,'internal_number'=>$internal_number])->createCommand()->queryAll();

      if(count($check_subject)>0){
        foreach($check_subject as $sub){
          $mark_query = new Query();
         
          if(count($check_absent)>0 || count($check_faculty_allocate)>0){            
            return 0;           
          
          }else{            
            foreach($check_subject as $exm_id){
              $delete_halls = Yii::$app->db->createCommand("delete from coe_hall_allocate_int where exam_timetable_id='".$exm_id['coe_exam_timetable_id']."' AND internal_number='".$internal_number."'")->execute();
            }
            return 1;            
          }

        }
        
      }

      
    }
    /**
     * Finds the HallAllocateInt model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return HallAllocateInt the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = HallAllocateInt::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }


    public function actionQpdistribution(){
      $model = new HallAllocateInt();
      $exam = new ExamTimetableInt();
      
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to QP Distribution');
        return $this->render('qpdistribution', [
            'model' => $model,'exam' => $exam,
        ]);
        
    }

     public function actionQpcnt(){

      $hall_id = [];
      $hall_array = [];
      $qp_array = [];
      $exam_id = [];
      $total_qp_count = 0;
      $total_stu = 0;
      $exam_date =  date("Y-m-d",strtotime($_POST['date']));
      $exam_year = Yii::$app->request->post('exam_year');
      $exam_month = Yii::$app->request->post('exam_month');
      $internal_number = Yii::$app->request->post('internal_number');
      $time= Yii::$app->request->post('time');

      $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT');
            $cia= $array[$internal_number];
      
      $getQpPract = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Practical%'")->queryScalar();
      $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

      $qp_code_exam = Yii::$app->db->createCommand('select * from coe_exam_timetable_int as A JOIN coe_subjects_mapping as B ON B.coe_subjects_mapping_id =A.subject_mapping_id where subject_type_id!='.$getQpPract.' and exam_date="'.$exam_date.'" and exam_session='.$_POST['session'].' and exam_month='.$exam_month.' and exam_year='.$exam_year.' and internal_number='.$internal_number.' and time_slot='.$time.'')->queryAll();

       $mark_query = new Query();
        $mark_query->select('coe_exam_timetable_id,qp_code')
            ->from('coe_exam_timetable_int as A')
            ->where(['exam_date'=>$exam_date,'exam_year'=>$exam_year,'exam_month'=>$exam_month,'internal_number'=>$internal_number,'exam_session'=>$_POST['session'],'time_slot'=>$time]);
       
          $qp_code_list=  $mark_query->createCommand()->queryAll();

      foreach($qp_code_list as $qp_hall)
      { 

        $qp_count = Yii::$app->db->createCommand("select count(a.register_number) from coe_hall_allocate_int a,coe_hall_master b where a.hall_master_id=b.coe_hall_master_id and a.exam_timetable_id='".$qp_hall['coe_exam_timetable_id']."' group by hall_master_id,exam_timetable_id")->queryScalar();

        if($qp_count>0)
        {
            array_push($qp_array,$qp_hall['qp_code']);
            array_push($exam_id, $qp_hall['coe_exam_timetable_id']);
        }
        
        $hall_name = Yii::$app->db->createCommand("select distinct(a.hall_master_id),b.hall_name from coe_hall_allocate_int a,coe_hall_master b where a.hall_master_id=b.coe_hall_master_id and a.exam_timetable_id='".$qp_hall['coe_exam_timetable_id']."' group by hall_master_id,exam_timetable_id")->queryAll();

        foreach($hall_name as $hl_nme){
          if(!in_array($hl_nme['hall_name'], $hall_array))
          {
            array_push($hall_array, $hl_nme['hall_name']);
            array_push($hall_id, $hl_nme['hall_master_id']);
          }                
        }
      }     
      $qp_array = array_unique($qp_array);
      if(count($qp_code_list)>0){
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        /* 
        *   Already Defined Variables from the above included file
        *   $org_name,$org_email,$org_phone,$org_web,$org_address,$org_tagline, 
        *   use these variables for application
        *   use $file_content_available=="Yes" for Content Status of the Organisation
        */
        $qp_table = '';
        $col_merge = count($qp_array)+2;
        if($file_content_available!="Yes")
        {
            return $qp_table=0;
        }
        $exam_sess_for = Categorytype::findOne($_POST['session']);
      $exam_date_for =  date("d-m-Y",strtotime($_POST['date']));
       $exam_time = Categorytype::findOne($time);
         $qp_table.='<table width="100%" style="overflow: auto" id="checkAllFeat1" class="table table- table-responsive table-striped" align="center" border="0"><tr>
                <td colspan=2> 
                    <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
                </td>
                <td colspan="'.($col_merge-4).'" align="center"> 
                    <center><b><font size="4px">'.$org_name.'</font></b></center>
                    <center>'.$org_address.'</center>
                    
                    <center>'.$org_tagline.'</center> 
                </td>
                <td  colspan=2 align="center">  
                    <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                </td>
            </tr>
            <tr>
            <td  style="font-size:15px" colspan="'.($col_merge).'" align="center"><b>Internal Examinations - '.$cia.'</b></td>
            </tr>
            <tr>
            <td   style="font-size:15px" colspan="'.($col_merge).'" align="center"><b>Question Paper Distribution List for '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' Date : '.$exam_date_for.'  Session : '.$exam_sess_for->category_type.' </b><br>FN: '.$exam_time->category_type.'</td>
            </tr>
            </table>';
        $qp_table .= '<table width="100%" style="overflow: auto" id="checkAllFeat1" class="table table- table-responsive table-striped" align="center" border="1">';
       
      
      $qp_table.='
      <tr><th>Hall Name</th>';

        $qp_codes_list='';
        $exam_ids='';
        sort($qp_array);
        
        for($q=0;$q<count($qp_array);$q++){
          $qp_table.='<th>'.$qp_array[$q].'</th>';
          $qp_codes_list .="'".$qp_array[$q]."', ";
        }
        
        $qp_codes_list =trim($qp_codes_list,", ");
        $qp_table.= '<th>Total</th></tr>';

        if(count($qp_code_exam)>0)
        {
          for ($i=0; $i <count($qp_code_exam) ; $i++) 
          { 
              $exam_ids[]= $qp_code_exam[$i]['coe_exam_timetable_id'];
          }
        }
        sort($hall_array);
        for($hc=0;$hc<count($hall_array);$hc++)
        {
          $qp_table.='<tr><td>'.$hall_array[$hc].'</td>';
          $hallId = HallMaster::find()->where(['hall_name'=>$hall_array[$hc]])->one();
          for($qc=0;$qc<count($qp_array);$qc++)
          {
           $qp_exam_list = '';
           $qp_code_exam = ExamTimetableInt::find()->where(['exam_date'=>$exam_date,'exam_session'=>$_POST['session'],'qp_code'=>$qp_array[$qc],'exam_month'=>$exam_month,'exam_year'=>$exam_year])->all();
           
            for($q_1=0;$q_1<count($qp_code_exam);$q_1++)
            {
              $qp_exam_list .="'".$qp_code_exam[$q_1]->coe_exam_timetable_id."', ";
            }
            $qp_exam_list = trim($qp_exam_list,', ');
            $qp_count = Yii::$app->db->createCommand("select count(A.register_number) from coe_hall_allocate_int as A JOIN coe_student as B ON B.register_number=A.register_number JOIN coe_student_mapping as C ON C.student_rel_id=B.coe_student_id where exam_timetable_id IN (".$qp_exam_list.") and status_category_type_id NOT IN('".$det_disc_type."') and hall_master_id='".$hallId->coe_hall_master_id."'")->queryScalar();
            $disp_qp = $qp_count==0?"-":$qp_count;
            $qp_table.='<td>'.$disp_qp.'</td>';
            $total_qp_count+=$qp_count;
          }
         // echo $total_qp_count;exit;
          $qp_table.='<td>'.$total_qp_count.'</td></tr>';
          $total_qp_count=0;
        }
        $qp_table.='<tr><td><b>Total</b></td>';
        
        $overalltot=0;
        for($qc=0;$qc<count($qp_array);$qc++)
        {
             $qry= "SELECT count(A.register_number) FROM coe_hall_allocate_int as A JOIN coe_exam_timetable_int as B ON B.coe_exam_timetable_id=A.exam_timetable_id WHERE exam_date='".date("Y-m-d", strtotime($exam_date))."' AND exam_session='".$_POST['session']."'  AND qp_code='".$qp_array[$qc]."'"; 
            $hall_count = Yii::$app->db->createCommand($qry)->queryScalar();
                
           
            $qp_table.='<td>'.$hall_count.'</td>';
            $overalltot=$overalltot+$hall_count;
        }
        
        $qp_table.='<td><b>'.$overalltot.'</b></td></tr></table>';

        if(isset($_SESSION['questionpaper_print'])){ unset($_SESSION['questionpaper_print']);}     
        $_SESSION['questionpaper_print']=$qp_table;
        return $qp_table;

      }else{
        
        return $qp_table = 0;
      }

    }    



    public function actionPrintQpPdf()
    {
        
      require(Yii::getAlias('@webroot/includes/use_institute_info.php'));  

      $content = $_SESSION['questionpaper_print'];
      //unset($_SESSION['student_application_date']);
          $pdf = new Pdf([
             
              'mode' => Pdf::MODE_CORE, 
              
              'filename' => 'QP Data.pdf',                
              'format' => Pdf::FORMAT_A4,                 
              'orientation' => Pdf::ORIENT_LANDSCAPE,                 
              'destination' => Pdf::DEST_BROWSER,                 
              'content' => $content,  
              'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
              'cssInline' => ' @media all{
                      table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }td,th{border:1px solid #999}
                  }   
              ', 
              'options' => ['title' => ' Internal QP Data'],
              'methods' => [ 
                  'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                  'SetFooter'=>['Question Paper Distribution'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
              ]
          ]);

      Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
      $headers = Yii::$app->response->headers;
      $headers->add('Content-Type', 'application/pdf');
      return $pdf->render(); 
    }

    public function actionHallvsabsentstudentpdf(){
        
      require(Yii::getAlias('@webroot/includes/use_institute_info.php'));  
      $content = $_SESSION['hallvsstudentabsent'];
      $pdf = new Pdf([
             
              'mode' => Pdf::MODE_CORE, 
              
              'filename' => 'Hall Vs Student Absent.pdf',                
              'format' => Pdf::FORMAT_A4,                 
              'orientation' => Pdf::ORIENT_PORTRAIT,                 
              'destination' => Pdf::DEST_BROWSER,                 
              'content' => $content,  
              'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
              'cssInline' => ' @media all{
                      table{border-collapse:collapse; border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }td,th{border:1px solid #999}
                  }   
              ', 
              'options' => ['title' => 'Hall Vs Student Absent'],
              'methods' => [ 
                  'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                  'SetFooter'=>['Hall Vs Student Absent '.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
              ]
          ]);

      Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
      $headers = Yii::$app->response->headers;
      $headers->add('Content-Type', 'application/pdf');
      return $pdf->render(); 
    }

    public function actionExcelhallvsabsentstudent(){
        
        $content = $_SESSION['hallvsstudentabsent'];
        $fileName ="Hall Vs ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)."  Reports".date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    public function actionHallvsstudent(){
      $model = new HallAllocateInt();
      $exam = new ExamTimetableInt();

      if ($model->load(Yii::$app->request->post()) &&  $exam->load(Yii::$app->request->post())) {
    
        return $this->redirect(['view', 'id' => $model->coe_hall_allocate_id]);
        
      }
      else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Vs '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('hallvsstudent', [
                'model' => $model,'exam' => $exam,
            ]);
      }

    }
    public function actionHallvsAbsentStudent()
    {
      $model = new HallAllocateInt();
      $exam = new ExamTimetableInt();

      if ($model->load(Yii::$app->request->post()) &&  $exam->load(Yii::$app->request->post())) 
      {
        return $this->redirect(['view', 'id' => $model->coe_hall_allocate_id]);        
      }
      else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Vs '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('hallvs-absent-student', [
                'model' => $model,'exam' => $exam,
            ]);
      }

    }

    // Date Wise reports Starts Here 

    public function actionDatewisereports()
    { 
        $model = new HallAllocateInt();
        $exam = new ExamTimetableInt();

        if (isset($_POST['get_date_reports'])) 
        {
            $exam_date =  date("Y-m-d",strtotime($_POST['ExamTimetableInt']['exam_date']));
            $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
            $query = new  Query();
            $query->select('DISTINCT (H.subject_code) as subject_code,count(I.register_number) as count,  H.subject_name,F.qp_code,F.exam_date, D.degree_name, D.degree_code, E.programme_name,A.batch_name ,I.year,K.category_type,L.category_type as exam_type,ses.category_type as session')
                            ->from('coe_bat_deg_reg as C')
                            ->join('JOIN','coe_degree as D','D.coe_degree_id=C.coe_degree_id')
                            ->join('JOIN','coe_programme as E','E.coe_programme_id=C.coe_programme_id')
                            ->join('JOIN','coe_batch as A','A.coe_batch_id=C.coe_batch_id')
                            ->join('JOIN','coe_subjects_mapping as G','G.batch_mapping_id=C.coe_bat_deg_reg_id')
                            ->join('JOIN','coe_subjects H','H.coe_subjects_id=G.subject_id')
                            ->join('JOIN','coe_exam_timetable_int F','F.subject_mapping_id=G.coe_subjects_mapping_id')
                            ->join('JOIN','coe_hall_allocate_int I','I.exam_timetable_id=F.coe_exam_timetable_id')
                            ->join('JOIN','coe_student_mapping as abc','G.batch_mapping_id=abc.course_batch_mapping_id')
                            ->join('JOIN','coe_student as stu','stu.coe_student_id=abc.student_rel_id and stu.register_number=I.register_number')
                            ->join('JOIN','coe_category_type ses','ses.coe_category_type_id=F.exam_session')
                            ->join('JOIN','coe_category_type K','K.coe_category_type_id=I.month')
                            ->join('JOIN','coe_category_type L','L.coe_category_type_id=F.exam_type');
                                 
                $query->Where(['I.year' => $_POST['HallAllocateInt']['year'],'I.month'=> $_POST['HallAllocateInt']['month'],'F.exam_date'=>$exam_date, 'F.exam_session'=>$_POST['ExamTimetableInt']['exam_session'] ])->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query->groupBy('F.subject_mapping_id,H.subject_code');
                $query->orderBy('H.coe_subjects_id');

            $date_wise_reports = $query->createCommand()->queryAll();
          
            if(!empty($date_wise_reports))
            {
                return $this->render('datewisereports', [
                    'model' => $model,'exam'=>$exam,'date_wise_reports'=>$date_wise_reports,
                ]);
            }
            else
            {
               Yii::$app->ShowFlashMessages->setMsg('error',"No Data Found" );
               return $this->redirect(['hall-allocate-int/datewisereports']); 
            }
            
          
        }
        else {
              Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' Date Wise '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
              return $this->render('datewisereports', [
                  'model' => $model,'exam'=>$exam,
              ]);
        }
    }

    public function actionExcelDatewisereports(){
        
        
        $content = $_SESSION['date_wise_reports'];
        $fileName =ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Date Wise "." Reports".date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionPrintDatewisereports()
    {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
        $content = $_SESSION['date_wise_reports'];
         
        
        //unset($_SESSION['student_application_date']);
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Date Wise "." Reports.pdf",                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: 1px solid #ccc !important; font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; padding: 20px 0px; } td{ border: 1px solid #ccc;} table.no-border
                        {
                          border: 1px solid #ccc !important;
                        } 
                    }   
                ', 
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Date Wise "." Reports"],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Date Wise "." Reports".' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }

// Ends the Date Wise reports

    // Subject Wise reports Starts Here 

    public function actionSubjectwisereports()
    { 
        $model = new MarkEntry(); 
        $hallModel = new HallAllocateInt();  
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (isset($_POST['get_subject_reports'])) 
        {
            $query = new  Query();
            $query->select('DISTINCT (H.subject_code) as subject_code,count(I.register_number) as count,  H.subject_name,  D.degree_code,D.degree_name,E.programme_name, I.year,K.category_type,L.category_type as exam_type')
                            ->from('coe_bat_deg_reg as C')
                            ->join('JOIN','coe_degree as D','D.coe_degree_id=C.coe_degree_id')
                            ->join('JOIN','coe_programme as E','E.coe_programme_id=C.coe_programme_id')
                            ->join('JOIN','coe_subjects_mapping as G','G.batch_mapping_id=C.coe_bat_deg_reg_id')
                            ->join('JOIN','coe_subjects H','H.coe_subjects_id=G.subject_id')
                            ->join('JOIN','coe_exam_timetable_int F','F.subject_mapping_id=G.coe_subjects_mapping_id')
                            
                            ->join('JOIN','coe_hall_allocate_int I','I.exam_timetable_id=F.coe_exam_timetable_id')
                            ->join('JOIN','coe_student_mapping as abc','G.batch_mapping_id=abc.course_batch_mapping_id')
                            ->join('JOIN','coe_student as stu','stu.coe_student_id=abc.student_rel_id and stu.register_number=I.register_number')
                            ->join('JOIN','coe_category_type K','K.coe_category_type_id=I.month')
                            ->join('JOIN','coe_category_type L','L.coe_category_type_id=F.exam_type');
                                 
                $query->Where(['I.year' => $_POST['mark_year'],'I.month'=> $_POST['month'], 'H.subject_code'=>$_POST['subject_code'] ])->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query->groupBy('F.subject_mapping_id,H.subject_code');
                $query->orderBy('H.coe_subjects_id');

            $subject_reports = $query->createCommand()->queryAll();
           
            if(!empty($subject_reports))
            {
                return $this->render('subjectwisereports', [
                    'model' => $model,'hallModel'=>$hallModel,'subject_reports'=>$subject_reports,
                ]);
            }
            else
            {
               Yii::$app->ShowFlashMessages->setMsg('error',"No Data Found" );
               return $this->redirect(['hall-allocate-int/subjectwisereports']); 
            }
            
          
        }
        else {
              Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' Wise '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
              return $this->render('subjectwisereports', [
                  'model' => $model,'hallModel'=>$hallModel,
              ]);
        }
    }

    public function actionExcelSubjectreports(){
        
          $content = $_SESSION['subject_reports'];
        $fileName =ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." Wise "." Reports".date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionPrintSubjectreports()
    {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
        $content = $_SESSION['subject_reports'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." Wise "." Reports.pdf",                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: 1px solid #ccc !important; font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; padding: 20px 0px; } td{ border: 1px solid #ccc;} table.no-border
                        {
                          border: 1px solid #ccc !important;
                        } 
                    }   
                ', 
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." Wise "." Reports"],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." Wise "." Reports".' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }

// Ends the Subject Wise reports

    // Programme Wise Exam Printing

    public function actionProgrammeexamreports()
    { 
        $model = new MarkEntry();  
        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
        if (isset($_POST['get_count_reports'])) 
        {
            $query = new Query();
         
            $query->select('DISTINCT (H.subject_code) as subject_code,I.exam_timetable_id,count(I.register_number) as count,  H.subject_name, D.degree_name, D.degree_code,E.programme_name, I.year,K.category_type,L.category_type as exam_type')
                            ->from('coe_bat_deg_reg as C')
                            ->join('JOIN','coe_degree as D','D.coe_degree_id=C.coe_degree_id')
                            ->join('JOIN','coe_programme as E','E.coe_programme_id=C.coe_programme_id')
                            ->join('JOIN','coe_subjects_mapping as G','G.batch_mapping_id=C.coe_bat_deg_reg_id')
                            ->join('JOIN','coe_subjects H','H.coe_subjects_id=G.subject_id')
                            ->join('JOIN','coe_exam_timetable_int F','F.subject_mapping_id=G.coe_subjects_mapping_id')
                            
                            ->join('JOIN','coe_hall_allocate_int I','I.exam_timetable_id=F.coe_exam_timetable_id')
                            ->join('JOIN','coe_student_mapping as abc','G.batch_mapping_id=abc.course_batch_mapping_id')
                            ->join('JOIN','coe_student as stu','stu.coe_student_id=abc.student_rel_id and stu.register_number=I.register_number')
                            ->join('JOIN','coe_category_type K','K.coe_category_type_id=I.month')
                            ->join('JOIN','coe_category_type L','L.coe_category_type_id=F.exam_type');
                                 
                $query->Where(['I.year' => $_POST['mark_year'],'F.exam_year' => $_POST['mark_year'],'G.batch_mapping_id'=>$_POST['bat_map_val'],'I.month'=> $_POST['month'] ,'F.exam_month'=> $_POST['month'] ])->andWhere(['<>', 'status_category_type_id', $det_disc_type]);
                $query->groupBy('I.exam_timetable_id,H.subject_code');
                $query->orderBy('H.coe_subjects_id');

          $degree_reports = $query->createCommand()->queryAll();
          
            if(!empty($degree_reports))
            {
                return $this->render('programmeexamreports', [
                    'model' => $model,'degree_reports'=>$degree_reports,
                ]);
            }
            else
            {
               Yii::$app->ShowFlashMessages->setMsg('error',"No Data Found" );
               return $this->redirect(['hall-allocate-int/programmeexamreports']); 
            }
            
          
        }
        else {
              Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).' '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).' '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
              return $this->render('programmeexamreports', [
                  'model' => $model,
              ]);
        }
    }

    public function actionExcelProgrammeexamreports(){
        
        
        $content = $_SESSION['course_reports'];
        $fileName =ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).'-'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM).'-Reports'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionPrintProgrammeexamreports()
    {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
        $content = $_SESSION['course_reports'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE)." "." ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Reports.pdf",                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse:collapse; border: 1px solid #ccc !important; font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; padding: 20px 0px; } td{ border: 1px solid #ccc;} table.no-border
                        {
                          border: 1px solid #ccc !important;
                        } 
                    }   
                ', 
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE)." "." ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Reports"],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_DEGREE)." "." ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Reports".' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }


    // Course Exam Printing ends here



    // Hall Ticket Printing

    public function actionHallTicket()
    { 
        $model = new MarkEntry();  
        $student = new Student();
        $Practical = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Theory%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();

        if (isset($_POST['get_hall_tickets'])) 
        {
            $query = new Query();
            $query = new  Query();
            $query->select('J.hall_name,A.name,I.register_number,G.semester,H.subject_code,H.subject_name, A.dob, A.gender, D.degree_code, D.degree_name,E.programme_name,F.exam_date, I.year,J.hall_name,K.category_type, L.category_type as exam_session,I.seat_no,')
                            ->from('coe_student as A')                    
                            ->join('JOIN','coe_student_mapping as B','B.student_rel_id=A.coe_student_id ')
                            ->join('JOIN','coe_bat_deg_reg as C','C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                            ->join('JOIN','coe_degree as D','D.coe_degree_id=C.coe_degree_id')
                            ->join('JOIN','coe_programme as E','E.coe_programme_id=C.coe_programme_id')
                            ->join('JOIN','coe_subjects_mapping as G','G.batch_mapping_id=C.coe_bat_deg_reg_id')
                            ->join('JOIN','coe_subjects H','H.coe_subjects_id=G.subject_id')
                            ->join('JOIN','coe_exam_timetable_int F','F.subject_mapping_id=G.coe_subjects_mapping_id')
                            ->join('JOIN','coe_category_type K','K.coe_category_type_id=F.exam_month')
                            ->join('JOIN','coe_hall_allocate_int I','I.exam_timetable_id=F.coe_exam_timetable_id and I.register_number=A.register_number')
                            ->join('JOIN','coe_hall_master J','J.coe_hall_master_id=I.hall_master_id')
                            ->join('JOIN','coe_category_type L','L.coe_category_type_id=F.exam_session');
                          
            $query->Where(['F.exam_year' => $_POST['mark_year'],'B.course_batch_mapping_id'=>$_POST['bat_map_val'],'F.exam_month'=> $_POST['month'], 'A.student_status'=>'Active' ])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type]) 
            ->andWhere(['between', "A.register_number", $_POST['Student']['register_number_from'],$_POST['Student']['register_number_to'] ]);
            if($_POST['sec']!='All')
            {
              $query->andWhere(['B.section_name' =>$_POST['sec']]);
            }
            $query->orderBy('I.register_number,subject_code');
            $query->orderBy('I.register_number,exam_date');

          $print_halls = $query->createCommand()->queryAll();

          $semester_number = ConfigUtilities::semCaluclation($_POST['mark_year'],$_POST['month'],$_POST['bat_map_val']);
          $query1 = new Query();
            $query1 = new  Query();
            $query1->select('A.name,A.register_number,G.semester, H.subject_code,H.subject_name, A.dob, A.gender, D.degree_code, D.degree_name,E.programme_name,F.exam_date, F.exam_year as year,K.category_type, L.category_type as exam_session')
                            ->from('coe_student as A')                    
                            ->join('JOIN','coe_student_mapping as B','B.student_rel_id=A.coe_student_id ')
                            ->join('JOIN','coe_bat_deg_reg as C','C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                            ->join('JOIN','coe_degree as D','D.coe_degree_id=C.coe_degree_id')
                            ->join('JOIN','coe_programme as E','E.coe_programme_id=C.coe_programme_id')
                            ->join('JOIN','coe_subjects_mapping as G','G.batch_mapping_id=C.coe_bat_deg_reg_id')
                            ->join('JOIN','coe_subjects H','H.coe_subjects_id=G.subject_id')
                            ->join('JOIN','coe_exam_timetable_int F','F.subject_mapping_id=G.coe_subjects_mapping_id')
                            ->join('JOIN','coe_category_type K','K.coe_category_type_id=F.exam_month')
                            ->join('JOIN','coe_category_type L','L.coe_category_type_id=F.exam_session');
                          
            $query1->Where(['F.exam_year' => $_POST['mark_year'],'B.course_batch_mapping_id'=>$_POST['bat_map_val'],'F.exam_month'=> $_POST['month'], 'A.student_status'=>'Active','G.semester'=>$semester_number  ])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->andWhere(['<>','paper_type_id',$Practical]) 
            ->andWhere(['between', "A.register_number", $_POST['Student']['register_number_from'],$_POST['Student']['register_number_to']]);
            if($_POST['sec']!='All')
            {
              $query1->andWhere(['B.section_name' =>$_POST['sec']]);
            }
            $query1->orderBy('A.register_number,subject_code');
            $query1->orderBy('A.register_number,exam_date');

            $print_halls_pract = $query1->createCommand()->queryAll();
            if(!empty($print_halls_pract))
            {
              $print_halls = array_merge($print_halls,$print_halls_pract);
              array_multisort(array_column($print_halls, 'register_number'),  SORT_ASC, $print_halls);
            }

            $query2 = new Query();
            $query2 = new  Query();
            $query2->select('A.name,A.register_number,G.semester, H.subject_code,H.subject_name, A.dob, A.gender, D.degree_code, D.degree_name,E.programme_name,F.exam_date, F.exam_year as year,K.category_type, L.category_type as exam_session')
                            ->from('coe_student as A')                    
                            ->join('JOIN','coe_student_mapping as B','B.student_rel_id=A.coe_student_id ')
                            ->join('JOIN','coe_bat_deg_reg as C','C.coe_bat_deg_reg_id=B.course_batch_mapping_id')
                            ->join('JOIN','coe_degree as D','D.coe_degree_id=C.coe_degree_id')
                            ->join('JOIN','coe_programme as E','E.coe_programme_id=C.coe_programme_id')
                            ->join('JOIN','coe_subjects_mapping as G','G.batch_mapping_id=C.coe_bat_deg_reg_id')
                            ->join('JOIN','coe_subjects H','H.coe_subjects_id=G.subject_id')
                            ->join('JOIN','coe_exam_timetable_int F','F.subject_mapping_id=G.coe_subjects_mapping_id')
                            ->join('JOIN','coe_category_type K','K.coe_category_type_id=F.exam_month')
                            ->join('JOIN','coe_category_type L','L.coe_category_type_id=F.exam_session')
                            ->join('JOIN','coe_mark_entry_master M','M.subject_map_id=G.coe_subjects_mapping_id and M.student_map_id=B.coe_student_mapping_id');                          
            $query2->Where(['F.exam_year' => $_POST['mark_year'],'B.course_batch_mapping_id'=>$_POST['bat_map_val'],'F.exam_month'=> $_POST['month'], 'A.student_status'=>'Active' ])
            ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
            ->andWhere(['<>','paper_type_id',$Practical]) 
            ->andWhere(['<','G.semester',$semester_number]) 
            ->andWhere(['NOT LIKE','M.result','Pass']) 
            ->andWhere(['between', "A.register_number", $_POST['Student']['register_number_from'],$_POST['Student']['register_number_to']]);
            if($_POST['sec']!='All')
            {
              $query2->andWhere(['B.section_name' =>$_POST['sec']]);
            }
            $query2->orderBy('A.register_number,subject_code');
            $query2->orderBy('A.register_number,exam_date');

            $print_halls_pract_arr = $query2->createCommand()->queryAll();
            if(!empty($print_halls_pract_arr))
            {
              $print_halls = array_merge($print_halls,$print_halls_pract_arr);
              array_multisort(array_column($print_halls, 'register_number'),  SORT_ASC, $print_halls);
            }
            $print_halls = array_map("unserialize", array_unique(array_map("serialize", $print_halls)));
            if(!empty($print_halls))
            {
                return $this->render('hall-ticket', [
                    'model' => $model,'student' => $student,'print_halls'=>$print_halls,
                ]);
            }
            else
            {
               Yii::$app->ShowFlashMessages->setMsg('error',"No Data Found" );
               return $this->redirect(['hall-ticket']);
            }
            
          
        }
        else {
              Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Ticket Printing');
              return $this->render('hall-ticket', [
                  'model' => $model,'student' => $student,
              ]);
        }
    }

    public function actionExcelHallTicket()
    {
        $content = $_SESSION['hall_ticket_print'];
        
        $fileName ='Hall-Ticket-Printing-'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionPrintHallTickets()
    {
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
       
          $content = $_SESSION['hall_ticket_print'];
         
        
        //unset($_SESSION['student_application_date']);
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => 'Hall Ticket Printing.pdf',                
                'format' => Pdf::FORMAT_A4,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: left;  font-family:"Roboto, sans-serif";  border: 1px solid #000; width:100%; } 
                        
                        table td{.
                           white-space: nowrap;
                            border: 1px solid #000;
                            text-align: left;
                            font-size: 12px;
                            line-height: 1.5em;
                        }
                        table th{
                          white-space: nowrap;
                            border: 1px solid #000;
                            text-align: left;
                            font-size: 12px;
                            line-height:1.5em;
                        }
                    }   
                ',
                'options' => ['title' => 'Hall Ticket Printing'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".strtoupper($org_name)], 
                    'SetFooter'=>['Hall Ticket Printing PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ],
                
            ]);
         
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }


    // Hall Ticket Printing ends here


    // Galley Arrangement Query 

    public function actionGalleyArrangementReports()
    {
      $model = new HallAllocateInt();
      $exam = new ExamTimetableInt();
      $det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

        $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
      if ($model->load(Yii::$app->request->post())) 
        {
          $exam_date =  date("Y-m-d",strtotime($_POST['ExamTimetableInt']['exam_date']));
          $query = new Query();
          $query->select('A.name, concat(`K`.`degree_name`,`L`.`programme_code`) as degree_name, M.batch_name, B.register_number,B.seat_no, C.exam_date, C.qp_code, H.category_type as exam_session, D.hall_name, B.row, B.row_column, B.year, E.category_type as month,G.subject_code')
                  ->from('coe_student as A')  
                  ->join('JOIN','coe_student_mapping as I','I.student_rel_id = A.coe_student_id')
                  ->join('JOIN','coe_bat_deg_reg as J','J.coe_bat_deg_reg_id=I.course_batch_mapping_id')
                  ->join('JOIN','coe_degree as K','K.coe_degree_id=J.coe_degree_id')
                  ->join('JOIN','coe_programme as L','L.coe_programme_id=J.coe_programme_id')
                  ->join('JOIN','coe_batch as M','M.coe_batch_id=J.coe_batch_id')                 
                  ->join('JOIN','coe_hall_allocate_int as B','B.register_number=A.register_number')
                  ->join('JOIN','coe_exam_timetable_int as C','C.coe_exam_timetable_id=B.exam_timetable_id')
                  ->join('JOIN','coe_hall_master as D','D.coe_hall_master_id=B.hall_master_id')
                  ->join('JOIN','coe_category_type as E','E.coe_category_type_id=B.month')
                  ->join('JOIN','coe_subjects_mapping as F','F.coe_subjects_mapping_id=C.subject_mapping_id')
                  ->join('JOIN','coe_subjects as G','G.coe_subjects_id=F.subject_id')
                  ->join('JOIN','coe_category_type as H','H.coe_category_type_id=C.exam_session')
                  //->groupBy('B.register_number')
                  ->orderBy('D.hall_name,B.seat_no');

          $query->where(['B.year'=>$model->year,'B.month'=>$model->month,'C.exam_date'=>$exam_date ,'C.exam_session'=>$_POST['ExamTimetableInt']['exam_session']])
                ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                ->andWhere(['A.student_status'=>'Active']);

          $get_data = $query->createCommand()->queryAll();
        
        if(!empty($get_data))
        {
            return $this->render('galley-arrangement-reports', [
                'model' => $model,'exam' => $exam,'get_data'=>$get_data,
            ]);
        }
        else
        {
           Yii::$app->ShowFlashMessages->setMsg('error',"No Data Found" );
           return $this->redirect(['galley-arrangement-reports']);
        }

        
      }
      else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Galley Arrangement '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_REPORT));
            return $this->render('galley-arrangement-reports', [
                'model' => $model,'exam' => $exam,
            ]);
      }

    }

        // Galley Arrangement Query Ends Here  

    public function actionAttendanceSheet()
    {

      $model = new HallAllocateInt();
      $exam = new ExamTimetableInt();
      if ($model->load(Yii::$app->request->post())) 
      {
          $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
          $prac_ticla = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Theory%'")->queryScalar();

            $exam_type_g = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%arrear%'")->queryScalar();
           $exam_date = date('Y-m-d',strtotime($_POST['ExamTimetableInt']['exam_date'])); 
           $exam_session = $_POST['ExamTimetableInt']['exam_session']; 
           $print_for = 'Normal';

           $internal_number = $_POST['ExamTimetableInt']['internal_number']; 

           $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT'); 
            $cia= $array[$internal_number];
                  
              $query = new Query();
              $query->select('A.name,B.exam_timetable_id,B.hall_master_id,B.register_number,B.seat_no, C.exam_date,H.category_type as exam_session,D.hall_name,B.year, E.category_type as month,G.subject_code,G.subject_name')
                      ->from('coe_student as A')                    
                      ->join('JOIN','coe_hall_allocate_int as B','B.register_number=A.register_number')
                      ->join('JOIN','coe_exam_timetable_int as C','C.coe_exam_timetable_id=B.exam_timetable_id')
                      ->join('JOIN','coe_hall_master as D','D.coe_hall_master_id=B.hall_master_id')
                      ->join('JOIN','coe_category_type as E','E.coe_category_type_id=B.month')
                      ->join('JOIN','coe_subjects_mapping as F','F.coe_subjects_mapping_id=C.subject_mapping_id')
                      ->join('JOIN','coe_student_mapping as stu','stu.course_batch_mapping_id=F.batch_mapping_id and stu.student_rel_id=A.coe_student_id')
                      ->join('JOIN','coe_subjects as G','G.coe_subjects_id=F.subject_id')
                      ->join('JOIN','coe_category_type as H','H.coe_category_type_id=C.exam_session')
                      //->groupBy('B.register_number')
                      ->groupBy('C.exam_date, B.hall_master_id,B.register_number')
                      ->orderBy('D.hall_name,B.seat_no');

            $query->where(['B.year'=>$model->year,'B.month'=>$model->month,'C.exam_date'=>$exam_date,'C.exam_session'=>$exam_session,'C.internal_number'=>$internal_number])
                  ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                  ->andWhere(['A.student_status'=>'Active']); 
          
          $get_data = $query->createCommand()->queryAll();
        
        if(!empty($get_data))
        {
            return $this->render('attendance-sheet', [
                'model' => $model,'get_data'=>$get_data,'exam' => $exam,'cia'=>$cia
            ]);
        }
        else
        {
           Yii::$app->ShowFlashMessages->setMsg('error',"No Data Found" );
           return $this->redirect(['attendance-sheet']);
        }

        
      }
      else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Attendance Sheet');
            return $this->render('attendance-sheet', [
                'model' => $model,'exam' => $exam,
            ]);
      }

    }

    // Galley Arrangement Reports Starts Here 
    // 
    //  Galley Arrangement Excel Sheet
    
    public function actionExcelGalleyArrangement(){
      
          $content = $_SESSION['galley_reports'];        
        $fileName ='Galley-Arrangement-Report-'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionGalleyArrangementPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        
          $content = $_SESSION['galley_reports'];
          $file_name_pri = $_SESSION['galley_arrange_ment_repo_date'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => $file_name_pri.'.pdf',                
                'format' => Pdf::FORMAT_LEGAL,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                
                'cssInline' => ' @media all{
                        table{border-collapse: collapse; font-family:Helvetica Neue, Helvetica, Arial, sans-serif; width:100%; font-size: 20px !important;  }

                        table td table{border: none !important;}
                        table td{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                            padding: 10px;
                        }
                        table td.reduce_qp_height 
                        {
                          padding: 5px !important;
                        }
                        table th{
                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                           
                        }
                    }   
                ', 
                'options' => ['title' => 'Galley Arrangement Report Sheet'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['Galley Arrangement Report Sheet'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }



    // Galley Arrangement Report Ends Here 

    public function actionAttendanceSheetPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['attendance_sheet'];
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => 'Internal Attendance Sheet.pdf',                
                'format' => Pdf::FORMAT_LEGAL,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                //'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse; font-family:Helvetica Neue, Helvetica, Arial, sans-serif; width:100%; font-size: 16px !important;  }

                        table td table{border: none !important;}
                        table td{
                          white-space: nowrap;
                            font-size: 15px !important; 
                            border: 1px solid #CCC;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                            padding: 5px;
                        }
                        table th{
                           
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: left;
                           
                        }
                       
                    }   
                ', 
                'options' => ['title' => 'Internal Attendance Sheet'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['INTERNAL - ATTENDANCE SHEET'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        $pdf->marginTop = "10";
        $pdf->marginLeft = "10";
        $pdf->marginRight = "10";
        $pdf->marginBottom = "10";
        $pdf->marginHeader = "10";
        $pdf->marginFooter = "10";

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }

    public function actionAttendanceSheetPracticalPdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 
        $content = $_SESSION['attendance_sheet_practical'];        
        //unset($_SESSION['student_application_date']);
            $pdf = new Pdf([
               
                'mode' => Pdf::MODE_CORE,
                'filename' => 'Attendance Sheet Practical.pdf',                
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
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['Attendance Sheet Practical'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }

    public function actionExcelAttendancePracticalSheet()
    {
        $content = $_SESSION['attendance_sheet'];
        $fileName ='Attendance-Sheet-'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
    // Excel Attendance Hall Printing
    public function actionExcelAttendanceSheet(){        
        
        $content = $_SESSION['attendance_sheet'];
        $fileName ='Attendance-Sheet-'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    // Excel Hall VS STudent Printing
    public function actionExcelhallvsstudent()
    {
       
        $content = $_SESSION['hallvsstudent'];
         
        $fileName ='Hall-vs-'.ConfigUtilities::getConfigDesc(ConfigConstants::CONFIG_STUDENT).'-'.date('Y-m-d-H-i-s').'.xls';        
        $options = ['mimeType'=>'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }


    public function actionHallvsstudentpdf(){
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php')); 

         $content = $_SESSION['hallvsstudentpdf'];        
            $pdf = new Pdf([               
                'mode' => Pdf::MODE_CORE,                
                'filename' => 'Internal_HallvsStudent_'.date('d-m-yH:i:s').'.pdf',                
                'format' => Pdf::FORMAT_A4,                
                'orientation' => Pdf::ORIENT_PORTRAIT,                
                'destination' => Pdf::DEST_BROWSER,                
                'content' => $content,  
                'options' => ['title' => 'Internal - Hall vs Student'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>['Internal - Hall-vs-Student PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();        
        unset($_SESSION['hallvsstudent']);
    }

  public function actionHallvsabsentstudentreport()
  {
    $exam_year = Yii::$app->request->post('exam_year');
    $exam_month = Yii::$app->request->post('exam_month');
    $exam_date =  date("Y-m-d",strtotime($_POST['date']));
    require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
    $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
    $exam_id = ExamTimetableInt::find()->where(['exam_date'=>$exam_date,'exam_session'=>$_POST['session'],'exam_month'=>$exam_month,'exam_year'=>$exam_year])->all();

    $hl_list = Yii::$app->db->createCommand("select distinct(hall_master_id) from coe_hall_allocate_int a,coe_exam_timetable_int b where a.exam_timetable_id=b.coe_exam_timetable_id and b.exam_date='".$exam_date."' and b.exam_session='".$_POST['session']."' and b.exam_year='".$exam_year."' and b.exam_month='".$exam_month."' ")->queryAll();
    
    $exam_id_list = Yii::$app->db->createCommand("select a.coe_exam_timetable_id from coe_exam_timetable_int a,coe_category_type b where a.exam_session=b.coe_category_type_id and a.exam_date='".$exam_date."' and a.exam_session='".$_POST['session']."' and a.exam_year='".$exam_year."' and a.exam_month='".$exam_month."' ")->queryAll();
    $table = '';
    $div_table = '';
    $div_table .= '<div class="panel  box box-info"><div class="box-header  with-border" role="tab" ><div class="row"><div class="col-md-10"><h4 class="padding box-title"><a data-toggle="collapse" data-parent="#accordion" href="#collapseOne">Hall Vs '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Information</a></h4></div></div></div><div id="collapseOne" class="panel-collapse collapse in"><div class="box-body">';
    $table .= '<table id="checkAllFeat2" class="table table-responsive table-striped" align="center" ><thead id="t_head"><th>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME).' Code</th><th>Hall Name</th><th>Register Number</th><th>Total</th><th>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).'</th></thead><tbody>';
    $a = 0;
    $stu_count = $total_absents = 0;
    $pgm_count = array();
   
    $old_programme_code='';
    
    foreach($exam_id_list as $exam_id)
    {
      $batch_mapping_id = Yii::$app->db->createCommand("select batch_mapping_id from coe_subjects_mapping a,coe_exam_timetable_int b where a.coe_subjects_mapping_id=b.subject_mapping_id and b.coe_exam_timetable_id='".$exam_id['coe_exam_timetable_id']."' ")->queryScalar();
      
      $programme_code = Yii::$app->db->createCommand("select CONCAT (batch_name,': ',degree_code,'-',programme_code ) from coe_bat_deg_reg a,coe_degree b,coe_programme c,coe_batch d where a.coe_degree_id=b.coe_degree_id and a.coe_programme_id=c.coe_programme_id and d.coe_batch_id=a.coe_batch_id and a.coe_bat_deg_reg_id='".$batch_mapping_id."'")->queryScalar();
      array_push($pgm_count, $programme_code);
      $pgm_count = array_unique($pgm_count);

      $hall_name = Yii::$app->db->createCommand("select distinct(hall_name),b.hall_master_id from coe_hall_master a,coe_hall_allocate_int b where a.coe_hall_master_id=b.hall_master_id and b.exam_timetable_id='".$exam_id['coe_exam_timetable_id']."'")->queryAll();
      $prev_hall_name='';
      
      if(!empty($hall_name))
      {
          $hall_status=0;
          $total_max = 0;
          $table .= '<tr><td>'.$programme_code.'</td>';
          array_push($pgm_count, $programme_code);
        foreach($hall_name as $hall)
        {
            if($prev_hall_name!=$hall['hall_name'])
            {
              $prev_hall_name=$hall['hall_name'];
                if($a!=0)
                {
                    $table .='</tr><tr><td></td>';
                }
                $a++;
                  
                $table .= '<td>'.$hall['hall_name'].'</td>';
                
                $hall_status=1;

              }          
              $hall_reg_num = Yii::$app->db->createCommand("select A.register_number from coe_hall_allocate_int as A JOIN coe_student as B ON B.register_number=A.register_number JOIN coe_student_mapping as C ON C.student_rel_id=B.coe_student_id where hall_master_id='".$hall['hall_master_id']."' and exam_timetable_id='".$exam_id['coe_exam_timetable_id']."' and status_category_type_id!='".$det_disc_type."' order by A.register_number ")->queryAll();
              $reg_count_hall = count($hall_reg_num);
              $cnt_reg=0;
              $abs_count = 0;
              $table .='<td>';

              foreach($hall_reg_num as $reg_hall)
              {         
                $exam_info = ExamTimetableInt::findOne($exam_id['coe_exam_timetable_id']);
                $student_reg = Student::find()->where(['register_number'=>$reg_hall['register_number']])->one();
                $student_map_id_inf = StudentMapping::find()->where(['student_rel_id'=>$student_reg['coe_student_id']])->one();
                $check_absent_stu = AbsentEntryInt::find()->where(['exam_date'=>$exam_info['exam_date'],'exam_month'=>$exam_info['exam_month'],'exam_session'=>$exam_info['exam_session'],'exam_subject_id'=>$exam_info['subject_mapping_id'],'absent_student_reg'=>$student_map_id_inf['coe_student_mapping_id']])->all();
               
                $reg_number = !empty($check_absent_stu)? "<b style='color: #f00;'>".$reg_hall['register_number']."</b>":$reg_hall['register_number'];

                $abs_count = !empty($check_absent_stu)? $abs_count+1:$abs_count ;

                $table .=$reg_number.", ";
                $cnt_reg++;            
                        
              }
              $table .='</td><td>'.$reg_count_hall.'</td><td>'.$abs_count.'</td></tr>';
              $stu_count += $reg_count_hall;
              $total_absents +=$abs_count;
            }
            $table.='</tr>';$old_programme_code = $programme_code;
        }
    }
    $table.='<tr> <td colspan=3> No of Halls :'.count($hl_list).'</td> <td>No of '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' :'.$stu_count.'</td><td> Total '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT).' '.$total_absents.'</td></tr>'; 
    $table.='</tbody></table>';      

    $div_close = '</div></div></div></div>';
    $full_output = $div_table.$table.$div_close;
    if(isset($_SESSION['hallvsstudentabsent']))
    {
      unset($_SESSION['hallvsstudentabsent']);
    }
      $_SESSION['hallvsstudentabsent']=$table;
    

    return $full_output;
  }


  
  public function actionHallvsstudentreport()
  {
    $exam_year = Yii::$app->request->post('exam_year');
    $exam_month = Yii::$app->request->post('exam_month');
    $exam_date =  date("Y-m-d",strtotime($_POST['date']));
    $internal_number = Yii::$app->request->post('internal_number');
    $exam_session = Yii::$app->request->post('session');

     $exam_session = Yii::$app->db->createCommand("select distinct(a.category_type) from coe_category_type a,coe_exam_timetable_int b where a.coe_category_type_id=b.exam_session and b.exam_session='".$exam_session."'")->queryScalar(); 

    $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT');  
    $cia= $array[$internal_number];

    require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
    $exam_id = ExamTimetableInt::find()->where(['exam_date'=>$exam_date,'exam_session'=>$_POST['session'],'exam_month'=>$exam_month,'exam_year'=>$exam_year])->all();
    $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
    // What is the use of below query please explain
    $hl_list = Yii::$app->db->createCommand("select distinct(hall_master_id) from coe_hall_allocate_int a,coe_exam_timetable_int b where a.exam_timetable_id=b.coe_exam_timetable_id and b.exam_date='".$exam_date."' and b.exam_session='".$_POST['session']."' and b.exam_year='".$exam_year."' and b.exam_month='".$exam_month."'")->queryAll();
    
    $exam_id_list = Yii::$app->db->createCommand("select a.coe_exam_timetable_id from coe_exam_timetable_int a,coe_category_type b where a.exam_session=b.coe_category_type_id and a.exam_date='".$exam_date."' and a.exam_session='".$_POST['session']."' and a.exam_year='".$exam_year."' and a.exam_month='".$exam_month."' and a.internal_number='".$internal_number."'")->queryAll();
   
    $head ='<table id="checkAllFeet" border="0" width="100%" class="table table-responsive table-striped" align="center" style="font-family:Roboto, sans-serif;font-size: 12px;margin-bottom: 0px !important;" ><tbody align="center">';                 
    $head.='<tr>
                <td>
                    <img width="100" height="100" src="'.Yii::getAlias("@web").'/images/main_logo.png" alt="College Logo">
               </td> 
               <td colspan=6 align="center">
                   <center><b><font size="4px">'.$org_name.'</font></b></center>
                   <center>'.$org_address.'</center>
                   <center>'.$org_tagline.'</center> 
                </td>
                <td align="center">  
                    <img width="100" height="100" class="img-responsive" src="'.Yii::getAlias("@web").'/images/skacas.png" alt="College Logo 2">
                 </td>
            </tr>';
    $head.='<tr><td style="font-size:15px" colspan="8" align="center"><b>INTERNAL EXAMINATIONS - HALL PLAN</b></td></tr>';

    $head.='<tr><td align="center" colspan="8"><b> Date of Examinations: '.$exam_date.' - '.$exam_session.' &nbsp; Internal Number: '.$cia.'</b> <br>FN: (10:30 AM TO 12:00 PM) AN: (02:30 PM TO 04:00 PM)</td></tr></tbody></table>';
    $div_table = '';
    $div_table .= '<div class="panel  box box-info"><div class="box-header  with-border" role="tab" ><div class="row"><div class="col-md-10"><h4 class="padding box-title"><a data-toggle="collapse" data-parent="#accordion" href="#collapseOne">Hall Vs '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Information</a></h4></div></div></div><div id="collapseOne" class="panel-collapse collapse in"><div class="box-body">';


    $table = '';
    $head .= '<table id="checkAllFeat2" class="table table-responsive table-striped" align="left" width="100%" border="1" style="margin-bottom:0px !important;" >
   
    <tbody> <tr><td style="border-top: 1px solid #1e1c1c;"><b>Hall Name</b></td>
    <td style="border-top: 1px solid #1e1c1c;"><b>Register Number</b></td>
    <td style="border-top: 1px solid #1e1c1c;"><b>Count</b></td></tr>';
    $a = 0;
    $stu_count = 0;
    $pgm_count = array();
   
    $old_programme_code='';
      
    foreach($exam_id_list as $exam_id)
    {
      $batch_mapping_id = Yii::$app->db->createCommand("select batch_mapping_id from coe_subjects_mapping a,coe_exam_timetable_int b where a.coe_subjects_mapping_id=b.subject_mapping_id and b.coe_exam_timetable_id='".$exam_id['coe_exam_timetable_id']."'")->queryScalar();
      
      $programme_code = Yii::$app->db->createCommand("select c.programme_name from coe_bat_deg_reg a,coe_degree b,coe_programme c where a.coe_degree_id=b.coe_degree_id and a.coe_programme_id=c.coe_programme_id and a.coe_bat_deg_reg_id='".$batch_mapping_id."'")->queryScalar();
      array_push($pgm_count, $programme_code);
      $pgm_count = array_unique($pgm_count);
      if($old_programme_code=='')
     {
        $table .= '<tr><td colspan="3" align="center"><b>'.$programme_code.'</b></td></tr><tr>';
     }
     else
    {
        $table .= '<tr><td colspan="3" align="center"><b>'.$programme_code.'</b></td></tr><tr>';
    }
      
      
      $hall_name = Yii::$app->db->createCommand("select distinct(hall_name),b.hall_master_id from coe_hall_master a,coe_hall_allocate_int b where a.coe_hall_master_id=b.hall_master_id and b.exam_timetable_id='".$exam_id['coe_exam_timetable_id']."'")->queryAll();
      $prev_hall_name='';
      $hall_status=0;
      $total_max = 0;

      foreach($hall_name as $hall)
      {
        if($prev_hall_name!=$hall['hall_name'])
        {
          $prev_hall_name=$hall['hall_name'];
          if($a!=0)
          {
              $table .='</tr><tr>';
          }
          $a++;
            
          $table .= '<td>'.$hall['hall_name'].'</td>';
          
          $hall_status=1;

        }          
        $hall_reg_num = Yii::$app->db->createCommand("select A.register_number from coe_hall_allocate_int as A  JOIN coe_student as B ON B.register_number=A.register_number JOIN coe_student_mapping as C ON C.student_rel_id=B.coe_student_id where hall_master_id='".$hall['hall_master_id']."' and exam_timetable_id='".$exam_id['coe_exam_timetable_id']."' and status_category_type_id!='".$det_disc_type."' order by A.register_number")->queryAll();
        $reg_count_hall = count($hall_reg_num);
        $cnt_reg=0;

        $table .='<td>';

        foreach($hall_reg_num as $reg_hall)
        {            
          $table .=$reg_hall['register_number'].", ";
          $cnt_reg++;            
          // if($cnt_reg==$reg_count_hall){
          //   $table.='<td></td>';
          // }else{
          //   $table.='<td></td><td></td>';
          // }            
        }
        $table .='</td><td>'.$reg_count_hall.'</td></tr>';
        $stu_count += $reg_count_hall;
      }
      $table.='</tr>';

      $old_programme_code = $programme_code;
    }
    $table.='</tbody></table>'; 

    $table .= '<table id="checkAllFeat2" class="table table-responsive table-striped" align="left" width="100%" border="1" ><tbody>';
    $table.='<tr><td> No of Degree : '.count($pgm_count).'</td><td>No of Halls :'.count($hl_list).'</td><td>No of Student :'.$stu_count.'</td></tr></tbody></table>'; 
    $table.='</tbody></table>';    
    $full_output = $head.$table;
    
    if(isset($_SESSION['hallvsstudentpdf'])){ unset($_SESSION['hallvsstudentpdf']);}
    $_SESSION['hallvsstudentpdf'] = $full_output;
    

    return $full_output;
  }

   public function actionReprintGalleyArrangement()
    {
        $hallmaster = new HallMaster();
        $model = new HallAllocateInt();
        $categorytype = new Categorytype();
        $exam = new ExamTimetableInt();
        $cia=''; 
        if ($model->load(Yii::$app->request->post()) ) 
        {
          $time_slot = $_POST['ExamTimetableInt']['time_slot']; 
          $exam_session = $_POST['ExamTimetableInt']['exam_session'];
          $exam_date = $_POST['ExamTimetableInt']['exam_date'];
          $exam_month = $_POST['HallAllocateInt']['month'];
          $internal_number = $_POST['HallAllocateInt']['internal_number'];

            $exam_sn = Yii::$app->db->createCommand("select distinct(a.description) from coe_category_type a,coe_exam_timetable_int b where a.coe_category_type_id=b.exam_session and b.exam_session='".$exam_session."'")->queryScalar(); 
              $exam_date =  date("Y-m-d",strtotime($exam_date));

             
              $query_allocate = new Query();
               $query_allocate->select("hall_name,a.register_number,row,row_column,seat_no, c.subject_mapping_id as subject_map_id,coe_student_mapping_id as student_map_id,subject_code,c.exam_date,c.exam_session,c.exam_month,c.exam_year,c.time_slot")
                              ->from('coe_hall_allocate_int a')
                              ->join('JOIN','coe_hall_master b','a.hall_master_id=b.coe_hall_master_id')
                              ->join('JOIN','coe_exam_timetable_int c','c.coe_exam_timetable_id=a.exam_timetable_id')
                              ->join('JOIN','coe_subjects_mapping d','d.coe_subjects_mapping_id=c.subject_mapping_id')
                              ->join('JOIN','coe_subjects sub','sub.coe_subjects_id=d.subject_id')
                              ->join('JOIN','coe_student_mapping e','e.course_batch_mapping_id=d.batch_mapping_id')
                              ->join('JOIN','coe_student f','f.coe_student_id=e.student_rel_id and f.register_number=a.register_number')
                              ->where(['c.exam_date' =>$exam_date,'c.exam_month'=>$exam_month,'c.exam_session'=>$exam_session,'c.time_slot'=>$time_slot,'c.internal_number'=>$internal_number])
                              ->orderBy('hall_name,seat_no');
               // echo $query_allocate->createCommand()->getrawsql(); exit();
              $allocated_value =$query_allocate->createCommand()->queryAll();
              if(empty($allocated_value))
              {
                Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                return $this->redirect(['reprint-galley-arrangement']);
              }

             $time_slot = Yii::$app->db->createCommand("select distinct(a.description) from coe_category_type a,coe_exam_timetable_int b where a.coe_category_type_id=b.time_slot and b.time_slot='".$exam->time_slot."'")->queryScalar();


               $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT');  
            $cia= $array[$internal_number];
              return $this->render('view', [
                'model' => $model,
                'hallmaster' => $hallmaster,
                'categorytype' => $categorytype,
                'exam' => $exam,                
                'exam_date' => $exam_date,
                'exam_session' => $exam_sn,
                'time_slot'=>$time_slot,
                'internal_number'=>$cia,
                'allocated_value' => $allocated_value,
            ]);

        } else {
             Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Re Print of Galley Arrangement');
            return $this->render('reprint-galley-arrangement', [
                'model' => $model,'hallmaster' => $hallmaster,'categorytype' => $categorytype,'exam' => $exam,
            ]);
        }
    }

    public function actionResetGalleyArrangement1()
    {
        $hallmaster = new HallMaster();
        $model = new HallAllocateInt();
        $categorytype = new Categorytype();
        $exam = new ExamTimetableInt();

        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Reset Galley Arrangement');
        return $this->render('reset-galley-arrangement1', [
                'model' => $model,
                'hallmaster' => $hallmaster,
                'categorytype' => $categorytype,
                'exam' => $exam,
            ]);
        
    }

    public function actionGalleyArrangementstudentreport()
    {
        $hallmaster = new HallMaster();
        $model = new HallAllocateInt();
        $categorytype = new Categorytype();
        $exam = new ExamTimetableInt();
        $cia=''; 
        if ($model->load(Yii::$app->request->post()) ) 
        {
          
          $time_slot = $_POST['ExamTimetableInt']['time_slot'];
          $exam_session = $_POST['ExamTimetableInt']['exam_session'];
          $exam_date = $_POST['ExamTimetableInt']['exam_date'];
          $exam_month = $_POST['HallAllocateInt']['month'];
          $internal_number = $_POST['HallAllocateInt']['internal_number'];

            
              $exam_date =  date("Y-m-d",strtotime($exam_date));

              $query_allocate = new Query();
              $query_allocate->select("DISTINCT (e.course_batch_mapping_id), `k`.`degree_code`,`l`.`programme_name`")
                              ->from('coe_hall_allocate_int a')
                              ->join('JOIN','coe_hall_master b','a.hall_master_id=b.coe_hall_master_id')
                              ->join('JOIN','coe_exam_timetable_int c','c.coe_exam_timetable_id=a.exam_timetable_id')
                              ->join('JOIN','coe_subjects_mapping d','d.coe_subjects_mapping_id=c.subject_mapping_id')
                              ->join('JOIN','coe_subjects sub','sub.coe_subjects_id=d.subject_id')
                              ->join('JOIN','coe_student_mapping e','e.course_batch_mapping_id=d.batch_mapping_id')
                              ->join('JOIN','coe_student f','f.coe_student_id=e.student_rel_id and f.register_number=a.register_number')
                              ->join('JOIN','coe_bat_deg_reg as g','g.coe_bat_deg_reg_id=e.course_batch_mapping_id')
                              ->join('JOIN','coe_degree as k','k.coe_degree_id=g.coe_degree_id')
                              ->join('JOIN','coe_programme as l','l.coe_programme_id=g.coe_programme_id')
                              ->where(['c.exam_date' =>$exam_date,'c.exam_month'=>$exam_month,'c.exam_session'=>$exam_session,'c.internal_number'=>$internal_number,'c.time_slot'=>$time_slot]);
              //echo $query_allocate->createCommand()->getrawsql(); exit();
             $allocated_value = $query_allocate->createCommand()->queryAll();
              if(empty($allocated_value))
              {
                Yii::$app->ShowFlashMessages->setMsg('Error','NO DATA FOUND');
                return $this->redirect(['reprint-galley-arrangement']);
              }
               
               $time_slot = Yii::$app->db->createCommand("select distinct(a.description) from coe_category_type a,coe_exam_timetable_int b where a.coe_category_type_id=b.time_slot and b.time_slot='".$time_slot."'")->queryScalar();

              return $this->render('studentview', [
                'model' => $model,
                'hallmaster' => $hallmaster,
                'categorytype' => $categorytype,
                'exam' => $exam,                
                'exam_date' => $exam_date,
                'exam_session' => $exam_session,
                'internal_number'=>$internal_number,
                'time_slot'=>$time_slot,
                'allocated_value' => $allocated_value,
            ]);

        } else {
             Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Galley Arrangement Student Report');
            return $this->render('galley-arrangement_student_report', [
                'model' => $model,'hallmaster' => $hallmaster,'categorytype' => $categorytype,'exam' => $exam,
            ]);
        }
    }

    public function actionFacultyHallArrange()
    {
        $model = new FacultyHallArrangeInt();
        $modelfa = new ValuationFacultyAllocate();

        $model1 = new HallAllocateInt();
        $categorytype = new Categorytype();
        $exam = new ExamTimetableInt();

        if (Yii::$app->request->post()) 
        { 
           
            $month=$_POST['intexam_month'];
            $year=$_POST['intexam_year'];
            $fh_date=$_POST['exam_date'];
            $fh_session=$_POST['intexam_session'];
            $time_slot=$_POST['time_slot'];
            $internal_number=$_POST['internal_number'];
                       
            $namearr = explode("&",trim($_POST['hallName'],"&"));

            $namearr_rhs = explode("&",trim($_POST['hallName_rhs'],"&"));

           
             $faculty_hall_data = Yii::$app->db->createCommand("SELECT hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "' order By C.faculty_board ASC")->queryAll();


             if(!empty($faculty_hall_data))
            {
                if(!empty($faculty_hall_data))    
                {
                        $rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "'")->queryAll();

                        $monthname = Categorytype::findOne($month);

                        $ex_session = Categorytype::findOne($fh_session);
                               
                        
                        $timeslot = Categorytype::findOne($time_slot);
                        $timeslot=$timeslot['category_type'];

                        $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT'); 
                        $cia= $array[$internal_number];
                               
                        $_SESSION['faculty_hall_dataxl'] = $faculty_hall_data;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year.' - '.$cia;
                        $_SESSION['get_examsession'] = 'Exam Date: '.$fh_date.' & '.$ex_session['category_type'].' Time: '.$timeslot;

                        return $this->render('faculty_hall_arrange', [
                                'model' => $model,
                                'modelfa'=> $modelfa,
                                'faculty_hall_data'=>$faculty_hall_data,
                                'rhsdata'=>$rhsdata,
                                'year'=>$year,
                                'month'=>$month,
                                'fh_date'=>$fh_date,
                                'fh_session'=>$fh_session,
                                 ]);
                }    
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                                return $this->redirect(['hall-allocate-int/faculty-hall-arrange']);
                }  
            }
            else
            {                

                $hall_data = Yii::$app->db->createCommand("SELECT DISTINCT hall_master_id FROM coe_hall_allocate_int A JOIN coe_exam_timetable_int B ON B.coe_exam_timetable_id=A.exam_timetable_id WHERE B.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND B.exam_session='" . $fh_session . "' AND B.time_slot='" . $time_slot . "' AND B.internal_number='" . $internal_number . "' ")->queryAll(); 

               shuffle($namearr);

                //echo count($hall_data)."==".count($namearr);exit;
                if(count($hall_data) == count($namearr))
                {
                                    
                    //print_r($hall_data); exit;
                    $r=1; $rhs=0;  $Success=$Error2= $Error1=$rhs_faculty=0;
                    for ($i=0; $i <count($hall_data) ; $i++) 
                    { 
                        $check_inserted = FacultyHallArrangeInt::find()->where(['year'=>$year,'month'=>$month,'hall_master_id'=>$hall_data[$i]['hall_master_id'],'exam_date'=>date('Y-m-d',strtotime($fh_date)),'exam_session'=>$fh_session, 'time_slot'=>$time_slot, 'internal_number'=>$internal_number])->one();


                        if($r==5 && $i==4)
                        {
                          if(count($namearr_rhs)>0)
                          {
                            $rhs_faculty=$namearr_rhs[$rhs];
                          }
                          else
                          {
                            $rhs_faculty=0;
                          }
                            
                            $r=1;
                        }
                        elseif ($r==10 && $i>4) 
                        {
                           $rhs=$rhs+1;
                           if($rhs<count($namearr_rhs) && count($namearr_rhs)>0)
                           {
                                 $rhs_faculty=$namearr_rhs[$rhs];
                           }
                           else
                          {
                            $rhs_faculty=0;
                          }
                          
                           $r=1;
                        }

                        if(empty($check_inserted))
                        {
                            $created_at = date("Y-m-d H:i:s");
                            $updateBy = Yii::$app->user->getId();
                            $model1 = new FacultyHallArrangeInt();
                            $model1->hall_master_id = $hall_data[$i]['hall_master_id'];
                            $model1->year = $year;
                            $model1->month = $month;
                            $model1->exam_date =date('Y-m-d',strtotime($fh_date));
                            $model1->exam_session = $fh_session;
                            $model1->faculty_id = $namearr[$i];
                            $model1->time_slot = $time_slot;
                            if($rhs_faculty>0)
                            {
                              $model1->rhs = $rhs_faculty; 
                            }
                            
                            $model1->internal_number = $internal_number;                   
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
                    
                    $faculty_hall_data = Yii::$app->db->createCommand("SELECT hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name  FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "'")->queryAll();

                    $rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs  WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "'")->queryAll();

                    if(!empty($faculty_hall_data))    
                    {
                        $monthname = Categorytype::findOne($month);

                        $ex_session = Categorytype::findOne($fh_session);

                        $timeslot = Categorytype::findOne($time_slot);
                        $timeslot=$timeslot['category_type'];

                        $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT'); 
                        $cia= $array[$internal_number];
                               
                        $_SESSION['faculty_hall_dataxl'] = $faculty_hall_data;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year.' - '.$cia;
                        $_SESSION['get_examsession'] = 'Exam Date: '.$fh_date.' & '.$ex_session['category_type'].' Time: '.$timeslot;

                        return $this->render('faculty_hall_arrange', [
                                'model' => $model,
                                'modelfa'=> $modelfa,
                                'faculty_hall_data'=>$faculty_hall_data,
                                'rhsdata'=>$rhsdata,
                                'year'=>$year,
                                'month'=>$month,
                                'fh_date'=>$fh_date,
                                'fh_session'=>$fh_session,
                                'timeslot'=>$timeslot,
                                'cia'=>$cia
                                 ]);
                    }    
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                                return $this->redirect(['hall-allocate-int/faculty-hall-arrange']);
                    }  
                  
                }    
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error', "Faculty are not sufficient to assign hall");
                            return $this->redirect(['hall-allocate-int/faculty-hall-arrange']);
                }  

            }
                  
               
        }
        else
        {
             Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation');
            return $this->render('faculty_hall_arrange', [
                'model' => $model,
                'modelfa'=> $modelfa,
                'faculty_hall_data'=>'',
                'model1'=>$model1,
                'exam'=>$exam
            ]);
        }
       
    }

    public function actionQpfacultyhallPdf()
    {
        $content=$_SESSION['faculty_hall_data'];
        
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
         $pdf = new Pdf([                   
                    'mode' => Pdf::MODE_CORE,                 
                    'filename' => 'FacultyHallArrangementInternalExam.pdf',                
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
                        'options' => ['title' => 'Hall Invigilation Internal Exam'],
                        'methods' => [ 
                            'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                            'SetFooter'=>['Hall Invigilation Internal Exam- {PAGENO}'],
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

            $head='Hall Invigilation Internal Exam'.$_SESSION['get_examyear'].' Examinations';

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
        header('Content-Disposition: attachment; filename="Hall Invigilation Internal Exam.xlsx"');
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save('php://output');
    }

     public function actionFacultyHallArrangeUpdate()
    {
        $model = new FacultyHallArrangeInt();
        $modelfa = new ValuationFacultyAllocate();

         $model1 = new HallAllocateInt();
        $categorytype = new Categorytype();
        $exam = new ExamTimetableInt();

        if (Yii::$app->request->post()) 
        { 
           
            $month=$_POST['intexam_month'];
            $year=$_POST['intexam_year'];
            $fh_date=$_POST['exam_date'];
            $fh_session=$_POST['intexam_session'];
            $time_slot=$_POST['time_slot'];
            $internal_number=$_POST['internal_number'];

             $hallmaster = Yii::$app->db->createCommand("SELECT coe_hall_master_id,hall_name FROM coe_hall_master")->queryAll();

            $intfaculty = ValuationFaculty::find()->where(['faculty_mode'=>'INTERNAL'])->orderBy(['coe_val_faculty_id'=>SORT_ASC])->all();

            $extfaculty = ValuationFaculty::find()->where(['faculty_mode'=>'EXTERNAL'])->orderBy(['coe_val_faculty_id'=>SORT_ASC])->all();
             

             $faculty_hall_data = Yii::$app->db->createCommand("SELECT A.faculty_id,fh_arrange_id,hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "'")->queryAll();


             if(!empty($faculty_hall_data))
            {
                if(!empty($faculty_hall_data))    
                {
                        $rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "' AND rhs!=0")->queryAll();

                         $additional_staff = Yii::$app->db->createCommand("SELECT A.faculty_id,fh_arrange_id,hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name, additional_staff,hall_master_id FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "' AND additional_staff=1")->queryAll();

                        $monthname = Categorytype::findOne($month);
                        $ex_session = Categorytype::findOne($fh_session);
                        $timeslot = Categorytype::findOne($time_slot);
                        $timeslot=$timeslot['category_type'];

                        $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT'); 
                        $cia= $array[$internal_number];
                               
                        $_SESSION['faculty_hall_dataxl'] = $faculty_hall_data;
                        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year.' - '.$cia;
                        $_SESSION['get_examsession'] = 'Exam Date: '.$fh_date.' & '.$ex_session['category_type'].' Time: '.$timeslot;

                         $_SESSION['year'] = $year;
                          $_SESSION['month'] = $month;
                           $_SESSION['fh_date'] = $fh_date;
                            $_SESSION['fh_session'] = $fh_session;
                             $_SESSION['time_slot'] = $time_slot;
                              $_SESSION['internal_number'] = $internal_number;

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
                                return $this->redirect(['hall-allocate-int/faculty-hall-arrange-update']);
                }  
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error', "No data Found");
                return $this->redirect(['hall-allocate-int/faculty-hall-arrange-update']);
            }  
                  
               
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Invigilation Update');
            return $this->render('faculty_hall_arrange_update', [
                'model' => $model,
                'modelfa'=> $modelfa,
                'faculty_hall_data'=>'',
                'model1'=>$model1,
                'exam'=>$exam
            ]);
        }
       
    }

    public function actionQpfacultyhallupdatePdf()
    {
        $month=$_SESSION['month'];
        $year=$_SESSION['year'];
        $fh_date=$_SESSION['fh_date'];
        $fh_session=$_SESSION['fh_session'];
        $time_slot=$_SESSION['time_slot'];
        $internal_number=$_SESSION['internal_number'];

        $faculty_hall_data = Yii::$app->db->createCommand("SELECT A.faculty_id,fh_arrange_id,hall_name,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.faculty_id JOIN coe_hall_master D ON D.coe_hall_master_id=A.hall_master_id WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "'")->queryAll();

       $rhsdata = Yii::$app->db->createCommand("SELECT DISTINCT rhs,concat(C.faculty_name,', ',C.faculty_designation,', ',C.faculty_board) as faculty_name FROM coe_faculty_hall_arrange_int A  JOIN coe_valuation_faculty C ON C.coe_val_faculty_id=A.rhs WHERE A.exam_date='" .date("Y-m-d",strtotime($fh_date)) . "' AND A.exam_session='" . $fh_session . "' AND A.time_slot='" . $time_slot . "' AND A.internal_number='" . $internal_number . "'")->queryAll();

        $monthname = Categorytype::findOne($month);

        $ex_session = Categorytype::findOne($fh_session);
               
       $timeslot = Categorytype::findOne($time_slot);
        $timeslot=$timeslot['category_type'];

        $array= array('1'=>'CIA 1','2'=>'CIA 2','3'=>'CIA 3','5'=>'MODEL','4'=>'COMPONENT'); 
        $cia= $array[$internal_number];
        $_SESSION['get_examyear'] = $monthname['category_type'].' - '.$year.' - '.$cia;
        $_SESSION['get_examsession'] = 'Exam Date: '.$fh_date.' & '.$ex_session['category_type'].' Time: '.$timeslot;

        
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
                         <h4> Hall Invigilation Internal Exam - '.$_SESSION['get_examyear'].' Examinations </h4>
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
}
