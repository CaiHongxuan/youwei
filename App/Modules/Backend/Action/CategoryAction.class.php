<?php
class CategoryAction extends InitializeAction {
	//根据类型显示分类列表
	/*public function clist() {
		$type = I('get.type');
		if (empty($type))
			msgBox("非法操作，请稍后再试");
		import('ORG.Util.Page');
		$Category = M('Category');
		$condition = array('type' => $type);
		$count = $Category->where($condition)->count();
		$page = new Page($count,8);
		$limit = $page->firstRow.','.$page->listRows;
		$category = $Category->where($condition)->limit($limit)->select();
		$this->assign('category', $category);
		$this->page = $page->show();
		$this->display();
	}*/

	//根据类型显示分类列表
	//oneMan 5.25 
	public function clist() {
		$type = I('param.type','project');
		$keyword = I('param.keyword','');

		if($keyword!=='') {// 模糊查询 名称 序号 地点
			$data['name'] = array('like','%'.$keyword.'%');
			$data['sign'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
		}
		if($data) {//模糊查询
			$condition = array('type'=>$type,$data);
		}else {//无模糊查询
			$condition = array('type'=>$type);
		}

		$Category = M('Category');
		
		//分页跳转的时候保证查询条件
		$map = array('type' => $type ,'keyword'=>$keyword );
		$listAndPage = page($Category,$condition,$map);//分页处理
		//模板赋值
		$this->key = $keyword;	//页面回显关键词
		$this->type = $type;//返回下拉框的值
		$this->category = $listAndPage['list'];
		$this->page = $listAndPage['page'];
		$this->display();
	
	}

	//显示添加页面
	public function add_show() {
		$this->display();
	}

	//实现添加功能
	public function add() {
		$data['name'] = I('post.name');
		$data['sign'] = I('post.sign');
		$data['type'] = I('post.type');
		if(empty(trim($data['name'])) || empty(trim($data['sign']))) {
			$this->error('请填写完整数据');return;
			// msgBox("请填写完整数据");
		}
		$Category = M('Category');
		if (!$Category->create($data)) {
			$this->error('分类添加失败，请稍后再试');
			return;
			// msgBox("分类添加失败，请稍后再试");
		}
		$Category->add();

		$this->addLog('添加活动分类'); 
		
		$this->success('分类已添加',U('Backend/Category/add_show'));
		// $this->redirect('Category/add_show');
		// msgBox('添加成功',U('Category/add_show'));
	}

	//显示编辑页面
	public function edit_show() {
		$id = I('get.id');
		$Category = M('Category');
		$category  =$Category->find($id);
		$this->assign('category', $category);
		$this->display();
	}

	//实现编辑功能
	public function edit() {
		$id = I('post.id');
		$type = I('post.type');
		$data['name'] = I('post.name');
		$data['sign'] = I('post.sign');

		if(empty(trim($data['name'])) || empty(trim($data['sign']))) {
			$this->error('请填写完整数据');return;
		}

		$Category = M('Category');

		if (!$Category->where(array('id' => $id))->save($data)) {
			// msgBox("修改失败");
			$this->error('修改失败');return;
		}
		if (!$this->addLog('修改活动分类')){
			// msgBox("系统出错，请稍后再试");
		}
			
		$this->success('分类已修改',U('Category/clist',array('type'=>$type)));
		
		// $this->redirect('Category/clist', array('type'=>$type));
	}
	
	//实现删除功能
	public function delete() {
		$id = I('get.id');
		$type = I('get.type');
		$Category = M('Category');
		if(!$Category->delete($id))
			msgBox("删除失败");
		if (!$this->addLog('删除活动分类'))
			msgBox("系统出错，请稍后再试");
		$this->redirect('Category/clist', array('type'=>$type));
	}

	//发布
	public function turnOn() {
		$id = I('get.id');
		$type = I('get.type');
		$Category = M('Category');
		$data['status'] = 1;
		$flag = $Category->where(array('id' => $id))->save($data);
		if (!$flag)
			msgBox("发布失败，请稍后再试");
		if (!$this->addLog('发布活动分类'))
			msgBox("系统出错，请稍后再试");
		$this->redirect('Category/clist', array('type'=>$type));
	}

	//不发布
	public function turnOff() {
		$id = I('get.id');
		$type = I('get.type');
		$Category = M('Category');
		$data['status'] = 0;
		$flag = $Category->where(array('id' => $id))->save($data);
		if (!$flag)
			msgBox("取消发布失败，请稍后再试");
		if (!$this->addLog('取消发布活动分类'))
			msgBox("系统出错，请稍后再试");
		$this->redirect('Category/clist', array('type'=>$type));
	}
}