<?php

class ProjectAction extends CommonAction {


	//项目首页
	public function index() {
		//取得类别 首页限制显示8个类别
		$categories = M('Category')->where("type='project'")->limit(12)->select();
		$this->assign('categories',$categories);

		//取得项目信息
		$category_id = I('param.cateId','',intval);
		$keywords = I('param.keywords','');
		//查询条件	
		
		if($keywords !== '') {
			$data['name'] = array('like','%'.$keywords.'%');
			$data['label'] = array('like','%'.$keywords.'%');
			$data['introduce'] = array('like','%'.$keywords.'%');
			$data['_logic'] = 'OR';
		}
		if($category_id) {
			$data['category_id'] = $category_id;
		} 
		if($data) {
			$condition = array('ispassed'=>1,'_logic'=>'AND',$data);
		}else{
			$condition = array('ispassed'=>1);
		}
		//分页跳转保持的条件
		$params = array('cateId' => $category_id ,'keywords'=>$keywords);

		$Project = M('Project');
 		$result = page($Project,$condition,$params,8,'pass_time desc','id,name,days,pass_time,favor,attachment_id') ;
    
		$projects = $result['list'];

		//剩余时间 及 封面图片路径处理
		foreach ($projects as $key => $perPro) {//可以用视图优化
			
			//获取项目封面路径
			$attaId = $perPro['attachment_id'];
			$attaPath = M('Attachment')->where('id='.$attaId)->getField('path');

			$rootPath = './Uploads/pro_pics/';// 项目封面附件存放目录
			$projects[$key]['pro_pic_url'] =   __ROOT__.'/'.$rootPath.$attaPath  ; 

			// 计算剩余时间 当前时间-审核时间 为负置为0
			
			$remainingday  = $perPro['days'] - intval((time() - strtotime($perPro['pass_time']))/(60*60*24));
			$projects[$key]['remain_day'] = $remainingday>0?$remainingday:0;

			//计算点赞数
			$projects[$key]['favor'] = count(explode(",",$perPro['favor']));	 
		}

		$this->assign('projects',$projects);
		$this->page = $result['page'];
		$this->key = $keywords;
		
		$this->display ();
	}

