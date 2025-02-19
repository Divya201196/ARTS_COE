<?php
namespace app\components;

use Yii;
use yii\base\Component;
use \kartik\widgets\Growl;
use \kartik\widgets\Alert;
use yii\helpers\ArrayHelper;

class ShowFlashMessages extends Component
{
	public static function setMsg($title = 'info', $body = 'info msg')
    {
        \Yii::$app->session->setFlash($title, $body);
    }
    public static function showFlashes($additional = [])
    {
        self::showMsg('', '', array_merge($additional, ['useSessionFlash' => true]));
    }
    public static function showMsg($title = 'info', $body = 'info msg', $additional = [])
    {
 
        $additional = ArrayHelper::merge([
            'linkUrl' => null,
            'delay' => 0,
            'showSeparator' => true,
            'from' => 'top',
            'align' => 'right',
            'alertType' => 'Growl',
            'useSessionFlash' => false,
            'mouse_over'=>'pause',
            'timer'=>1000,
 
        ], $additional);
 
        $typeIndex = 'info';
 
        $typeArray = [
            'success' => ['type' => ['Growl'=>Growl::TYPE_SUCCESS,'Alert'=>Alert::TYPE_SUCCESS],
                'icon' => 'glyphicon glyphicon-ok-sign'],
            'info' => ['type' => ['Growl'=>Growl::TYPE_INFO,'Alert'=>Alert::TYPE_INFO],
                'icon' => 'glyphicon glyphicon-info-sign'],
            'warning' => ['type' => ['Growl'=>Growl::TYPE_WARNING,'Alert'=>Alert::TYPE_WARNING],
                'icon' => 'glyphicon glyphicon-exclamation-sign'],
            'danger' => ['type' => ['Growl'=>Growl::TYPE_DANGER,'Alert'=>Alert::TYPE_DANGER],
                'icon' => 'glyphicon glyphicon-remove-sign'],
            
        ];
 
        $delay = $additional['delay'];
        if ($additional['useSessionFlash']) {
            $flashes = \yii::$app->session->getAllFlashes(true);
            if ($delay == 0  and $additional['alertType']=="Alert") $delay += 2000;
            //self::dump(($flashes));
        } else {
            $flashes = [$title => $body];
        }
 
        foreach ($flashes as $title => $body) {
            $typeIndex = 'info';
            if (!empty($title)) {
                if (stristr($title, "danger") or stristr($title, "error")) {
                    $typeIndex = 'danger';
                } elseif (stristr($title, "warning")) {
                    $typeIndex = 'warning';
                } elseif (stristr($title, "success")) {
                    $typeIndex = 'success';
                } elseif (stristr($title, "info")) {
                    $typeIndex = 'info';
                };
            }
            $msgArray = [
                'type' => $typeArray[$typeIndex]['type'][$additional['alertType']],
                'title' => $title,
                'icon' => $typeArray[$typeIndex]['icon'],
                'body' => $body,
                'linkUrl' => $additional['linkUrl'],
                'showSeparator' => $additional['showSeparator'],
                'delay' => $delay,
                'pluginOptions' => [
                    'timer'=>$additional['timer'],
                    'mouse_over'=>$additional['mouse_over'],
                    'placement' => [
                        'from' => $additional['from'],
                        'align' => $additional['align'],
                    ]
                ]
            ];
            $delay += 1500;
            switch ($additional['alertType']) {
                case 'Growl':
                    echo Growl::widget($msgArray);
                    break;
                default:
                    ArrayHelper::remove($msgArray,'linkUrl');
                    ArrayHelper::remove($msgArray,'pluginOptions');
                    echo Alert::widget($msgArray);
                    break;
            }
        }
 
    }
}

?>