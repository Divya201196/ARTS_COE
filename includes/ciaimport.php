<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\Programme;

use app\models\MarkEntry;
use app\models\MarkEntryMaster;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;


$interate = 1; // Check only 1 time for Sheet Columns
unset($sheetData[1]);
$transaction = Yii::$app->db->beginTransaction();
$array_month = [0=>'Oct/Nov',1=>'April/May',2=>'Dec',3=>'Mar/Apr',4=>'July/Aug',5=>'Oct',6=>'Jan',7=>'May'];

foreach($sheetData as $k => $line)
{    

    $line = array_map('trim', $line);
    $subjects = new Subjects();
    $MarkEntry = new MarkEntry();
    $MarkEntryMaster = new MarkEntryMaster();
    $subject_mapping = new SubjectsMapping();
    $batch = isset($line['G'])?Batch::findOne(['batch_name'=>$line['G']]):"";
    $programme = isset($line['F'])?Programme::findOne(['programme_code'=>$line['F']]):"";
    $degree = isset($line['A'])?Degree::findOne(['degree_code'=>$line['A']]):"";

    if(!empty($batch) && !empty($programme) && !empty($degree) && !in_array(null, $line, true))
    { 
        
        $batchMapping = CoeBatDegReg::find()->where(['coe_programme_id'=>$programme->coe_programme_id,'coe_batch_id'=>$batch->coe_batch_id,'coe_degree_id'=>$degree->coe_degree_id])->one(); 

        $student_map_id = $this->valueReplace($line['E'], StudentMapping::getStudentId());

        
        $month=  $this->valueReplace($array_month[$line['D']], Categorytype::getCategoryId());
        $term=  $this->valueReplace($line['AG']=='E'?'End':'Supplimentary', Categorytype::getCategoryId());
        $batch_year=$line['G'];         
        $month1= $array_month[$line['D']];

        $app_month = $month1;
        $exam_year=$line['C'];
        $batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;  

        $sem_count = ConfigUtilities::semCaluclation($exam_year,$app_month,$batch_mapping_id);
        $subject_map_id = $this->valueReplace($line['I'], SubjectsMapping::getSubjectMappingId($batchMapping->coe_bat_deg_reg_id,$sem_count));
        $mark_type_get = $sem_count==$line['H']?'Regular':'Arrear';
        $mark_type = $this->valueReplace($mark_type_get, Categorytype::getCategoryId());
        $check_cia_marks = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>46,'year'=>$line['C']])->all();


        if(count($check_cia_marks)>0 && !empty($check_cia_marks))
        {
            $marks_available = 1;
             //$dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'CIA Marks Already Available']);
        }
        else
        {
            $MarkEntry->student_map_id = $student_map_id;
            $MarkEntry->subject_map_id = $subject_map_id;
            $MarkEntry->category_type_id = 46; // Hard Coded for Internal marks
            $MarkEntry->category_type_id_marks = $line['M']=="NULL" || $line['M']==''?0:$line['M']; 
            $MarkEntry->year = $line['C'];
            $MarkEntry->month = $month;
            $MarkEntry->term = $term;
            $MarkEntry->mark_type = $mark_type;
            $MarkEntry->status_id = 1; // For Data Migration only
            $MarkEntry->attendance_percentage = $line['K']=="NULL" || $line['K']==0 ?55:75; // Hard Coded for Data Migration Only
            $MarkEntry->attendance_remarks = $line['K']=="NULL" || $line['K']==0 ?"Absent":"Allowed"; // Hard Coded for Data Migration Only
            $MarkEntry->created_by = $created_by;
            $MarkEntry->updated_by = $created_by;
            $MarkEntry->created_at = $created_at;
            $MarkEntry->updated_at = $created_at; 
            
        } 

        $condition_check = isset($marks_available)?$marks_available==1:$MarkEntry->save(false);
        //echo "correct";exit;
        if($condition_check)
        {               

            if($line['V']==1) // Check if student has moderation 
            {
                $moderation = $line['T']-$line['W'];
                $ModerationMarkEntry = new MarkEntry();
                $ModerationMarkEntry->student_map_id = $student_map_id;
                $ModerationMarkEntry->subject_map_id = $subject_map_id;
                $ModerationMarkEntry->category_type_id = 49; 
                $ModerationMarkEntry->category_type_id_marks =  $line['W']=="NULL" || $line['W']==''?0:$line['W']; 
                $ModerationMarkEntry->year = $line['C'];
                $ModerationMarkEntry->month = $month;
                $ModerationMarkEntry->term = $term;
                $ModerationMarkEntry->mark_type = $mark_type;
                $ModerationMarkEntry->status_id = 1; // For Data Migration only
                $ModerationMarkEntry->created_by = $created_by;
                $ModerationMarkEntry->updated_by = $created_by;
                $ModerationMarkEntry->created_at = $created_at;
                $ModerationMarkEntry->updated_at = $created_at; 
                $ModerationMarkEntry->save(false);


                $ModerationMarkEntry1 = new MarkEntry();
                $ModerationMarkEntry1->student_map_id = $student_map_id;
                $ModerationMarkEntry1->subject_map_id = $subject_map_id;
                $ModerationMarkEntry1->category_type_id = 50; 
                $ModerationMarkEntry1->category_type_id_marks =  $moderation; 
                $ModerationMarkEntry1->year = $line['C'];
                $ModerationMarkEntry1->month = $month;
                $ModerationMarkEntry1->term = $term;
                $ModerationMarkEntry1->mark_type = $mark_type;
                $ModerationMarkEntry1->status_id = 1; // For Data Migration only
                $ModerationMarkEntry1->created_by = $created_by;
                $ModerationMarkEntry1->updated_by = $created_by;
                $ModerationMarkEntry1->created_at = $created_at;
                $ModerationMarkEntry1->updated_at = $created_at; 
                $ModerationMarkEntry1->save(false);
                $inserted_res = 1;
            }
            if($line['X']==1)
            {
                $RevalEntry = new MarkEntry();
                $RevalEntry->student_map_id = $student_map_id;
                $RevalEntry->subject_map_id = $subject_map_id;
                $RevalEntry->category_type_id = 49; 
                $RevalEntry->category_type_id_marks =  $line['Y']=="NULL" || $line['Y']==''?0:$line['Y']; 
                $RevalEntry->year = $line['C'];
                $RevalEntry->month = $month;
                $RevalEntry->term = $term;
                $RevalEntry->mark_type = $mark_type;
                $RevalEntry->status_id = 1; // For Data Migration only
                $RevalEntry->created_by = $created_by;
                $RevalEntry->updated_by = $created_by;
                $RevalEntry->created_at = $created_at;
                $RevalEntry->updated_at = $created_at; 
                $RevalEntry->save(false);

                $RevalEntry1 = new MarkEntry();
                $RevalEntry1->student_map_id = $student_map_id;
                $RevalEntry1->subject_map_id = $subject_map_id;
                $RevalEntry1->category_type_id = 62; 
                $RevalEntry1->category_type_id_marks =  $line['Z']; 
                $RevalEntry1->year = $line['C'];
                $RevalEntry1->month = $month;
                $RevalEntry1->term = $term;
                $RevalEntry1->mark_type = $mark_type;
                $RevalEntry1->status_id = 1; // For Data Migration only
                $RevalEntry1->created_by = $created_by;
                $RevalEntry1->updated_by = $created_by;
                $RevalEntry1->created_at = $created_at;
                $RevalEntry1->updated_at = $created_at; 
                $RevalEntry1->save(false);
                $inserted_res = 1;
            }
            else
            {
                $MarkEntry1 = new MarkEntry();                 
                $MarkEntry1->student_map_id = $student_map_id;
                $MarkEntry1->subject_map_id = $subject_map_id;
                $MarkEntry1->category_type_id = 49; // Hard Coded for ESE marks                
                $MarkEntry1->category_type_id_marks =  $line['T']=="NULL" || $line['T']==''?0:$line['T']; 
                $MarkEntry1->year = $line['C'];
                $MarkEntry1->month = $month;
                $MarkEntry1->term = $term;
                $MarkEntry1->mark_type = $mark_type;
                $MarkEntry1->status_id = 1; // For Data Migration only
                $MarkEntry1->created_by = $created_by;
                $MarkEntry1->updated_by = $created_by;
                $MarkEntry1->created_at = $created_at;
                $MarkEntry1->updated_at = $created_at; 
                $MarkEntry1->save(false);
                $inserted_res = 1;
            }
            
            if($inserted_res == 1)
            {
                $year_of_passing_change = $line['AI']==0 && $line['AA']=="1" ? $month.'-'.$line['C']: "";
                $exam_attempt = Yii::$app->db->createCommand('select max(attempt) as attempt from coe_mark_entry_master where student_map_id ="'.$student_map_id.'" and subject_map_id ="'.$subject_map_id.'" ')->queryScalar();
                $MarkEntryMaster->student_map_id = $student_map_id;
                $MarkEntryMaster->subject_map_id = $subject_map_id;

                $MarkEntryMaster->CIA = $line['M']=="NULL" || $line['M']==''?0:$line['M'];
                $MarkEntryMaster->ESE = $line['T']=="NULL" || $line['T']==''?0:$line['T'];
                $MarkEntryMaster->total = $line['U']=="NULL" || $line['U']==''?0:$line['U'];
                $MarkEntryMaster->result = $line['AA']=="1"?'Pass':'Fail'; // For Data Migration
                $MarkEntryMaster->grade_point = $line['AD']=="NULL" || $line['AD']==''?0:$line['AD'];
                $MarkEntryMaster->grade_name = $line['AC']=="NULL" || $line['AC']==''?0:$line['AC'];
                $MarkEntryMaster->year = $line['C'];
                $MarkEntryMaster->month = $month; 
                $MarkEntryMaster->term = $term;
                $MarkEntryMaster->mark_type = $mark_type;
                $MarkEntryMaster->status_id = 1; // For Data Migration only
                $MarkEntryMaster->year_of_passing = $year_of_passing_change; 
                $MarkEntryMaster->attempt = $exam_attempt==0 || empty($exam_attempt) ?0:$exam_attempt+1;
                $MarkEntryMaster->withheld = $line['AI']==0?'':'w'; 
                $MarkEntryMaster->created_by = $created_by;
                $MarkEntryMaster->updated_by = $created_by;
                $MarkEntryMaster->created_at = $created_at;
                $MarkEntryMaster->updated_at = $created_at;  
                
                if($MarkEntryMaster->save())
                { 
                    
                    $totalSuccess+=1;
                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                }
                else
                { 
                    
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Nothing Imported']);
                } 
            }
            else
            {
               
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Imported']);
            }
                                 
        }
        else
        {
            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable to Import the File the Marks']);
        }
           
       
        
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng']);
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
      $transaction->rollback(); 
       $message = "Something Wrong";
   }
   $dispResults[] = array_merge($line, ['type' => 'E',  'message' => $message]);
}


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Marks'];
?>