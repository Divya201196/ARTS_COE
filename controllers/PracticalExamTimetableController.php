<?php

namespace app\controllers;

use Yii;
use app\models\PracticalExamTimetable;
use app\models\PracticalExamTimetableSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

use app\models\PracticalEntry;
use app\models\MarkEntry;
use app\models\Student;
use app\models\StudentMapping;
use app\models\MarkEntryMaster;
use app\models\SubjectsMapping;
use app\models\Subjects;
/**
 * PracticalExamTimetableController implements the CRUD actions for PracticalExamTimetable model.
 */
class PracticalExamTimetableController extends Controller
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
     * Lists all PracticalExamTimetable models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new PracticalExamTimetableSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single PracticalExamTimetable model.
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
     * Creates a new PracticalExamTimetable model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new PracticalExamTimetable();
        $markEntry = new MarkEntry();
        $student = new Student();
        $MarkEntryMaster = new MarkEntryMaster();
        if ($model->load(Yii::$app->request->post())) 
        {
            echo "<PRe>";
            print_r($model); exit;
            
        } else {
            Yii::$app->ShowFlashMessages->setMsg('Welcome','Welcome to Practical Exam Timetable');
            return $this->render('create', [
                'model' => $model,
                'student'=>$student,
                'markEntry'=>$markEntry,
                'MarkEntryMaster'=>$MarkEntryMaster,
            ]);
        }
    }

    /**
     * Updates an existing PracticalExamTimetable model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
         $markEntry = new MarkEntry();
        $student = new Student();
        $MarkEntryMaster = new MarkEntryMaster();
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->coe_practical_exam_timetable_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
                'student'=>$student,
                'markEntry'=>$markEntry,
                'MarkEntryMaster'=>$MarkEntryMaster,
            ]);
        }
    }

    /**
     * Deletes an existing PracticalExamTimetable model.
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
     * Finds the PracticalExamTimetable model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return PracticalExamTimetable the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = PracticalExamTimetable::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
