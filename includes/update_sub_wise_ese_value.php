<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Regulation;
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
use app\models\UpdateTracker;
$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    $exam_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'MARK TYPE','D'=>'TERM','E'=>'SUBJECT CODE','F'=>'REGISTER NUMBER','G'=>'ESE (OUT OF MAXIMUM)'];

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

$det_cat_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%detain%'")->queryScalar();

$det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
foreach($sheetData as $k => $line)
{    

    $line = array_map('trim', $line);
    $subjects = new CoeValueSubjects();
    $MarkEntry = new InternalMarkEntry();
    $MarkEntryMaster = new CoeValueMarkEntry();
    $subject_mapping = new Sub();

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
        $connection = Yii::$app->db;

            if(count($subject_code>0))
            {
                foreach ($subject_code as $sub_map) 
                {
                    $sub_map_id_get = Sub::find()->where(['val_subject_id'=>$sub_map['coe_val_sub_id'],'batch_mapping_id'=>$stu_mapping->course_batch_mapping_id])->one();
                    if(!empty($sub_map_id_get))
                    {
                        $subject_map_id = $sub_map_id_get['coe_sub_mapping_id'];
                        break;
                    }
                }
                
            }
            else
            {
                $subject_map_id = $this->valueReplace($line['E'], Sub::getSubjectMappingId($stu_mapping->course_batch_mapping_id,$sem_verify));
            }
            
            $cia_cat_id = Categorytype::find()->where(['description'=>'CIA'])->orWhere(['category_type'=>'Internal'])->one();
            $internal_id = $cia_cat_id->coe_category_type_id;

            $exe_cat_id = Categorytype::find()->where(['description'=>'External'])->orWhere(['category_type'=>'ESE'])->orWhere(['category_type'=>'ESE(Dummy)'])->one();
            

            $exe_cat_id_2 = Categorytype::find()->where(['description'=>'ESE(Dummy)'])->orWhere(['category_type'=>'ESE(Dummy)'])->one();

            $check_cia_marks = InternalMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$internal_id])->orderBy('mark_entry_id DESC')->one();

            $check_ese_marks = InternalMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$exe_cat_id->coe_category_type_id,'year'=>$line['A'],'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->orderBy('mark_entry_id DESC')->one();

            $check_ese_marks_dum = InternalMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'category_type_id'=>$exe_cat_id_2->coe_category_type_id,'year'=>$line['A'],'month'=>$month,'mark_type'=>$mark_type,'term'=>$term])->orderBy('mark_entry_id DESC')->one();
            
            $check_ese_marks = !empty($check_ese_marks) ? $check_ese_marks : $check_ese_marks_dum;

            $external_id = !empty($check_ese_marks) ?$exe_cat_id->coe_category_type_id:$exe_cat_id_2->coe_category_type_id;
            $get_sub_info = Yii::$app->db->createCommand('SELECT A.* FROM coe_value_subjects as A JOIN sub as B ON B.val_subject_id=A.coe_val_sub_id WHERE coe_sub_mapping_id="'.$subject_map_id.'" and A.subject_code="'.$line['E'].'" ')->queryOne();
            //print_r($get_sub_info);
           // exit;
            
            if($line['G']<=$get_sub_info['ESE_max'])
            {
                $status_check = 'YES';                
            }
            else
            {
                $status_check = 'NO';
            }
            
            if(count($check_cia_marks)>0 && !empty($check_cia_marks) && $status_check=='YES')
            {                $check_mark_entry_master = CoeValueMarkEntry::find()->where(['student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'mark_type'=>$mark_type,'term'=>$term,'year'=>$year,'month'=>$month])->one();


                $attempt = 1;
                if(!empty($check_mark_entry_master))
                {
                    $ciaMArks = $check_mark_entry_master['CIA'];
                    $ese_marks = $line['G'];

                    $insert_total = $ese_marks+$ciaMArks;
                    $final_sub_total = $get_sub_info['ESE_max']+$get_sub_info['CIA_max'];
                    if($final_sub_total<100)
                    {
                      $total_marks = round((($insert_total/$final_sub_total)*100),0);
                    }
                    else
                    {
                      $total_marks = $ese_marks+$ciaMArks;
                    }
                    $arts_college_grade = round(($insert_total/$final_sub_total)*10,1);

                    if($get_sub_info['ESE_max']==0 && $get_sub_info['ESE_min']==0)
                    {
                        $cia_marks = $ciaMArks;
                        $total_marks = $ciaMArks;
                        $convert_ese_marks = 0;
                    }

                    $grade_details = Regulation::find()->where(['regulation_year'=>$batchMapping->regulation_year])->all();
                  foreach ($grade_details as $value) 
                  {
                      if($value['grade_point_to']!='')
                      {                            
                          if($total_marks >= $value['grade_point_from'] &&  $total_marks <= $value['grade_point_to'] )
                          {

                              if($ciaMArks<$get_sub_info['CIA_min'] || $ese_marks<$get_sub_info['ESE_min'] || $insert_total<$get_sub_info['total_minimum_pass'])
                              {
                                $student_res_data = ['result'=>'Fail','total_marks'=>$insert_total,'grade_name'=>'U','grade_point'=>0,'attempt'=>$attempt,'year_of_passing'=>'','ese_marks'=>$ese_marks];        
                              }      
                              else
                              {
                                $grade_name_prit = $value['grade_name'];
                                $grade_point_arts = $total_marks/10; ;
                                if(isset($get_id_details))
                                {
                                    $student_res_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'year_of_passing'=>$get_id_details['exam_month']."-".$get_id_details['exam_year'],'ese_marks'=>$ese_marks];
                                }
                                else if(!empty($year) && !empty($month))
                                {
                                    $student_res_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$ese_marks,'year_of_passing'=>$month."-".$year];
                                }
                                else
                                {
                                    $student_res_data = ['result'=>'Pass','total_marks'=>$insert_total,'grade_name'=>$grade_name_prit,'grade_point'=>$grade_point_arts,'attempt'=>$attempt,'ese_marks'=>$ese_marks,'year_of_passing'=>''];
                                }
                                
                              }
                          } // Grade Point Caluclation
                      } // Not Empty of the Grade Point 
                      
                  }
                    $year_of_passing = $student_res_data['result'] == "Pass" || $student_res_data['result'] == "pass" || $student_res_data['result'] == "PASS" ? $month. "-" . $year : '';
                   
                    $ip_address = Yii::$app->params['ipAddress'];
                    $UpdateTracker = New UpdateTracker();
                    $UpdateTracker->student_map_id = $check_mark_entry_master['student_map_id'];
                    $UpdateTracker->subject_map_id = $check_mark_entry_master['subject_map_id'];
                    $UpdateTracker->exam_year = $year;
                    $UpdateTracker->exam_month = $month;

                    $UpdateTracker->updated_link_from = 'Import->External Marks Update';
                    $UpdateTracker->data_updated = 'PREVIOUS ESE MARKS '.$check_ese_marks['category_type_id_marks'].' NEW ESE MARKS'.$student_res_data['ese_marks'].' OLD RESULT '.$check_mark_entry_master['result'].' NEW RESULT '.$student_res_data['result'].' OLD TOTAL '.$check_mark_entry_master['total'].' NEW TOTAL '.$insert_total;

                    $UpdateTracker->updated_ip_address = ConfigUtilities::getIpAddress();
                    $UpdateTracker->updated_by = ConfigUtilities::getCreatedUser();
                    $UpdateTracker->updated_at = ConfigUtilities::getCreatedTime();
                    
                    $UpdateTracker->save();
                    unset($UpdateTracker);
                    $UpdateTracker = New UpdateTracker();

                    if(!empty($check_ese_marks))
                    {
                        $command1 = $connection->createCommand('UPDATE internal_mark_entry SET category_type_id_marks="'.$ese_marks.'",updated_by="'.$created_by.'",updated_at="'.$created_at.'" WHERE mark_entry_id="'.$check_ese_marks->mark_entry_id.'" ');
                        $command1->execute();
                    }

                    $command2 = $connection->createCommand('UPDATE coe_value_mark_entry SET CIA="'.$ciaMArks.'",ESE="'.$student_res_data['ese_marks'].'",total="'.$insert_total.'",result="'.$student_res_data['result'].'",grade_point="'.$student_res_data['grade_point'].'",grade_name="'.$student_res_data['grade_name'].'",updated_by="'.$created_by.'",updated_at="'.$created_at.'",is_updated="YES",year_of_passing="'.$year_of_passing.'" WHERE coe_value_mark_entry_id="'.$check_mark_entry_master->coe_value_mark_entry_id.'" ');

                    if($command2->execute())
                    {
                        $totalSuccess+=1;
                        $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                    }
                    else{ 
                        $dispResults[] = array_merge($line, ['type' => 'E', 'message' => 'Unable to Update Result']);
                     }
                }-+
                else
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'ESE Marks Already Updated No more update again']);
                }

            }
            else{
                $dispResults[] = array_merge($line, ['type' => 'E', 'message' => 'Marks Not Available to Update Or MAXIMUM Crosssed']);
            }  
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
    }           
} // Foreach Ends Here  

$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Internal_update'];
?>