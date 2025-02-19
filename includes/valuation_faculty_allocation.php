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
    $exam_columns=['A'=>'YEAR','B'=>'MONTH','C'=>'QP CODE','D'=>'COVER NUMBER','E'=>'BOARD','F'=>'FACULTY EMAIL','G'=>'VALUATION DATE(DD-MM-YYYY)','H'=>'VALUATION SESSION'];

    $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H']];

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
    //echo $line['G']; exit;
    $year = isset($line['A'])?$line['A']:""; 
    $month = isset($line['B'])?$this->valueReplace($line['B'], Categorytype::getCategoryId()):"";
    $qpcode = isset($line['C'])?$line['C']:"";
    $coverno = isset($line['D'])?$line['D']:""; 
    $board = isset($line['E'])?$this->valueReplace($line['E'], Categorytype::getCategoryId()):"";
    if($board!=''){$board=$line['E'];}
    $faculty_email = isset($line['F'])?$line['F']:"";
    $valuation_date = isset($line['G'])?$line['G']:""; 
    if($valuation_date!=''){$valuation_date = $line['G']; }   
    $valuation_session = isset($line['H'])?$line['H']:""; 
  
    $userid = Yii::$app->user->getId();
    //echo $year.$month.$board; exit;
   
     $totalSuccess=0;
    if(!empty($year) && !empty($month) && !empty($qpcode) && !empty($coverno) && !empty($board) && !empty($faculty_email) && !empty($valuation_date) && !empty($valuation_session))
    {      
        $check = Yii::$app->db->createCommand("select coe_val_faculty_id from coe_valuation_faculty where email = '".$faculty_email."'")->queryScalar();


        $update_id = Yii::$app->db->createCommand("select val_faculty_all_id from coe_valuation_faculty_allocate where exam_year = '".$year."' AND exam_month='".$month."' AND subject_code='".$qpcode."' AND subject_pack_i='".$coverno."'")->queryScalar();
        //print_r($check);exit;


        if(empty($update_id) && !empty($check))
        {    
             $check_val_fact = Yii::$app->db->createCommand("SELECT sum(total_answer_scripts) FROM coe_valuation_faculty_allocate  WHERE exam_month='" . $month . "' AND exam_year='" . $year . "' AND valuation_date='".date('Y-m-d',strtotime($valuation_date))."' AND valuation_session='".$valuation_session."' AND coe_val_faculty_id='".$check."'")->queryScalar();



            $total_pack1 = Yii::$app->db->createCommand("SELECT count(stu_reg_no) FROM coe_answerpack_regno  WHERE exam_month='" . $month . "' AND exam_year='" . $year . "' AND answer_packet_number='" . $coverno . "'")->queryScalar(); 

            $totpack=$total_pack1; 

            if(empty($check_val_fact))
            {
                $ct=$totpack;
            }
            else
            {
                $ct=$check_val_fact+$totpack;
            }

            if($totpack!=0)
            {
                if(($ct)<=100)
                {
                    $scrutiny_id = Yii::$app->db->createCommand("SELECT coe_scrutiny_id FROM coe_valuation_scrutiny  WHERE department='" . $board . "'")->queryScalar();

                    if($scrutiny_id)
                    {
                        $insertqry='INSERT into coe_valuation_faculty_allocate(scrutiny_status,coe_scrutiny_id,scrutiny_date,scrutiny_session,board,coe_val_faculty_id,exam_year,exam_month,subject_code,subject_pack_i,total_answer_scripts,valuation_date,valuation_session,valuation_status,created_at,created_by) values(1,"'.$scrutiny_id.'","'.date('Y-m-d',strtotime($valuation_date)).'","'.$valuation_session.'","'.$board.'","'.$check.'","'.$year.'","'.$month.'","'.$qpcode.'","'.$coverno.'","'.$totpack.'","'.date('Y-m-d',strtotime($valuation_date)).'","'.$valuation_session.'",3,"'.date('Y-m-d H:i:s').'","'.$userid.'") '; 

                        $insert = Yii::$app->db->createCommand($insertqry)->execute();
                   
                        if($insert)
                        {
                            $totalSuccess=$totalSuccess+1;
                            $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Faculty Assigned Succesfully']);
                        }
                        else
                        {
                            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Faculty Not Assigned! Please check']);
                        }
                    }
                    else
                    {
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Scrutiny Not created For '.$board.' Board Please Check']);
                    }
                }
                else
                {
                   $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Maximum Script 50 Already Assigned '.$check_val_fact]);
                }
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Total Script is Zero, Please Check Print Register Number Report']);
            }
        }
        else if(!empty($update_id) && !empty($check))
        {
            $getallocatedata = Yii::$app->db->createCommand("select * from coe_valuation_faculty_allocate where exam_year = '".$year."' AND exam_month='".$month."' AND subject_code='".$qpcode."' AND subject_pack_i='".$coverno."'")->queryone();


            if(!empty($getallocatedata))
            {
                $checkmarkentry = Yii::$app->db->createCommand("SELECT count(*) FROM coe_valuation_mark_details  WHERE month='" . $month . "' AND year='" . $year . "' AND val_faculty_all_id='" . $update_id . "'")->queryScalar(); 
                if($checkmarkentry==0)
                {
                    $check_val_fact = Yii::$app->db->createCommand("SELECT sum(total_answer_scripts) FROM coe_valuation_faculty_allocate  WHERE exam_month='" . $month . "' AND exam_year='" . $year . "' AND valuation_date='".date('Y-m-d',strtotime($valuation_date))."' AND valuation_session='".$valuation_session."' AND coe_val_faculty_id='".$check."'")->queryScalar();

                    $total_pack1 = Yii::$app->db->createCommand("SELECT count(stu_reg_no) FROM coe_answerpack_regno  WHERE exam_month='" . $month . "' AND exam_year='" . $year . "' AND answer_packet_number='" . $coverno . "'")->queryScalar();


                    $totpack=$total_pack1;

                    if(empty($check_val_fact))
                    {
                        $ct=$totpack;
                    }
                    else
                    {
                        $ct=$check_val_fact+$totpack;
                    }

                    if($totpack!=0)
                    {
                        if(($ct)<=60)
                        {
                            $scrutiny_id = Yii::$app->db->createCommand("SELECT coe_scrutiny_id FROM coe_valuation_scrutiny  WHERE department='" . $board . "'")->queryScalar();

                            if($scrutiny_id)
                            {
                            
                                $updateqry = 'UPDATE coe_valuation_faculty_allocate SET coe_scrutiny_id="'.$scrutiny_id.'", scrutiny_date="'.date('Y-m-d',strtotime($valuation_date)).'", scrutiny_session="'.$valuation_session.'", board="'.$board.'", coe_val_faculty_id="'.$check.'", valuation_date="'.date('Y-m-d',strtotime($valuation_date)).'", valuation_session="'.$valuation_session.'", updated_at="'.date('Y-m-d H:i:s').'", updated_by="'.$userid.'" WHERE val_faculty_all_id="'.$update_id.'"';

                                $updatedata = Yii::$app->db->createCommand($updateqry)->execute();
                           
                                if($updatedata)
                                {
                                     $totalSuccess=$totalSuccess+1;
                                    $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Faculty Allocate updated Succesfully']);
                                }
                                else
                                {
                                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Cannot Update Faculty Allocate! Please check']);
                                }
                            }
                            else
                            {
                                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Scrutiny Not created For '.$board.' Board Please Check']);
                            }
                        }
                        else
                        {
                           $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Maximum Script 60 Already Assigned '.$check_val_fact]);
                        }
                    }
                    else
                    {
                        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Total Script is Zero, Please Check Print Register Number Report']);
                    } 
                }
                else
                {
                    $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Mark Entered, Can not update Allocation Details']);
                }
            }
            else
            {
                $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Faculty Not Found! Please check']);
            }
        }
        else
        {
            $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Faculty Not Found! Please check']);
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


$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'Faculty Allocation'];
?>