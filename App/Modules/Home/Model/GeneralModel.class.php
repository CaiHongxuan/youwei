<?php

/**
 * 普通用户Model
 * @author 谢伟鹏
 */
class GeneralModel extends Model {

	protected $_validate = array(
		array('telephone',"/^1[3|4|5|8][0-9]\\d{8}$/",'手机号码格式不正确',2,'regex'),
		// array('idcard','number','身份证格式不正确',2),
		array('idcard',"/^[1-9]\d{5}[1-9]\d{3}((0\d)|(1[0-2]))(([0|1|2]\d)|3[0-1])\d{3}(\d|x|X)$/",'身份证格式不正确',2,'regex'),
	 );

	// 是否批处理验证
	protected $patchValidate = true;
}

?>