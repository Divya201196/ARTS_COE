<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Categorytype;
use app\models\AbsentEntry;
use app\models\Degree;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\Programme;
use app\models\Revaluation;

use app\models\MarkEntry;
use app\models\MarkEntryMaster;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;


/*ALTER TABLE `SKCET_DATAMIGRATION`.`coe_mark_entry` 
DROP INDEX `student_map_id1` ,
ADD UNIQUE INDEX `student_map_id1` (`student_map_id` ASC, `subject_map_id` ASC, `category_type_id` ASC, `month` ASC, `year` ASC, `mark_type` ASC, `category_type_id_marks` ASC);
*/

$interate = 1; // Check only 1 time for Sheet Columns
unset($sheetData[1]);
$transaction = Yii::$app->db->beginTransaction();
$array_month = [0=>'Oct/Nov',1=>'April/May',2=>'Dec',3=>'Mar',4=>'July',5=>'Oct',6=>'Jan',7=>'May'];

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
        $inserted_res = 0;
        $batchMapping = CoeBatDegReg::find()->where(['coe_programme_id'=>$programme->coe_programme_id,'coe_batch_id'=>$batch->coe_batch_id,'coe_degree_id'=>$degree->coe_degree_id])->one(); 

        $student_map_id = $this->valueReplace($line['E'], StudentMapping::getStudentId());

        $subject_map_id = $this->valueReplace($line['I'], SubjectsMapping::getSubjectMappingId($batchMapping->coe_bat_deg_reg_id));
        $month=  $this->valueReplace($array_month[$line['D']], Categorytype::getCategoryId());
        $term=  $this->valueReplace($line['AG']=='E'?'End':'Supplimentary', Categorytype::getCategoryId());
		$cia_entry=  Categorytype::find()->where(['description'=>'CIA'])->one();
        $batch_year=$line['G'];         
        $month1= $array_month[$line['D']];

        $app_month = $month1;
        $exam_year=$line['C'];
        $batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;  

        $sem_count = ConfigUtilities::semCaluclation($exam_year,$app_month,$batch_mapping_id);

        $mark_type_get = $sem_count==$line['H']?'Regular':'Arrear';
        $mark_type = $this->valueReplace($mark_type_get, Categorytype::getCategoryId());
        $check_cia_marks = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>46])->all();

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
            $MarkEntry->status_id = 0; // For Data Migration only
            $MarkEntry->attendance_percentage = $line['K']=="NULL" || $line['K']==0 ?55:75; // Hard Coded for Data Migration Only
            $MarkEntry->attendance_remarks = $line['K']=="NULL" || $line['K']==0 ?"Absent":"Allowed"; // Hard Coded for Data Migration Only
            $MarkEntry->created_by = $created_by;
            $MarkEntry->updated_by = $created_by;
            $MarkEntry->created_at = $created_at;
            $MarkEntry->updated_at = $created_at; 
            
        } 

        if($line['K']==0)
        {
        	$query_insert = 'INSERT INTO coe_absent_entry (`absent_student_reg`,`exam_type`,`absent_term`,`exam_subject_id`,`exam_absent_status`,`exam_date`,`exam_session`,`exam_month`,`created_by`,`created_at`,`updated_by`,`updated_at`) VALUES ("'.$student_map_id.'","'.$mark_type.'","'.$term.'","'.$subject_map_id.'","43","'.DATE('Y-m-d').'","90","'.$month.'","'.$created_by.'","'.$created_at.'","'.$created_by.'","'.$created_at.'")';
        }

        $condition_check = isset($marks_available)?$marks_available==1:$MarkEntry->save(false);
        //echo "correct";exit;
		$ese_saved =0;
        if($condition_check)
        {               
            if($line['V']==1) // Check if student has moderation 
            {
				$marks_converted = 0;
				/*if($line['S']==0 || $line['T']==0 || $line['S']=="" || $line['T']=="" || $line['S']== "NULL" || $line['T']=="NULL")
				{
					$marks_converted = 0;
				}*/
				if($line['S']==0 && $line['T']==0)
				{
					$marks_converted = 0;
				}
				else if($line['S']!=0 && $line['T']==0)
				{
					$marks_converted = $line['S'];
				}
				//else if($line['S']==$line['T'] || ($line['S']=='' && $line['T']!="") || ($line['S']=='NULL' && $line['T']!="") && ($line['S']==0 && $line['T']!=""))
				else
				{
					$get_sub_id = SubjectsMapping::findOne($subject_map_id);
					$subject_get_details = Subjects::findOne($get_sub_id->subject_id);
					$ese_max = $subject_get_details->ESE_max=="NULL" || $subject_get_details->ESE_max=="0" ? 1 : $subject_get_details->ESE_max;
					$mark_100 = ($line['T'] / $ese_max);
					$marks_converted = round($mark_100*100);
				}
				
                $moderation = $line['T']-$line['W'];
                $ModerationMarkEntry = new MarkEntry();
                $ModerationMarkEntry->student_map_id = $student_map_id;
                $ModerationMarkEntry->subject_map_id = $subject_map_id;
                $ModerationMarkEntry->category_type_id = 49; 
                $ModerationMarkEntry->category_type_id_marks =  $marks_converted; 
                $ModerationMarkEntry->year = $line['C'];
                $ModerationMarkEntry->month = $month;
                $ModerationMarkEntry->term = $term;
                $ModerationMarkEntry->mark_type = $mark_type;
                $ModerationMarkEntry->status_id = 0; // For Data Migration only
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
                $ModerationMarkEntry1->status_id = 0; // For Data Migration only
                $ModerationMarkEntry1->created_by = $created_by;
                $ModerationMarkEntry1->updated_by = $created_by;
                $ModerationMarkEntry1->created_at = $created_at;
                $ModerationMarkEntry1->updated_at = $created_at; 
                $ModerationMarkEntry1->save(false);
                $inserted_res = 1;
				$ese_saved = 1;
            }
            if($line['X']==1) // Check if student has Revaluation 
            {
				if($ese_saved==0)
				{
					$marks_converted = 0;
					/*if($line['S']==0 || $line['T']==0 || $line['S']== "" || $line['T']=="" || $line['S']=="NULL" || $line['T']=="NULL")
					{
						$marks_converted = 0;
					}*/
					if($line['S']==0 && $line['T']==0)
					{
						$marks_converted = 0;
					}
					else if($line['S']!=0 && $line['T']==0)
					{
						$marks_converted = $line['S'];
					}
					//else if($line['S']==$line['T'] || ($line['S']=='' && $line['T']!="") || ($line['S']=='NULL' && $line['T']!="") && ($line['S']==0 && $line['T']!=""))
					else
					{
						$get_sub_id = SubjectsMapping::findOne($subject_map_id);
						$subject_get_details = Subjects::findOne($get_sub_id->subject_id);
						$ese_max = $subject_get_details->ESE_max=="NULL" || $subject_get_details->ESE_max=="0" ? 1 : $subject_get_details->ESE_max;
						$mark_100 = ($line['T'] / $ese_max);
						$marks_converted = round($mark_100*100);
					}
					
					$check_mark_entry_st = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>49,'year'=>$line['C'],'mark_type'=>$mark_type,'term'=>$term])->all();
					if(!empty($check_mark_entry_st) && count($check_mark_entry_st)>0)
					{
						
					}
					else
					{
						$RevalEntry = new MarkEntry();
						$RevalEntry->student_map_id = $student_map_id;
						$RevalEntry->subject_map_id = $subject_map_id;
						$RevalEntry->category_type_id = 49; 
						$RevalEntry->category_type_id_marks =  $marks_converted; 
						$RevalEntry->year = $line['C'];
						$RevalEntry->month = $month;
						$RevalEntry->term = $term;
						$RevalEntry->mark_type = $mark_type;
						$RevalEntry->status_id = 0; // For Data Migration only
						$RevalEntry->created_by = $created_by;
						$RevalEntry->updated_by = $created_by;
						$RevalEntry->created_at = $created_at;
						$RevalEntry->updated_at = $created_at; 
						$RevalEntry->save(false);
					}
					
				}

                $RevalEntry1 = new MarkEntry();
                $RevalEntry1->student_map_id = $student_map_id;
                $RevalEntry1->subject_map_id = $subject_map_id;
                $RevalEntry1->category_type_id = 62; 
                $RevalEntry1->category_type_id_marks =  $line['Z']; 
                $RevalEntry1->year = $line['C'];
                $RevalEntry1->month = $month;
                $RevalEntry1->term = $term;
                $RevalEntry1->mark_type = $mark_type;
                $RevalEntry1->status_id = 0; // For Data Migration only
                $RevalEntry1->created_by = $created_by;
                $RevalEntry1->updated_by = $created_by;
                $RevalEntry1->created_at = $created_at;
                $RevalEntry1->updated_at = $created_at; 
                $RevalEntry1->save(false);
                $inserted_res = 1;
            }
			
            if($inserted_res == 0)
            {
				$marks_converted = 0;
				//if($line['S']==0 || $line['T']==0 || $line['S']=="" || $line['T']=="" || $line['S']=="NULL" || $line['T']=="NULL")
				if($line['S']==0 && $line['T']==0)
				{
					$marks_converted = 0;
				}
				else if($line['S']!=0 && $line['T']==0)
				{
					$marks_converted = $line['S'];
				}
				//else if($line['S']==$line['T'] || ($line['S']=='' && $line['T']!="") || ($line['S']=='NULL' && $line['T']!="") && ($line['S']==0 && $line['T']!=""))
				else
				{
					$get_sub_id = SubjectsMapping::findOne($subject_map_id);
					$subject_get_details = Subjects::findOne($get_sub_id->subject_id);
					$ese_max = $subject_get_details->ESE_max=="NULL" || $subject_get_details->ESE_max=="0" ? 1 : $subject_get_details->ESE_max;
					$mark_100 = ($line['T'] / $ese_max);
					$marks_converted = round($mark_100*100);
				}			
			    $check_attempt = Yii::$app->db->createCommand("select count(*) from coe_mark_entry_master where subject_map_id = '".$subject_map_id."' and student_map_id='".$student_map_id."' and result not like '%Pass%' ")->queryScalar();
				$config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
				if ($check_attempt >=$config_attempt) {
					$marks_converted = $line['S'];
				} else {
					$marks_converted = $marks_converted;
				}
                $MarkEntry1 = new MarkEntry();                 
                $MarkEntry1->student_map_id = $student_map_id;
                $MarkEntry1->subject_map_id = $subject_map_id;
                $MarkEntry1->category_type_id = 49; // Hard Coded for ESE marks                
                $MarkEntry1->category_type_id_marks =  $marks_converted; 
                $MarkEntry1->year = $line['C'];
                $MarkEntry1->month = $month;
                $MarkEntry1->term = $term;
                $MarkEntry1->mark_type = $mark_type;
                $MarkEntry1->status_id = 0; // For Data Migration only
                $MarkEntry1->created_by = $created_by;
                $MarkEntry1->updated_by = $created_by;
                $MarkEntry1->created_at = $created_at;
                $MarkEntry1->updated_at = $created_at; 
                $MarkEntry1->save(false);
                $inserted_res = 1;
            }
            
            if($inserted_res == 1)
            {
				$grade_point = $line['AD']=="NULL" || $line['AD']=='' || $line['AC']=='RA' ? 0 : $line['AD'];
				$year_of_passing_change = $line['AI']==0 && $line['AA']=="1" ? $month.'-'.$line['C']: "";
				$check_mark_entry = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$line['C'],'month'=>$month])->all();
				if(!empty($check_mark_entry) && count($check_mark_entry)>0)
				{
					$cia = $line['M']=="NULL" || $line['M']==''?0:$line['M'];					
					$result = $line['AA']=="1"?'Pass':'Fail';
					if($line['X']==1)
					{
						$ese = $line['Y'] > $line['Z'] ? $line['Y'] : $line['Z'];
					}
					else
					{
						$ese = $line['T']=="NULL" || $line['T']==''?0:$line['T'];
					}
					$total = $cia+$ese;					
					$grade_name = $line['AC']=="NULL" || $line['AC']==''?0:$line['AC'];					
					$update_query = 'update coe_mark_entry_master set ESE="'.$ese.'",CIA="'.$cia.'",total="'.$total.'",result="'.$result.'",
					grade_point="'.$grade_point.'",grade_name="'.$grade_name.'",year_of_passing="'.$year_of_passing_change.'" where student_map_id="'.$student_map_id.'" and subject_map_id="'.$subject_map_id.'" and mark_type="'.$mark_type.'" and term="'.$term.'" and year="'.$line['C'].'" and month="'.$month.'"';
					$update_res = Yii::$app->db->createCommand($update_query)->execute();
					if($update_res)
					{ 
						$totalSuccess+=1;
						$dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Updated Successfully!!']);
					}
					else
					{ 
						
						$dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Something went Wrong']);
					} 
				}
				else
				{
					
					if($line['K']=='0')
					{
						$MarkEntryMaster->student_map_id = $student_map_id;
						$MarkEntryMaster->subject_map_id = $subject_map_id;

						$check_attempt_1 = Yii::$app->db->createCommand("select count(*) from coe_mark_entry_master where subject_map_id = '".$subject_map_id."' and student_map_id='".$student_map_id."' and result not like '%Pass%' ")->queryScalar();
						$config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
						if ($check_attempt_1 >= $config_attempt) {
							$cia_marks = 0;
							$MarkEntryMaster->CIA = 0;
						} else {
							$cia_marks = $line['M']=="NULL" || $line['M']==''?0:$line['M'];
							$MarkEntryMaster->CIA = $line['M']=="NULL" || $line['M']==''?0:$line['M'];
						}
						$attempt = isset($check_attempt_1) && $check_attempt_1!="" ? ($check_attempt_1+1) : 0;
						$MarkEntryMaster->ESE = 0;
						$MarkEntryMaster->total = $cia_marks;
						$MarkEntryMaster->result = 'Absent'; // For Data Migration
						$MarkEntryMaster->grade_point =0;
						$MarkEntryMaster->grade_name = 'AB';
						$MarkEntryMaster->year = $line['C'];
						$MarkEntryMaster->month = $month; 
						$MarkEntryMaster->term = $term;
						$MarkEntryMaster->mark_type = $mark_type;
						$MarkEntryMaster->status_id = 0; // For Data Migration only
						$MarkEntryMaster->year_of_passing = ''; 
						$MarkEntryMaster->attempt = $attempt;
						$MarkEntryMaster->withheld = ''; 
						$MarkEntryMaster->created_by = $created_by;
						$MarkEntryMaster->updated_by = $created_by;
						$MarkEntryMaster->created_at = $created_at;
						$MarkEntryMaster->updated_at = $created_at;
					}
					else {
				    
					$MarkEntryMaster->student_map_id = $student_map_id;
					$MarkEntryMaster->subject_map_id = $subject_map_id;
					
					$check_attempt_1 = Yii::$app->db->createCommand("select count(*) from coe_mark_entry_master where subject_map_id = '".$subject_map_id."' and student_map_id='".$student_map_id."' and result not like '%Pass%' ")->queryScalar();
					$config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
					if ($check_attempt_1 >= $config_attempt) {
						$cia_marks = 0;
						$MarkEntryMaster->CIA = 0;
					} else {
						$cia_marks = $line['M']=="NULL" || $line['M']==''?0:$line['M'];
						$MarkEntryMaster->CIA = $line['M']=="NULL" || $line['M']==''?0:$line['M'];
					}

					$convert_ese_marks =  $line['T']=="NULL" || $line['T']==''?0:$line['T'];
					  $ese_marks = $line['S']=="NULL" || $line['S']=='' ? 0 : $line['S'];
					  
					  $total_marks = $cia_marks+$convert_ese_marks;

					  $check_attempt_reg = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="'.$subject_map_id.'" AND student_map_id="'.$student_map_id.'" AND result not like "%Pass%"')->queryScalar();

					  if(!empty($check_attempt_reg)) 
					  {
						$check_attempt_wd = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="'.$subject_map_id.'" AND student_map_id="'.$student_map_id.'" AND result not like "%pass%" and grade_name like "WD%" ')->queryScalar();
					  }
					     
					  $check_attempt = isset($check_attempt_wd) && !empty($check_attempt_wd) ? 0 : $check_attempt_reg;
					  $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
					  $attempt = isset($check_attempt) && $check_attempt!="" ? ($check_attempt+1) : 0;
					  
					  if($attempt>$config_attempt) // For SKCT
					  {      
							$total_marks = $ese_marks;
							$convert_ese_marks =  $line['T'];
					  }
					
					$MarkEntryMaster->ESE = $convert_ese_marks;
					$MarkEntryMaster->total = $total_marks;
					$MarkEntryMaster->result = $line['AA']=="1"?'Pass':'Fail'; // For Data Migration
					$MarkEntryMaster->grade_point = $grade_point;
					$MarkEntryMaster->grade_name = $line['AC']=="NULL" || $line['AC']==''?0:$line['AC'];
					$MarkEntryMaster->year = $line['C'];
					$MarkEntryMaster->month = $month; 
					$MarkEntryMaster->term = $term;
					$MarkEntryMaster->mark_type = $mark_type;
					$MarkEntryMaster->status_id = 0; // For Data Migration only
					$MarkEntryMaster->year_of_passing = $year_of_passing_change; 
					$MarkEntryMaster->attempt = $attempt;
					$MarkEntryMaster->withheld = $line['AI']==0?'':'w'; 
					$MarkEntryMaster->created_by = $created_by;
					$MarkEntryMaster->updated_by = $created_by;
					$MarkEntryMaster->created_at = $created_at;
					$MarkEntryMaster->updated_at = $created_at;  
					

					}
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