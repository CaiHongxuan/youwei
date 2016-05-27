<?php

/**
 * 基本用户Model
 * @author 谢伟鹏
 */
class UserModel extends Model {

	protected $_validate = array(

		//array(验证字段,验证规则,错误提示,[验证条件,附加规则,验证时间]),
		array('name','/^[A-Za-z0-9]{4,16}$/','4~16字母',1,'regex'),

		// array('account','require','Email不能为空。',1),
		array('account','email','Email格式有误',1),
		// 在新增的时候验证account字段 即用户登录邮箱是否唯一
		array('account','','帐号已存在',1,'unique',1), 

		// array('password','require','密码不能为空。',1),
		array('password','/^[a-zA-Z0-9]{6,16}$/','6~16字母或数字',1,'regex'), 
		// 验证确认密码是否和密码一致
		array('repassword','password','确认密码不一致',1,'confirm'),

	    
	 );

	
	// 是否批处理验证
	protected $patchValidate = true;
}

?>