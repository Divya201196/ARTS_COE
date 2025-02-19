<?php
use kartik\mpdf\Pdf;
$params = require(__DIR__ . '/params.php');
$db = require(__DIR__ . '/db.php');

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
     
    'components' => [
       
        'ConfigConstants' =>  [
             'class' => 'app\components\ConfigConstants',
        ],
        'ConfigUtilities' =>  [
             'class' => 'app\components\ConfigUtilities',
        ],
        'ShowFlashMessages' =>  [
             'class' => 'app\components\ShowFlashMessages',
        ],
        'excel'=>[
            'class'=>'app\components\ExportToExcel',
        ],
        'formatter' => [
            'class' => 'yii\i18n\Formatter',
            'nullDisplay' => '',
        ],
        'pdf' => [
                'class' => Pdf::classname(),
                'format' => Pdf::FORMAT_A4,
                'orientation' => Pdf::ORIENT_PORTRAIT,
                'destination' => Pdf::DEST_BROWSER,
                // refer settings section for all configuration options
        ],
        /* */

        // Admin Look
        'view' => [
         'theme' => [
             'pathMap' => [
                '@app/views' => '@app/views'
                ],
            ],
        ],
        'assetManager' => [
        'bundles' => [
            'dmstr\web\AdminLteAsset' => [
                'skin' => 'skin-blue',
                ],
            ],
         
         ],
         // Admin Look

        // Authentication Manager with MDM Soft extension
        'authManager' => [
            'class' => 'yii\rbac\DbManager', // or use 'yii\rbac\DbManager'
        ],

        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '-EG6ddQr9Y-v_PGxtgGvtRkrdqcQTRij',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
            //'class' => 'yii\caching\ApcCache',
            //'class' => 'yii\caching\MemCache',
        ],
        'user' => [
            'identityClass' => 'mdm\admin\models\User',
            'class' => 'app\components\User',
            'loginUrl' => ['site/login'],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        
    ],
    'params' => $params,
     'modules' => [
        'gridview' => [ 
            'class' => '\kartik\grid\Module',
            
            ], 
        'admin' => [
            'class' => 'mdm\admin\Module', 
            'layout' => 'left-menu', 
            'menus' => [
                'assignment' => [
                    'label' => 'Grant Access' // change label
                ],
                'route' => null, // disable menu
            ],
            'mainLayout' => '@app/views/layouts/main.php',           
        ],
        'rbac' => [
            'class' => 'mdm\admin\Module',
            'controllerMap' => [
                 'assignment' => [
                    'class' => 'mdm\admin\controllers\AssignmentController',
                    'userClassName' => 'mdm\admin\models\User', 
                    'idField' => 'id',
                    'usernameField' => 'username',
                   
                ],
            ],
            //'layout' => 'left-menu',
            'mainLayout' => '@app/views/layouts/main.php',
        ],
       
        /* Start Adding the Modules Here */
        
        /*Finish Adding the modules*/


    ],
    'as access' => [
    'class' => 'mdm\admin\components\AccessControl',
    'allowActions' => [
        'site/*',
        
        ]
    ],


];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => ['127.0.0.1', '::1', '192.168.0.*', '192.168.88.142'],
        'generators' => [ //here
            'crud' => [
                'class' => 'yii\gii\generators\crud\Generator',
                'templates' => [
                    'adminlte' => '@vendor/dmstr/yii2-adminlte-asset/gii/templates/crud/simple',
                ]
            ]
        ],
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
