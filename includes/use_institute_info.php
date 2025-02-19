<?php 
use app\models;
$allow_ip = ['172.21.2.1','172.21.2.4'];
$filename = Yii::getAlias('@webroot/includes/institute_info.php');
$modified_status = filesize($filename); 
if($modified_status>1)
{
	if(in_array($_SERVER['REMOTE_ADDR'], $allow_ip))
	{
		require(Yii::getAlias('@webroot/includes/institute_info.php'));
	}
	else
	{
		require(Yii::getAlias('@webroot/includes/institute_info.php'));
	}
	
	$org_name = stripcslashes($institute_info['org_name']); 
	$org_email=stripcslashes($institute_info['org_email']); 
	$org_phone=stripcslashes($institute_info['org_phone']); 
	$org_web=stripcslashes($institute_info['org_web']); 
	$org_address=stripcslashes($institute_info['org_address']);
	$org_tagline=stripcslashes($institute_info['org_tagline']); 
	$file_content_available="Yes";
}
else
{
	$file_content_available="No";
}
        
?>