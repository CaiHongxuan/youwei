<?php
/**
 * 后台首页控制器
 */
class IndexAction extends InitializeAction {

	public function index() {
		$this->display ();
	}


	/**
	 * 退出登录
	 * @return [type] [description]
	 */
	public function logout() {
		session_unset();
		session_destroy();
		$this->redirect('Backend/Login/index');
	}
}