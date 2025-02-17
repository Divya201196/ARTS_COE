<?php

namespace app\controllers;

use Yii;
use app\models\TransferCertificates;
use app\models\TransferCertificatesSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
/**
 * TransferCertificatesController implements the CRUD actions for TransferCertificates model.
 */
class TransferCertificatesController extends Controller
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
     * Lists all TransferCertificates models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new TransferCertificatesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single TransferCertificates model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new TransferCertificates model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new TransferCertificates();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_transfer_certificates_id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing TransferCertificates model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) ) 
        {
            //// TRCKING CODE STARTS //////

             $updated_from = ucwords( str_replace('-', ' ', Yii::$app->controller->action->controller->id.' '.Yii::$app->controller->action->id) );
              $data_updated = 'TC Details Updated for Reg Num : '.$model->register_number;

             $data_array = ['subject_map_id'=>0,'updated_link_from'=>$updated_from,'data_updated'=>nl2br($data_updated),'exam_month'=>0,'exam_year'=>0,'student_map_id'=>0 ];            
              $update_track = ConfigUtilities::updateTracker($data_array);

            //// TRCKING CODE ENDS //////

            $model->date_of_app_tc = date('Y-m-d',strtotime($_POST['date_of_app_tc']));
            $model->admission_date = date('Y-m-d',strtotime($_POST['admission_date']));
            $model->date_of_tc = date('Y-m-d',strtotime($_POST['date_of_tc']));
            $model->date_of_left = date('Y-m-d',strtotime($_POST['date_of_left']));
            $model->save();
            return $this->redirect(['view', 'id' => $model->coe_transfer_certificates_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing TransferCertificates model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $find_data = $this->findModel($id);
        //// TRCKING CODE STARTS //////

         $updated_from = ucwords( str_replace('-', ' ', Yii::$app->controller->action->controller->id.' '.Yii::$app->controller->action->id) );
          $data_updated = 'TC Details Removed : '.$find_data['register_number'];

         $data_array = ['subject_map_id'=>0,'updated_link_from'=>$updated_from,'data_updated'=>nl2br($data_updated),'exam_month'=>0,'exam_year'=>0,'student_map_id'=>0 ];            
          $update_track = ConfigUtilities::updateTracker($data_array);

        //// TRCKING CODE ENDS //////

        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the TransferCertificates model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return TransferCertificates the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = TransferCertificates::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
