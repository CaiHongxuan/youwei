<?php
class IndexAction extends CommonAction  {


	 // 首页
	function index() {


		//图片处理 
		$Pic = M('focusPic');
		//获取焦点图
		$this->focusPics = $Pic->where(array('type'=>1,'status'=>1))->field('url,title,description,link')->order('upload_time DESC')->select();
		
		//获取广告图
		$this->advertPics = $Pic->where(array('type'=>0,'status'=>1))->field('url,link')->order('upload_time DESC')->select();
		//取得类别 首页限制显示8个类别
		$categories = M('Category')->where("type='project'")->limit(8)->select();
		$acategories = M('Category')->where("type='activity'")->limit(8)->select();
		$this->assign('categories',$categories);
		$this->assign('acategories',$acategories);



		$Project =  M('project');
		$Activity = M('Activity');
		if(IS_GET) {
			//取得项目信息
			$condition = array(	//展示条件
				// 'iscommited' => 1,//已经发布而非草稿 
				'ispassed' => 1,//已经通过审核
				'packed'=>1,
				);
			$category_id = I('get.cateId','',intval);
			//GET请求带类别参数时
			if($category_id) {
				$condition['category_id'] = $category_id;
			} 

			$projects = $Project->where($condition)->order('pass_time desc')->field('id,name,days,pass_time,favor,attachment_id,category_id')->limit(18)->select();
			$activitys = $Activity->where($condition)->order('passed_time desc')->field('id,name,passed_time,image_attachment_id,category_id')->limit(18)->select();
		}else if(IS_POST) {//首页搜索

			$keywords = I('post.keywords');
		
			$condition['name'] = array('like','%'.$keywords.'%');
			$condition['label'] = array('like','%'.$keywords.'%');
			$condition['introduce'] = array('like','%'.$keywords.'%');
			$condition['_logic'] = 'OR';
			
			
			$projects = $Project->where(array('ispassed'=>1,'packed'=>1,'_logic'=>'AND',$condition))->order('pass_time desc')->field('id,name,days,pass_time,favor,attachment_id,category_id')->limit(18)->select();
			
		}
		// p($Project->getLastSql());die;

		$catesToShow = array();
		$current_user_id = session('uid');//当前登录用户user_id
		//剩余时间 及 封面图片路径处理 统计分类
		foreach ($projects as $key => $perPro) {
			if(!in_array($perPro['category_id'], $catesToShow)) {
				array_push($catesToShow, $perPro['category_id']);
			}

			//获取项目封面路径
			$attaId = $perPro['attachment_id'];
			$attaPath = M('Attachment')->where('id='.$attaId)->getField('path');

			$rootPath = './Uploads/pro_pics/';// 项目封面附件存放目录
			$projects[$key]['pro_pic_url'] =   __ROOT__.'/'.$rootPath.$attaPath  ; 

			// 计算剩余时间 当前时间-审核时间 
			
			$remain_day = $perPro['days'] - intval((time() - strtotime($perPro['pass_time']))/(60*60*24));
			$remain_day>0?$projects[$key]['remain_day'] =$remain_day:$projects[$key]['remain_day']=0;
			//计算点赞数
			$projects[$key]['favor'] = count(explode(",",$perPro['favor']));
			/*if($current_user_id) {//处于登录状态
				if(strpos($favor,','.$user_id.',') !== false) {
					$projects[$key]['has_favor']=1;//点过赞
				}
			}*/
		}
		//封面图片路径处理
		foreach ($activitys as $key => $activity) {

			//获取项目封面路径
			$attaId = $activity['image_attachment_id'];
			$attaPath = M('Attachment')->where('id='.$attaId)->getField('path');

			$rootPath = './Uploads/activity/';// 封面附件存放目录
			$activitys[$key]['pro_pic_url'] =   __ROOT__.'/'.$rootPath.$attaPath  ;
		}
		$this->assign('catesToShow',$catesToShow);
		$this->assign('projects',$projects);
		$this->assign('activitys', $activitys);


		//获取活动精选
		
		$this->display ();
	}

	/**
	 * [login description]
	 * @return [type] [description]
	 */
	function login() {
		if(!IS_AJAX)
		 	halt('页面不存在');

		//已登录不显示登录页面 直接跳转至首页
		if($_SESSION && isset($_SESSION['user_id']) && isset($_SESSION['account']) && $_SESSION['type']!='Admin') {
			// $this->redirect('Index/index');
			$this->ajaxReturn(null, "当前已登录", 0);
			exit;
		}

		if(I('verify_code','','md5') != session('verify')) {
			$this->ajaxReturn(null, "验证码错误", 0);
		}

		$account = I('username');
		$password = md5(md5(I('password').'youwei'));

		$user = M('user')->where(array('account' => $account))->find();
		
		if(!$user || $user['password'] != $password  || $user['type']=='Admin') {
			// $this->error('账号或密码错误');
			$this->ajaxReturn(null, "账号或密码错误", 0);
		}

		$isActived = M($user['type'])->where(array('user_id' => $user['id']))->getField('status');

		if(!$user['status'])
			$this->ajaxReturn(null, "用户被锁定", 0);

		if(!$isActived)
			$this->ajaxReturn(null, "用户未激活", 0);

		session('account',$user['account']);
		session('name',$user['name']);
		session('user_id',$user['id']);
		session('type',$user['type']);

		$this->ajaxReturn(U('Index/index'),'',1);
		// $this->redirect('Index/index');
	}


	/**
	 * [异步处理点赞]
	 * @return [type] [description]
	 */
	public function favor() {
		if(!IS_AJAX)
			halt('页面不存在');

		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			//用户为登录  
			$this->ajaxReturn(null,'', 0);
		}

		$user_id = session('user_id');
		$project_id = I('post.proid');

		// 读取项目favor字段 ：1,2,3, 比对用户ID是否在该字符串中
		$Project = M('Project');
		$favor = $Project->where('id='.$project_id)->getField('favor');
		// dump($favor);die();

		if(strpos($favor,','.$user_id.',') === false) {//尚未点过赞
			
			$Project->where('id='.$project_id)->setField('favor',$favor.$user_id.',');
			$favor_num = count(explode(",",$favor))+1;
			$this->ajaxReturn($favor_num,'', 1);

		}else{
			$this->ajaxReturn(null,'', 0);
		}
	}

	
}