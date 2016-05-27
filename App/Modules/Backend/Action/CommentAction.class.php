<?php

/**
 * 后台评论管理控制器
 */
class CommentAction extends InitializeAction {

	//评论列表
	public function clist() {
		
		$keyword = I('param.keyword','','trim');
		
		$condition = null;
		if($keyword !== '') {
			$condition['project.name'] = array('like','%'.$keyword.'%');
			$condition['project_comment.content'] = array('like','%'.$keyword.'%');
			$condition['_logic'] = 'OR';
		}
		if($condition === null) {
			$condition = true;
		}

		import('ORG.Util.Page');
	 	$ProjectComment =  M('ProjectComment');

	 	$count = $ProjectComment
	 			->join('user on user.id=project_comment.user_id') 
				->join('project on project.id=project_comment.project_id')
				->field('project_comment.id,project_comment.username,project_comment.content,project_comment.type,project_comment.created_time,user.id as user_id,user.type as user_type,project.id as project_id,project.name as project_name')
				->where($condition)
				->limit($Page->firstRow.','.$Page->listRows)
				->count();

		$Page = new Page($count,8);
		//分页跳转的时候保证查询条件
		$keyword==''||$params = array('keyword' => $keyword);
		if($params) {
         foreach($params as $key=>$val) 
            $Page->parameter   .=   "$key=".urlencode($val).'&';
        }

		$data = $ProjectComment 
				->join('user on user.id=project_comment.user_id')
				->join('project on project.id=project_comment.project_id')
				->field('project_comment.id,project_comment.username,project_comment.content,project_comment.type,project_comment.created_time,user.id as user_id,user.type as user_type,project.id as project_id,project.name as project_name')
				->where($condition)
				->limit($Page->firstRow.','.$Page->listRows)
				->select();

		
    
		// p($ProjectComment->getLastSql());die;
		 
		$this->key = $keyword;	//页面回显关键词
		$this->page = $Page->show();
		$this->comments = $data;
		$this->display();

	}

	// 删除评论
	public function  delete() {
		$delId = I('get.id',0,'intval');
		if(M('ProjectComment')->where(array('id'=>$delId))->delete()) {
			// msgBox('删除成功',U('Comment/clist'));
			$this->success('删除成功');
			return;
		} else {
			// msgBox('删除失败',U('Comment/clist'));
			$this->error('删除失败');
			return;
		}

	}
}