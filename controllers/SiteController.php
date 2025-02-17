<?php
namespace app\controllers;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\Signup;
use app\models\LoginDetails;
use mdm\admin\models\form\ChangePassword;
use app\components\ConfigConstants;
use app\components\ConfigUtilities;
use kartik\widgets\Growl;
class SiteController extends Controller
{
    /**                           
     * @inheritdoc                                                                                      */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }
    /**
        * @inheritdoc
    */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }
    /**                                
        * Displays homepage.                                                                                                                                                                                                                    
     *                                                                                                                                                                                                                                       
     * @return string                                                                                                                                                                                                                        
     */
    public function actionIndex()
    {
        if (\Yii::$app->user->isGuest){
            return $this->redirect(['/site/login']);
        }
        else 
        {
                $userid=Yii::$app->user->getId();

            $item_name = Yii::$app->db->createCommand("SELECT item_name FROM auth_assignment WHERE  user_id='" . $userid . "'")->queryScalar();

            if($item_name=='Scrutiny Access')
            {
                 return $this->render('scrutiny-dashboard');
            }
            else
            {
                 return $this->render('user-dashboard');
            }
        }
    }
/**                                                                                                                                                                                                                                      
     * Signup new user                                                                                                                                                                                                                       
     * @return string                                                                                                                                                                                                                        
     */
    public function actionSignup()
    {
        $model = new Signup();
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                return $this->goHome();
            }
        }
        return $this->render('signup', [
                'model' => $model,
        ]);
    }
    /**                                                                                                                                                                                                                                      
     * Login action.                                                                                                                                                                                                                         
     *                                                                                                                                                                                                                                       
     * @return Response|string                                                                                                                                                                                                               
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            Yii::$app->ShowFlashMessages->setMsg('Success','Logged in Successfully!!!');
            return $this->goHome();
        }
        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) ) {
            if($model->login())
            {
                $loginDetails = new LoginDetails();
                $loginDetails->login_user_id = Yii::$app->user->getId();
                $loginDetails->login_at = new \yii\db\Expression('NOW()');
                $loginDetails->login_out = new \yii\db\Expression('NOW()');
                $loginDetails->login_ip_address = LoginDetails::get_ip_address();                
                $loginDetails->save();
                Yii::$app->ShowFlashMessages->setMsg('information','You have Successfully Logged In to  '.Yii::$app->params['app_name']);
               return $this->goBack();
            }
        }
        Yii::$app->ShowFlashMessages->setMsg('information','Log in to Continue the '.Yii::$app->params['app_name']);
        return $this->render('login', [
            'model' => $model,
        ]);
    }
    /**                                                                                                                                                                                                                                      
     * Logout action.                                                                                                                                                                                                                        
     *                                                                                                                                                                                                                                       
     * @return Response                                                                                                                                                                                                                      
     */
    public function actionLogout()
    {
        $loginDetails = LoginDetails::find()->where(['login_ip_address'=>LoginDetails::get_ip_address(),'login_user_id'=>Yii::$app->user->getId(),'login_status'=>0])->orderBy('login_detail_id DESC')->one();
        $user_id = Yii::$app->user->getId();
        if(Yii::$app->user->logout())        
        {     
            if(!empty($loginDetails))
            {
                $loginDetails_data = LoginDetails::updateAll(['login_status' => 1, 'login_out'=> new \yii\db\Expression('NOW()')],"login_user_id= '".$user_id."' AND login_detail_id='".$loginDetails->login_detail_id."' AND login_status=0 AND login_ip_address='".LoginDetails::get_ip_address()."'");           
                Yii::$app->ShowFlashMessages->setMsg('information','You have Successfully Logged out Please Login Again to Continue to '.Yii::$app->params['app_name']);
            }
            else
            {
                Yii::$app->ShowFlashMessages->setMsg('Error','You have Successfully Logged out Some Un Expected Error Please Login Again to Continue to '.Yii::$app->params['app_name']);
            }
            return $this->goHome();           
        }
        return $this->goHome();
    }
 /**                                                                                                                                                                                                                                      
     * Displays contact page.                                                                                                                                                                                                                
     *                                                                                                                                                                                                                                       
     * @return Response|string                                                                                                                                                                                                               
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');
            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }
    /**                                                                                                                                                                                                                                      
     * Displays about page.                                                                                                                                                                                                                  
     *                                                                                                                                                                                                                                       
     * @return string                                                                                                                                                                                                                        
     */
    public function actionAbout()
    {
        return $this->render('about');
    }
 /**                                                                                                                                                                                                                                      
     * Reset password                                                                                                                                                                                                                        
     * @return string                                                                                                                                                                                                                        
     */
    public function actionChangePassword()
    {
      $model = new ChangePassword();
      if ($model->load(Yii::$app->getRequest()->post()) && $model->change()) {
               return $this->goHome();
      }
      return $this->render('change-password', [
                                               'model' => $model,
                                               ]);
    }
}