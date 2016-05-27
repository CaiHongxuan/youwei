<?php

/**
 * 图片管理Action
 */
class PhotoAction extends InitializeAction {

	/**
	 * 图片列表 
	 * @return [type] [description]
	 */
	public function piclist() {

		if(IS_POST) {
			
			$keyword = I('post.keyword');
			$type = I('post.type');
			
			if($type!=='') {
				$condition['type'] = $type;
			}

			if($keyword!=='') { 
				$condition['title'] = array('like','%'.$keyword.'%');
				$condition['description'] = array('like','%'.$keyword.'%');
				$condition['upload_time'] = array('like','%'.$keyword.'%');
				$condition['_logic'] = 'OR';
			}

			import('ORG.Util.Page');
			$Pic = M('focusPic');
			$count = $Pic->where($condition)->count();
			$page = new Page($count,8);
			$limit = $page->firstRow.','.$page->listRows;
			
			$this->type=$type;
			$this->pics = $Pic->where($condition)->order('upload_time DESC')->limit($limit)->select();
			$this->page = $page->show();
			$this->display();

		}else{
			import('ORG.Util.Page');
			$count = M('focusPic')->count();
			$page = new Page($count,8);
			$limit = $page->firstRow.','.$page->listRows;
			$this->pics = M('focusPic')->order('upload_time DESC')->limit($limit)->select();
			$this->page = $page->show();
			$this->display();
		}

	}

	/**
	 * 添加图片
	 * @return [type] [description]
	 */
	public function addpic() {

		if(IS_POST) {
			//处理上传请求
			
			import('ORG.Net.UploadFile');
			$upload = new UploadFile();// 实例化上传类
			$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
			$upload->allowExts  = array('jpg', 'png', 'jpeg');// 设置附件上传类型
			$uploadpath = './Uploads/show_pics/';
			$upload->savePath = $uploadpath;// 设置附件上传目录
			//上传目录不存在 则创建
			if(!file_exists($uploadpath)) {
				mkdir($uploadpath,0777);
			}

			$upload->autoSub = true;
			$upload->subType = 'date';
			$upload->dateFormat = 'Ym/dH';

			if(!$upload->upload()) {// 上传错误提示错误信息
								
				$this->error($upload->getErrorMsg());
			}  
									
			$uploadFileInfos =  $upload->getUploadFileInfo();
			

			// 保存表单数据 包括附件数据
			$Pic = M('focusPic');
			$Pic->create();

			$Pic->upload_time = date('Y-m-d H:i:s');
			$Pic->url = $uploadFileInfos[0]['savename'];

			if($Pic->add()) {
				$this->success('添加成功');
			}else{
				$this->error('添加失败');
			}

		} else {
			$this->display();
		}

	}


	/**
	 * 根据ID删除图片
	 * @return [type] [description]
	 */
	public function deletepic() {

		//获取请求删除的ID
		$delId = I('get.delId');

		//根据ID查找图片路径
		$Pic = M('focusPic');
		$path = $Pic->where("id=$delId")->getField('url');

		if($path) {

			$path = './Uploads/show_pics/'.$path;
	 
			//根据路径删除图片 记录
			if(@unlink($path) && $Pic->where("id=$delId")->delete()) {
				$this->success('删除成功');
			}

		}else {
			$this->error('删除失败');
		}

	}

	/**
	 * 设置图片在前台显示
	 * @return [type] [description]
	 */
	public function showpic() {
		
		$id = I('get.id');
		M('focusPic')->where('id='.$id)->setField('status',1);
		$this->redirect('Photo/piclist');
	}


	/**
	 * 设置图片为不显示 
	 * @return [type] [description]
	 */
	public function hiddenpic() {

		$id = I('get.id');
		M('focusPic')->where('id='.$id)->setField('status',0);
		$this->redirect('Photo/piclist');
	

	}

	/**
	 * 图片预览及编辑页面
	 * @return [type] [description]
	 */
	public function editpic() {

		if(IS_POST)  {//处理修改
			
			$data = array(
				'id' => I('post.id'),
				'title' => I('post.title'),
				'description' => I('post.description'), 
				'type' => I('post.type'),
				'status' => I('post.status',0),
				'link' => I('post.link',''),
				'upload_time' => date('Y-m-d H:i:s')
				);
			if(M('focusPic')->save($data)){
				$this->success('修改成功','piclist');
			}else {
				$this->error('修改失败');
			}
				
		} else {
			//点击详细页面 
			$id = I('get.id');
			$this->thePic = M('focusPic')->where('id='.$id)->find();
			$this->display();
		}
	}


}

