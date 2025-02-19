<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Regulation;
use app\models\FeesPaid;
use app\models\Categorytype;
use app\models\Degree;
use app\models\CoeValueSubjects;
use app\models\Sub;
use app\models\Programme;
use yii\db\Query;
use app\models\MarkEntry;
use app\models\CoeValueMarkEntry;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\UpdateTracker;
$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    $exam_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'MARK TYPE','D'=>'TERM','E'=>'REGISTER NUMBER','F'=>'SUBJECT CODE','G'=>'ESE OUT OF MAXIMUM'];

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
    $MarkEntry = new MarkEntry();
    $MarkEntryMaster = new CoeValueMarkEntry();
    $subject_mapping = new Sub();

    $year = isset($line['A'])?$line['A']:""; 
    $month = isset($line['B'])?$this->valueReplace($line['B'], Categorytype::getCategoryId()):"";
    $mark_type = isset($line['C'])?$this->valueReplace($line['C'], Categorytype::getCategoryId()):"";
    $term = isset($line['D'])?$this->valueReplace($line['D'], Categorytype::getCategoryId()):"";
    $subject_code = isset($line['F'])?CoeValueSubjects::find()->where(['subject_code'=>$line['F']])->all():"";

    $reg_num_12 = isset($line['E'])?Student::find()->where(['register_number'=>$line['E']])->one():"";
    $reg_num_stu = '';
    if(isset($reg_num_12) && !empty($reg_num_12))
    { 
        $stu_map_id_che = Yii::$app->db->createCommand('SELECT * FROM coe_student_mapping WHERE status_category_type_id NOT IN ("'.$det_disc_type.'") and student_rel_id="'.$reg_num_12->coe_student_id.'" ')->queryOne();

        if(!empty($stu_map_id_che))
        {
          $reg_num_stu = StudentMapping::find()->where(['student_rel_id'=>$reg_num_12['coe_student_id']])->one();
          $reg_num = $line['E'];
        }
    }
    
    if(!empty($year) && !empty($month) && !empty($mark_type) && !empty($reg_num_stu) && !empty($subject_code) && !in_array(null, $line, true))
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
            if(!empty($subject_code))
            {
               
                foreach ($subject_code as $sub_map) 
                {
                    $sub_map_id_get = Sub::find()->where(['val_subject_id'=>$sub_map['coe_val_sub_id'],'batch_mapping_id'=>$stu_mapping->course_batch_mapping_id,'semester'=>$sem_verify])->one();
                     //print_r($sub_map_id_get);exit;


                    if(!empty($sub_map_id_get))
                    {
                        $subject_map_id = $sub_map_id_get['coe_sub_mapping_id'];
                        break;
                    }
                }
                
            }
            else if($mark_type==27)
            {
                
                $subject_map_id = $this->valueReplace($line['F'], Sub::getSubjectMappingId($stu_mapping->course_batch_mapping_id,$sem_verify));
            }
            else
            {
                 
                $subject_map_id = $this->valueReplace($line['F'], Sub::getSubjectMappingId($stu_mapping->course_batch_mapping_id));
            }
           

            
            $get_sub_info = Yii::$app->db->createCommand('SELECT A.* FROM coe_value_subjects as A JOIN sub as B ON B.val_subject_id=A.coe_val_sub_id WHERE coe_sub_mapping_id="'.$subject_map_id.'" and semester="'.$sem_verify.'" ')->queryOne();

            $cia_cat_id = Categorytype::find()->where(['description'=>'CIA'])->orWhere(['category_type'=>'CIA'])->one();
            $internal_id = $cia_cat_id->coe_category_type_id;

            $ese_cat_id = Categorytype::find()->where(['description'=>'ESE'])->orWhere(['category_type'=>'ESE'])->orWhere(['category_type'=>'ESE(Dummy)'])->one();
            $external_id = $ese_cat_id->coe_category_type_id;

            //$check_cia_marks = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$internal_id])->one();   
            
            $check_cia_marks_master = CoeValueMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id])->one();
            $config_attempt = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CIA_ZEO);
            $check_attempt = Yii::$app->db->createCommand('SELECT count(*) FROM coe_mark_entry_master WHERE subject_map_id="' . $subject_map_id . '" AND student_map_id="' . $student_map_id . '" AND result not like "%pass%" ')->queryScalar();
            $attempt = isset($check_attempt) && $check_attempt!="" ? (count($check_attempt)+1) : 0;


           
            $condition_check = $check_cia_marks_master['CIA'];
            $nominal_available = 'NO';
            
            if( empty($check_cia_marks_master))
            {  
                $subs_id = Sub::findOne($subject_map_id);
                $sub_cat_type = Categorytype::findOne($subs_id->subject_type_id);

                //print_r($subs_id);exit;
                
                if($sub_cat_type->description=="Elective" )
                {
                    $stu_tab_id = StudentMapping::findOne($student_map_id);
                    $check_nominal_query = new Query();
                    $check_nominal_query->select('*')
                    ->from('coe_value_nominal')                                
                    ->where(["coe_student_id"=>$stu_tab_id->student_rel_id,'coe_subjects_id'=>$subs_id->val_subject_id,'semester'=>$sem_verify]);
                    $check_nominal = $check_nominal_query->createCommand()->queryAll();
                    $nominal_available = 'YES';
                }                
                $get_sub_max = $subject_details = CoeValueSubjects::findOne($subs_id->val_subject_id);                
                if((!empty($line['G']) || $line['G']==0) && ($line['G']<= ($get_sub_max->CIA_max+$get_sub_max->ESE_max))) // Check if column is not null
                {
                    $ciaMArks = $ese_marks = $line['G']<=0 ? '0': $line['G'];
                    $convert_ese_marks =  $ese_marks;
                    //$insert_total = $ese_marks+$cia_marks;
                    $final_sub_total = $subject_details->ESE_max+$subject_details->CIA_max;
                   
                    

                   
                    /*elseif($final_sub_total=200)
                    {

                         $total_marks_1 = round((($insert_total/100)*10),1);

                        $CD= round((($total_marks_1 /200)*100),1);
                        $total_marks=$CD*10;


                    }*/
                    

                    if($get_sub_max->ESE_max==0 && $get_sub_max->ESE_min==0)
                    {
                        $cia_marks = $ciaMArks;
                        $total_marks = $ciaMArks;
                        $convert_ese_marks = 0;
                    }
                    else if($get_sub_max->CIA_max==0 && $get_sub_max->CIA_min==0)
                    {
                        $cia_marks = 0;
                        $total_marks = $ese_marks;
                        $convert_ese_marks = $ese_marks;
                    }


                    
                    foreach ($grade_details as $value) 
                      {


                          if($value['grade_point_to']!='')
                          {
                              if($subject_details['CIA_max']==0  && $subject_details['ESE_max']<=100)
                              {
                                $final_sub_total = $subject_details['ESE_max']+$subject_details['CIA_max'];

                                $total_marks = round(((0+$convert_ese_marks)/($subject_details['ESE_max']+0) )*100); 
                                $arts_college_grade = round(( (0+$convert_ese_marks)/$final_sub_total)*10,1);
                              }
                              else if($subject_details['ESE_max']>100 )
                              {
                                $final_sub_total = $subject_details['ESE_max']+$subject_details['CIA_max'];
                                $total_marks = ((($cia_marks+$ese_marks)/($subject_details['ESE_max']+$subject_details['CIA_max']) )*100);
                                $arts_college_grade = round(( ($cia_marks+$ese_marks)/$final_sub_total)*10,1);
                              }
                              

                              if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                              {
                                  if( $subject_details->CIA_max!=0 && ( $cia_marks<$subject_details->CIA_min || $convert_ese_marks<$subject_details->ESE_min ||  $total_marks<$subject_details->total_minimum_pass ) )
                                  {
                                    $result_stu = 'Fail';
                                    $student_res_data = ['result'=>$result_stu,'total_marks'=> $total_marks,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                  } 
                                  else if($subject_details->CIA_max==0 && ( $convert_ese_marks<$subject_details->ESE_min ||  $total_marks<$subject_details->total_minimum_pass ) )
                                  {
                                    $result_stu = 'Fail';
                                    $student_res_data = ['result'=>$result_stu,'total_marks'=> $total_marks,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$convert_ese_marks];        
                                  }      
                                  else
                                  {
                                    $grade_name_prit = $value['grade_name'];
                                    
                                    $grade_point_arts = round(( $total_marks/($get_sub_max->ESE_max+$get_sub_max->CIA_max) *10),1) ;
                                  if($subject_details['CIA_max']==0)
                                  {
                                    $grade_point_arts = round(( (0+$convert_ese_marks)/$final_sub_total)*10,1);
                                  }

                                    $student_res_data = ['result'=>'Pass','total_marks'=> $total_marks,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$convert_ese_marks,'year_of_passing'=>$month."-".$year];

                                   }



        
                                  
                              }
                          } // Not Empty of the Grade Point 
                      }

                     
                    
                    $check_mark_entry = CoeValueMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$year,'month'=>$month])->all();
                    $check_pass = CoeValueMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'result'=>'Pass'])->one();
                    //$check_fees_pay = FeesPaid::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'status'=>'YES'])->one();
                    if(!empty($check_mark_entry) && count($check_mark_entry)>0)
                    {                         
                        $status =0;   
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Marks Already Available']);
                    }
                   /* else if(empty($check_fees_pay) && $mark_type!=27)
                    {
                        $status ='NOT PAID';   
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Student Hasn\'t Paid Fees']);
                    }
                    */
                    else if(!empty($check_pass))
                    {
                        $status ='Pass';   
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Student Already Pass']);
                    }
                    else
                    {
                        $check_status = Yii::$app->db->createCommand('SELECT status_id FROM coe_mark_entry_master WHERE YEAR="'.$year.'" AND month="'.$month.'"')->queryScalar();
                       //$publish_status = (empty($check_status) || $check_status==0) ? 1 : 2;

                        if($nominal_available == 'NO')
                        {
                            $status =1;
                        }
                        else if($sub_cat_type->description=="Elective" && $nominal_available == 'YES' && (!empty($ciaMArks) || $line['G']==0) )
                        {
                            $status =1;
                        }
                        else
                        {
                            $status =0;
                        }
                        
                       // $check_mark_entry_ESE = MarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$external_id,'year'=>$year,'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->all();




                        $check_mark_entry_master_ESE = CoeValueMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'year'=>$year,'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->all();

                       
                        
						
						  $year_of_passing = $student_res_data['result'] == "Pass" || $student_res_data['result'] == "pass" || $student_res_data['result'] == "PASS" ? $month. "-" . $year : '';
						
                        $MarkEntryMaster->student_map_id = $student_map_id;
                        $MarkEntryMaster->subject_map_id = $subject_map_id;
                        $MarkEntryMaster->CIA = ($cia_marks==0 || $cia_marks=='')?0:$cia_marks;
                        $MarkEntryMaster->ESE = $student_res_data['ese_marks'];
                        $MarkEntryMaster->total = $student_res_data['total_marks'];
                        $MarkEntryMaster->result = $student_res_data['result']; // For Data Migration
                        $MarkEntryMaster->grade_point = $student_res_data['grade_point'];
                        $MarkEntryMaster->grade_name = $student_res_data['grade_name'];
                        $MarkEntryMaster->year = $year;
                        $MarkEntryMaster->month = $month; 
                        $MarkEntryMaster->term = $term;
                        $MarkEntryMaster->mark_type = $mark_type;
                        $MarkEntryMaster->status_id = 0; // For Data Migration only
                        $MarkEntryMaster->year_of_passing = $year_of_passing; 
                        $MarkEntryMaster->attempt = $student_res_data['attempt'];
                        $MarkEntryMaster->created_by = $created_by;
                        $MarkEntryMaster->updated_by = $created_by;
                        $MarkEntryMaster->created_at = $created_at;
                        $MarkEntryMaster->updated_at = $created_at;   
                                  
                      // if($publish_status==1)
                       // { 
                          //  if($status==1)
                           // {
                                if( empty($check_mark_entry_master_ESE)&& $MarkEntryMaster->save(false))
                                {
                                    $totalSuccess+=1;
                                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                                }
                                else
                                {
                                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Something Wrong Contact SKIT']);
                                }
                               
                            //}
                            //else
                            //{
                              //  $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Wrong Data Submitted']);
                            //}
                            
                        //}
                     //else
                        //{                             
                          //  $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Results already Published']);
                        //} 
                        
                       
                    }
                }
                else
                {
                   
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Wrong Entry']);
                }                   
            }
            else
            {
                if($condition_check==0)
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO DATA FOUND']);
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


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Arts Subwise Marks'];
?>