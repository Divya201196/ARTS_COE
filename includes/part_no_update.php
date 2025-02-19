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
use app\models\Programme;
use app\models\Regulation;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{    
     $subject_columns=['A'=>'Subject Code','B'=>'Part No'];

        $template_clumns=['A'=>$line['A'],'B'=>$line['B']];

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

foreach($sheetData as $k => $line)
{    

    $line = array_map('trim', $line);
    $subjects = new Subjects();
    $subject_mapping = new SubjectsMapping();
    $subjects_da = isset($line['A'])?Subjects::findOne(['subject_code'=>$line['A']]):"";    
    if(!empty($subjects_da) && !in_array(null, $line, true))
    {   
        $connection = Yii::$app->db;
        $command1 = $connection->createCommand('UPDATE coe_subjects SET part_no="'.$line['B'].'",updated_by="'.$created_by.'",updated_at="'.$created_at.'" WHERE coe_subjects_id="'.$subjects_da->coe_subjects_id.'" ');
        if($command1->execute())
         { 
            $totalSuccess+=1;
            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);
         }
         else{ 
           
            $dispResults[] = array_merge($line, ['type' => 'E', 'message' => 'Unable to Error']);
         }                     
        

    }// If already subject Available  
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
    }           
} // Foreach Ends Here  

$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Update_part_no'];
?>