<?php 
use yii\helpers\Url;
use app\models\Categorytype;
use yii\db\Query;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\ValuationFaculty;
use app\models\Signup;

$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    $exam_columns=['A'=>'NAME','B'=>'DESIGNATION','C'=>'BOARD','D'=>'FACULTY MODE','E'=>'EXPRIENCE','F'=>'PHONE','G'=>'COLLEGE NAME','H'=>'BANK ACC NO','I'=>'BANK NAME','J'=>'BANK BRANCH','K'=>'BANK IFSC','L'=>'EMAIL','M'=>'NEW PHONE NO','N'=>'DEPARTMENT'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H'],'I'=>$line['I'],'J'=>$line['J'],'K'=>$line['K'],'L'=>$line['L'],'M'=>$line['M'],'N'=>$line['N']];

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

foreach($sheetData as $k => $line)
{    

    $line = array_map('trim', $line);

    $faculty_name = isset($line['A'])?$line['A']:""; 
    $faculty_designation = isset($line['B'])?$line['B']:""; 
    $faculty_board = isset($line['C'])?$this->valueReplace($line['C'], Categorytype::getCategoryId()):"";
   //$faculty_board = isset($line['C'])?$line['C']:""; 
    $faculty_mode = isset($line['D'])?$line['D']:""; 
    $faculty_experience = isset($line['E'])?$line['E']:""; 
    $phone_no = isset($line['F'])?$line['F']:""; 
    $college_code = isset($line['G'])?$line['G']:""; 

  
    $bank_accno ='';
    if(!empty($line['H']))
    {
        if(is_numeric($line['H'])) 
        {

            $acc_no= number_format($line['H'],0,'','');
            $bank_accno=$acc_no;
        }
        else
        {
             $bank_accno=$line['H'];
        }
    }
    //echo $bank_accno; exit;
    $bank_name = isset($line['I'])?$line['I']:""; 
    $bank_branch = isset($line['J'])?$line['J']:""; 
    $bank_ifsc = isset($line['K'])?$line['K']:""; 
    $email = isset($line['L'])?$line['L']:""; 
    $new_ph_no = isset($line['M'])?$line['M']:""; 
    $department = isset($line['I'])?$line['I']:"";
   // print_r($faculty_board);exit;
    
    if(!empty($faculty_name) && !empty($faculty_designation) && !empty($faculty_board) && !empty($faculty_mode) && !empty($faculty_experience) && !empty($phone_no) && !empty($email))
    {      

        $update_id = Yii::$app->db->createCommand("select coe_val_faculty_id from coe_valuation_faculty where phone_no = '".$phone_no."'")->queryScalar();
        
        $check = Yii::$app->db->createCommand("select coe_val_faculty_id from coe_valuation_faculty where phone_no = '".$new_ph_no."'")->queryScalar();

        if(empty($update_id) && empty($check))
        {    
            if($faculty_mode=='INTERNAL')
            {

                $checkstatus = Yii::$app->db->createCommand('SELECT count(*) as count FROM coe_valuation_faculty  WHERE phone_no="'.$phone_no.'"  AND email="'.$email.'" AND faculty_board="'.$faculty_board.'" ')->queryScalar(); 
               
                if($checkstatus==0)
                {         
                        
                        $model = new ValuationFaculty();
                        $model->faculty_name = $faculty_name;
                        $model->faculty_designation = $faculty_designation;
                        $model->faculty_board = $faculty_board;
                        $model->faculty_mode = $faculty_mode;
                        $model->faculty_experience = $faculty_experience;
                        $model->bank_accno = $bank_accno;
                        $model->bank_name = $bank_name;
                        $model->bank_branch = $bank_branch;
                        $model->bank_ifsc = $bank_ifsc;
                        $model->phone_no = $phone_no;
                        $model->email = $email;
                        $model->department = $department;
                        $model->created_by = Yii::$app->user->getId();
                        $model->created_at = new \yii\db\Expression('NOW()');

                        if($model->save(false))
                        { 
                            unset($model);
                            $model = new ValuationFaculty();
                            $totalSuccess+=1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'SUCCESS INSERTED']);

                        
                        }
                                                  
                        else
                        {                         
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        } 
                   
                                         
                }
                else
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'ALREADY DATA FOUND']);
                }
            }
            else if($faculty_mode=='EXTERNAL' && !empty($college_code))
            {
                 $checkstatus = Yii::$app->db->createCommand('SELECT count(*) as count FROM coe_valuation_faculty  WHERE phone_no="'.$phone_no.'"  AND email="'.$email.'" AND faculty_board="'.$faculty_board.'" ')->queryScalar(); 
               
                 if($checkstatus==0)
                {         
                        
                        $model = new ValuationFaculty();
                        $model->faculty_name = $faculty_name;
                        $model->faculty_designation = $faculty_designation;
                        $model->faculty_board = $faculty_board;
                        $model->faculty_mode = $faculty_mode;
                        $model->faculty_experience = $faculty_experience;
                        $model->bank_accno = $bank_accno;
                        $model->bank_name = $bank_name;
                        $model->bank_branch = $bank_branch;
                        $model->bank_ifsc = $bank_ifsc;
                        $model->phone_no = $phone_no;
                        $model->email = $email;
                        $model->department = $department;
                        $model->college_code = $college_code;
                        $model->created_by = Yii::$app->user->getId();
                        $model->created_at = new \yii\db\Expression('NOW()');

                        if($model->save(false))
                        { 
                            unset($model);
                            $model = new ValuationFaculty();
                            $totalSuccess+=1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'SUCCESS INSERTED']);
                        
                        }
                        else
                        {                         
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        } 
                   
                                         
                }
                else
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'ALREADY DATA FOUND']);
                }
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'COLLEGE NAME MISSING']);
            }

        }
        else if(!empty($update_id))
        {
            if($new_ph_no!=$phone_no && $new_ph_no!='')
            {
                $phone_no=$new_ph_no;
            }

            if($faculty_mode=='INTERNAL')
            {
     
                        $model = ValuationFaculty::findOne($update_id);
                        $model->faculty_name = $faculty_name;
                        $model->faculty_designation = $faculty_designation;
                        $model->faculty_board = $faculty_board;
                        $model->faculty_mode = $faculty_mode;
                        $model->faculty_experience = $faculty_experience;
                         $model->bank_accno = $bank_accno;
                         $model->bank_name = $bank_name;
                        $model->bank_branch = $bank_branch;
                        $model->bank_ifsc = $bank_ifsc;
                        $model->phone_no = $phone_no;
                         $model->email = $email;
                        $model->updated_by = Yii::$app->user->getId();
                        $model->updated_at = new \yii\db\Expression('NOW()');

                        if($model->save(false))
                        { 
                            unset($model);
                            $totalSuccess+=1;
                           $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'SUCCESS UPDATED']);

                         

                        }
                        else
                        {                         
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        } 
                
            }
            else if($faculty_mode=='EXTERNAL' && !empty($college_code))
            {
                      
                        
                        $model = ValuationFaculty::findOne($update_id);
                        $model->faculty_name = $faculty_name;
                        $model->faculty_designation = $faculty_designation;
                        $model->faculty_board = $faculty_board;
                        $model->faculty_mode = $faculty_mode;
                        $model->faculty_experience = $faculty_experience;
                        $model->bank_accno = $bank_accno;
                        $model->bank_name = $bank_name;
                        $model->bank_branch = $bank_branch;
                        $model->bank_ifsc = $bank_ifsc;
                        $model->phone_no = $phone_no;
                        $model->email = $email;
                        $model->college_code = $college_code;
                        $model->updated_by = Yii::$app->user->getId();
                        $model->updated_at = new \yii\db\Expression('NOW()');

                        if($model->save(false))
                        { 
                            unset($model);
                            $totalSuccess+=1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'SUCCESS UPDATED']);


                         
                        }
                        else
                        {                         
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
                        } 
                   
                
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'COLLEGE NAME MISSING']);
            }
        }
        else
        {
            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Please check faculty Phone Number']);
        }
    } // Not empty of Batch & Other related ids
    else
    {                    
        $dispResults[] = array_merge($line, 
            ['type' => 'E',  'message' => 'Data submision is worng or Some data missing']);
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


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Valuation Faculty'];
?>