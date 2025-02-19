<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\AbsentEntry;
use app\models\FeesPaid;
use app\models\CoeBatDegReg;
use app\models\Regulation;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\Programme;
use yii\db\Query;
use app\models\MarkEntry;
use app\models\MarkEntryMaster;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    $exam_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'MARK TYPE','D'=>'TERM','E'=>'SEMESTER','F'=>'SUBJECT CODE','G'=>'REGISTER NUMBER','H'=>'ESE OUT OF (100)'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H']];

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
    $subjects = new Subjects();
    $MarkEntry = new MarkEntry();
    $MarkEntryMaster = new MarkEntryMaster();
    $subject_mapping = new SubjectsMapping();

    $year = isset($line['A'])?$line['A']:""; 
    $month = isset($line['B'])?$this->valueReplace($line['B'], Categorytype::getCategoryId()):"";
    $mark_type = isset($line['C'])?$this->valueReplace($line['C'], Categorytype::getCategoryId()):"";
    $term = isset($line['D'])?$this->valueReplace($line['D'], Categorytype::getCategoryId()):"";
    $subject_code = isset($line['F'])?Subjects::find()->where(['subject_code'=>$line['F']])->all():"";
    $reg_num_12 = isset($line['G'])?Student::find()->where(['register_number'=>$line['G']])->one():"";
    $reg_num_stu = '';
    if(isset($reg_num_12) && !empty($reg_num_12))
    { 
        $stu_map_id_che = Yii::$app->db->createCommand('SELECT * FROM coe_student_mapping WHERE status_category_type_id NOT IN ("'.$det_disc_type.'") and student_rel_id="'.$reg_num_12->coe_student_id.'" ')->queryOne();

        if(!empty($stu_map_id_che))
        {
          $reg_num_stu = StudentMapping::find()->where(['student_rel_id'=>$reg_num_12['coe_student_id']])->one();
          $reg_num = $line['G'];
        }
    }
    
    //print_r($subject_code);exit;
    if(!empty($year) && !empty($month) && !empty($line['E']) && !empty($mark_type) && !empty($reg_num_stu) && !empty($subject_code) && !in_array(null, $line, true))
    { 
        $inserted_res = 0;
        $student = Student::find()->where(['register_number'=>$reg_num])->one();
        $stu_mapping = StudentMapping::find()->where(['student_rel_id'=>$student->coe_student_id])->one();
        $batchMapping = CoeBatDegReg::findOne($stu_mapping->course_batch_mapping_id);
        $student_map_id = $stu_mapping->coe_student_mapping_id;
        
        $regulation = CoeBatDegReg::find()->where(['coe_batch_id'=>$batchMapping->coe_batch_id,'coe_bat_deg_reg_id'=>$stu_mapping->course_batch_mapping_id])->one();
        
        $grade_details = Regulation::find()->where(['regulation_year'=>$regulation->regulation_year])->all();
        $sem_verify = ConfigUtilities::SemCaluclation($year,$month,$stu_mapping->course_batch_mapping_id);
       
        if($sem_verify==0)
        {

        }
        else
        {
            $subject_map_id = '';
            if(count($subject_code>0))
            {
                foreach ($subject_code as $sub_map) 
                {
                    $sub_map_id_get = SubjectsMapping::find()->where(['subject_id'=>$sub_map['coe_subjects_id'],'batch_mapping_id'=>$stu_mapping->course_batch_mapping_id,'semester'=>$line['E']])->one();
                    if(!empty($sub_map_id_get))
                    {
                        $subject_map_id = $sub_map_id_get['coe_subjects_mapping_id'];
                        break;
                    }
                }
                
            }
            else
            {
                $subject_map_id = $this->valueReplace($line['F'], SubjectsMapping::getSubjectMappingId($stu_mapping->course_batch_mapping_id,$line['E']));
            }
            
            $get_sub_info = Yii::$app->db->createCommand('SELECT A.* FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$subject_map_id.'" and semester="'.$line['E'].'" ')->queryOne();

            $cia_cat_id = Categorytype::find()->where(['description'=>'CIA'])->orWhere(['category_type'=>'CIA'])->one();
            $internal_id = $cia_cat_id->coe_category_type_id;

            $ese_cat_id = Categorytype::find()->where(['description'=>'ESE'])->orWhere(['category_type'=>'ESE'])->one();
            $external_id = $ese_cat_id->coe_category_type_id;
            $exe_cat_id_2 = Categorytype::find()->where(['description'=>'ESE(Dummy)'])->orWhere(['category_type'=>'ESE(Dummy)'])->one();

            $check_cia_marks = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$internal_id])->one();   
            
            $check_cia_marks_master = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id])->one();

            $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
            $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id . '" AND student_map_id="' . $student_map_id . '" AND result not like "%pass%" ')->queryScalar();
            $attempt = isset($check_attempt) && $check_attempt!="" ? (count($check_attempt)+1) : 0;
            if(count($check_cia_marks)>0 && !empty($check_cia_marks))
            {
                $cia_marks = $check_cia_marks['category_type_id_marks'];

                $marks_available = 1;
            } 
            else if(count($check_cia_marks_master) > 0)
            {
                $cia_marks = $check_cia_marks_master['CIA'];
                $marks_available = 1;
            }
            else
            {
                $cia_marks='';
                $marks_available=0;
            }
            
            $condition_check = $marks_available==1?1:0; 
            $nominal_available = 'NO';
            if($condition_check==1 || $condition_check==0)
            {  
                $subs_id = SubjectsMapping::findOne($subject_map_id);
                $sub_cat_type = Categorytype::findOne($subs_id->subject_type_id);

                $pquery='SELECT * FROM coe_exam_timetable WHERE subject_mapping_id = "'.$subject_map_id.'" and exam_month="'.$month.'" AND exam_year="'.$year.'" and exam_type='.$mark_type;

                $prev_examAllDet=Yii::$app->db->createCommand($pquery)->queryone();

                //print_r($prev_examAllDet['exam_date']); exit;
                
                if($sub_cat_type->description=="Elective" )
                {
                    $stu_tab_id = StudentMapping::findOne($student_map_id);
                    $check_nominal_query = new Query();
                    $check_nominal_query->select('*')
                    ->from('coe_nominal')                                
                    ->where(["coe_student_id"=>$stu_tab_id->student_rel_id,'coe_subjects_id'=>$subs_id->subject_id,'semester'=>$line['E']]);
                    $check_nominal = $check_nominal_query->createCommand()->queryAll();
                    $nominal_available = 'YES';
                }
                
                $get_sub_max = $subject_details = Subjects::findOne($subs_id->subject_id);
                
                if((!empty($line['H']) || $line['H']==0) && ($line['H']<= ($get_sub_max->CIA_max+$get_sub_max->ESE_max))) // Check if column is not null
                {
                    $ciaMArks = $ese_marks = $line['H']<=0 ? '0': $line['H'];
                    
                    $ese_max = $get_sub_max->ESE_max;
                    $convert_ese_marks =  round( ($ese_marks*$ese_max)/100 );
                    $total_marks = $cia_marks+$convert_ese_marks;
                    if($check_attempt >= $config_attempt) 
                    {
                        if($get_sub_max->ESE_max==0 && $get_sub_max->ESE_min==0)
                        {
                            $cia_marks = $ciaMArks;
                            $total_marks = $convert_ese_marks = $ciaMArks;
                        }
                        else
                        {
                            $cia_marks=0;
                            $total_marks = $convert_ese_marks = $ese_marks;
                        }
                          foreach ($grade_details as $value) 
                          {
                              if($value['grade_point_to']!='')
                              {
                                  
                                  if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                                  {
                                      if($cia_marks<$subject_details->CIA_min || $convert_ese_marks<$subject_details->ESE_min || $total_marks<$subject_details->total_minimum_pass)
                                      {
                                        $result_stu = 'Fail';
                                        $student_res_data = ['result'=>$result_stu,'total_marks'=>$total_marks,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                      }      
                                      else
                                      {
                                        $grade_name_prit = $value['grade_name'];
                                        $grade_point_arts = $value['grade_point'];
                                        $student_res_data = ['result'=>'Pass','total_marks'=>$total_marks,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$convert_ese_marks,'year_of_passing'=>$month."-".$year];
                                       }
                                  } // Grade Point Caluclation
                              } // Not Empty of the Grade Point 
                          }
                    } else {

                        if($get_sub_max->ESE_max==0 && $get_sub_max->ESE_min==0)
                        {
                            $cia_marks = $ciaMArks;
                            $total_marks = $convert_ese_marks = $ciaMArks;
                        }
                        foreach ($grade_details as $value) 
                          {
                              if($value['grade_point_to']!='')
                              {
                                  
                                  if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                                  {
                                      if($cia_marks<$subject_details->CIA_min || $convert_ese_marks<$subject_details->ESE_min || $total_marks<$subject_details->total_minimum_pass)
                                      {
                                        $result_stu = 'Fail';
                                        $student_res_data = ['result'=>$result_stu,'total_marks'=>$total_marks,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                      }      
                                      else
                                      {
                                        $grade_name_prit = $value['grade_name'];
                                        $grade_point_arts = $value['grade_point'];
                                        $student_res_data = ['result'=>'Pass','total_marks'=>$total_marks,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$convert_ese_marks,'year_of_passing'=>$month."-".$year];
                                       }
                                  } // Grade Point Caluclation
                              } // Not Empty of the Grade Point 
                          }

                    }

                    $check_mark_entry = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$year,'month'=>$month])->all();
                    $check_pass = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'result'=>'Pass'])->one();
                    $check_fees_pay = FeesPaid::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'status'=>'YES'])->one();

                    if(!empty($check_mark_entry) && count($check_mark_entry)>0)
                    {                         
                        $status =0;   
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Marks Already Available']);
                    }
                    else if(!empty($check_pass))
                    {
                        $status ='Pass';   
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Student Already Pass']);
                    }
                    /*else if(!empty($check_fees_pay))
                    {
                        $status ='NOT PAID';   
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Student Hasn't Paid Fees']);
                    }*/
                    else
                    {
                        $check_status = Yii::$app->db->createCommand('SELECT status_id FROM coe_mark_entry_master WHERE YEAR="'.$year.'" AND month="'.$month.'"')->queryScalar();
                        $publish_status = (empty($check_status) || $check_status==0) ? 1 : 2;

                        if($nominal_available == 'NO')
                        {
                            $status =1;
                        }
                        else if($sub_cat_type->description=="Elective" && $nominal_available == 'YES' && (!empty($ciaMArks) || $line['H']==0) )
                        {
                            $status =1;
                        }
                        else
                        {
                            $status =0;
                        }
                        
                        $check_mark_entry_ESE = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$external_id,'year'=>$year,'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->all();
                        
                        $check_ese_marks_dum = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$exe_cat_id_2->coe_category_type_id,'year'=>$year,'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->one();

                        $check_mark_entry_master_ESE = MarkEntryMaster::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->all();

                        $getAbsentList = AbsentEntry::find()->where(['exam_type'=>$mark_type,'absent_term'=>$term,'exam_subject_id'=>$subject_map_id,'exam_month'=>$month,'exam_year'=>$year,'absent_student_reg'=>$student_map_id])->all();
                        $get_cat_entry_type = Categorytype::find()->where(['description'=>'Hall Wise Entry'])->one();
                        $absent_entry_type = $get_cat_entry_type['coe_category_type_id'];

                        $absentInsert = new AbsentEntry();
                        $absentInsert->absent_student_reg = $student_map_id;
                        $absentInsert->exam_type = $mark_type;
                        $absentInsert->absent_term = $term;
                        $absentInsert->exam_subject_id = $subject_map_id;
                        $absentInsert->exam_absent_status = $absent_entry_type;
                        $absentInsert->exam_month = $month;
                        $absentInsert->exam_year = $year;
                        $absentInsert->exam_date =$prev_examAllDet['exam_date'];
                        $absentInsert->exam_session =$prev_examAllDet['exam_session'];
                        $absentInsert->created_by = $created_by;
                        $absentInsert->updated_by = $created_by;
                        $absentInsert->created_at = $created_at;
                        $absentInsert->updated_at = $created_at; 

                        $ciaMArks = $ciaMArks=='' ? 0: $ciaMArks;
                        $MarkEntry1 = new MarkEntry();                 
                        $MarkEntry1->student_map_id = $student_map_id;
                        $MarkEntry1->subject_map_id = $subject_map_id;
                        $MarkEntry1->category_type_id = $external_id; // Hard Coded for ESE marks                
                        $MarkEntry1->category_type_id_marks =  0; 
                        $MarkEntry1->year = $year;
                        $MarkEntry1->month = $month;
                        $MarkEntry1->term = $term;
                        $MarkEntry1->mark_type = $mark_type;
                        $MarkEntry1->status_id = 0; // For Data Migration only
                        $MarkEntry1->created_by = $created_by;
                        $MarkEntry1->updated_by = $created_by;
                        $MarkEntry1->created_at = $created_at;
                        $MarkEntry1->updated_at = $created_at; 
                        
                        $year_of_passing = $student_res_data['result'] == "Pass" || $student_res_data['result'] == "pass" || $student_res_data['result'] == "PASS" ? $month. "-" . $year : '';
                         $MarkEntryMaster = new MarkEntryMaster();  
                        $MarkEntryMaster->student_map_id = $student_map_id;
                        $MarkEntryMaster->subject_map_id = $subject_map_id;
                        $MarkEntryMaster->CIA = ($cia_marks==0 || $cia_marks=='')?0:$cia_marks;
                        $MarkEntryMaster->ESE = 0;
                        $MarkEntryMaster->total = ($cia_marks==0 || $cia_marks=='')?0:$cia_marks;
                        $MarkEntryMaster->result = 'Absent'; // For Data Migration
                        $MarkEntryMaster->grade_point = 0;
                        $MarkEntryMaster->grade_name ='U';
                        $MarkEntryMaster->year = $year;
                        $MarkEntryMaster->month = $month; 
                        $MarkEntryMaster->term = $term;
                        $MarkEntryMaster->mark_type = $mark_type;
                        $MarkEntryMaster->status_id = 0; // For Data Migration only
                        $MarkEntryMaster->year_of_passing = ''; 
                        $MarkEntryMaster->attempt = $student_res_data['attempt'];
                        $MarkEntryMaster->created_by = $created_by;
                        $MarkEntryMaster->updated_by = $created_by;
                        $MarkEntryMaster->created_at = $created_at;
                        $MarkEntryMaster->updated_at = $created_at;   
                        
                        if(empty($getAbsentList))
                        {
                           if($absentInsert->save(false) && $MarkEntryMaster->save(false) )
                            {
                                $totalSuccess+=1;
                                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                            }
                            else
                            {
                                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Something Wrong Contact SKIT']);
                            }
                        }
                        else
                        {
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Something Wrong Contact SKIT']);
                        }

                     /*if($publish_status==1)
                        { 
                            if($status==1)
                          {
                             if((empty($check_mark_entry_ESE) || empty($check_ese_marks_dum)) && empty($check_mark_entry_master_ESE) && $MarkEntry1->save(false) && $MarkEntryMaster->save(false))
                                {
                                    $totalSuccess+=1;
                                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                                }
                                else
                                {
                                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Something Wrong Contact SKIT']);
                                }
                               
                           }
                            else
                            {
                               $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Wrong Entry']);
                            }
                            
                        }
                       else
                        {                             
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Results already Published']);
                        } */
                    }
                }
                else
                {
                   
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Wrong Entry']);
                }                   
            }
            else
            {
                if($marks_available==0 && $cia_marks=='')
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO DATA FOUND PLEASE CHECK CIA MARK']);
                }
                else
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO CIA MARKS']);
                }
                
            }
        }
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng11']);
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


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Absent Import'];
?>