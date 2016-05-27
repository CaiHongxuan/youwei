<?php

 // 后台登录控制器
Class LoginAction extends Action {

	// 登录视图
	Public function index () {
		$this->display();
	}


	//处理登陆
	Public function login () {

		
		if(!IS_AJAX) halt('页面不存在');
		$verify = session('verify');
		if(I('code', '', 'md5') != $verify) {
			$this->ajaxReturn(null, '验证码错误', 0);
		}
		session('verify', null);

		$account = I('post.email');
		$pwd = md5(md5(I('password').'youwei'));

		$user = M('user');
		$finduser = $user->where(array('account' => $account))->find();

		if(!$finduser || $finduser['password'] != $pwd || $finduser['type']!=='Admin') {

			$this->ajaxReturn(null, '邮箱或密码错误', 0);
		}

		//记录当前登陆账号的session
		session('uid', $finduser['id']);
		session('account', $finduser['account']);
		session('username',$finduser['name']);
		session('type',$finduser['type']);//管理员标志

		$this->ajaxReturn(U('Backend/Index/index'),'登陆成功',1);
	}

	/**
	 * 修改管理原密码
	 * @return [type] [description]
	 */
	public function changePasswordForAdmin() {
		
		//检测是否登录
        if(!isset($_SESSION['uid']) || !isset($_SESSION['account']) || !isset($_SESSION['account'])  ||  !isset($_SESSION['type']) || $_SESSION['type']!=='Admin') {
            $this->redirect('Backend/Login/index');return;
        }

        if(IS_POST) {//处理修改请求
        	$oldPwd = I('post.oldPwd');
        	$newPwd = I('post.newPwd');
        	$repwd = I('post.rePwd');

        	if($newPwd!==$repwd){
        		msgBox('密码不一致',U('Backend/Login/changePasswordForAdmin'));
        		exit();
        	}
        	$Model = M('user');
        	$res = $Model->where(array('id'=>session('uid'),'type'=>session('type')))->find();
        	if($res) {
        		if(md5(md5($oldPwd.'youwei'))!==$res['password']) {
        			$this->error('旧密码错误');
        			exit();
        		}
        		$res['password']=md5(md5($newPwd.'youwei'));
        		if($Model->save($res)) {
        			$this->success('修改成功',U('Backend/Index/index'));
        			return;
        		}else{
        			$this->error('修改失败');return;
        		}
        	}
        }else {//提供修改表单
        	$this->display();
  
        }
	}

	/**
	 * 验证码
	 * @return [type] [description]
	 */
	Public function verify () {
		import('ORG.Util.Image');
		Image::buildImageVerify(4, 1, 'png', 48, 25);
	}
}
