<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\assets;

use yii\web\AssetBundle;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/fonts.css',
        'css/site.css',
        'style/bower_components/bootstrap/dist/css/bootstrap.min.css',
        'style/bower_components/font-awesome/css/font-awesome.min.css',
        'style/bower_components/Ionicons/css/ionicons.min.css',
        'style/bower_components/jvectormap/jquery-jvectormap.css',
        'style/dist/css/AdminLTE.min.css',
        'style/dist/css/skins/_all-skins.min.css',
        'style/dist/css/pace.css',
        'style/plugins/iCheck/all.css',
    ];
    public $js = [
        
        'style/plugins/jQueryUI/jquery-ui.min.js',
        'style/dist/js/adminlte.min.js',
        'style/dist/js/adminlte.js',
        'style/dist/js/pace.min.js',
        'js/icheck-min.js',
        'js/coe_functions.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap\BootstrapAsset',
        'dmstr\web\AdminLteAsset',
    ];
}
