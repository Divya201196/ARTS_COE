<?php 
use yii\helpers\Url;
use app\models\Categorytype;
use app\models\HallMaster;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{ 
  $hall_columns=['A'=>'Hall Name','B'=>'Description','C'=>'Method (Get the Method From Categories Hall Type)'];
      $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C']];

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

foreach($sheetData as $k => $line)
{   

    $line = array_map('trim', $line);
    $hall = new HallMaster();
    $hall_name = isset($line['A'])?$line['A']:"";
    $hall_description =  isset($line['B'])?$line['B']:"";
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