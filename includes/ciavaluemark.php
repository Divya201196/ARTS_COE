<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\CoeValueSubjects;
use app\models\Sub;
use app\models\Programme;
use yii\db\Query;
use app\models\InternalMarkEntry;
use app\models\CoeValueMarkEntry;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    $exam_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'MARK TYPE','D'=>'TERM','E'=>'SUBJECT CODE','F'=>'REGISTER NUMBER','G'=>'CIA (OUT OF MAXIMUM)'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G']];



    $mis_match=array_diff_assoc($exam_columns,$template_clumns);
    if($interate==1 && count($mis_match)!=0 && count($mis_match)>0)
    {
        $misMatchingColumns = '';
        foreach ($mis_match as $key => $value) {
            $misMatchingColumns .= $key.", ";
        }
        $misMatchingColumns = trim($misMatchingColumns,', ');
        $misMatchingColumns = wordwrap($misMatchingColumns, 10, "<br />\n");
         Yii::$app->ShowFlashMessages->setMsg('Error',"<b style='font-size: 17px;'> Column (s) ".$misMatchingColumns." Are Not matching with our Original ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." Template </b> Please use the Original Sample Template from the Download Link!!");
        return Yii::$app->response->redirect(Url::to(['import/index']));
    }
    else
    {
        break;
    }
    $interate +=7;
    
}

unset($sheetData[1]);
$transaction = Yii::$app->db->beginTransaction();
$det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

