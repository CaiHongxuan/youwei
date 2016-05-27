<?php
return array(
	//'PAGESIZE'=>5,
	'show_page_trace' => 'true',
	'APP_GROUP_MODE' => 1,
	'APP_GROUP_LIST' => 'Home,Backend',
	'DEFAULT_GROUP'  => 'Home',
	'APP_GROUP_PATH' => 'Modules',
	'DB_HOST' => 'localhost',
	'DB_TYPE' => 'mysql',
	'DB_CHARSET' => 'utf8',

	'DB_PREFIX'        =>	'',    // 数据库表前缀

	'DB_NAME' => 'youwei',
	'DB_PORT' => '3306',
	'DB_USER' => 'root',
	'DB_PWD' => '',

	//默认跳转对应的模板文件
 	'TMPL_ACTION_ERROR' => 'Public/dispatch_jump',
 	'TMPL_ACTION_SUCCESS' => 'Public/dispatch_jump',



	'MAIL_ADDRESS'=>'xxxxxx@163.com', // 邮箱地址
	'MAIL_SMTP'=>'smtp.163.com', // 邮箱SMTP服务器
	'MAIL_LOGINNAME'=>'xxxxxx@163.com', // 邮箱登录帐号
	'MAIL_PASSWORD'=>'password', // 邮箱密码




);
?>
