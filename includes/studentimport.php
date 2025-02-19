<?php 
use yii\helpers\Url;
use app\models\Batch;
use app\models\BatDegReg;
use app\models\Categorytype;
use app\models\Degree;
use app\models\Programme;
use app\models\Student;
use app\models\StudentMapping;
use app\models\StuAddress;
use app\models\Guardian;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{    
     $stu_columns=['A'=>'YEAR','B'=>'DEGREE','C'=>'MAJOR','D'=>'SECTION','E'=>'STUDENT_NAME','F'=>'DOB(mm-dd-yyyy)','G'=>'GENDER','H'=>'BLOOD_GROUP','I'=>'RELIGION','J'=>'COMMUNITY','K'=>'CASTE','L'=>'PARENT_NAME','M'=>'DOOR_NO/STREET','N'=>'TOWN/TALUK','O'=>'CITY/DISTRICT','P'=>'STATE','Q'=>'EMAIL_ID','R'=>'PIN_CODE','S'=>'PARENT_PHONE','T'=>'STUDENT_PHONE','U'=>'REGISTER_NUMBER','V'=>'PART_KEY','W'=>'DOJ(mm-dd-yyyy)','X'=>'Aadhar Number','Y'=>'abc_number_id','Z'=>'EMIS_no'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H'],'I'=>$line['I'],'J'=>$line['J'],'K'=>$line['K'],'L'=>$line['L'],'M'=>$line['M'],'N'=>$line['N'],'O'=>$line['O'],'P'=>$line['P'],'Q'=>$line['Q'],'R'=>$line['R'],'S'=>$line['S'],'T'=>$line['T'],'U'=>$line['U'],'V'=>$line['V'],'W'=>$line['W'],'X'=>$line['X'],'Y'=>$line['Y'],'Z'=>$line['Z']];

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
$Adm_cat = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Management Quota%'")->queryScalar();

$stu_status = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%General%'")->queryScalar();
unset($sheetData[1]);
$transaction = Yii::$app->db->beginTransaction();
foreach($sheetData as $k => $line)
{    
    $line = array_map('trim', $line);
    $student = new Student();
    $stuAddress = new StuAddress();
    $guardian = new Guardian();
    $stuMapping = new StudentMapping();
    
    $batch = isset($line['A'])?Batch::findOne(['batch_name'=>$line['A']]):"";
    $degree = isset($line['B'])?Degree::findOne(['degree_code'=>$line['B']]):"";
    $programme = isset($line['C'])?Programme::findOne(['programme_code'=>$line['C']]):"";

    if(!empty($batch) && !empty($programme) && !empty($degree) && !in_array(null, $line, true))
    { 

        $batchMapping = BatDegReg::find()->where(['coe_programme_id'=>$programme->coe_programme_id,'coe_batch_id'=>$batch->coe_batch_id,'coe_degree_id'=>$degree->coe_degree_id])->one();              
        if(isset($line['G'])){
            $stu_gender = $line['G']=="F" || $line['G']=="FEMALE" || $line['G']=="Female" || $line['G']=="female" ? "F" : "M";    
        }
        else
        {
            $stu_gender ="M";
        }
        
        $count_1 = "";
        $stu_name = isset($line['E'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['E'], -1, $count_1):"";
        $religion = isset($line['I'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['I'], -1, $count_1):"";
      //  $nationality = isset($line['AD'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['AD'], -1, $count_1):"";
        $caste = isset($line['J'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['J'], -1, $count_1):"";
        $sub_caste = isset($line['K'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['K'], -1, $count_1):"";
        //echo $line['I']."--".date('Y-m-d', strtotime(str_replace('-','/', $line['I']))); exit;
        $date_of_birth = isset($line['F'])?date("Y-m-d", PHPExcel_Shared_Date::ExcelToPHP($line['F'])):""; 
        $admission_date = isset($line['W'])?date("Y-m-d", PHPExcel_Shared_Date::ExcelToPHP($line['W'])):""; 
        if(!empty($batchMapping))
        {
            $student_section=array();
            $student_section = array_filter($student_section);
            $section_list = $batchMapping->no_of_section!=0 ? $batchMapping->no_of_section:0;
            for ($char = 65; $char < 65+$section_list; $char++) {
                $student_section[chr($char)]= chr($char);
            }
            if(in_array($line['D'], $student_section))
            {
                $student->name = $stu_name;
                $student->register_number = isset($line['U'])?strtoupper($line['U']):"";
                $student->gender = $stu_gender;
                $student->dob = $date_of_birth;


                $student->religion = $religion;
               // $student->nationality = $nationality;
                $student->caste = $caste;
                $student->sub_caste = $sub_caste;
                $student->bloodgroup = isset($line['H'])?$line['H']:"";
                $student->email_id = strtolower(isset($line['Q'])?$line['Q']:"");
                $student->admission_year = date("Y");
                $student->admission_date = $admission_date;
                $student->mobile_no = isset($line['T'])?$line['T']:"";
                $student->admission_status =$stu_status;  
                $student->aadhar_number = isset($line['X'])?$line['X']:"";
                $student->abc_number_id = isset($line['Y'])?$line['Y']:""; 
                $student->EMIS_no = isset($line['Z'])?$line['Z']:"";      
                $student->created_at = $created_at;
                $student->created_by = $created_by;
                $student->updated_at = $created_at;
                $student->updated_by = $created_by; 
if(!empty($date_of_birth)){

                $student_check = Student::findOne(['register_number'=>$line['U']]);
 
                if( empty($student_check) && $student->save(false))
                   {  
                    
                     // SAVE IN STUDENT ADDRESS 
                    $stuAddress->stu_address_id =  $student->coe_student_id;

                    $stuAddress->current_address =  $line['M'];
                    $stuAddress->current_city =  $line['O'];
                    $stuAddress->current_state =  $line['P'];
                  //  $stuAddress->current_country =  $line['T'];
                    $stuAddress->current_pincode =  $line['R'];
                    $stuAddress->permanant_city =  $line['O'];
                    $stuAddress->permanant_address =  $line['M'];
                    $stuAddress->permanant_state =  $line['P'];
                 //   $stuAddress->permanant_country =  $line['Z'];
                  $stuAddress->permanant_pincode =  $line['R'];

                    $guardian->stu_guardian_id = $student->coe_student_id;

                    $guardian->guardian_name = $line['L'];
                  //  $guardian->guardian_relation = $line['N'];
                    $guardian->guardian_mobile_no = $line['S'];
                    $guardian->guardian_address = $line['M'];
                  //  $guardian->guardian_email = $line['Q'];
                  //  $guardian->guardian_occupation = $line['R'];
                 //   $guardian->guardian_income = $line['P'];

                    $stuMapping->student_rel_id = $student->coe_student_id;

                    $stuMapping->course_batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;
                    $stuMapping->section_name = $line['D'];
                   $stuMapping->status_category_type_id = $student->admission_status ;
                   $stuMapping->admission_category_type_id = $Adm_cat;
                    $stuMapping->created_at = $created_at;
                    $stuMapping->created_by = $created_by;
                    $stuMapping->updated_at = $created_at;
                    $stuMapping->updated_by = $created_by;

                     if($guardian->save(false) && $stuAddress->save(false) && $stuMapping->save(false))
                     {
                        $guardian->save();
                        $stuAddress->save();
                        $stuMapping->save();
                        $totalSuccess+=1;
                        $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'No Errors Found']);
                     }
                     else{ 
                        $id = $student->coe_student_id;
                        $model = $student->findModel($id);                    
                        $stuMapping = StudentMapping::deleteAll(['student_rel_id' => $id]);
                        $guardian = Guardian::deleteAll(['stu_guardian_id' => $id]);
                        $stuAddress = StuAddress::deleteAll(['stu_address_id' => $id]);
                        $model->delete();  
                        $transaction->rollback();      
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => ' Unable to Insert']);
                     }  

                   }


                   else
                   {
                        $transaction->rollback();  
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Duplicate entry']);

                   }
                  
}

else{

  $transaction->rollback();  
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'DOB is empty']);

}


                
                
               } // Section Checking for Student Completed
               else
               {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SECTION)." Not Found"]);
               }
        }// Not empty of batch mapping id which means no Information found on Degree / Batch / Programme 

    } // Not empty of Batch & Other related ids 






    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'No data found']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
       
    }    


} // Foreach Ends Here  

try
{
 
    $transaction->commit();               
}
catch(\Exception $e)
{
     $transaction->rollBack();
     if($e->getCode()=='23000')
     {
         $message = "Duplicate Entry";
     }
     else if($e->getCode()=='8')
     {
        $message = "Values Should Not Be Empty";
     }
     else 
     {
         $message = "Error";
     }
     $dispResults[] = array_merge($line, ['type' => 'E',  'message' => $message]);


}


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)];
?>