<?php

namespace app\controllers;

use Yii;
use app\models\HallMaster;
use app\models\HallMasterSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Categorytype;
use app\models\HallAllocate;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;

/**
 * HallMasterController implements the CRUD actions for HallMaster model.
 */
class HallMasterController extends Controller
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
     * Lists all HallMaster models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new HallMasterSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Master Management');
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single HallMaster model.
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
     * Creates a new HallMaster model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new HallMaster();
        $categorytype = new Categorytype();

        if ($model->load(Yii::$app->request->post())) {

            //echo $model->hall_name."----".$model->description."----".$model->hall_type_id;
            $exist_hall_name = HallMaster::find('hall_name')->where(['hall_name' => $model->hall_name])->one();
            if(empty($exist_hall_name)){
                $model->hall_name = $model->hall_name;
                $model->description = $model->description;
                $model->hall_type_id = $model->hall_type_id;
                $model->created_at = new \yii\db\Expression('NOW()');
                $model->created_by = Yii::$app->user->getId();
                $model->updated_at = new \yii\db\Expression('NOW()');
                $model->updated_by = Yii::$app->user->getId(); 
                $model->save();
                Yii::$app->ShowFlashMessages->setMsg('Success','Record Saved Successfully!!!');
                return $this->redirect(['create',]);
            }else{
                Yii::$app->ShowFlashMessages->setMsg('Error','Record Already Exist!!!');
                return $this->redirect(['create',]);
            }        

            //return $this->redirect(['view', 'id' => $model->coe_hall_master_id]);
        } else {
        Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Hall Creation');

            return $this->render('create', [
                'model' => $model,'categorytype' => $categorytype,
            ]);
        }
    }

    /**
     * Updates an existing HallMaster model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $categorytype = new Categorytype();
        
        if ($model->load(Yii::$app->request->post())) {

            $hall_mapping = HallAllocate::find()->where(['hall_master_id'=>$model->coe_hall_master_id])->one();
            $name_of_hall = $model->hall_name;
            if(empty($hall_mapping))
            {
                $model->save();
                Yii::$app->ShowFlashMessages->setMsg('Success',"<b>".$name_of_hall.'</b> Has Updated Successfully!! ');
                return $this->redirect(['update', 'model' => $model,'categorytype' => $categorytype,'id' => $model->coe_hall_master_id]);                
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error',' You can not Update <b>'.$name_of_hall.'</b> Because already '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." were Assigned OR <b>".$name_of_hall."</b> is in Use");
                return $this->redirect(['update',  'model' => $model,'categorytype' => $categorytype,'id' => $model->coe_hall_master_id]);
            }
        } else {
            return $this->render('update', [
                'model' => $model,
                'categorytype' => $categorytype,
                
            ]);
        }
    }

    /**
     * Deletes an existing HallMaster model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        $hall_mapping = HallAllocate::find()->where(['hall_master_id'=>$model->coe_hall_master_id])->one();       
         $name_of_hall = $model->hall_name;
        if(empty($hall_mapping))
        {
            $this->findModel($id)->delete();
            Yii::$app->ShowFlashMessages->setMsg('Success',"<b>".$name_of_hall.'</b> Has Deleted Successfully!! ');
            return $this->redirect(['index']);
            
        }
        else
        {
            
           Yii::$app->ShowFlashMessages->setMsg('Error',' You can not Delete <b>'.$name_of_hall.'</b> Because already '.ConfigUtilities::getConfigValue(ConfigConstants::CONFIG_EXAM)." were Assigned OR <b>".$name_of_hall."</b> is in Use");
            return $this->redirect(['index']);
            
        }
    }

    /**
     * Finds the HallMaster model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return HallMaster the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = HallMaster::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
