function change_code(obj) {
	$("#code").attr("src", verifyURL + '/' + Math.random());
	return false;
}
//登录验证  1为空   2为错误
var validate = {
	email: 1,
	password: 1,
	code: 1
}
$(function() {
	$("#login").submit(function() {
		if (validate.email == 0 && validate.password == 0 && validate.verifypwd == 0 && validate.code == 0) {
			return true;
		}
		//验证用户名
		$("input[name='email']").trigger("blur");
		//验证密码
		$("input[name='password']").trigger("blur");
		//验证验证码
		$("input[name='code']").trigger("blur");
		return false;
	})
})
$(function() {
	//验证用户名
	$("input[name='email']").blur(function() {
			var email = $("input[name='email']");
			if (email.val().trim() == '') {
				email.parent().find("span").remove().end().append("<span class='error'>邮箱不能为空</span>");
				return;
			}
			else{
				email.parent().find("span").remove();
			}
		})
		//验证密码
	$("input[name='password']").blur(function() {
			var password = $("input[name='password']");
			var email = $("input[name='email']");
			if (email.val().trim() == '') {
				return;
			}
			if (password.val().trim() == '') {
				password.parent().find("span").remove().end().append("<span class='error'>密码不能为空</span>");
				return;
			}
			else{
				password.parent().find("span").remove();
			}

		})
		//验证验证码
	$("input[name='code']").blur(function() {
		var code = $("input[name='code']");
		if (code.val().trim() == '') {
			code.parent().find("span").remove().end().append("<span class='error'>验证码不能为空</span>");
			return;
		}
		else{
			code.parent().find("span").remove();
		}
	})
})