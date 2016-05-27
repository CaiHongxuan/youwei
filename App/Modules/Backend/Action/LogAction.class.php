<?php
class LogAction extends InitializeAction {
	/*public function loglist() {
		import('ORG.Util.Page');
		$Log = M('Log');
		$count = $Log->where($condition)->count();
		$page = new Page($count,12);
		$limit = $page->firstRow.','.$page->listRows;
		$log = $Log->limit($limit)->select();
		$this->assign('log', $log);
		$this->page = $page->show();
		$this->display();
	}*/

	public function loglist() {

		$keyword = I('param.keyword','','trim');
		
		if($keyword!=='') {
			$data['name'] = array('like','%'.$keyword.'%');
			$data['operation'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
			$params = array('keyword' => $keyword);
		} else {
			$data = true;
			$params = null;
		}

		$Log = M('Log');
		$result = page($Log,$data,$params);
		
		$this->keyword = $keyword;
		$this->log = $result['list'];
		$this->page = $result['page'];
		$this->display();

	}
}