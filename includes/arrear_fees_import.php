<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\AbsentEntry;
use app\models\MarkEntryMaster;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Subjects;
use app\models\FeesPaid;
use app\models\StuInfo;
use app\models\SubjectsMapping;
use app\models\Programme;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{    
     $subject_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'REGISTER NUMBER','D'=>'SUBJECT CODE','E'=>'FEES STATUS'];

      $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E']];

        $mis_match=array_diff_assoc($subject_columns,$template_clumns);

        if($interate==1 && count($mis_match)!=0 && count($mis_match)>0)
        {
            $misMatchingColumns = '';
            foreach ($mis_match as $key => $value) {
                $misMatchingColumns .= $key.", ";
            }
            
            $misMatchingColumns = trim($misMatchingColumns,', ');
            $misMatchingColumns = wordwrap($misMatchingColumns, 40, "<br />\n");
            Yii::$app->ShowFlashMessages->setMsg('Error',"<b style='font-size: 17px;'> Column (s) ".$misMatchingColumns." Are Not matching with our Original ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_NOMINAL)." Template </b> Please use the Original Sample Template from the Download Link!!");
            return Yii::$app->response->redirect(Url::to(['import/index']));
        }
        else
        {
            break;
        }
    $interate +=5;        
}

unset($sheetData[1]);

$det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
$transaction = Yii::$app->db->beginTransaction();
foreach($sheetData as $k => $line)
{    

    $subject_mapping_id = $stu_map_id = $reg_dum_num = '';  $enter_inside =0;
    $line = array_map('trim', $line);
    $stu_data = isset($line['C'])?Student::find()->where(['register_number'=>$line['C']])->one():"";
    $stu_map_id = '';
    if(!empty($stu_data))
    {   
        $stu_map_id_che = Yii::$app->db->createCommand('SELECT * FROM coe_student_mapping WHERE status_category_type_id NOT IN ("'.$det_disc_type.'") and student_rel_id="'.$stu_data['coe_student_id'].'" ')->queryOne();
        if(!empty($stu_map_id_che))
        {
          $stu_map_id = StudentMapping::find()->where(['student_rel_id'=>$stu_data['coe_student_id']])->one();
        }
    }
    $subject_mapping_id = array_filter([]);
    $subject = isset($line['D'])?Subjects::find()->where(['subject_code'=>$line['D']])->one():"";  
   
    if(!empty($subject) && !empty($stu_map_id))
    {
        $student_map_id = $stu_map_id['coe_student_mapping_id'];
        if(!empty($line['B']))
        {
            $month_id_get = $this->valueReplace($line['B'], Categorytype::getCategoryId());
        }
          $subject_mapping_id = Yii::$app->db->createCommand('SELECT * FROM coe_subjects_mapping as A JOIN coe_subjects as B ON B.coe_subjects_id=A.subject_id WHERE subject_code="'.$subject['subject_code'].'" and batch_mapping_id ="'.$stu_map_id['course_batch_mapping_id'].'" ')->queryOne();
           
          if(!empty($subject_mapping_id))
          {
           
            $subject_map_id = $subject_mapping_id['coe_subjects_mapping_id'];
            $check_cia_marks_master = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id])->one();
            if(!empty($check_cia_marks_master))
            {
               $check_pass = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'result'=>'Pass'])->one();

                if(!empty($check_pass))
                {
                    $status ='Pass';   
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Student Already Pass']);
                }
                else
                {
                  $enter_inside =1;
                }
            }
            else
            {
              $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'No Data Found']);
            }

          }
          else
          {
            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'No Data Found']);
          }
        
    }
    else
    {
      $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'No Data Found']);
    }
    if($enter_inside ==1 && !empty($subject_mapping_id) &&  !empty($stu_map_id)  &&  !empty($line['A']) && !in_array(null, $line, true))
    {     
            $month = $this->valueReplace($line['B'], Categorytype::getCategoryId());
            $year = $line['A'];
            $checkInserted = FeesPaid::find()->where(['year'=>$year,'month'=>$month,'student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id])->all();

            $updated_at = ConfigUtilities::getCreatedTime();
            $updated_by = ConfigUtilities::getCreatedUser();

            if(empty($checkInserted))
            {
                $feespaid =new FeesPaid();
                $paid_status = $line['E']=='YES'?'YES':'NO';
                $feespaid->student_map_id =$student_map_id;
                $feespaid->subject_map_id  =$subject_map_id;
                $feespaid->year =$year;
                $feespaid->month =$month;
                $feespaid->is_imported ='YES';
                $feespaid->status= $paid_status;
                $feespaid->created_by = $updated_by;
                $feespaid->created_at = $updated_at;
                $feespaid->updated_by = $updated_by;
                $feespaid->updated_at = $updated_at;
                $feespaid->save(false);
                unset($feespaid);
                 
            }
            else
            {
              $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Imported']);
            }
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'No Data Found']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
    }           
} // Foreach Ends Here  

try
{
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
       $message = "Error";
   }
   $transaction->rollback(); 
   $dispResults[] = array_merge($line, ['type' => 'E',  'message' => $message]);
}

$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'fees_pay'];
?>