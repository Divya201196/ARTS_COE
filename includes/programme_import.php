<?php 
use yii\helpers\Url;
use app\models\Programme;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    if($interate==1)
    {
        $programe_columns=['A'=>'Programme Code','B'=>'Description'];
        $template_clumns=['A'=>$line['A'],'B'=>$line['B']];
        $mis_match=array_diff_assoc($programe_columns,$template_clumns);
        if(count($mis_match)!=0 && count($mis_match)>0)
        {
            $misMatchingColumns = '';
            foreach ($mis_match as $key => $value) {
                $misMatchingColumns .= $key.", ";
            }
            $misMatchingColumns = trim($misMatchingColumns,', ');
            $misMatchingColumns = wordwrap($misMatchingColumns, 10, "<br />\n");
            Yii::$app->ShowFlashMessages->setMsg('Error',"<b style='font-size: 17px;'> Column (s) ".$misMatchingColumns." Not matching with our Original ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)." Template </b> Please use the Original Sample Template from the Download Link!!");
            return Yii::$app->response->redirect(Url::to(['import/index']));
        }
        
    }
    else
    {
        break;
    }    
    $interate+=2;
}
unset($sheetData[1]);
$transaction = Yii::$app->db->beginTransaction();
foreach($sheetData as $k => $line)
{    
    $line = array_map('trim', $line);
    $model = new Programme();
    $programmeCode = isset($line['A'])?Programme::findOne(['programme_code'=>$line['A']]):"";
    $programmeName =  isset($line['B'])?Programme::findOne(['programme_name'=>$line['B']]):"";
        
    if(empty($programmeCode) && empty($programmeName) && !in_array(null, $line, true))
    {      
            $programme_find = isset($line['A'])?Programme::findOne(['programme_code'=>$line['A']]):0;
            if($programme_find && count($programme_find)>0)
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Available']);
            }
            else if(isset($line['A']) && isset($line['B']))
            {
                $model->programme_code = $line['A'];
                $model->programme_name = $line['B'];               
                $model->created_at = $created_at;
                $model->created_by = $created_by;
                $model->updated_at = $created_at;
                $model->updated_by = $created_by;
                if($model->save())
                {
                   
                    $totalSuccess+=1;
                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'No Errors Found']);    
                }
                else
                {
                    
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Error']);
                }
                 
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => "Empty Values Found"]);
            }
        
         
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Oops '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)." Already Available"]);
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
     else if($e->getCode()=='8')
     {
         $message = "Empty Values Found";
     }
     else
     {
         $message = "Error";
     }
     $transaction->rollback(); 
     $dispResults[] = array_merge($line, ['type' => 'E',  'message' => $message]);
 }
$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_PROGRAMME)];
?>