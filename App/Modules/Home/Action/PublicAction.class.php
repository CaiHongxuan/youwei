<?php

class PublicAction extends CommonAction {

	//帮助
	public function help() {
		$this->display();
	}

	//关于我们
	public function about() {
		$this->display();
	}

	//联系我们
	public function contact() {
		$this->display();
	}

	//公用页头
	public function header(){
		$this->display();
	}

	//公用页尾
	public function footer(){
		$this->display();
	}
}

?>