$det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
foreach($sheetData as $k => $line)
{    

    $line = array_map('trim', $line);
    $subjects = new CoeValueSubjects();

    $MarkEntry = new InternalMarkEntry();
    $MarkEntryMaster = new CoeValueMarkEntry();
    $subject_mapping = new Sub();
	$subject_map_id = 0;
    $year = isset($line['A'])?$line['A']:""; 
    $month = isset($line['B'])?$this->valueReplace($line['B'], Categorytype::getCategoryId()):"";
    $mark_type = isset($line['C'])?$this->valueReplace($line['C'], Categorytype::getCategoryId()):"";
    $term = isset($line['D'])?$this->valueReplace($line['D'], Categorytype::getCategoryId()):"";
    $subject_code = isset($line['E'])?CoeValueSubjects::find()->where(['subject_code'=>$line['E']])->all():"";
    $reg_num_12 = isset($line['F'])?Student::find()->where(['register_number'=>$line['F']])->one():"";
    $reg_num_stu = '';
    if(!empty($reg_num_12))
    {
        $stu_map_id_che = Yii::$app->db->createCommand('SELECT * FROM coe_student_mapping WHERE status_category_type_id NOT IN ("'.$det_disc_type.'","'.$det_cat_type.'") and student_rel_id="'.$reg_num_12->coe_student_id.'" ')->queryOne();
        if(!empty($stu_map_id_che))
        {
          $reg_num_stu = StudentMapping::find()->where(['student_rel_id'=>$reg_num_12['coe_student_id']])->one();
          $reg_num = $line['F'];
        }
    }

    if(!empty($year) && !empty($month) && !empty($mark_type) && !empty($reg_num_stu) && !empty($subject_code) && !in_array(null, $line, true))
    {      
        $inserted_res = 0;
        $student = Student::find()->where(['register_number'=>$reg_num])->one();
        $stu_mapping = StudentMapping::find()->where(['student_rel_id'=>$student->coe_student_id])->one();
        $batchMapping = CoeBatDegReg::findOne($stu_mapping->course_batch_mapping_id);
        $student_map_id = $stu_mapping->coe_student_mapping_id;

        $sem_verify = ConfigUtilities::SemCaluclation($year,$month,$stu_mapping->course_batch_mapping_id);

        if($sem_verify==0)
        {

        }
        else
        {
            if(count($subject_code>0))
            {
                foreach ($subject_code as $sub_map) 
                {
                   $sub_map_id_get = Sub::find()->where(['val_subject_id'=>$sub_map['coe_val_sub_id'],'batch_mapping_id'=>$stu_mapping->course_batch_mapping_id,'semester'=>$sem_verify])->one();
                    if(!empty($sub_map_id_get))
                    {
                        $subject_map_id = $sub_map_id_get['coe_sub_mapping_id'];
                        break;
                    }
                }
                
            }
            else
            {
                $subject_map_id = $sub_map_id_get['coe_sub_mapping_id'];
                        break;
            }
            $get_sub_info = Yii::$app->db->createCommand('SELECT A.* FROM coe_value_subjects as A JOIN sub as B ON B.val_subject_id=A.coe_val_sub_id WHERE coe_sub_mapping_id="'.$subject_map_id.'" ')->queryOne();

            $cia_cat_id = Categorytype::find()->where(['description'=>'CIA'])->orWhere(['category_type'=>'CIA'])->one();
            $internal_id = $cia_cat_id->coe_category_type_id;

            $check_cia_marks = InternalMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$internal_id])->one();

            if(count($check_cia_marks)>0 && !empty($check_cia_marks))
            {
                $marks_available = 0;
            }          
            
            $condition_check = isset($marks_available)?$marks_available==0:1;
            if($condition_check)
            {         
                $subs_id = Sub::findOne($subject_map_id);
                 //print_r($subs_id  );exit;
                $sub_cat_type = Categorytype::findOne($subs_id->subject_type_id);
                $nominal_available = 'NO';
                
                if( $sub_cat_type->description==="Elective" )
                {
                    $stu_tab_id = StudentMapping::findOne($student_map_id);
                    $check_nominal_query = new Query();
                    $check_nominal_query->select('*')
                    ->from('coe_value_nominal')                                
                    ->where(["coe_student_id"=>$stu_tab_id->student_rel_id,'coe_subjects_id'=>$subs_id->val_subject_id]);
                    $check_nominal = $check_nominal_query->createCommand()->queryAll();

                    $nominal_available =count($check_nominal)>0? 'YES':'NO';


                }
              
                $cia_marks_condition = $line['G']==0?0:$line['G'];
                $get_sub_max = $subject_details = CoeValueSubjects::findOne($subs_id->val_subject_id); 
                
                if((!empty($cia_marks_condition) || $cia_marks_condition==0  ) && $cia_marks_condition<=$get_sub_max->CIA_max) // Check if column is not null
                {
                    $ciaMArks = $line['G']<=0 ? '0': $line['G'];
                    $stu_result_data = ConfigUtilities::StudentResult($student_map_id,$subject_map_id , $ciaMArks,0,$year,$month );
                     // print_r($stu_result_data);exit;
                
                 
                    $check_mark_entry = CoeValueMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$year,'month'=>$month])->all();

                    if($nominal_available == 'NO')
                    {
                        $status =1;
                    }
                    else if( $sub_cat_type->description ==="Elective" && $nominal_available == 'YES' && !empty($ciaMArks))
                    {
                        $status =1;
                    }
                    else
                    {
                        $status =0;
                    }
                    if(empty($check_mark_entry) && $get_sub_info['ESE_max']==0 && $get_sub_info['ESE_min']==0)
                    {   

                  //  $stu_result_data = ConfigUtilities::StudentResult($student_map_id, $subject_map_id, $ciaMArks,0,$year,$month );                         
                        $ciaMArks = $ciaMArks=='' || $ciaMArks==0 ? '0' : (!empty($check_cia_marks['category_type_id_marks'])?$check_cia_marks['category_type_id_marks']:$ciaMArks);
                        $MarkEntry1 = new InternalMarkEntry();                 
                        $MarkEntry1->student_map_id = $student_map_id;
                        $MarkEntry1->subject_map_id = $subject_map_id;
                        $MarkEntry1->category_type_id = $internal_id; // Hard Coded for ESE marks                
                        $MarkEntry1->category_type_id_marks =  $ciaMArks; 
                        $MarkEntry1->attendance_percentage = '70';
                        $MarkEntry1->year = $year;
                        $MarkEntry1->month = $month;
                        $MarkEntry1->term = $term;
                        $MarkEntry1->mark_type = $mark_type;
                        $MarkEntry1->status_id = 0; // For Data Migration only
                        $MarkEntry1->created_by = $created_by;
                        $MarkEntry1->updated_by = $created_by;
                        $MarkEntry1->created_at = $created_at;
                        $MarkEntry1->updated_at = $created_at; 
                        
                        $MarkEntryMaster->student_map_id = $student_map_id;
                        $MarkEntryMaster->subject_map_id = $subject_map_id;
                        $MarkEntryMaster->CIA = $ciaMArks;
                        $MarkEntryMaster->ESE = $stu_result_data['ese_marks'];
                        $MarkEntryMaster->total = $stu_result_data['total_marks'];
                        $MarkEntryMaster->result = $stu_result_data['result']; // For Data Migration
                        $MarkEntryMaster->grade_point = $stu_result_data['grade_point'];
                        $MarkEntryMaster->grade_name = $stu_result_data['grade_name'];
                        $MarkEntryMaster->year = $year;
                        $MarkEntryMaster->month = $month; 
                        $MarkEntryMaster->term = $term;
                        $MarkEntryMaster->mark_type = $mark_type;
                        $MarkEntryMaster->status_id = 0; // For Data Migration only

                        $year_of_passing = $stu_result_data['result'] == "Pass" || $stu_result_data['result'] == "pass" || $stu_result_data['result'] == "PASS" ? $month. "-" . $year : '';

                        $MarkEntryMaster->year_of_passing = $year_of_passing; 
                        $MarkEntryMaster->attempt = $stu_result_data['attempt'];
                        $MarkEntryMaster->created_by = $created_by;
                        $MarkEntryMaster->updated_by = $created_by;
                        $MarkEntryMaster->created_at = $created_at;
                        $MarkEntryMaster->updated_at = $created_at;  
                        if($status==1 && $MarkEntry1->save(false) && $MarkEntryMaster->save(false))
                        { 
                            $totalSuccess+=1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                        }
                        else
                        { 
                            
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        } 
                    }
                    else if (!empty($check_mark_entry) && $get_sub_info['ESE_max']==0 && $get_sub_info['ESE_min']==0) 
                    {
                       
                        $ciaMArks = $ciaMArks=='' || $ciaMArks==0 ? '0' : $ciaMArks;
                        $MarkEntry1 = new InternalMarkEntry();                 
                        $MarkEntry1->student_map_id = $student_map_id;
                        $MarkEntry1->subject_map_id = $subject_map_id;
                        $MarkEntry1->category_type_id = $internal_id; // Hard Coded for CIA 
                        $MarkEntry1->attendance_percentage = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT_STATUS); 
                        $MarkEntry1->attendance_remarks = 'Allowed';           
                        $MarkEntry1->category_type_id_marks =  $ciaMArks; 
                        $MarkEntry1->year = $year;
                        $MarkEntry1->month = $month;
                        $MarkEntry1->term = $term;
                        $MarkEntry1->mark_type = $mark_type;
                        $MarkEntry1->status_id = 0; // For Data Migration only
                        $MarkEntry1->created_by = $created_by;
                        $MarkEntry1->updated_by = $created_by;
                        $MarkEntry1->created_at = $created_at;
                        $MarkEntry1->updated_at = $created_at; 

                        $check_mark_entry_cia = InternalMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$internal_id,'year'=>$year])->one();
                        
                        if(empty($check_mark_entry_cia) && $status==1 && $MarkEntry1->save(false))
                        { 
                            $totalSuccess+=1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                        }
                        else
                        { 
                            
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        }
                     
                    }
                    else
                    {   
                        $ciaMArks = $ciaMArks=='' || $ciaMArks==0 ? '0' : $ciaMArks;
                        $MarkEntry1 = new InternalMarkEntry();                 
                        $MarkEntry1->student_map_id = $student_map_id;
                        $MarkEntry1->subject_map_id = $subject_map_id;
                        $MarkEntry1->category_type_id = $internal_id; // Hard Coded for CIA 
                        $MarkEntry1->attendance_percentage = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_ABSENT_STATUS); 
                        $MarkEntry1->attendance_remarks = 'Allowed';           
                        $MarkEntry1->category_type_id_marks =  $ciaMArks; 
                        $MarkEntry1->year = $year;
                        $MarkEntry1->month = $month;
                        $MarkEntry1->term = $term;
                        $MarkEntry1->mark_type = $mark_type;
                        $MarkEntry1->status_id = 0; // For Data Migration only
                        $MarkEntry1->created_by = $created_by;
                        $MarkEntry1->updated_by = $created_by;
                        $MarkEntry1->created_at = $created_at;
                        $MarkEntry1->updated_at = $created_at; 

                        $check_mark_entry_cia = InternalMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$internal_id,'year'=>$year])->one();
                        
                        if(empty($check_mark_entry_cia) && $status==1 && $MarkEntry1->save(false))
                        { 
                            $totalSuccess+=1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                        }
                        else
                        { 
                            
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        }
                    }                   
                    
                }
                else
                {
                   
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Wrong Entry']);
                }
                                     
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO CIA MARKS']);
            }
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


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Subwise CIA Marks'];
?>