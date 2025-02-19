<?php 
use yii\helpers\Url;
use app\models\Categorytype;
use app\models\StuInfo;
use app\models\TcData;
use app\models\TransferCertificates;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use app\models\CoeBatDegReg;
use app\models\Degree;
use app\models\Programme;

$interate = 1; // Check only 1 time for Sheet Columns

foreach($sheetData as $k => $line)
{ 
  $hall_columns=['A'=>'REGISTER NUMBER','B'=>'FATHER NAME','C'=>'NATIONALITY','D'=>'RELIGION','E'=>'DATE OF ADMISSION  (dd-mmm-yyyy)','F'=>'LEAVING CLASS','G'=>'REASON FOR LEAVING','H'=>'CONDUCT AND CHARACTER','I'=>'DATE OF APPLIED  (dd-mmm-yyyy)','J'=>'DATE OF LEFT  (dd-mmm-yyyy)','K'=>'CASTE','L'=>'COMMUNITY'];
      $template_clumns=['A'=>$line['A'],'B'=>$line['B'],'C'=>$line['C'],'D'=>$line['D'],'E'=>$line['E'],'F'=>$line['F'],'G'=>$line['G'],'H'=>$line['H'],'I'=>$line['I'],'J'=>$line['J'],'K'=>$line['K'],'L'=>$line['L']];

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
    $transfer = new TransferCertificates();
    $reg_num = isset($line['A'])?$line['A']:"";
    $father = isset($line['B'])?$line['B']:"";
    $nationality = isset($line['C'])?$line['C']:"";
    $religion = isset($line['D'])?$line['D']:"";
    $doa = isset($line['E'])?$line['E']:"";
    $class = isset($line['F'])?$line['F']:"";
    $reason = isset($line['G'])?$line['G']:"";
    $conduct = isset($line['H'])?$line['H']:"";
    $dol  = $dot = isset($line['J'])?$line['J']:"";
    $doapp = isset($line['I'])?$line['I']:"";
    $caste = isset($line['K'])?$line['K']:"";
    $community = isset($line['L'])?$line['L']:"";

    if(!empty($reg_num) && !empty($father) && !empty($doapp) && !empty($nationality) && !empty($religion) && !empty($doa) && !empty($class) && !empty($reason) && !empty($conduct) && !empty($dot) && !empty($dol)  && !in_array(null, $line, true))
    {        
        $replace = "";
        $reg_num = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['A'], -1, $replace);
        $father = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['B'], -1, $replace);
        $nationality = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['C'], -1, $replace);
        $religion = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['D'], -1, $replace);
        $reason = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['G'], -1, $replace);
        $class = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['F'], -1, $replace);
        $conduct = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['H'], -1, $replace);
        $caste = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['K'], -1, $replace);
        $community = preg_replace('~[\\\\\\\\/:*,?"<>|]~', '', $line['L'], -1, $replace);

        $dot = date("Y-m-d", str_replace('-','/', PHPExcel_Shared_Date::ExcelToPHP($dot))); 
        $doa = date("Y-m-d", str_replace('-','/', PHPExcel_Shared_Date::ExcelToPHP($doa))); 
        $doapp = date("Y-m-d", str_replace('-','/', PHPExcel_Shared_Date::ExcelToPHP($doapp))); 
        $dol = date("Y-m-d", str_replace('-','/', PHPExcel_Shared_Date::ExcelToPHP($dol))); 

        $getInfo = StuInfo::findOne(['reg_num'=>$reg_num]);
        $DEG = CoeBatDegReg::findOne($getInfo['batch_map_id']);
        $prg = Programme::findOne($DEG['coe_programme_id']);
        $deg = Degree::findOne($DEG['coe_degree_id']);
        $tcinfo = TcData::findOne(['reg_num'=>$reg_num]);
        $checkTras = TransferCertificates::findOne(['register_number'=>$reg_num]);
        if(empty($checkTras))
        {
            $checkCou = TransferCertificates::find()->count();
            $replace = substr_replace(substr(Yii::$app->params['tc_extension'].'00000', 0,-1),$checkCou,strlen(substr(Yii::$app->params['tc_extension'].'00000', 0,-1)) );
            $addval = strlen($checkCou)==0? Yii::$app->params['tc_extension'].'000001':$replace;

            $model = new TransferCertificates();
            $model->register_number = $reg_num;
            $model->name = strtoupper($tcinfo['name']);
            $model->parent_name = strtoupper($father);
            $model->dob = $tcinfo['dob'];
            $model->admission_date = $doa;
            $model->nationality = strtoupper($nationality);
            $model->religion = strtoupper($religion);
            $model->community = strtoupper($community);
            $model->class_studying = strtoupper($class.'-'.$deg['degree_code'].'('.$prg['programme_name'].')');
            $model->reason = strtoupper($reason);
            $model->is_qualified = strtoupper('Refer Marksheet');
            $model->conduct_char = strtoupper($conduct);
            $model->date_of_tc = $dot;
            $model->date_of_left = $dol;
            $model->date_of_app_tc = $doapp;
            $model->caste = strtoupper($caste);
            $model->serial_no =$addval ;
            $model->created_at = ConfigUtilities::getCreatedTime();
            $model->created_by = ConfigUtilities::getCreatedUser();
            if($model->save())
            {
                $totalSuccess+=1;
                $dispResults[] = array_merge($line, ['type' => 'S',  'message' => 'Success']);   
            }
            else
            {
              $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Error']);
            }
            unset($model);
        }
        else
        {
          $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Already Available']);
        }
    }   
    else
    {                    
        $dispResults[] = array_merge($line, ['type' => 'E',  'message' => 'Data submision is worng']);
        Yii::$app->ShowFlashMessages->setMsg('Error',"We are Unable to fetch the data with your submision.");
    }           
} // Foreach Ends Here  
$_SESSION['importResults'] = ['dispResults'=>$dispResults,'totalSuccess'=>$totalSuccess,'result_for'=>'TC'];
?>