<?php

class ActivityAction extends CommonAction {

	public function index() {
		$Activity = M('Activity');
		$Category = M('Category');
		$this->category = $Category->where(array('type' => 'activity'))->limit(12)->select();

		//取得项目信息
		$category_id = I('param.cateId','',intval);
		if($category_id) {
			$data['category_id'] = $category_id;
		}
		if($data) {
			$condition = array('is_passed'=>1,$data);
		}else{
			$condition = array('is_passed'=>1);
		}
		//分页跳转保持的条件
		$params = array('cateId' => $category_id);

		$result = pageJoin($Activity,$condition,$params,8,'passed_time desc','activity.id as aid,activity.name as aname,passed_time,category.name as cname','category ON category.id = activity.category_id') ;
		$activitys = $result['list'];
		$this->assign('activitys',$activitys);
		$this->page = $result['page'];

		$this->display();
	}
	/**
	 * 活动详情
	 * @return [type] [description]
	 */
	public function message() {
		$id = I("get.id");
		$Activity = M('Activity');
		$click = $Activity->where('id='.$id)->getField('click');
		$Activity->where(array('id' => $id))->save(array('click' => $click+1));

		$activity= $Activity
			->join('join user on user.id = activity.user_id')
			->field('user.name as uname, activity.name as aname,
			activity.passed_time,activity.description,activity.detail,activity.id as aid,click,file_attachment_id')
			->where(array('activity.id' =>$id))
			->find();
		$this->is_attend = M('ActivityInfo')->where(array('activity_id' => $id, 'user_id' => $_SESSION['user_id']))->find();
		//上一篇
		$front=$Activity->where("id<".$id." and is_passed = 1")->order('id desc')->limit('1')->field('id,name')->find();
		//下一篇
		$after=$Activity->where("id>".$id." and is_passed = 1")->order('id desc')->limit('1')->field('id,name')->find();
		$this->assign('front',$front);
		$this->assign('after',$after);
		$this->assign('vo', $activity);
		$this->display();
	}

	/**
	 *活动发布页面
	 *
	 */
	public function launchActivity() {
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
		}
		$id = $_SESSION['user_id'];
		$user = M('User')->where(array('id' => $id))->find();
		if ($user['type'] != "Enterprise")
			$this->redirect('Activity/index');
		$Category = M('Category');
		$this->category = $Category->where(array('type' => 'activity'))->select();
		$this->display();
	}

	/**
	 *活动添加
	 *
	 */
	public function add() {
		if(!IS_POST)
			$this->redirect('Activity/launchActivity');
		$data['name'] = I('post.name');
		$data['description'] = I('post.description');
		$data['category_id'] = I('post.cate_id');
		$data['detail'] = I('post.detail');
		$data['user_id'] = $_SESSION['user_id'];
		$data['ispublished'] = 1;
		//封面不为空
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
		if(!$Activity->create($data) || !$Activity->add())
			msgBox("活动添加失败");
		$this->redirect('Activity/launchActivity');
	}

	public function editActivity() {
		$id = I('get.id');
		$Activity = M('Activity');
		$this->category = M('Category')->where('type=activity')->select();
		$this->activity = $Activity
			->join('attachment on activity.image_attachment_id = attachment.id')
			->where('activity.id='.$id)
			->field('activity.id,name,category_id,description,detail,path')
			->find();
		$this->display();
	}

	public function edit() {
		$data['name'] = I('post.name');
		$data['description'] = I('post.description');
		$data['category_id'] = 1;//I('post.cate_id'); id获取
		$data['detail'] = I('post.detail');
		$id = I('post.id');
		//封面不为空
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
		if(!$Activity->where('id='.$id)->where('user_id='.$_SESSION['user_id'])->save($data)) {
			msgBox("活动失败");
			exit();
		}
		$this->redirect('Activity/index');
	}

	//delete 还未完善
	public function delete() {
		$id = I('get.id');
		$activity = M('Activity')->where('id='. $id)->where('user_id='.$_SESSION['user_id'])->find();
		if (!$activity ==null) {
			msgBox("删除失败");
		}
		M('Attachment')->where('id='.$activity['image_attachment_id'])->delete();
		M('Attachment')->where('id='.$activity['file_attachment_id'])->delete();
		M('ActivityInfo') ->where('activity_id='.$id)->delete();
		M('Activity') ->where('id='.$id)->delete();
	}


	//下载附件
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

	//报名
	public function attend() {
		$id = I('post.id');
		$user_id = $_SESSION['user_id'];
		if ($id==null || $user_id==null) {
			msgBox("系统异常，请稍后再试");
			exit();
		}
		$data['activity_id'] = $id;
		$data['user_id'] = $user_id;
		$info = M('ActivityInfo')->where($data)->find();
		if ($info !=null) {
			$this->ajaxReturn('','你已报名',2);
		}

		$Info = M("ActivityInfo"); // 实例化对象
		if ($Info->add($data))
			$this->ajaxReturn('','报名成功',1);
		$this->ajaxReturn('','报名失败，请稍后再试',0);
	}

}
