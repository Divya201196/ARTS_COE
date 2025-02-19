<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\Student;
use app\models\BatDegReg;
use app\models\CoeBatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Subjects;
use app\models\SubjectsMapping;
use app\models\CoeValueSubjects;
use app\models\CoeValueNominal;
use app\models\Sub;
use app\models\Programme;
use app\models\Regulation;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{    
     $subject_columns=['A'=>'Regulation Year','B'=>'Batch','C'=>'Degree','D'=>'Programme Code','E'=>'Course Code','F'=>'Semester','G'=>'Course Name','H'=>'Paper No','I'=>'Paper Type','J'=>'CIA Min','K'=>'CIA Max','L'=>'ESE Min','M'=>'ESE Max','N'=>'Total min Pass','O'=>'Total Max','P'=>'Credit Points','Q'=>'Course Type','R'=>'Programme Type','S'=>'ESE Value Mark','T'=>'Course Fee','U'=>'Part No'];

        $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H'],'I'=>$line['I'],'J'=>$line['J'],'K'=>$line['K'],'L'=>$line['L'],'M'=>$line['M'],'N'=>$line['N'],'O'=>$line['O'],'P'=>$line['P'],'Q'=>$line['Q'],'R'=>$line['R'],'S'=>$line['S'],'T'=>$line['T'],'U'=>$line['U']];

        $mis_match=array_diff_assoc($subject_columns,$template_clumns);

        if($interate==1 && count($mis_match)!=0 && count($mis_match)>0)
        {
            $misMatchingColumns = '';
            foreach ($mis_match as $key => $value) {
                $misMatchingColumns .= $key.", ";
            }
            
            $misMatchingColumns = trim($misMatchingColumns,', ');
            $misMatchingColumns = wordwrap($misMatchingColumns, 40, "<br />\n");
            Yii::$app->ShowFlashMessages->setMsg('Error',"<b style='font-size: 17px;'> Column (s) ".$misMatchingColumns." Are Not matching with our Original ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." Template </b> Please use the Original Sample Template from the Download Link!!");
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
    $subjects = new CoeValueSubjects();
    $subject_mapping = new Sub();
    //$subject_mapping = new SubjectsMapping();
    $batch = isset($line['B'])?Batch::findOne(['batch_name'=>$line['B']]):"";
    $regulation = isset($line['A'])?Regulation::findOne(['regulation_year'=>$line['A']]):"";
    $programme = isset($line['D'])?Programme::findOne(['programme_code'=>$line['D']]):"";
    $degree = isset($line['C'])?Degree::findOne(['degree_code'=>$line['C']]):"";
    
    if(!empty($batch) && !empty($regulation) && !empty($programme) && !empty($degree) && !in_array(null, $line, true))
    { 
        $batchMapping = CoeBatDegReg::find()->where(['coe_programme_id'=>$programme->coe_programme_id,'coe_batch_id'=>$batch->coe_batch_id,'coe_degree_id'=>$degree->coe_degree_id])->one();                
        $subjects->subject_code = $line['E'];
        $subjects->subject_name = $line['G'];
        $subjects->subject_fee = $line['T'];
        $subjects->CIA_min = $line['J'];
        $subjects->CIA_max = $line['K'];
        $subjects->ESE_min = $line['L'];
        $subjects->ESE_max = $line['M'];   
        $subjects->part_no = $line['U'];     
        $subjects->total_minimum_pass = $line['N'];
        $subjects->credit_points = $line['P'];
        $subjects->end_semester_exam_value_mark =($line['M']+$line['K'])==$line['S']?$line['S']:($line['M']+$line['K']);
        $subjects->created_by = $created_by;
        $subjects->updated_by = $created_by;
        $subjects->created_at = $created_at;
        $subjects->updated_at = $created_at;
        
        $Subjects_check = $subjects::findOne(['subject_code'=>$line['E']]);

        if(empty($Subjects_check->coe_val_sub_id))
        {
            
             if($subjects->save())
               {  
                 
                 $subject_mapping->batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;
                 $subject_mapping->val_subject_id = $subjects->coe_val_sub_id;
                 $subject_mapping->paper_type_id = $this->valueReplace($line['I'], Categorytype::getCategoryId()); 
                 $subject_mapping->subject_type_id = $this->valueReplace($line['Q'], Categorytype::getCategoryId());
                 $subject_mapping->course_type_id = $this->valueReplace($line['R'], Categorytype::getCategoryId());
                 $subject_mapping->paper_no = $line['H'];
                 $subject_mapping->semester = $line['F'];
                 $subject_mapping->migration_status = "NO";
                 $subject_mapping->created_by = $created_by;
                 $subject_mapping->updated_by = $created_by;
                 $subject_mapping->created_at = $created_at;
                 $subject_mapping->updated_at = $created_at;
                 
                 if($subject_mapping->save())
                 { 
                    $totalSuccess+=1;
                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                 }
                 else{ 
                   
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable to Insert']);
                 }                     
               }
               else
               {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Error']);
               }
        }
        else
        {                
            
            if(empty($batchMapping))
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'No Data Found']);
            }
            else
            {

            $subject_id = Sub::findOne(['val_subject_id'=>$Subjects_check->coe_val_sub_id,'batch_mapping_id'=>$batchMapping->coe_bat_deg_reg_id,'semester'=>$line['F']]); 
                    
            if(empty($subject_id->subject_id))
            {   
                 $subject_mapping->batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;
                 $subject_mapping->val_subject_id = $Subjects_check->coe_val_sub_id;
                 $subject_mapping->paper_type_id = $this->valueReplace($line['I'], Categorytype::getCategoryId()); 
                $subject_mapping->subject_type_id = $this->valueReplace($line['Q'], Categorytype::getCategoryId());
                $subject_mapping->course_type_id = $this->valueReplace($line['R'], Categorytype::getCategoryId());
                 $subject_mapping->migration_status = "NO";
                 $subject_mapping->paper_no = $line['H'];
                 $subject_mapping->semester = $line['F'];
                 $subject_mapping->created_by = $created_by;
                 $subject_mapping->updated_by = $created_by;
                 $subject_mapping->created_at = $created_at;
                 $subject_mapping->updated_at = $created_at;
                 if($subject_mapping->save())
                 { 
                    
                    $totalSuccess+=1;
                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
                 }
                 else{ 
                   
                    $dispResults[] = array_merge($line, ['type' => 'E', 'message' => 'Unable to Error']);
                 }
             } // Not Empty of Subject Code 
             else
             {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Imported']);
             }

            } // If Batch Mapping Found then it will update data

           }// If already subject Available  
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
    }           
} // Foreach Ends Here  


try {
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
     $message = "Unknow Error";
 }
  $transaction->rollback(); 
 $dispResults[] = array_merge($line, ['type' => 'E',  'message' => $message]);
}

$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)];
?>