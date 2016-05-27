<?php 

class TutorModel extends Model {

	protected $_validate = array(

		//array(验证字段,验证规则,错误提示,[验证条件,附加规则,验证时间]),
		
		array('education','require','*',1),
		array('unit','require','*',1),
		array('position','require','*',1),
		array('mail','email','格式错误',1),
		array('address','require','*',1),
		array('introduction','require','*',1),
		array('level','number','请填写数字',1),
		
		array('name','require','*',1),
		array('password','/^[a-zA-Z0-9]{6,16}$/','6~16字母或数字',1,'regex'), 
		array('repassword','password','密码不一致',1,'confirm'),

		array('tele',"/^1[3|4|5|8][0-9]\\d{8}$/",'格式错误',1,'regex'),
		array('idcard',"/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}(\d|x|X)$/",'格式错误',2,'regex'),
	 );

	// 是否批处理验证
	protected $patchValidate = true;


}