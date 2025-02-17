<?php

namespace app\controllers;

use Yii;
use app\models\Categories;
use app\models\CategoriesSearch;
use app\models\Categorytype;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use yii\db\Query;
use app\models\BarCodeQuestMarks;
use kartik\mpdf\Pdf;
/**
 * CategoriesController implements the CRUD actions for Categories model.
 */
class CategoriesController extends Controller
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
    public function actionBarPrintPdf()
    {
        require(Yii::getAlias('@webroot/includes/use_institute_info.php'));
        $content = $_SESSION['bar_print'];
        //unset($_SESSION['student_application_date']);
        $pdf = new Pdf([
            'mode' => Pdf::MODE_CORE,
            'filename' => 'BAR CODE PRINT.pdf',
            'format' => Pdf::FORMAT_A4,
            'orientation' => Pdf::ORIENT_PORTRAIT,
            'content' => $content,
            'cssFile' => '@vendor/kartik-v/yii2-mpdf/assets/kv-mpdf-bootstrap.min.css',
            'cssInline' => ' @media all{
                        table{border: none; font-family:"Roboto, sans-serif"; width:100%; font-size: 12.5px; }
                    }',
            'options' => ['title' => 'BAR CODE PRINT'],
            'methods' => [
                'SetHeader' => ["OFFICE OF THE CONTROLLER OF EXAMINATIONS ".$org_name],
                'SetFooter' => ['Single Attempt Pass Count' . ' PRINTED ON : {DATE d-m-Y H:i:s:A}  PAGE :{PAGENO}'],
            ]
        ]);

        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'application/pdf');
        return $pdf->render();
    }
    /**
     * Lists all Categories models.
     * @return mixed
     */
    public function actionIndex()
    {
        Yii::$app->ShowFlashMessages->setMsg('Welcome ',"Welcome to Categories Section");
        return $this->redirect(['categories/create']);
    }

    /**
     * Displays a single Categories model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        
        return $this->redirect(['categories/create']);
        /*return $this->render('view', [
            'model' => $this->findModel($id),
        ]);*/
    }

    public function actionBarCode()
    {
        $model = new Categories();
        return $this->render('bar-code', [
            'model' => $model,
        ]);
    }

    public function actionGenerateBarCode()
    {
        $model = new BarCodeQuestMarks();
        return $this->render('generate-bar-code', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new Categories model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Categories();
        $categorytype = new Categorytype();
        
        if ($model->load(Yii::$app->request->post()) && $categorytype->load(Yii::$app->request->post()))
        {
            if(isset($_POST['c_val']))
            {
                $c_val=$_POST['c_val'];
            }

	    $check_cat = Categories::find()->where(['category_name'=>$model->category_name,'description'=>$model->description])->one();
            if(count($check_cat)>0)
	      {
        		Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CATEGORY).' already created');
                        return $this->redirect(['categories/create']);
	      }

          $query = new  Query();
            $query->select('*')
                ->from('coe_category_type as A')                    
                ->join('JOIN','coe_categories as B','B.coe_category_id=A.category_id')
                ->Where(['category_type'=>$categorytype->category_type,'A.description'=>$categorytype->description,'B.category_name'=>$model->category_name ]);                      
            $check_cat_type = $query->createCommand()->queryAll();
            if(count($check_cat_type)>0)
	      {
    		Yii::$app->ShowFlashMessages->setMsg('Error',ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_CATEGORY_TYPE).' already created');
                    return $this->redirect(['categories/create']);
	      }
	    
            $cat_type=$_POST['c_list'];
            $cat_desc=$_POST['c_list1'];
            
            $type_merge[]=$categorytype->category_type;
            $desc_merge[]=$categorytype->description;  

            if($cat_type!="" && $cat_desc!="") 
            {
                $exp=explode("#", trim($cat_type,"#"));
                $exp1=explode("#", trim($cat_desc,"#"));

                if($categorytype->category_type!="" && $categorytype->description!="") 
                {
                    $type_merge=array_merge($exp,$type_merge);
                    $desc_merge=array_merge($exp1, $desc_merge);
                    
                } 
                else 
                {
                    $type_merge=$exp;
                    $desc_merge=$exp1;
                }
            }
            
            $rows="";
            if($c_val==0) 
            {   
                $model->created_at = new \yii\db\Expression('NOW()');
                $model->created_by = Yii::$app->user->getId();
                $model->updated_at = new \yii\db\Expression('NOW()');
                $model->updated_by = Yii::$app->user->getId();
                if($model->save())
                {
                    $rows = Categories::find()->where(['category_name' => $model->category_name])->one();
                }
            } 
            else 
            {
                $rows = Categories::find()->where(['coe_category_id' => $c_val])->one();
            }
            $model->created_at = new \yii\db\Expression('NOW()');
            $model->created_by = Yii::$app->user->getId();
            $model->updated_at = new \yii\db\Expression('NOW()');
            $model->updated_by = Yii::$app->user->getId();
                    
            for($k=0;$k<count($type_merge);$k++)                    
            {     
                $categorytype = new Categorytype();                 
                $categorytype->category_id = $rows->coe_category_id;
                $categorytype->category_type = $type_merge[$k];
                $categorytype->description = $desc_merge[$k];                        
                $categorytype->created_at = new \yii\db\Expression('NOW()');
                $categorytype->created_by = Yii::$app->user->getId();
                $categorytype->updated_at = new \yii\db\Expression('NOW()');
                $categorytype->updated_by = Yii::$app->user->getId();           
                $categorytype->save();
                unset($categorytype);
                unset($model);
            }
             
            $categorytype = new Categorytype();
            $model = new Categories();
            //return $this->render('view', ['model' => $model,'id' => $categorytype->category_id]);
            return $this->render('create', ['model' => $model,'categorytype' => $categorytype]);
        }
        else 
        {
           Yii::$app->ShowFlashMessages->setMsg('Welcome ',"Welcome to Categories Section");
            return $this->render('create', ['model' => $model,'categorytype' => $categorytype]);
        }
    }

 
    /**
     * Updates an existing Categories model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $categorytype = new Categorytype();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_category_id]);
        } else {
            return $this->render('update', [
                'model' => $model,'categorytype' => $categorytype,
            ]);
        }
    }

    /**
     * Deletes an existing Categories model.
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
     * Finds the Categories model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Categories the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Categories::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}