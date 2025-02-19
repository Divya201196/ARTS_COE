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
     $stu_columns=['A'=>'Batch','B'=>'Degree','C'=>'Programme Code','D'=>'Section','E'=>'Roll no','F'=>'Name of the student (in caps)','G'=>'Gender','H'=>'Blood group','I'=>'Dob(mm-dd-yyyy)','J'=>'Admission Date(mm-dd-yyyy)','K'=>'Adimission category','L'=>'Student Status','M'=>'Parent / Guardian Name','N'=>'Parent / Guardian Relation','O'=>'Parent / Guardian Mobile Number','P'=>'Parent / Guardian Income','Q'=>'Parent / Guardian Email','R'=>'Parent / Guardian Occupation','S'=>'Parent / Guardian Address','T'=>'Current country','U'=>'Current state','V'=>'Current city','W'=>'Current Address','X'=>'Current pincode','Y'=>'Permanent Address','Z'=>'Permanent country','AA'=>'Permanent city','AB'=>'Permanent state','AC'=>'Permanent pincode','AD'=>'Nationality','AE'=>'Religion','AF'=>'Caste','AG'=>'Sub Caste','AH'=>'Email id','AI'=>'Student mobile number','AJ'=>'Aadhar Number'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H'],'I'=>$line['I'],'J'=>$line['J'],'K'=>$line['K'],'L'=>$line['L'],'M'=>$line['M'],'N'=>$line['N'],'O'=>$line['O'],'P'=>$line['P'],'Q'=>$line['Q'],'R'=>$line['R'],'S'=>$line['S'],'T'=>$line['T'],'U'=>$line['U'],'V'=>$line['V'],'W'=>$line['W'],'X'=>$line['X'],'Y'=>$line['Y'],'Z'=>$line['Z'],'AA'=>$line['AA'],'AB'=>$line['AB'],'AC'=>$line['AC'],'AD'=>$line['AD'],'AE'=>$line['AE'],'AF'=>$line['AF'],'AG'=>$line['AG'],'AH'=>$line['AH'],'AI'=>$line['AI'],'AJ'=>$line['AJ']];

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
        $stu_name = isset($line['F'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['F'], -1, $count_1):"";
        $religion = isset($line['AE'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['AE'], -1, $count_1):"";
        $nationality = isset($line['AD'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['AD'], -1, $count_1):"";
        $caste = isset($line['AF'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['AF'], -1, $count_1):"";
        $sub_caste = isset($line['AG'])?preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['AG'], -1, $count_1):"";
        //echo $line['I']."--".date('Y-m-d', strtotime(str_replace('-','/', $line['I']))); exit;
        $date_of_birth = isset($line['I'])?date("Y-m-d", PHPExcel_Shared_Date::ExcelToPHP($line['I'])):""; 
        $admission_date = isset($line['J'])?date("Y-m-d", PHPExcel_Shared_Date::ExcelToPHP($line['J'])):""; 
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
                $student->register_number = isset($line['E'])?strtoupper($line['E']):"";
                $student->gender = $stu_gender;
                $student->dob = $date_of_birth;
                $student->religion = $religion;
                $student->nationality = $nationality;
                $student->caste = $caste;
                $student->sub_caste = $sub_caste;
                $student->bloodgroup = isset($line['H'])?$line['H']:"";
                $student->email_id = strtolower(isset($line['AH'])?$line['AH']:"");
                $student->admission_year = date("Y");
                $student->admission_date = $admission_date;
                $student->mobile_no = isset($line['AI'])?$line['AI']:"";
                $student->admission_status = isset($line['L'])?$this->valueReplace($line['L'], Categorytype::getCategoryId()):"";
                ;
                $student->aadhar_number = isset($line['AJ'])?$line['AJ']:"";
                $student->created_at = $created_at;
                $student->created_by = $created_by;
                $student->updated_at = $created_at;
                $student->updated_by = $created_by; 

                if($student->save(false))
                   {  
                    
                     // SAVE IN STUDENT ADDRESS 
                    $stuAddress->stu_address_id =  $student->coe_student_id;

                    $stuAddress->current_address =  $line['W'];
                    $stuAddress->current_city =  $line['V'];
                    $stuAddress->current_state =  $line['U'];
                    $stuAddress->current_country =  $line['T'];
                    $stuAddress->current_pincode =  $line['X'];
                    $stuAddress->permanant_city =  $line['AA'];
                    $stuAddress->permanant_address =  $line['Y'];
                    $stuAddress->permanant_state =  $line['AB'];
                    $stuAddress->permanant_country =  $line['Z'];
                    $stuAddress->permanant_pincode =  $line['AC'];

                    $guardian->stu_guardian_id = $student->coe_student_id;

                    $guardian->guardian_name = $line['M'];
                    $guardian->guardian_relation = $line['N'];
                    $guardian->guardian_mobile_no = $line['O'];
                    $guardian->guardian_address = $line['S'];
                    $guardian->guardian_email = $line['Q'];
                    $guardian->guardian_occupation = $line['R'];
                    $guardian->guardian_income = $line['P'];

                    $stuMapping->student_rel_id = $student->coe_student_id;

                    $stuMapping->course_batch_mapping_id = $batchMapping->coe_bat_deg_reg_id;
                    $stuMapping->section_name = $line['D'];
                    $stuMapping->status_category_type_id = $this->valueReplace($line['L'], Categorytype::getCategoryId()); 
                    $stuMapping->admission_category_type_id = $this->valueReplace($line['K'], Categorytype::getCategoryId());
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
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable to Perform']);
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