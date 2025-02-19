<?php 
use yii\helpers\Url;
use app\models\Categorytype;
use yii\db\Query;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\ValuationScrutiny;
use app\models\Signup;

$interate = 1; // Check only 1 time for Sheet Columns
foreach($sheetData as $k => $line)
{ 
    $exam_columns=['A'=>'NAME','B'=>'DESIGNATION','C'=>'BOARD','D'=>'PASSWORD','E'=>'EMAIL'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E']];

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

    $name = isset($line['A'])?$line['A']:""; 
    $designation = isset($line['B'])?$line['B']:""; 
    $dept = isset($line['C'])?$line['C']:""; 
    $phone = isset($line['D'])?$line['D']:""; 
    $email = isset($line['E'])?$line['E']:"";
   
    
    if(!empty($name) && !empty($designation) && !empty($dept) && !empty($phone) && !empty($email))
    {      

        $update_id = Yii::$app->db->createCommand("select coe_scrutiny_id from coe_valuation_scrutiny where name = '".$name."'")->queryScalar();
        
        if(empty($update_id))
        {    
            $checkstatus = Yii::$app->db->createCommand('SELECT count(*) as count FROM coe_valuation_scrutiny  WHERE phone_no="'.$phone.'" AND email="'.$email.'"')->queryScalar(); 
               
            if($checkstatus==0)
            {         
                $model = new ValuationScrutiny();
                $model->name = $name;
                $model->designation = $designation;
                $model->department = $dept;
                $model->phone_no = $phone;
                $model->email = $email;
                $model->created_by = Yii::$app->user->getId();
                $model->created_at = new \yii\db\Expression('NOW()');

                        if($model->save(false))
                        { 
                           
                            unset($model);
                            $model = new ValuationScrutiny();
                            $totalSuccess+=1;
                            
                             $check_user = Yii::$app->db->createCommand('SELECT count(*) as count FROM user  WHERE email="'.$email.'"')->queryScalar();
                                if(empty($check_user))
                                {
                                    $userModel = new Signup();
                                    $userModel->username = strtolower($name);
                                    $userModel->password = $phone;
                                    $userModel->ConfirmPassword = $phone;
                                    $userModel->email = $email;
                                    $userModel->signup();
                                    $created = strtotime(ConfigUtilities::getCreatedTime());
                                    $user_id_LAST = Yii::$app->db->createCommand('SELECT id FROM user order by id desc limit 1')->queryScalar();
                                    $Assing = Yii::$app->db->createCommand('INSERT INTO auth_assignment (`item_name`,`user_id`,`created_at`) values ("Scrutiny Access","'.$user_id_LAST.'","'.$created.'" )')->execute();

                                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'SUCCESS INSERTED AND USER ID CREATED']);
                                }
                                else
                                {                         
                                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'User already exist! Unable to Create User']);
                                } 
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
            $model = ValuationScrutiny::findOne($update_id);
            $model->name = $name;
            $model->designation = $designation;
            $model->department = $dept;
            //$model->phone_no = $phone;
            $model->email = $email;
            $model->updated_by = Yii::$app->user->getId();
            $model->updated_at = new \yii\db\Expression('NOW()');

            if($model->save(false))
            { 

                unset($model);
                $totalSuccess+=1;
                
                $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'SUCCESSFULLY UPDATED 
                    ']);
              

            }
            else
            {                         
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Unable To Import']);
            } 
                
            
            
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


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Valuation Scrutiny'];
?>