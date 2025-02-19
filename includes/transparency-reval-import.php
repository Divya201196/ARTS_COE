<?php 
use yii\helpers\Url;
use app\models\Categorytype;
use app\models\Student;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\StudentMapping;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{ 
  $hall_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'REGISTER NUMBER','D'=>'SEMESTER','E'=>'SUBJECT CODE','F'=>'TRANSPARENCY','G'=>'REVALUATION'];
      $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G']];

      $mis_match=array_diff_assoc($hall_columns,$template_clumns);
      if($interate==1 && count($mis_match)!=0 && count($mis_match)>0)
      {
          $misMatchingColumns = '';
          foreach ($mis_match as $key => $value) {
              $misMatchingColumns .= $key.", ";
          }
          $misMatchingColumns = trim($misMatchingColumns,', ');
          $misMatchingColumns = wordwrap($misMatchingColumns, 40, "<br />\n");
          Yii::$app->ShowFlashMessages->setMsg('Error',"<b style='font-size: 17px;'> Column (s) ".$misMatchingColumns." Are Not matching with our Original Template </b> Please use the Sample Template from the Download Link!!");
          return Yii::$app->response->redirect(Url::to(['import/index']));
      }
      else
      {
         break;
      }
      $interate +=8;
      
}
unset($sheetData[1]);
 $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
$elective_id_get = Categorytype::find()->where(['description'=>'Elective'])->orWhere(['category_type'=>'Elective'])->one();
foreach($sheetData as $k => $line)
{   

    $line = array_map('trim', $line);
    $stu_data = isset($line['C'])?Student::find()->where(['register_number'=>$line['C']])->one():"";
    $stu_map_id = '';
   
    if(!empty($stu_data))
    {   
        $stu_map_id_che = Yii::$app->db->createCommand('SELECT * FROM coe_student_mapping WHERE status_category_type_id NOT IN ("'.$det_disc_type.'") and student_rel_id="'.$stu_data->coe_student_id.'" ')->queryOne();
        if(!empty($stu_map_id_che))
        {
          $stu_map_id = StudentMapping::find()->where(['student_rel_id'=>$stu_data['coe_student_id']])->one();
        }
    }
    $semester =  isset($line['D'])?$line['D']:"";
    $year =  isset($line['A'])?$line['A']:"";
    $month =  isset($line['B'])?$this->valueReplace($line['B'], Categorytype::getCategoryId()):"";
    $subject = isset($line['E'])?Subjects::find()->where(['subject_code'=>$line['E']])->one():"";  
    if(!empty($subject) && !empty($stu_map_id))
    {
      $SUB_MAP_ID_GET = SubjectsMapping::find()->where(['subject_id'=>$subject['coe_subjects_id'],'batch_mapping_id'=>$stu_map_id->course_batch_mapping_id,'semester'=>$semester])->one();
      if(!empty($SUB_MAP_ID_GET))
      {
         $getExam_dertails = Yii::$app->db->createCommand('SELECT B.* FROM coe_subjects_mapping as A Join coe_mark_entry_master as B ON B.subject_map_id=A.coe_subjects_mapping_id JOIN coe_student_mapping as D ON D.course_batch_mapping_id=A.batch_mapping_id and D.coe_student_mapping_id=B.student_map_id JOIN coe_subjects as C ON C.coe_subjects_id=A.subject_id WHERE C.subject_code="'.$subject['subject_code'].'" and A.coe_subjects_mapping_id="'.$SUB_MAP_ID_GET['coe_subjects_mapping_id'].'" and subject_map_id="'.$SUB_MAP_ID_GET['coe_subjects_mapping_id'].'" and A.subject_id="'.$subject['coe_subjects_id'].'" and B.student_map_id="'.$stu_map_id->coe_student_mapping_id.'" and C.subject_code="'.$subject['subject_code'].'" and A.batch_mapping_id="'.$stu_map_id->course_batch_mapping_id.'" and D.course_batch_mapping_id="'.$stu_map_id->course_batch_mapping_id.'" AND B.year="'.$year.'" AND B.month="'.$month.'"')->queryOne();
         if(!empty($getExam_dertails))
          {
              $is_nominal = 1;
          }
          else
          {
             $is_nominal=0;
          }
      }
      else
      {
        $is_nominal=0;
      }
        
    }
    $hall_type_method = isset($line['C'])?Categorytype::find()->where(['category_type'=>$line['C']])->all():"";

    if(!empty($hall_name) && !empty($hall_type_method) && !empty($hall_description) && !in_array(null, $line, true))
    {        
        $count_1 = "";
        $hall_name = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['A'], -1, $count_1);
        $hall_description = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['B'], -1, $count_1);

        $hall->hall_name = $hall_name;
        $hall->description = $hall_description;
        $hall->hall_type_id = $this->valueReplace($line['C'], Categorytype::getCategoryId()); 
        $hall->created_at = $created_at;
        $hall->created_by = $created_by;
        $hall->updated_at = $created_at;
        $hall->updated_by = $created_by;
        $transaction = Yii::$app->db->beginTransaction();
        try
           {
                if($hall->save()){
                    $transaction->commit();
                    $totalSuccess+=1;
                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);    
                }
                else
                {
                    $transaction->rollback(); 
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Error']);
                }
                
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
               $dispResults[] = array_merge($line, ['type' => 'E',  'message' => $message]);
           }
        
        
         
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
    }           
} // Foreach Ends Here  
$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Hall'];
?>