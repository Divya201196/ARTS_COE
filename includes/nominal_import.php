<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\StudentMapping;
use app\models\BatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Subjects;
use app\models\Nominal;
use app\models\SubjectsMapping;
use app\models\Programme;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{    
     $subject_columns=['A'=>'Batch','B'=>'Degree','C'=>'Programme Code','D'=>'Register Number','E'=>'Semester','F'=>'Course Code'];

        $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F']];

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
 $transaction = Yii::$app->db->beginTransaction();
foreach($sheetData as $k => $line)
{    
    $line = array_map('trim', $line);
    $nominal = new Nominal();    
    $batch = isset($line['A'])?Batch::findOne(['batch_name'=>$line['A']]):"";
    $programme = isset($line['C'])?Programme::findOne(['programme_code'=>$line['C']]):"";
    $degree = isset($line['B'])?Degree::findOne(['degree_code'=>$line['B']]):"";
    
    if(!empty($batch) &&  !empty($programme) && !empty($degree) && !in_array(null, $line, true))
    { 
        $batchMapping = BatDegReg::find()->where(['coe_programme_id'=>$programme->coe_programme_id,'coe_batch_id'=>$batch->coe_batch_id,'coe_degree_id'=>$degree->coe_degree_id])->one();        
        $student = Student::find()->where(['register_number'=>$line['D']])->one();
        $subjects_LIST = Subjects::find()->where(['subject_code'=>$line['F']])->all();

        if(!empty($subjects_LIST) && count($subjects_LIST)>1)
        {
            foreach ($subjects_LIST as $key => $value) 
            {
                $subjectsMapp = SubjectsMapping::find()->where(['subject_id'=>$value['coe_subjects_id'],'batch_mapping_id'=>$batchMapping->coe_bat_deg_reg_id,'semester'=>$line['E']])->one();
                if(!empty($subjectsMapp))
                {
                    $subjects = Subjects::findOne($subjectsMapp->subject_id);
                    break;
                }
            }
        }
        else
        {
            $subjects = Subjects::find()->where(['subject_code'=>$line['F']])->one();
        }
        
        $stu_elective = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Elective%'")->queryScalar();
        if(!empty($batchMapping) && !empty($student) && !empty($subjects))
        {
            $subjectsMap = SubjectsMapping::find()->where(['subject_id'=>$subjects->coe_subjects_id,'subject_type_id'=>$stu_elective])->one();
            if(!empty($subjectsMap))
            {
                $getSection = StudentMapping::find()->where(['student_rel_id'=>$student->coe_student_id])->one();
                $nominal->course_batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;
                $nominal->coe_student_id = $student->coe_student_id;
                $nominal->section_name = $getSection->section_name;
                $nominal->semester = $line['E'];
                $nominal->coe_subjects_id = $subjects->coe_subjects_id;
                $nominal->created_by = $created_by;
                $nominal->updated_by = $created_by;
                $nominal->created_at = $created_at;
                $nominal->updated_at = $created_at;
                $checkNominal = Nominal::find()->where(['coe_student_id'=>$student->coe_student_id,'coe_subjects_id'=>$subjects->coe_subjects_id,'course_batch_mapping_id'=>$batchMapping->coe_bat_deg_reg_id])->all();
                //print_r($checkNominal);exit;
                if(empty($checkNominal))
                 { 
                    if($nominal->save())
                    {
                        $totalSuccess+=1;
                        $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);     
                    }
                    else
                    {
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable to Import Nominal']);
                    }
                   
                 }
                 else
                 {  
                      $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Available']);
                 }
            }
            else
            {  
                  $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NO DATA FOUND']);
            }            
        }
        else
        {                    
            $dispResults[] = array_merge($line, 
                ['type' => 'E',  'message' => 'No Information Found']);
            Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
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

$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_NOMINAL)];
?>