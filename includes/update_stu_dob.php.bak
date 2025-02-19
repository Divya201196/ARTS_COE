<?php 
use yii\helpers\Url;
use yii\db\Query;
use app\models\Student;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{    
     $stu_columns=['A'=>'Roll no','B'=>'Dob(mm-dd-yyyy)','C'=>'Name','D'=>'Parent / Guardian Mobile Number','E'=>'Email id','F'=>'Student mobile number','G'=>'Aadhar Number','H'=>'Religion','I'=>'Blood group','J'=>'Caste','K'=>'Sub Caste'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H'],'I'=>$line['I'],'J'=>$line['J'],'K'=>$line['K']];

    $mis_match=array_diff_assoc($stu_columns,$template_clumns);
    if($interate==1 && count($mis_match)!=0 && count($mis_match)>0)
    {   
        $misMatchingColumns = '';
        foreach ($mis_match as $key => $value) {
            $misMatchingColumns .= $key.", ";
        }
        $misMatchingColumns = trim($misMatchingColumns,", ");
        $misMatchingColumns = wordwrap($misMatchingColumns, 40, "<br />\n");
        Yii::$app->ShowFlashMessages->setMsg('Error',"<b style='font-size: 17px;'> Column (s) ".$misMatchingColumns." Are Not matching with our Original ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)." Template </b> Please use the Sample Template from the Download Link!!");

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
    $student = new Student();

    if(!empty($line['A']) && !in_array(null, $line, true))
    { 
        //echo $line['I']."--".date('Y-m-d', strtotime(str_replace('-','/', $line['I']))); exit;
        $date_of_birth = isset($line['B'])?date("Y-m-d", PHPExcel_Shared_Date::ExcelToPHP($line['B'])):""; 
        $created_at = date("Y-m-d H:i:s");
        $createdBy = Yii::$app->user->getId(); 
         
        $stu_name = isset($line['C'])?strtoupper(preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['C'], -1, $count_1)):"";
        $religion = isset($line['H'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['H'], -1, $count_1):"";
        $check_stu = Student::find()->where(['register_number'=>$line['A']])->one();
        $update_status = 0;
        if(!empty($check_stu))
         {  
            $connection = Yii::$app->db;
           
            if(isset($line['B']) && !empty($line['B']))
            {
                $command = $connection->createCommand('UPDATE coe_student SET dob="'.$date_of_birth.'",updated_by="'.$createdBy.'",updated_at="'.$created_at.'" WHERE register_number="'.$line['A'].'" ');
                $command->execute();
                $update_status = 1;

            }
            if(isset($line['C']) && !empty($line['C']))
            {
                $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'",name="'.$stu_name.'" WHERE register_number="'.$line['A'].'" ');
                $command->execute();
                $update_status = 1;
            }               
            if(isset($line['D']) && !empty($line['D']))
            {
                $Guardian = Yii::$app->db;
                $Guardian_up = $Guardian->createCommand('UPDATE coe_stu_guardian SET guardian_mobile_no="'.$line['D'].'"  WHERE stu_guardian_id="'.$check_stu->coe_student_id.'" ');
                $Guardian_up->execute();
                $update_status = 1;
            }
            if(isset($line['E']) && !empty($line['E']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'",email_id="'.strtolower(isset($line['E'])?$line['E']:"").'"  WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            }
            if(isset($line['F']) && !empty($line['F']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'",mobile_no="'.$line['F'].'"  WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            }
            if(isset($line['G']) && !empty($line['G']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'", aadhar_number="'.$line['G'].'" WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            }
            if(isset($line['H']) && !empty($line['H']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'" ,religion="'.$religion.'"  WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            } 
            if(isset($line['I']) && !empty($line['I']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'" ,bloodgroup="'.$line['I'].'"  WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            } 
            if(isset($line['J']) && !empty($line['J']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'" ,caste="'.$line['J'].'"  WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            } 
            if(isset($line['K']) && !empty($line['K']))
            {
                 $command = $connection->createCommand('UPDATE coe_student SET updated_by="'.$createdBy.'",updated_at="'.$created_at.'" ,sub_caste="'.$line['K'].'"  WHERE register_number="'.$line['A'].'" ');
                 $command->execute();
                $update_status = 1;
            }                
             
            if($update_status == 1)
            {
                $totalSuccess+=1;
                $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Updated']);                    
            }
            else
            {
                $totalSuccess+=1;
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable to Update']);
            }
            
         }
         else{    
            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'NOT DATA FOUND']);
         } 
        

    } // Not empty of Batch & Other related ids 
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'No data found']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to Insert");
    }           
} // Foreach Ends Here  

$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>"dobupdate"];
?>