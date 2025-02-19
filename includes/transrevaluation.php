<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Revaluation;
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
    $exam_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'SUBJECT CODE','D'=>'REGISTER NUMBER','E'=>'TRANSPARENCY'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E']];

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
    $subject_code = isset($line['C'])?Subjects::find()->where(['subject_code'=>$line['C']])->one():"";
    $reg_num_12 = isset($line['D'])?Student::find()->where(['register_number'=>$line['D']])->one():"";
    $reg_num_stu = '';
    if(!empty($reg_num_12))
    {
        $stu_map_id_che = Yii::$app->db->createCommand('SELECT * FROM coe_student_mapping WHERE status_category_type_id NOT IN ("'.$det_disc_type.'") and student_rel_id="'.$reg_num_12->coe_student_id.'" ')->queryOne();
        if(!empty($stu_map_id_che))
        {
          $reg_num_stu = StudentMapping::find()->where(['student_rel_id'=>$reg_num_12['coe_student_id']])->one();
          $reg_num = $line['D'];
        }
    }

    if(!empty($year) && !empty($month) && !empty($reg_num_stu) && !empty($subject_code) && !in_array(null, $line, true))
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
            $subject_map_id = Yii::$app->db->createCommand('select subject_map_id FROM coe_subjects_mapping as A JOIN coe_subjects as B ON B.coe_subjects_id=A.subject_id JOIN coe_mark_entry_master as C ON C.subject_map_id=A.coe_subjects_mapping_id JOIN coe_student_mapping as D ON D.coe_student_mapping_id=C.student_map_id and D.course_batch_mapping_id=A.batch_mapping_id JOIN coe_student as E ON E.coe_student_id=D.student_rel_id WHERE C.year="'.$year.'" and C.month="'.$month.'" and E.register_number="'.$reg_num.'" and B.subject_code="'.$subject_code['subject_code'].'" and student_map_id="'.$student_map_id.'"')->queryScalar();
            
            if(!empty($subject_map_id))
            {
                $get_sub_info = Yii::$app->db->createCommand('SELECT A.* FROM coe_subjects as A JOIN coe_subjects_mapping as B ON B.subject_id=A.coe_subjects_id WHERE coe_subjects_mapping_id="'.$subject_map_id.'" ')->queryOne();

                $cia_cat_id = Categorytype::find()->where(['description'=>'CIA'])->orWhere(['category_type'=>'CIA'])->one();
                $internal_id = $cia_cat_id->coe_category_type_id;

                $check_cia_marks = Yii::$app->db->createCommand('SELECT B.* FROM coe_student_mapping as A JOIN coe_mark_entry_master as B ON B.student_map_id=A.coe_student_mapping_id JOIN coe_subjects_mapping as C ON C.coe_subjects_mapping_id=B.subject_map_id and C.batch_mapping_id=A.course_batch_mapping_id WHERE status_category_type_id NOT IN ("'.$det_disc_type.'") and student_rel_id="'.$reg_num_12->coe_student_id.'" and student_map_id="'.$student_map_id.'" and year="'.$year.'" and month="'.$month.'" and subject_map_id="'.$subject_map_id.'"')->queryOne();

                if(count($check_cia_marks)>0 && !empty($check_cia_marks))
                {
                    $marks_available = 0;
                }  

                $condition_check = isset($marks_available)?$marks_available==0:1;
                if($condition_check)
                {         
                    $student_map_id = $check_cia_marks['student_map_id'];
                    $subject_map_id = $check_cia_marks['subject_map_id'];
                    $subs_id = SubjectsMapping::findOne($subject_map_id);
                    $sub_cat_type = Categorytype::findOne($subs_id->subject_type_id);
                    $checkTrans = Revaluation::find()->where(['year'=>$year,'student_map_id'=>$student_map_id,'subject_map_id'=>$subject_map_id,'month'=>$month,'is_transparency'=>'S'])->all();
                   
                    if(empty($checkTrans))
                    {    
                        $model = new Revaluation();
                        $model->student_map_id = $student_map_id;
                        $model->subject_map_id = $subject_map_id;
                        $model->year = $year;
                        $model->month = $month;
                        $model->mark_type = $check_cia_marks['mark_type'];
                        $transparency = ($line['E']=='YES')?'S':'N';
                        $Revaluation = 'NO';
                        $model->is_transparency = $transparency;
                        $model->reval_status = $Revaluation;
                        $model->created_by = Yii::$app->user->getId();
                        $model->created_at = new \yii\db\Expression('NOW()');
                        $model->updated_by = Yii::$app->user->getId();
                        $model->updated_at = new \yii\db\Expression('NOW()');

                        if($model->save())
                        { 
                            unset($model);
                            $model = new Revaluation();
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
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Applied/No Data Found']);
                    } 
                                         
                }
                else
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO DATA FOUND']);
                }
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO DATA FOUND']);
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