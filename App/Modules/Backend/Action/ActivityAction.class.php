<?php
class ActivityAction extends InitializeAction {
	//活动列表
	/**
	 * oneMan 5.25 修改
	 */
	public function alist() {
		$ispassed = I('param.ispassed',0);
		$keyword = I('param.keyword','');
			
		if($keyword!=='') {// 模糊查询 名称 序号 地点
			$data['name'] = array('like','%'.$keyword.'%');
			$data['description'] = array('like','%'.$keyword.'%');
			$data['number'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
		}

		if($data) {//模糊查询
			$condition = array('is_passed'=>$ispassed,'ispublished'=>1,$data);
		}else {//无模糊查询
			$condition = array('is_passed'=>$ispassed,'ispublished'=>1);
		}
		$Activity = M('Activity');
		
		//分页跳转的时候保证查询条件
		$map = array('ispassed' => $ispassed ,'keyword'=>$keyword );
		$listAndPage = page($Activity,$condition,$map);//分页处理

	
		//模板赋值
		$this->key = $keyword;	//页面回显关键词
		$this->ispassed = $ispassed;//返回下拉框的值
		$this->activity = $listAndPage['list'];
		$this->page = $listAndPage['page'];
		$this->display();
	}

	//显示活动详细
	public function detail() {
		$id = I('get.id');

		$Activity = M('Activity');
		$ActivityInfo = M('ActivityInfo');
		$Category = M('Category');
		$User = M('User');
		$Attachment = M('Attachment');

		$activity = $Activity->find($id);
		$count = $ActivityInfo->where(array('activity_id' => $id))->count();
		$category = $Category->find($activity['category_id']);
		$user = $User->where('id', $activity['user_id'])->find();
		$image = $Attachment->find($activity['image_attachment_id']);
		$this->assign('activity', $activity);
		$this->assign('count', $count);
		$this->assign('cate', $category);
		$this->assign('user', $user);
		$this->assign('image', $image);
		$this->display();
	}

	//显示添加页面
	public function add_show() {
		$Category = M('Category');
		$this->category = $Category->where(array('type' => 'activity'))->select();
		$this->display();
	}

	//添加功能
	public function add() {
		$data['name'] = I('post.name');
		$data['description'] = I('post.description');
		$data['category_id'] = I('post.category_id');
		$data['detail'] = I('post.detail');
		$data['ispublished'] = 1;
		$data['user_id'] = $_SESSION['uid'];

		import('ORG.Net.UploadFile');
		if (!empty($_FILES['image']['tmp_name']) || !empty($_FILES['attachment']['tmp_name'])) {
			$upload = new UploadFile();// 实例化上传类
			$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
			$upload->savePath =  './Uploads/activity/';// 设置附件上传目录
			$upload->autoSub = true;
			$upload->subType = 'date';
			$upload->dateFormat = 'Ym/dH';
			if(!$upload->upload()) {// 上传错误提示错误信息
				msgBox($upload->getErrorMsg());
				exit();
			}else{// 上传成功 获取上传文件信息
				$files =  $upload->getUploadFileInfo();
				foreach ($files as $file) {
					if ($file['key'] == 'image')
						$image_name = $file['savename'];
					elseif ($file['key'] == 'attachment')
						$attachment_name = $file['savename'];
				}
				//封面
				if (!empty($image_name)) {
					$Attachment1 = M('Attachment');
					$Attachment1->create();
					$Attachment1->path = $image_name;
					$Attachment1->created_time = date('Y:m:d H:i:s');
					$image_attachment_id = $Attachment1->add();
					$data['image_attachment_id'] = $image_attachment_id;
				}
				//附件
				if (!empty($attachment_name)) {
					$Attachment2 = M('Attachment');
					$Attachment2->create();
					$Attachment2->path = $attachment_name;
					$Attachment2->created_time = date('Y:m:d H:i:s');
					$file_attachment_id = $Attachment2->add();
					$data['file_attachment_id'] = $file_attachment_id;
				}
			}
		}
		$Activity = M('Activity');
		if(!$Activity->create($data) || !$Activity->add()) {
			// msgBox("活动添加失败");
			$this->error('活动添加失败');
			return;
		}
		$this->success('活动已添加',U('Backend/Activity/add_show'));
		// $this->redirect('Activity/add_show');
	}

	//显示编辑页面
	public function edit_show() {
		$Category = M('Category');
		$this->category = $Category->where(array('type' => 'activity'))->select();
		$this->display();
	}

	//实现编辑功能
	public function edit() {

	}
	
	//实现删除功能
	public function delete() {
		$id = I('get.id');
		$action = I('get.action');
		$Activity = M('Activity');
		$Attachment = M('Attachment');

		$activity = $Activity->find($id);
		// p($activity);die();
		$Activity->startTrans();
		try{
			if ($activity['image_attachment_id'] != null) {
				$path = $Attachment->find($activity['image_attachment_id']);
				if(file_exists('./Uploads/activity/'.$path)) {
					unlink('./Uploads/activity/'.$path);
				}
			}
			if ($activity['file_attachment_id'] != null) {
				$path = $Attachment->find($activity['file_attachment_id']);
				if (file_exists('./Uploads/activity/' . $path)) {
					unlink('./Uploads/activity/' . $path);
				}
			}
			$Activity->delete($id);
			$Activity->commit();
			// $this->redirect('Activity/alist');
			$this->success('删除成功',$action);
			
		}catch (Exception $e) {
			$Activity->rollback();
			// msgBox("删除失败");
			$this->error('删除失败');
		}
	}

	//审核 is_passed 0 待审核 1通过 2未通过
	public function examine() {
		$data = array(
			'is_passed' => I('ispassed','0'),
			'passed_time' => date('Y-m-d H:i:s'),
		);
		$id = I('get.id');
		$Activity = M('Activity');
		if($data['is_passed'] == 1){

			//获取项目分类标志
			$category_id = $Activity->where('id='.$id)->getField('category_id');
			$cate_sign = M('Category')->where('id='.$category_id)->getField('sign');

			//查询当天已审核的项目个数
			$passnum = M('Activity')->where(array('pass_time'=>array('like',date('Y-m-d').'%')))->count();
			$passnum++;
			if($passnum<10) {
				$passnum = '00'.$passnum;
			}elseif($passnum >=10 && $passnum < 100) {
				$passnum = '0'.$passnum;
			}else {
				$passnum = ''.$passnum;
			}
			//生成活动序号
			$number = $cate_sign.date('ymd').$passnum;
			$data['number'] = $number;

		} else {//当项目由已通过改为未通过 序号置为EmptyString
			$data['number'] = '';
		}
		if($Activity->where('id='.$id)->save($data)) {
			// msgBox("操作成功", U('Backend/Activity/detail'));return;
			$this->success('操作成功',U('Backend/Activity/detail',array('id'=>$id)));
			return;
		} else {
			$this->error('操作失败');
			return;
			// msgBox("操作失败");
		}
	}

	//后台下载附件
	public function download() {
		$id = I('get.id');
		$Attachment = M('Attachment');
		$attachment = $Attachment->find($id);
		$basename = dirname(dirname(dirname(dirname(dirname(__FILE__) )))) . '/Uploads/activity/' . $attachment['path'];
		header("Content-Type: application/force-download");
        header("Content-Disposition: attachment; filename=".basename($basename));
        readfile($basename);
        exit;
	}
	//设置为活动精选或者取消
	public function packedOrNot() {

		$id = I('get.id',0,'intval');
		$setTo = I('get.setTo',0,'intval');
		$Activity = M('Activity');
		if($Activity->where(array('id'=>$id))->getField('is_passed')) {

			if($setTo) {
				if(!M('Activity')->where(array('id'=>$id))->setField('packed',1)){
					$this->error('操作失败');return;
				}
			}else{
				if(!M('Activity')->where(array('id'=>$id))->setField('packed',0)) {
					$this->error('操作失败');return;
				}
			}

			// $this->redirect('Project/detailProject',array('id'=>$id));
			$this->success('操作成功');
			return;
		}

		// msgBox('操作失败',U('Project/detailProject',array('id'=>$id)));
		$this->error('操作失败');
	}
	/**
	 * 发起人信息 发起者可以是 企业 管理员
	 * @return [type] [description]
	 */
	public function organiserInfos() {

		$user_id = I('get.id');

		$typeOfUser = M('User')->where('id='.$user_id)->getField('type');

		if($typeOfUser) {
			$this->redirect('User/detail'.$typeOfUser, array('user_id' => $user_id));
		} else {
			msgBox("查看失败".$typeOfUser);
		}

	}
}