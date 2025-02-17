<?php

namespace app\controllers;

use Yii;
use app\models\MandatoryStuMarks;
use app\models\MandatorySubcatSubjects;
use app\models\MandatorySubjects;
use app\models\Categorytype;
use app\models\MandatorySubcatSubjectsSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\SubjectsMapping;
use app\models\BatDegReg;
use yii\helpers\Json;
use yii\widgets\ActiveForm;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

/**
 * MandatorySubcatSubjectsController implements the CRUD actions for MandatorySubcatSubjects model.
 */
class MandatorySubcatSubjectsController extends Controller
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
     * Lists all MandatorySubcatSubjects models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new MandatorySubcatSubjectsSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        Yii::$app->ShowFlashMessages->setMsg('Welcome'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));    
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single MandatorySubcatSubjects model.
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
     * Creates a new MandatorySubcatSubjects model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionUpdatePaperNumber()
    {
        $model = new MandatorySubcatSubjects();
        $subjects = new SubjectsMapping();
        $mandatorySubjects = new MandatorySubjects();
       
        
        if (Yii::$app->request->post()) 
        {
            $updated_at = date("Y-m-d H:i:s");
            $updated_by = Yii::$app->user->getId();
            $connection = Yii::$app->db;
            if(isset($_POST['update_paper_num']) && count($_POST['update_paper_num'])>0)
            {
                $paper_no= 0;
                for ($i=0; $i <count($_POST['update_paper_num']) ; $i++) 
                { 
                   $command = $connection->createCommand('UPDATE coe_mandatory_subcat_subjects SET paper_no="'.$_POST['update_paper_num'][$i].'",updated_by="'.$updated_by.'",updated_at="'.$updated_at.'" WHERE coe_mandatory_subcat_subjects_id="'.$_POST['man_update'][$i].'" ');
                    if($command->execute())
                    {
                        $paper_no = $paper_no+1;
                    } 

                }
                Yii::$app->ShowFlashMessages->setMsg('Success',$paper_no.' Paper Numbers Update Successfully!!!');
                return $this->redirect(['mandatory-subcat-subjects/update-paper-number']);
            }
        }
        else
        {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Paper Number Update Mandatory '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));
            return $this->render('update-paper-number', [
                'model' => $model,
                'subjects' => $subjects,
                'mandatorySubjects' => $mandatorySubjects,
            ]);
        }
        
    }
    /**
     * Creates a new MandatorySubcatSubjects model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new MandatorySubcatSubjects();
        $subjects = new SubjectsMapping();
        $mandatorySubjects = new MandatorySubjects();
        if (Yii::$app->request->isAjax) 
        {
            if($model->load(Yii::$app->request->post())) 
            {
              \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
              return ActiveForm::validate($model);
            }
        }

        if ($model->load(Yii::$app->request->post()) ) 
        {   
            $created_by = Yii::$app->user->getId();
            $migrate_multiple = $model->created_at;
            $is_additional='NO';
            $course_type_id = Categorytype::find()->where(['description'=>'Optional'])->one();
            $subject_type_id = Categorytype::find()->where(['description'=>'Mandatory Course'])->one();
            $paper_type_id = Categorytype::find()->where(['description'=>'Theory'])->one();
            if(isset($_POST['MandatorySubcatSubjects']['is_additional']) && !empty($_POST['MandatorySubcatSubjects']['is_additional']) && $_POST['MandatorySubcatSubjects']['is_additional'][0]=='yes')
            {
                $is_additional='YES';
            }
            $getSbInfo = MandatorySubjects::findOne($model->man_subject_id);
            if(isset($migrate_multiple) && !empty($migrate_multiple))
            {
                $migrate_multiple = array_filter($migrate_multiple);
                sort($migrate_multiple);
                $model->paper_no = '5';
                for ($cou=0; $cou <count($migrate_multiple) ; $cou++) 
                { 
                    $checkSubName = MandatorySubcatSubjects::find()->where(['batch_map_id'=> $migrate_multiple[$cou],'sub_cat_name'=>$model->sub_cat_name])->one();
                    $getSubInfo = MandatorySubjects::findOne(['batch_mapping_id'=>$migrate_multiple[$cou],'subject_code'=>$getSbInfo->subject_code]);
                    $checkSubNameAddi = MandatorySubcatSubjects::find()->where(['batch_map_id'=> $migrate_multiple[$cou],'sub_cat_name'=>$model->sub_cat_name,'is_additional'=>'YES'])->one();
                    if(empty($checkSubName) && !empty($getSubInfo))
                    {
                        $new_Model = new MandatorySubcatSubjects();
                        $new_Model->attributes = $model->attributes;
                        $new_Model->man_subject_id = $getSubInfo->coe_mandatory_subjects_id;
                        $new_Model->coe_batch_id = $_POST['SubjectsMapping']['coe_batch_id'];
                        $new_Model->batch_map_id = $migrate_multiple[$cou];
                        $new_Model->paper_no = $_POST['paper_no'];
                        $new_Model->created_by = $created_by;
                        $new_Model->course_type_id = $course_type_id['coe_category_type_id'];
                        $new_Model->subject_type_id = $subject_type_id['coe_category_type_id'];
                        $new_Model->paper_type_id = $paper_type_id['coe_category_type_id'];
                        $new_Model->created_at = date('Y-m-d-H-i-s');
                        $new_Model->updated_at = date('Y-m-d-H-i-s');
                        $new_Model->updated_by = $created_by;  
                        $new_Model->is_additional = $is_additional;
                        $new_Model->save(false);  
                        Yii::$app->ShowFlashMessages->setMsg('Success'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY CREATED <b>".$model->sub_cat_name."</b>  Created successfully!! ");                    
                    }
                    else if(empty($checkSubNameAddi) && !empty($getSubInfo))
                    {
                        $new_Model = new MandatorySubcatSubjects();
                        $new_Model->attributes = $model->attributes;
                        $new_Model->man_subject_id = $getSubInfo->coe_mandatory_subjects_id;
                        $new_Model->coe_batch_id = $_POST['SubjectsMapping']['coe_batch_id'];
                        $new_Model->batch_map_id = $migrate_multiple[$cou];
                        $new_Model->paper_no = $_POST['paper_no'];
                        $new_Model->created_by = $created_by;
                        $new_Model->course_type_id = $course_type_id['coe_category_type_id'];
                        $new_Model->subject_type_id = $subject_type_id['coe_category_type_id'];
                        $new_Model->paper_type_id = $paper_type_id['coe_category_type_id'];
                        $new_Model->created_at = date('Y-m-d-H-i-s');
                        $new_Model->updated_at = date('Y-m-d-H-i-s');
                        $new_Model->updated_by = $created_by;  
                        $new_Model->is_additional = $is_additional;
                        $new_Model->save(false);  
                        Yii::$app->ShowFlashMessages->setMsg('Success'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY CREATED <b>".$model->sub_cat_name."</b>  Created successfully!! ");                    
                    }
                    else
                    {
                        Yii::$app->ShowFlashMessages->setMsg('Error'," NO DATA FOUND FOR OTHER DEPARTMENTS");
                    }

                }
            }
            else
            {
                $checkSubName = MandatorySubcatSubjects::find()->where(['batch_map_id'=> $model->batch_map_id,'sub_cat_name'=>$model->sub_cat_name])->one();
                $getSubInfo = MandatorySubjects::findOne(['batch_mapping_id'=>$model->batch_map_id,'subject_code'=>$getSbInfo->subject_code]);
                $checkSubNameAddi = MandatorySubcatSubjects::find()->where(['batch_map_id'=> $model->batch_map_id,'sub_cat_name'=>$model->sub_cat_name,'is_additional'=>'YES'])->one();
                if(empty($checkSubName))
                {
                    $new_Model = new MandatorySubcatSubjects();
                    $new_Model->attributes = $model->attributes;
                    $new_Model->man_subject_id = $getSubInfo->coe_mandatory_subjects_id;
                    $new_Model->coe_batch_id = $_POST['SubjectsMapping']['coe_batch_id'];
                    $new_Model->batch_map_id = $model->batch_map_id;
                    $new_Model->paper_no = $_POST['paper_no'];
                    $new_Model->created_by = $created_by;
                    $new_Model->course_type_id = $course_type_id['coe_category_type_id'];
                    $new_Model->subject_type_id = $subject_type_id['coe_category_type_id'];
                    $new_Model->paper_type_id = $paper_type_id['coe_category_type_id'];
                    $new_Model->created_at = date('Y-m-d-H-i-s');
                    $new_Model->updated_at = date('Y-m-d-H-i-s');
                    $new_Model->updated_by = $created_by;  
                    $new_Model->is_additional = $is_additional;  
                    $new_Model->save(false);  
                    Yii::$app->ShowFlashMessages->setMsg('Success'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY CREATED <b>".$model->sub_cat_name."</b>  Created successfully!! ");                    
                }
                else if(empty($checkSubNameAddi) && !empty($getSubInfo))
                {
                    $new_Model = new MandatorySubcatSubjects();
                    $new_Model->attributes = $model->attributes;
                    $new_Model->man_subject_id = $getSubInfo->coe_mandatory_subjects_id;
                    $new_Model->coe_batch_id = $_POST['SubjectsMapping']['coe_batch_id'];
                    $new_Model->batch_map_id = $migrate_multiple[$cou];
                    $new_Model->paper_no = $_POST['paper_no'];
                    $new_Model->created_by = $created_by;
                    $new_Model->course_type_id = $course_type_id['coe_category_type_id'];
                    $new_Model->subject_type_id = $subject_type_id['coe_category_type_id'];
                    $new_Model->paper_type_id = $paper_type_id['coe_category_type_id'];
                    $new_Model->created_at = date('Y-m-d-H-i-s');
                    $new_Model->updated_at = date('Y-m-d-H-i-s');
                    $new_Model->updated_by = $created_by;  
                    $new_Model->is_additional = $is_additional;
                    $new_Model->save(false);  
                    Yii::$app->ShowFlashMessages->setMsg('Success'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY CREATED <b>".$model->sub_cat_name."</b>  Created successfully!! ");                    
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Error'," NO DATA FOUND");
                }
            }

            
                  
            return $this->redirect(['create']);
            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Mandatory '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT));
            return $this->render('create', [
                'model' => $model,
                'subjects' => $subjects,
                'mandatorySubjects' => $mandatorySubjects,
            ]);
        }
    }

    /**
     * Updates an existing MandatorySubcatSubjects model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $subjects = SubjectsMapping::find()->where(['batch_mapping_id'=>$model->batch_map_id])->one();
        $mandatorySubjects = MandatorySubjects::find()->where(['coe_mandatory_subjects_id'=>$model->man_subject_id])->one();
        $created_by = Yii::$app->user->getId();

        if ($model->load(Yii::$app->request->post())) 
        {
            if(isset($_POST['MandatorySubcatSubjects']['created_at']))
            {
                $migrate_multiple = $_POST['MandatorySubcatSubjects']['created_at'];
                if(isset($migrate_multiple) && !empty($migrate_multiple))
                {
                    
                    for ($cou=0; $cou <count($migrate_multiple) ; $cou++) 
                    { 
                       
                        $checkSubName = MandatorySubcatSubjects::find()->where(['batch_map_id'=> $migrate_multiple[$cou],'sub_cat_name'=>$model->sub_cat_name])->one();
                        $checkSubNameAddi = MandatorySubcatSubjects::find()->where(['batch_map_id'=> $migrate_multiple[$cou],'sub_cat_name'=>$model->sub_cat_name,'is_additional'=>'YES'])->one();
                        $mandatoSub = MandatorySubjects::findOne($model->man_subject_id);
                        $getMandatoryDetails = MandatorySubjects::find()->where(['batch_mapping_id'=>$migrate_multiple[$cou],'subject_code'=>$mandatoSub->subject_code])->one();
                        
                        if(empty($checkSubName) && !empty($getMandatoryDetails))
                        {
                            $new_Model = new MandatorySubcatSubjects();
                            $new_Model->man_subject_id = $getMandatoryDetails['coe_mandatory_subjects_id'];
                            $new_Model->coe_batch_id = $model->coe_batch_id;
                            $new_Model->batch_map_id = $migrate_multiple[$cou];
                            $new_Model->sub_cat_code = $model->sub_cat_code;
                            $new_Model->sub_cat_name = $model->sub_cat_name;
                            $new_Model->paper_no = $model->paper_no+1;
                            $new_Model->credit_points = $model->credit_points;
                            $new_Model->created_by = $created_by;
                            $new_Model->course_type_id = $model->course_type_id;
                            $new_Model->subject_type_id = $model->subject_type_id;
                            $new_Model->paper_type_id = $model->paper_type_id;
                            $new_Model->created_at = date('Y-m-d-H-i-s');
                            $new_Model->updated_at = date('Y-m-d-H-i-s');
                            $new_Model->updated_by = $created_by;  
                            $new_Model->is_additional = $model->is_additional;  
                            $new_Model->save(false);  

                            Yii::$app->ShowFlashMessages->setMsg('Success'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY CREATED <b>".$model->sub_cat_name."</b> Created successfully!! ");                    
                        }
                        else if(empty($checkSubNameAddi) && !empty($getMandatoryDetails))
                        {
                            $new_Model = new MandatorySubcatSubjects();
                            $new_Model->man_subject_id = $getMandatoryDetails['coe_mandatory_subjects_id'];
                            $new_Model->coe_batch_id = $model->coe_batch_id;
                            $new_Model->batch_map_id = $migrate_multiple[$cou];
                            $new_Model->sub_cat_code = $model->sub_cat_code;
                            $new_Model->sub_cat_name = $model->sub_cat_name;
                            $new_Model->paper_no = $model->paper_no+1;
                            $new_Model->credit_points = $model->credit_points;
                            $new_Model->created_by = $created_by;
                            $new_Model->course_type_id = $model->course_type_id;
                            $new_Model->subject_type_id = $model->subject_type_id;
                            $new_Model->paper_type_id = $model->paper_type_id;
                            $new_Model->created_at = date('Y-m-d-H-i-s');
                            $new_Model->updated_at = date('Y-m-d-H-i-s');
                            $new_Model->updated_by = $created_by;  
                            $new_Model->is_additional = $model->is_additional;  
                            $new_Model->save(false);  

                            Yii::$app->ShowFlashMessages->setMsg('Success'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY CREATED <b>".$model->sub_cat_name."</b> Created successfully!! ");                    
                        }
                        else
                        {
                            Yii::$app->ShowFlashMessages->setMsg('Error'," MANDATORY ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CATEGORY NAME <b>".$model->sub_cat_name."</b>  Already Exists choose different name!!");
                        }

                    }
                }
                else
                {
                    
                    $connection = Yii::$app->db;
                    if(isset($_POST['MandatorySubcatSubjects']['credit_points']))
                    {
                        $command = $connection->createCommand('UPDATE coe_mandatory_subcat_subjects SET credit_points="'.$_POST['MandatorySubcatSubjects']['credit_points'].'", updated_at="'.date('Y-m-d-H-i-s').'",updated_by="'.$created_by.'" WHERE coe_mandatory_subcat_subjects_id="'.$id.'" ');
                        $command->execute();  
                        Yii::$app->ShowFlashMessages->setMsg('Success',"<b>".$model->sub_cat_code." </b>Updated Successfully!!");
                   }
                   if(isset($_POST['MandatorySubcatSubjects']['sub_cat_name']))
                    {
                        $connection1 = Yii::$app->db;
                        $command = $connection1->createCommand('UPDATE coe_mandatory_subcat_subjects SET sub_cat_name="'.$_POST['MandatorySubcatSubjects']['sub_cat_name'].'", updated_at="'.date('Y-m-d-H-i-s').'",updated_by="'.$created_by.'" WHERE coe_mandatory_subcat_subjects_id="'.$id.'" ');
                        $command->execute();  
                        Yii::$app->ShowFlashMessages->setMsg('Success',"<b>".$model->sub_cat_code." </b>Updated Successfully!!");
                   }
                   else
                   {
                        Yii::$app->ShowFlashMessages->setMsg('Error',"Nothing Changed");
                   }
                    
                }
            }


            return $this->redirect(['view', 'id' => $model->coe_mandatory_subcat_subjects_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
                'subjects' => $subjects,
                'mandatorySubjects' => $mandatorySubjects,
            ]);
        }
    }
    public function actionMandatorySubSubjectsFull()
    {

        $model = new MandatorySubjects();

        if ($model->load(Yii::$app->request->post()))
        {  
            $mandatotry_info = MandatorySubcatSubjects::find()->where(['coe_batch_id'=>$_POST['MandatorySubjects']['coe_batch_id']])->all();            
           if(!empty($mandatotry_info))
           {
               
                $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['coe_mandatory_subjects_id','batch_name','degree_code','programme_name','subject_code','sub_cat_code','sub_cat_name','subject_name','CIA_min','E.coe_programme_id','ESE_min','CIA_max','ESE_max','total_minimum_pass','end_semester_exam_value_mark as final_marks','credit_points'])  
                    ->from('coe_mandatory_subjects as A')   
                    ->join('JOIN','coe_mandatory_subcat_subjects as B','B.man_subject_id=A.coe_mandatory_subjects_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_batch_id=B.coe_batch_id')                    
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    
                    ->andWhere('B.coe_batch_id = :coe_batch_id', [':coe_batch_id' => $_POST['MandatorySubjects']['coe_batch_id']])
                    ->groupBy('B.man_subject_id,B.batch_map_id,B.coe_batch_id,B.sub_cat_code ,B.sub_cat_name')
                    ->orderBy('A.subject_code,B.sub_cat_code'); 
                $man_su_info = $fetch_query->createCommand()->queryAll();

                return $this->render('mandatory-sub-subjects-full', [
                    'man_su_info' => $man_su_info,
                    'model' => $model,
                   
                ]);
           }
           else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('mandatory-sub-subjects-full', [
                    'model' => $model,
                    
                ]);
           }
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to Mandatory ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." Full Export");
            return $this->render('mandatory-sub-subjects-full', [
                'model' => $model,
                
            ]);
        } 
    }

    public function actionManSubSubjectInfoExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['mandatory_sub_info'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => "Mandatory ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).'.pdf',                
                'format' => Pdf::FORMAT_A3,                 
                //'orientation' => Pdf::ORIENT_LANDSCAPE,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                        table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; font-size: 15px; } 
                        
                        table td{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                        }
                        table th{
                            border: 1px solid #000;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            text-align: center;
                        }
                    }   
                ',
                'options' => ['title' => "Mandatory ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>["Mandatory ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT).' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
    public function actionManSubSubjectExportInfoExcel()
    {        
        $content = $_SESSION['mandatory_sub_info'];         
        $fileName = "Mandatory ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." ". date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }

    /**
     * Deletes an existing MandatorySubcatSubjects model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $subjects = MandatorySubcatSubjects::findOne(['man_subject_id'=>$id]);        
        $name_of_subject = !empty($subjects)? $subjects->sub_cat_code:'';
        $sub_name = $this->findModel($id);
       
        $name_sub_code = $sub_name->sub_cat_code;
        $sub_name_sub_code = $sub_name->sub_cat_name;
        $check_marks = !empty($subjects)? MandatoryStuMarks::find()->where(['subject_map_id'=>$subjects->coe_mandatory_subcat_subjects_id])->all():'';

        if(!empty($check_marks) || !empty($subjects))
        {
           Yii::$app->ShowFlashMessages->setMsg('Error',' You can not delete <b>'.$name_of_subject.'</b> Because already <b>'.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE </b> are Assigned OR <b>".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT)."</b> are Available OR Marks Are Entered "); 
        }
        else
        {
            $sub_name_code = ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." CODE <b>".$name_sub_code." </b>".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_SUBJECT)." NAME <b>".$sub_name_sub_code."</b>";
            Yii::$app->ShowFlashMessages->setMsg('Success',$sub_name_code.' Deleted Successfully!!! ');
            $this->findModel($id)->delete();
        }
        return $this->redirect(['index']);
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the MandatorySubcatSubjects model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return MandatorySubcatSubjects the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = MandatorySubcatSubjects::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
