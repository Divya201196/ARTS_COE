<?php

namespace app\controllers;

use Yii;
use kartik\mpdf\Pdf;
use yii\db\Query;
use app\models\Nominal;
use app\models\NominalSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Batch;
use app\models\Programme;
use app\models\CoeBatDegReg;
use app\models\Student;
use app\models\StudentMapping;
use app\models\Subjects;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
/**
 * NominalController implements the CRUD actions for Nominal model.
 */
class NominalController extends Controller
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
     * Lists all Nominal models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new NominalSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Nominal model.
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
     * Creates a new Nominal model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Nominal();
        $batch = new Batch();
        $programme = new Programme();
        $coebatdegreg = new CoeBatDegReg();        
        $student = new Student();
        $subject = new Subjects();
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        if ($model->load(Yii::$app->request->post())) 
            {            
                $semester = $model->semester;

                if(isset($_POST['reg']))
                {
                    $reg_count = $_POST['reg'];    
                }
                else
                {
                    Yii::$app->ShowFlashMessages->setMsg('Success','Records Updated / Viewed Successfully!!!');
                     return $this->redirect(['create']);
                }
                
               
                for($i=0;$i<count($reg_count);$i++){
                    
                    $reg_no = Student::find()->where(['register_number' => $reg_count[$i],'student_status'=>'Active'])->one();                    
                    $k=$i+1;

                    $query_0 = new Query();  
                    $exist_nominal = $query_0->select('coe_subjects_id,coe_nominal_id')
                                            ->from('coe_nominal')
                                            ->where(['coe_student_id' => $reg_no->coe_student_id,'course_batch_mapping_id'=>$_POST['bat_map_val'],'semester'=>$semester])->createCommand()->queryAll(); 
//print_r($exist_nominal);exit;

                    for($j=1;$j<=2;$j++){ 
                        $model = !isset($model)?new Nominal():$model;                                           
                        if(isset($_POST['elective'.$k.'_'.$j]))
                        { 

                            $elective_id = Subjects::find()->where(['subject_code' => $_POST['elective'.$k.'_'.$j] ])->one(); 

                            if(!empty($elective_id))
                            {
                                $check_nom = Yii::$app->db->createCommand("SELECT * FROM coe_nominal WHERE coe_subjects_id='".$elective_id->coe_subjects_id."' and  course_batch_mapping_id='".$_POST['bat_map_val']."' and coe_student_id='".$reg_no->coe_student_id."' and semester='".$semester."' ")->queryAll();
                              
                                if(!empty($check_nom))
                                {
                                    $update_nominal = Yii::$app->db->createCommand("update coe_nominal set coe_subjects_id='".$elective_id->coe_subjects_id."',updated_by='".$updated_by."',updated_at='".$updated_at."' where course_batch_mapping_id='".$_POST['bat_map_val']."' and coe_student_id='".$reg_no->coe_student_id."' and semester='".$semester."' ")->execute();

                                }
                                else if(count($exist_nominal)==2)
                                {
                                    $update_nominal = Yii::$app->db->createCommand("update coe_nominal set coe_subjects_id='".$elective_id->coe_subjects_id."',updated_by='".$updated_by."',updated_at='".$updated_at."' where course_batch_mapping_id='".$_POST['bat_map_val']."' and coe_student_id='".$reg_no->coe_student_id."' and semester='".$semester."' and coe_nominal_id='".$exist_nominal[$j-1]['coe_nominal_id']."'")->execute();                                    
                                    unset($model);
                                }
                                else
                                {
                                    $section_name = StudentMapping::findOne(['student_rel_id'=>$reg_no->coe_student_id]);
                                    $model->course_batch_mapping_id=$_POST['bat_map_val'];
                                    $model->coe_student_id = $reg_no->coe_student_id;
                                    $model->section_name = $section_name->section_name;
                                    $model->semester = $semester;
                                    $model->coe_subjects_id = $elective_id->coe_subjects_id;
                                    $model->created_at = new \yii\db\Expression('NOW()');
                                    $model->created_by = Yii::$app->user->getId();
                                    $model->updated_at = new \yii\db\Expression('NOW()');
                                    $model->updated_by = Yii::$app->user->getId();
                                    $model->save();
                                    unset($model); 
                                }
                                
                            }else{
                                echo "empty";
                            }          
                        }                                 
                    }

                }
                Yii::$app->ShowFlashMessages->setMsg('Success','Record Saved Successfully!!!');
                return $this->redirect(['create',]);     
                        
        }
        else{
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_NOMINAL));
            return $this->render('create', [
                'model' => $model,'batch' => $batch,'programme' => $programme,'coebatdegreg'=>$coebatdegreg,'student'=>$student,'subject'=>$subject,
            ]);
        }
    }

    /**
     * Updates an existing Nominal model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_nominal_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Nominal model.
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
     * Finds the Nominal model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Nominal the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Nominal::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    public function actionOnlinenominal()
    {

       $model = new Nominal();
        $batch = new Batch();
        $programme = new Programme();
        $coebatdegreg = new CoeBatDegReg();        
        $student = new Student();
        $subject = new Subjects();
        $updated_at = date("Y-m-d H:i:s");
        $updated_by = Yii::$app->user->getId();
        

        if ($model->load(Yii::$app->request->post()))
        { 

          $bat_map_val=$_POST['bat_map_val'];
          $batch_id=$_POST['bat_val'];
          //$semester=$_POST['semester'];
          $semester = $model->semester;
          if(!empty($batch_id))
          {

                $det_disc_type = Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%Discontinued%'")->queryScalar();
                $elective= Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%elective%'")->queryScalar();
                $common= Yii::$app->db->createCommand("select coe_category_type_id from coe_category_type where category_type like '%common%'")->queryScalar();

               // print_r($common);exit;

                 $fetch_query = (new \yii\db\Query());
                $fetch_query->select(['A.coe_student_id','A.register_number','y.subject_code','y.subject_name','A.email_id'])  
                    ->from('coe_student as A')
                
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                     ->join('JOIN','coe_subjects_mapping as z','z.batch_mapping_id=D.course_batch_mapping_id')
                     ->join('JOIN','coe_subjects as y','y.coe_subjects_id=z.subject_id')
                     ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id,'z.semester'=>$semester])
                    ->andWhere(['D.course_batch_mapping_id'=>$bat_map_val])
                     ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['A.student_status'=>'Active'])
                     ->andWhere(['z.subject_type_id'=>$common])
                    ->orderBy('subject_code,register_number'); 
                $stu_data_1 = $fetch_query->createCommand()->queryAll();

                $fetch_query_2 = (new \yii\db\Query());
                $fetch_query_2->select(['A.coe_student_id','A.register_number','y.subject_code','y.subject_name','A.email_id'])  
                    ->from('coe_student as A')
                
                    ->join('JOIN','coe_student_mapping as D','A.coe_student_id=D.student_rel_id')
                    ->join('JOIN','coe_bat_deg_reg as E','E.coe_bat_deg_reg_id=D.course_batch_mapping_id')
                    ->join('JOIN','coe_degree as F','F.coe_degree_id=E.coe_degree_id')
                    ->join('JOIN','coe_programme as G','G.coe_programme_id=E.coe_programme_id')
                    ->join('JOIN','coe_batch as H','H.coe_batch_id=E.coe_batch_id')
                    
                    ->join('JOIN','coe_subjects_mapping as z','z.batch_mapping_id=D.course_batch_mapping_id')
                     ->join('JOIN','coe_subjects as y','y.coe_subjects_id=z.subject_id')
                     ->join('JOIN','coe_nominal as x','x.coe_subjects_id=z.subject_id and x.coe_student_id=D.student_rel_id' )
                     ->where(['E.coe_batch_id'=>$batch_id,'H.coe_batch_id'=>$batch_id,'x.semester'=>$semester])
                    ->andWhere(['D.course_batch_mapping_id'=>$bat_map_val])
                    ->andWhere(['<>', 'status_category_type_id', $det_disc_type])
                    ->andWhere(['A.student_status'=>'Active'])
                    
                    ->orderBy('subject_code,register_number'); 

                $stu_data_2 = $fetch_query_2->createCommand()->queryAll();
               // print_r($stu_data_2);exit;

                if(empty($stu_data_2))
            {
                $stu_data= array_merge($stu_data_1);
            }
            else{
                 $stu_data= array_merge($stu_data_1, $stu_data_2);
            }
                //print_r($stu_data_2);exit;
                 return $this->render('onlinenominal', [
                    'stu_data' => $stu_data,
                  
                   'model' => $model,'batch' => $batch,'programme' => $programme,'coebatdegreg'=>$coebatdegreg,'student'=>$student,'subject'=>$subject
                    
                ]);
           }else {
               Yii::$app->ShowFlashMessages->setMsg('Error',"No data Found");
               return $this->render('onlinenominal', [
                    'model' => $model,'batch' => $batch,'programme' => $programme,'coebatdegreg'=>$coebatdegreg,'student'=>$student,'subject'=>$subject, 'stu_data' => $stu_data,
                ]);
           }

                //print_r($stu_data);exit;



            
            
        }else {
            Yii::$app->ShowFlashMessages->setMsg('Success',"Welcome to Online Exam ".ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_NOMINAL)."  Data Export");
            return $this->render('onlinenominal', [
                'model' => $model,'batch' => $batch,'programme' => $programme,'coebatdegreg'=>$coebatdegreg,'student'=>$student,'subject'=>$subject
                
            ]);
        } 
    }

    public function actionStudentNominalExportPdf()
    {    
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));   
        $content = $_SESSION['stu_nominal_data'];
            $pdf = new Pdf([

                'mode' => Pdf::MODE_CORE,
                'filename' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Nominal VERIFICATION.pdf',                
                'format' => Pdf::FORMAT_LEGAL,                 
                'orientation' => Pdf::ORIENT_PORTRAIT,                 
                'destination' => Pdf::DEST_BROWSER,                 
                'content' => $content,  
                'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
                'cssInline' => ' @media all{
                       table{border-collapse: collapse;  text-align: center;  font-family:"Roboto, sans-serif"; width:100%; height:100;font-size: 25px; } 
                        
                        table td{
                            padding:30px;
                            border: 1px solid #000;
                            text-align: left;
                            line-height: 1.5em;
                        }
                        table th{
                             padding:30px;
                            line-height: 2em;
                            border: 1px solid #000;
                            text-align: left;
                        }
                    }   
                ',
                'options' => ['title' => ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Nominal VERIFICATION'],
                'methods' => [ 
                    'SetHeader'=>["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name], 
                    'SetFooter'=>[ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_STUDENT).' Nominal VERIFICATION'.' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
                ]
            ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render(); 
    }
     public function actionStudentNominalExportExcel()
    {        
        $content = $_SESSION['stu_nominal_data'];         
        $fileName = "Nominal Export  " . date('Y-m-d-H-i-s') . '.xls';
        $options = ['mimeType' => 'application/vnd.ms-excel'];
        return Yii::$app->excel->exportExcel($content, $fileName, $options);
    }
}