	//项目详情 包括项目主页 项目进展  评论 支持者 发起人信息
	public function detail() {

		//获取请求参数 项目ID
		$id = I('get.id','','intval');
		
		//获取项目信息
		$Project = M('Project');
		$projectInfos = $Project->join('attachment ON attachment.id=project.attachment_id')
						->join('category ON category.id=project.category_id')
						->where('project.id='.$id)
						->field('project.id as pid,project.name,days,address,attachment_id,category_id,user_id,label,detail,introduce,favor,pass_time,attachment.id as attachment_id,attachment.path,category.id as category_id,category.name as category_name')->find();

		if(!$projectInfos)
			halt('页面不存在');

		// 处理剩余时间
		$remainingday = $projectInfos['days'] - intval((time() - strtotime($projectInfos['pass_time']))/(60*60*24));
		$projectInfos['remain_day']  = $remainingday>0?$remainingday:0;
		
		//处理项目封面 附件地址
		$projectInfos['path'] = __ROOT__.'/Uploads/pro_pics/'.$projectInfos['path'];

		//计算点赞数 
		$projectInfos['favor'] = count(explode(",",$projectInfos['favor']));		

		//处理项目回报
		$rewardInfos = M('ProjectReward')->where('project_id='.$id)->field('id,reward_content,reward_quota,reward_time,reward_type')->select();
	 
		$ProjectSupport = M('ProjectSupport');	
		$user_id = session('user_id');

		foreach ($rewardInfos as $i => $perReward) { 

			$supportInfos = $ProjectSupport->where('reward_id='.$perReward['id'])->field('user_id')->select();

			foreach ($supportInfos as $supp) {
				if($supp['user_id']==$user_id) {
					$rewardInfos[$i]['is_supp'] = 1;break;
				}
			}
			$rewardInfos[$i]['support_num'] = count($supportInfos);

		}
			
		// 处理项目支持者
		
		import('ORG.Util.Page');

		$Model = D('ProjectSupport');

		$count = $Model->where('project_id='.$id)->count();
		$this->supportTotal = $count;
		$page = new Page($count,10);
		$limit = $page->firstRow.','.$page->listRows;

		$supporters = $Model->table(
			'project_support,project_reward,user'
			)->field(
			'project_support.project_id,project_support.reward_id,project_support.user_id,project_support.created_time,
			project_reward.id,project_reward.reward_content,
			user.id,user.name'
			)->where('project_support.project_id='.$id.' AND project_support.reward_id=project_reward.id AND project_support.user_id=user.id')->limit($limit)->select();

		foreach ($supporters as $i => $val) {	

			$supporters[$i]['reward_content'] =  mb_substr($val['reward_content'], 0, 25, 'utf-8').'...';
		}

		//处理项目进展

	 	$progressInfos = M('ProjectProgress')->join('attachment ON attachment.id=attachment_id')->field('project_progress.*,attachment.id as attachment_id,attachment.path')->where(array('project_progress.project_id'=> $id))->order('project_progress.created_time DESC')->select();
		
		// 处理项目评论 15.04.30
		
		$prostatus = I('get.prostatus','0');
		$this->prostatus = $prostatus;
		//评论的查询条件
		if($prostatus) {//前中后期
			$map = array('FIRST.project_id'=>$id,'project_progress.progress_status'=>$prostatus);
		}
		else {
			$map = array('FIRST.project_id'=>$id);
		}

		$commentCount = M()->table('project_comment as FIRST')->join('project_progress ON project_progress.id=FIRST.progress_id')->where($map)->count();
		$pageForComment = new Page($commentCount,10);

		$page_theme = "%upPage% %downPage% %prePage% %linkPage% %nextPage%";
		$pageForComment->setConfig('theme',$page_theme);
	
		$data = M()->table('project_comment as FIRST')
				->join('project_comment as SECOND ON FIRST.comment_id=SECOND.id ')
				->join('user on user.id=FIRST.user_id')
				->join('project_progress ON project_progress.id=FIRST.progress_id')
				->field('FIRST.id,FIRST.username,FIRST.content,FIRST.type,FIRST.created_time,SECOND.id as reply_id,SECOND.username as reply_name,user.type,project_progress.progress_status')
				->where($map)
				->order('FIRST.created_time DESC')
				->limit($pageForComment->firstRow.','.$pageForComment->listRows)
				->select();
		
		
		$this->commentInfos = $data;
		$this->commentCount = $commentCount;
		$this->commentPage = $pageForComment->show();

		//发起人信息 发起人类型有导师 个人 企业
		$userInfos = M('User')->where('id='.$projectInfos['user_id'])->field('id,name,type')->find();
		$Attachment = M('attachment');
		switch ($userInfos['type']) {
			case 'General':
				//获取用户信息
				$result = M('General')->where('user_id='.$projectInfos['user_id'])->field('headpic_attachment_id,resume')->find();
				//处理用户头像
				$generalInfos['headpic_path'] = __ROOT__.'/Uploads/head_pics/'.$Attachment->where('id='.$result['headpic_attachment_id'])->getField('path');
				$generalInfos['resume'] = $result['resume'];
				
				$this->generalInfos = $generalInfos;
				break;
			case 'Tutor':
			case 'Enterprise':
			default:
				//处理用户头像 企业 导师无头像用默认
				$generalInfos['headpic_path'] = __ROOT__.'/Public/images/noavatar_small.gif';
				$generalInfos['resume'] = '';
				
				$this->generalInfos = $generalInfos;
				break;
		}

		$this->progressInfos = $progressInfos;
		$this->page = $page->show(); 
		$this->supporters = $supporters ;
		$this->rewardInfos = $rewardInfos;
		$this->userInfos = $userInfos;
		$this->projectInfos = $projectInfos;
		$this->display();
	}
	//发起项目
	public function launchProject() {
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			// $this->redirect('User/login');
			$this->error('请先登录',U('User/login'));
			exit();
		}
		$this->category = M('Category')
			->where(array('type' => 'project','status' => 1))
			->field('id,name')
			->select();
		$this->display();
	}

	//项目信息添加
	public function add1() {
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		} 
		if (!IS_POST) {
			$this->redirect('Project/launchProject');
			exit();
		}

		$this->category = M('Category')->where('type = project')->where('status = 1')->field('id,name')->select();
				
		//处理表单数据
		$data['name'] = I('post.project_name');
		$data['days'] = I('post.deal_days');
		$data['category_id'] = I('post.cate_id');
		$data['address'] = I('post.province') . ',' . I('post.city');
		$data['introduce'] = I('post.introduce');
		$data['detail'] = I('post.prodetail');
		$data['label'] = I('post.tags');
		$data['user_id'] = $_SESSION['user_id'];
		$data['created_time'] = date('Y:m:d H:i:s');

		//验证数据
		if (trim($data['name']) == '')
			$error['name'] = "color:red;";
		if (trim($data['days']) == '')
			$error['days'] = "color:red;";
		if (trim($data['introduce']) == '')
			$error['introduce'] = "color:red;";
		//验证是否有上传文件
		if (empty($_FILES['image']['tmp_name'])) 
			$error['file'] = "请上传项目封面";
		if(!empty($error)) {
			$this->assign('error', $error);
			$this->assign('data', $data);
			$this->display('Project/launchProject');
			exit();
		}
		//校验成功
		$Project = M('Project');
		if ($Project->create($data)) {
			$Project->startTrans();
			import('ORG.Net.UploadFile');
			$upload = new UploadFile();// 实例化上传类
			$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
			$upload->allowExts  = array('jpg', 'png', 'jpeg');// 设置附件上传类型
			$upload->savePath =  './Uploads/pro_pics/';// 设置附件上传目录
			$upload->autoSub = true;
			$upload->subType = 'date';
			$upload->dateFormat = 'Ym/dH';
			if(!$upload->upload()) {// 上传错误提示错误信息
				$Project->rollback();
				self::setError("file", "上传失败，请稍后再试");
				$this->assign('data', $data);

				$this->display('Project/launchProject');
				exit();
				
			}else{// 上传成功 获取上传文件信息
				$info =  $upload->getUploadFileInfo();
				$Attachment = M('Attachment');
				$Attachment->create();
				$Attachment->path =   $info[0]['savename']; 
				$Attachment->created_time = date('Y:m:d H:i:s');
				$Attachment_id = $Attachment->add();
				$data['attachment_id'] = $Attachment_id;
				$pro_id = $Project->add($data);
				if (!$pro_id) {
					$Project->rollback();
					self::setError("file", "<script type='text/javascript'>alert('项目发起失败，请稍后再试');</script>");
					$this->assign('data', $data);
					$this->display('Project/launchProject');
					exit();
				}
				$Project->commit();
				session('pro_id', null);
				session('pro_id', $pro_id);
				$this->redirect('Project/reward');
			}
		}
	}

	//回报设置页面
	 function reward() {
	 	if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		} 
	 	
	 	$pro_id = $_SESSION['pro_id'];
		if($pro_id == null) {
			$this->redirect('Project/launchProject');
			exit();
		}

		$Reward = M('ProjectReward');
		$condition = array('project_id' => $pro_id);
		$lists = $Reward->where($condition)->select();
		$this->assign('reward', $lists);
		$this->display('editReward');
	}

	//回报设置添加 或 修改(isEdit theEidtId)
	 function add2() {
	 	//未登录
	 	if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		}
		//非法操作，不是通过ajax提交表单
		if(!IS_AJAX) {
			$this->redirect('Project/reward');
			exit();
		}

		//判断是否存在项目id，若无，则可以看成非法操作
		$data['project_id'] = $_SESSION['pro_id'];
		if ($data['project_id'] == null) {
			$this->ajaxReturn('', 'fail', 1);
			exit();
		}

		//封装数据
		$data['reward_content'] = I('post.description');
		$data['reward_quota'] = I('post.limit_num');
		$data['reward_time'] = I('post.repaid_day');
		$data['reward_type'] = I('post.return_type');
		//验证数据
		if (trim($data['reward_content']) == '' || trim($data['reward_quota']) == '' || trim($data['reward_time']) == '') {
			$this->ajaxReturn('',"error", 2);
			exit();
		}

		//数据格式正确
	 	$Reward = M('ProjectReward');

		//判断是否为修改
		$isEdit = I('post.isEdit','0'); 
		$theEidtId = I('post.theEidtId',0,'intval');

	 	if($isEdit==1&&$theEidtId){	//修改
	 		
	 		if(!$Reward->where(array('id'=>$theEidtId,'project_id'=>session('pro_id')))->save($data)) {
				$this->ajaxReturn('',"保存回报设置失败", 2);
				exit();
	 		}
			$this->ajaxReturn('',"保存回报设置成功", 1);


	 	}else{//新增

			if (!$Reward->create($data) || !$Reward->add($data)) {
				$this->ajaxReturn('',"添加回报设置失败", 2);
				exit();
			}
			$this->ajaxReturn('',"添加回报设置成功", 1);
	 	}

	}

	//发起人信息
	function sponsorInfo() {
		//未登录
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		}
		$General = M('General');
		$User = M('User');
		$user_id = $_SESSION['user_id'];
		$phone = $General->where(array('user_id' => $user_id))->getField('telephone');
		$name = $User->where(array('id' => $user_id))->getField('name');
		$this->assign('phone',$phone);
		$this->assign('name', $name);
		$this->display();
	}
	
	//发起人的信息添加
	function  add3() {
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		}
		if(!IS_AJAX) {
			$this->redirect('Project/sponsorInfo');
			exit();
		}
		$data['telephone'] = I('post.phone');
		$data['real_name'] = I('post.real_name');
		$data['address'] = I('post.province'). I('post.city');
		$General = M('General');
		$General->where(array('user_id' => $_SESSION['user_id']))->save($data);
		
		/*2015.5.16 修改 oneMan */	
		$saveOrCommit = I('get.stau',0,'intval');//保存或者提交
		
		if($saveOrCommit==0) {//保存
			$this->ajaxReturn(null, "已保存", 1);
			exit();
		
		}elseif($saveOrCommit==1){//提交审核
			
			$user_id = session('user_id');
			$project_id = session('pro_id');
			
			if ($project_id == null) {
				$this->ajaxReturn('', '提交失败', 1);
				exit();
			}

			if(M('Project')->where(array('id'=>$project_id,'user_id'=>$user_id))->setField('iscommited','1')) {
				session('pro_id',null);
				$this->ajaxReturn('', '', 1);
				exit();
			}else{
				$this->ajaxReturn('', '提交失败', 1);
				exit();
			}
		}
	}
	
	//设置单个错误函数
	private function setError($param, $message) {
		$error[$param] = $message;
		$this->assign('error', $error);
	}

	//删除项目并且删除所有与之相对应的记录
	function delete() {
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		}
		$id = I('get.id');
		$condition = array('id' => $id, 'user_id' => $_SESSION['user_id']);
		$Project = M('Project');
		$project = $Project->where($condition)->find();
		if(!$project)
			exit();
		$project_id = $project['id'];
		$attachment_id = $project['attachment_id'];
		$Attachment = M('Attachment');
		$Comment = M('ProjectComment');
		$Grade  = M('ProjectGrade');
		$Progress = M('ProjectProgress');
		$Reward = M('ProgressReward');
		$Project->startTrans();
		try{
			$Attachment->where(array('id' => $attachment_id))->delete();
			$Comment->where(array('project_id' => $project_id))->delete();
			$Grade->where(array('project_id' => $project_id))->delete();
			$Progress->where(array('project_id' => $project_id))->delete();
			$Reward->where(array('project_id' => $project_id))->delete();
			$Project->where(array('id' => $project_id))->delete();
			$Project->commit();

		}catch(\Exception $e){
			$Project->rollback();
		}
		$this->redirect('User/percenter');
	}


	// 支持或者取消支持
	public function support() {

		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			msgBox('请先登录',U('User/login'));
		 	exit();
		 }

		$reward_id = I('get.rid','','intval');
		$project_id = I('get.pid','','intval');
		$statu = I('get.stau','1','intval');
			 
		if(!M('ProjectReward')->where(array('id'=>$reward_id,'project_id'=>$project_id))->find()) {
			
			msg('访问出错。',U('Project/detail',array('id'=>$project_id)));
			exit();
		}

		$ProjectSupport = M('ProjectSupport');
		if($statu == 1) {//支持
			
			$data = array(
				'project_id' => $project_id,
				'reward_id' => $reward_id,
				'user_id' => session('user_id'), 
				'resource' => '人力、资金、场地、技术等', 
				'created_time' => date('Y-m-d H:i:s')
				 );
			$ProjectSupport->create($data);
			$ProjectSupport->add();

		}elseif($statu == 0) {//取消支持

			$ProjectSupport->where(array('user_id'=>session('user_id'),'project_id'=>$project_id,'reward_id'=>$reward_id))->delete();

		}else {	}

		$this->redirect('Project/detail',array('id'=>$project_id));

	}

	 // 评论
	public function comment() {
		if(!IS_AJAX)
			halt('页面不存在');

		//未登录 提示登录
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {

		 	$this->ajaxReturn(U('User/login'),'请登录后再进行评论',0);
		 	exit();
		 }

		//支持者方可评论
		$user_id = session('user_id');//当前登录用户id
		$project_id =  I('post.pro_id','0','intval');//当前项目id

		// 判断是否为支持者
		if(!M('ProjectSupport')->where(array('user_id'=>$user_id,'project_id'=>$project_id))->select()) {
			
			$this->ajaxReturn(U('Project/detail',array('id'=>$project_id)),'支持者方可评论，抱歉！',0);
		 	exit();
		}

		//获取当前项目最新的进程状态
		$progress_id = 0;
		$progressOfThePro = M('ProjectProgress')->where(array('project_id'=>$project_id))->order('created_time DESC')->find();
		if($progressOfThePro) {
			$progress_id=$progressOfThePro['id'];
		}
		//评论的内容待过滤
		//Code ....

		$info = array(
			'project_id' => $project_id,
			'type' => I('post.type',0),
			'content' => I('post.content'),
			'user_id' => session('user_id'),
			'username' => session('name'), 
			'created_time' => date('Y-m-d H:i:s',time()),  
			'progress_id' => $progress_id,//公共评论 进度默认0 
			'comment_id' => I('post.comment_id')//评论无外键
			 );

		$ProjectComment = M('ProjectComment');
		$ProjectComment->create($info);

		if($pk=$ProjectComment->add()) {

			if($info['comment_id']) {
				$reply_name = $ProjectComment->where(array('id'=>$info['comment_id']))->getField('username');
			}

			$script = '';//为异步添加的节点添加事件
			
			$html = 
			// <li id="Js" class="Js-talkList">
	          '<div class="f-outs">
	            <div class="f-outs-updow clearfix">
	              <a href="" class="cyclo-img01">
	              <img src="'.__ROOT__.'/public/images/noavatar_small.gif"></a>
	              <div class="fl">
	                 <p><a href="">'.$info['username'].'</a>&nbsp;';

	        if($reply_name) {
	        	$html.='<span style="font-size: 14px;">回复</span>
                                        <a>'.$reply_name.'</a>';
	        }

	        $html.=
	                 '<span>'.$info['created_time'].'</span></p>
	                 <p>'.$info['content'].'</p>
	              </div>
	            </div>
	            <p class="lp-hn">
	            <a href="javascript:;"  class="Js-showComment">回复</a>
	            </p>
	            <div class="clearfix" style="display:none">
	                <div class="f-criticism">
	                    <form class="comment-input" action=""  id="'.strtotime($info['created_time']).'">
	                        <input value="'.$info['project_id'].'" name="pro_id" type="hidden">
                            <input value="1" name="type" type="hidden">
                            <input name="commemt_id" value="'.$pk.'" class="hidden">
	                        <textarea name="content"></textarea>
	                        <p><a href="javascript:reply('.strtotime($info['created_time']).');" class="Js-showLogin  mm-blue text-01">评论</a></p>
	                   </form>
	                   <div class="Js-commentList">
	                   </div>
	                 </div>
	            </div>
	        </div>';
	    // </li>

	    $html.=$script;
		$this->ajaxReturn($html,'',1);
		}else {
			$this->ajaxReturn('','评论失败',1);
		}
	}

	//编辑项目 审核状态不可编辑 草稿、不通过可以编辑
	public function editProject() {

		//判断是否登录
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		}
		//获取项目ＩＤ　
		$project_id=null;
		if(IS_GET)
			$project_id = I('get.id');
		if(IS_POST)
			$project_id = I('post.project_id');
		
		$user_id = session('user_id');
		
		//判断项目是否从属与当前用户
		$Project = M('Project');
		$projectInfos = $Project->join('attachment ON attachment.id = project.attachment_id')
		->field('attachment.id as attachment_id,attachment.path,project.*')->where(array('project.id'=>$project_id))->find();
			
		if(!$projectInfos && $projectInfos['user_id']!=$user_id) {
			 $this->display('Public/404');
			 return ;
		}

		// 获取类别
		$this->category = M('Category')
			->where('type = project')
			->where('status = 1')
			->field('id,name')
			->select();

		$projectInfos['path'] = __ROOT__.'/Uploads/pro_pics/'.$projectInfos['path'] ;

		if(IS_POST) {

			//处理表单数据
			$hasfile = I('post.hasfile',0,'intval');
			$info['id'] = I('post.project_id');
			$info['name'] = I('post.project_name');
			$info['days'] = I('post.deal_days');
			$info['category_id'] = I('post.cate_id');
			$info['address'] = I('post.province') . ',' . I('post.city');
			$info['introduce'] = I('post.introduce');
			$info['detail'] = I('post.prodetail');
			$info['label'] = I('post.tags');
			$info['user_id'] = $_SESSION['user_id'];
			$info['created_time'] = date('Y:m:d H:i:s');
			
			//验证数据
			if (trim($info['name']) == '')
				$error['name'] = "color:red;";
			if (trim($info['days']) == '')
				$error['days'] = "color:red;";
			if (trim($info['introduce']) == '')
				$error['introduce'] = "color:red;";
			
			if(!empty($error)) {
				$info['path'] = $projectInfos['path'];
				$this->assign('error', $error);
				$this->assign('data', $info);
				$this->display('Project/eidtProject');
				exit();
			}
		
			//校验成功	
			$Project->startTrans();
			$info['attachment_id'] = $projectInfos['attachment_id'];
			$Project->where(array('id'=>$project_id))->save($info);
			// 判断是否有上传 
			if($hasfile) {//存在上传文件

				import('ORG.Net.UploadFile');
				$upload = new UploadFile();// 实例化上传类
				$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
				$upload->allowExts  = array('jpg', 'png', 'jpeg');// 设置附件上传类型
				$upload->savePath =  './Uploads/pro_pics/';// 设置附件上传目录
				$upload->autoSub = true;
				$upload->subType = 'date';
				$upload->dateFormat = 'Ym/dH';

				if(!$upload->upload()) {// 上传错误提示错误信息
					$Project->rollback();
					self::setError("file", "上传失败，请稍后再试");
					$this->assign('data', $info);

					$this->display('Project/editProject');
					exit();
					
				}else{// 上传成功 获取上传文件信息
					$uploadInfo =  $upload->getUploadFileInfo();
					$Attachment = M('Attachment');
					
					if(!$Attachment->where(array('id'=>$projectInfos['attachment_id']))->save(array('path'=>$uploadInfo[0]['savename'],'created_time'=>date('Y:m:d H:i:s')))) {

						$Project->rollback();
						self::setError("file", "<script type='text/javascript'>alert('项目发起失败，请稍后再试');</script>");
						$this->assign('data', $info);
						$this->display('Project/editProject');
						exit();
					} 
					//删除旧封面
					$oldImgPath = './Uploads/pro_pics/'.$projectInfos['path'];
					if(file_exists($oldImgPath)) {
						@unlink($oldImgPath);
					}
				}
			}	

			$Project->commit();
			session('pro_id', null);
			session('pro_id', $project_id);
			$this->redirect('Project/editReward');
			

		} else {
			// 获取项目信息 回显表单
			$this->data = $projectInfos;
			$this->display();
		}

	}


	//回报修改页面 提供修改页面 具体修改交由
	public function editReward() {
		
		if(IS_GET) {
			
			//未登录
		 	if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
				$this->redirect('User/login');
				exit();
			}
			//判断是否存在项目id，若无，则可以看成非法操作
			$user_id = session('user_id');
			$project_id = session('pro_id');
			//是否带回报id 有则表示编辑特定回报
			$reward_id = I('get.rid',0,'intval');

			if($project_id == null) {
				$this->display('Public/404');
			 	return ;
			}

			//获取原先回报
		 	$ProjectReward = M('ProjectReward');
			$rewardInfos = $ProjectReward->where(array('project_id'=>$project_id))->select();
			if($rewardInfos && $reward_id) {

				foreach ($rewardInfos as $key => $value) {
					if($value['id']==$reward_id) {
						$this->theEditRewKey = $key;
					}
				}
			}
			$this->reward = $rewardInfos;
			$this->display();			
			
		}
	}

	//编辑项目时删除回报
	public function delRewOfPro() {
		//未登录
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
			exit();
		}
		//判断是否存在项目id，若无，则可以看成非法操作
		$user_id = session('user_id');
		$project_id = session('pro_id');
		
		if($project_id == null) {
			$this->display('Public/404');
			return ;
		}

		$reward_id = I('get.reward_id',0,'intval');
		if(M('ProjectReward')->where(array('id'=>$reward_id,'project_id'=>$project_id))->delete()) {
			$this->redirect('Project/editReward');
			return;
		}else{
			msgBox('删除失败',U('Project/editReward'));
			return;
		}

	}

	public function success() {
		$this->display();
	}
}

