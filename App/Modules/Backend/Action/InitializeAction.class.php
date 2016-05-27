<?php

Class InitializeAction extends CommonAction {
	
    /**
     * 登录检测（是否通过用户名和密码登录）
     * @return [type] [description]
     */
    public function _initialize() {
        if(!isset($_SESSION['uid']) || !isset($_SESSION['account'])) {
            $this->redirect('Backend/Login/index');
        }
    }
    public function addLog($operation) {
        $data['user_id'] = $_SESSION['uid'];
        $data['name'] = $_SESSION['username'];
        $data['operation'] = $operation;
        $data['created_time'] = date('Y-m-d H:i:s',time());
 
        $Log = M('Log');
        if(!$Log->create($data) || !$Log->add())
            return 0;
        return 1;
    }
}
?>