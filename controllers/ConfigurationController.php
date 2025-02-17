<?php
namespace app\controllers;

use Yii;
use app\models\Configuration;
use app\models\ConfigurationSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\User;
use kartik\widgets\Growl;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use kartik\mpdf\Pdf;
/**
 * ConfigurationController implements the CRUD actions for Configuration model.
 */
class ConfigurationController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

     /**
     * Creates a new Configuration model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        // Update the data in Index Function As well. Both the scripts has to be same
        $model = new Configuration();        
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {  
            
            $locking_start = isset($_POST['start_date'])?date('d-m', strtotime(str_replace('-','/', $_POST['start_date']))):"";

            $difference = isset($_POST['start_date'])?strtotime($_POST['end_date'])-strtotime($_POST['start_date']):"";

             $locking_end = isset($_POST['end_date'])?date('d-m', strtotime(str_replace('-','/', $_POST['end_date']))):"";               

            $nominal = isset($_POST['is_status'])?$_POST['is_status']:"";
            
            $config_value = $difference!=0 && !empty($difference) && $difference>0?$difference:$model->config_value;            
            $config_value = !empty($nominal) ? $nominal : $config_value;
            
            if(!empty($config_value))
            {           
                $config_value=addslashes($config_value); 
                $config_name = $model->config_name;        
                if(isset($_POST['start_date']) && $difference!=0 )
                {
                    $updateRecord = ConfigUtilities::UpdateBatchLocking($config_name,$locking_start,$locking_end);
                }
                else{
                    $updateRecord = ConfigUtilities::UpdateConfigValue($config_name,$config_value);
                } 
                
                if($updateRecord==1)
                {
                     Yii::$app->ShowFlashMessages->setMsg('Success',$config_name.' Updated Successfully!!!');
                    return $this->redirect(['create']);    
                }
                else
                {
                     Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Perform the action for ".$config_name.' <br /> Please re-check the Submitted Data');
                    return $this->render('create', [
                        'model' => $model,
                    ]);
                }
               
            }
            else {
                    Yii::$app->ShowFlashMessages->setMsg('Error','Data Submitted Wrongly');
                    return $this->render('create', [
                        'model' => $model,
                    ]);
            }

        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_NAME));
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Lists all Configuration models.
     * @return mixed
     */
    public function actionIndex()
    {
        
        $model = new Configuration();        
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {  

               
            $locking_start = isset($_POST['start_date'])?date('d-m', strtotime(str_replace('-','/', $_POST['start_date']))):"";

           
            $difference = isset($_POST['start_date'])?strtotime($_POST['end_date'])-strtotime($_POST['start_date']):"";

             $locking_end = isset($_POST['end_date'])?date('d-m', strtotime(str_replace('-','/', $_POST['end_date']))):"";               

            $nominal = isset($_POST['is_status'])?$_POST['is_status']:"";

            $config_value = $difference!=0 && !empty($difference) && $difference>0?$difference:$model->config_value;
            $config_value = !empty($nominal) ? $nominal : $config_value;
            
            if(!empty($config_value))
            {   
                $config_name = $model->config_name;                
                if(isset($_POST['start_date']) && $difference!=0)
                {
                    $updateRecord = ConfigUtilities::UpdateBatchLocking($config_name,$locking_start,$locking_end);
                }
                else{
                    $updateRecord = ConfigUtilities::UpdateConfigValue($config_name,$config_value);
                }    

                
                if($updateRecord==1)
                {
                     Yii::$app->ShowFlashMessages->setMsg('Success',$config_name.' Updated Successfully!!!');
                    return $this->redirect(['create']);    
                }
                else
                {
                     Yii::$app->ShowFlashMessages->setMsg('Error',"Unable to Perform the action for ".$config_name.' <br /> Please re-check the Submitted Data');
                    return $this->render('create', [
                        'model' => $model,
                    ]);
                }
               
            }
            else {
                    Yii::$app->ShowFlashMessages->setMsg('Error','Data Submitted Wrongly');
                    return $this->render('create', [
                        'model' => $model,
                    ]);
            }
            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_NAME));
            return $this->render('create', [
                'model' => $model,
            ]);
        }
        
    }

    /**
     * Displays a single Configuration model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Displays a single Configuration model.
     * @param integer $id
     * @return mixed
     */
    public function actionOrganisationInfo()
    {
        $model = new Configuration();
        $filename = Yii::getAlias('@webroot/includes/institute_info.php');
        $modified_status = filesize($filename);

        if($modified_status==0 || isset($_POST["Configuration"]["org_name"]))
        {
            if ($model->load(Yii::$app->request->post())) {
                $file_content = "";
                $content = "";
                $content .='<?php 
                $institute_info = 
                [ "org_name"=> "'.addslashes($_POST["Configuration"]["org_name"]).'",
                "org_email"=> "'.addslashes($_POST["Configuration"]["org_email"]).'",
                "org_phone" => "'.addslashes($_POST["Configuration"]["org_phone"]).'",
                "org_web" => "'.addslashes($_POST["Configuration"]["org_web"]).'",
                "org_address" => "'.addslashes($_POST["Configuration"]["org_address"]).'",
                "org_tagline" => "'.addslashes($_POST["Configuration"]["org_tagline"]).'",
                "update_details" => "'.date("Y-m-d").'" ];  ?>'; 
                  
                if(file_put_contents($filename, $content.PHP_EOL , FILE_APPEND | LOCK_EX))
                {         
                    $file_content = file_get_contents($filename, true);           
                    Yii::$app->ShowFlashMessages->setMsg('Success','Data Updated');
                }
                else {
                     Yii::$app->ShowFlashMessages->setMsg('Error','Data Submitted Wrongly');
                }
                return $this->render('organisation-info',['model'=>$model,'file_content'=>$file_content]);
            }
            return $this->render('organisation-info',['model'=>$model]);
        }
        else if($modified_status>2){ // Checking the file size in bytes
            $file_content = file_get_contents($filename, true);
            Yii::$app->ShowFlashMessages->setMsg('Warning','Institute Information has already been Updated. <br /> You can update the info after 180 days');
            return $this->render('organisation-info',[
                'model'=>$model,
                'file_content' => $file_content,
            ]);
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Organisation Set up. You can update the information of your Institution Twice in a Year. ');
            return $this->render('organisation-info',[
                'model'=>$model,
            ]);
        }
        
    }
   
    /**
     * Updates an existing Configuration model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            $model->updated_at = new \yii\db\Expression('NOW()');
            $model->updated_by = Yii::$app->user->getId();
            $model->save();
            return $this->redirect(['view', 'id' => $model->coe_config_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Configuration model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Configuration model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Configuration the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Configuration::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    


}
