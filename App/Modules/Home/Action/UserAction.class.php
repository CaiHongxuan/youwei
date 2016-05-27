<?php

class UserAction extends CommonAction {

	// 注册
	// 企业用户注册
	public function enregister() {
		$this->display();
	}
	// 普通用户注册
	public function register() {
			
		if(IS_POST) {//处理注册请求

			//封装UserModel数据

			$data['name'] = I('post.username');
			$data['account'] = I('post.email');
			$data['password'] = I('post.pass');
			$data['repassword'] = I('post.repass');//临时字段
			$data['type'] = 'General';
			$data['created_time'] = date('Y-m-d H:i:s');


			//封装GeneralModel数据
			
			$info['email'] = $data['account'];
			$info['telephone'] = I('post.telephone');
			$info['idcard'] = I('post.idcard');
			$info['address'] = I('post.addr');
			$info['unit'] = I('post.unit');
			$info['position'] = I('post.job');
			$info['created_time'] = date('Y-m-d H:i:s');

			//实例化自定义模型 自动验证
			$User = D("User");
			$General = D('General');


			if(!$User->create($data)) {//验证User字段
				
				$this->errors = $User->getError();
				$this->form_data = I('post.');
				$this->display('User/register');
				
			} else {//验证通过

				//验证邮箱是否被注册
				if($User->where(array('account'=>$data['account']))->select()) {
					$this->errors = array('account'=>'邮箱已被注册');
					$this->form_data = I('post.');
					$this->display('User/register');
					return;
				}

				
				//密码加密规则 明文+'youwei' 两次md5
				$User->password = md5(md5($data['password'].'youwei'));

				try {

					$User->startTrans();//开启事务
					if($userId = $User->add()) {

						$info['user_id'] = $userId;

						if(!$General->create($info)) {//验证General字段

							$User->rollback(); 
							$this->errors = $General->getError();
							$this->form_data = I('post.');
							$this->display('User/register');
							return;
				
						} else {
						

							if($_POST['isUpload'])  {//处理上传图片
							
								import('ORG.Net.UploadFile');
								$upload = new UploadFile();// 实例化上传类
								$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
								$upload->allowExts  = array('jpg', 'png', 'jpeg');// 设置附件上传类型
								$upload->savePath =  './Uploads/id_pics/';// 设置附件上传目录

								//上传目录不存在 则创建 3.27
								if(!file_exists($upload->savePath)) {
									mkdir($upload->savePath,0777);
								}

								$upload->autoSub = true;
								$upload->subType = 'date';
								$upload->dateFormat = 'Ym/dH';

								if(!$upload->upload()) {// 上传错误提示错误信息
									$User->rollback();
									$errors['upfile']=$upload->getErrorMsg();
									$this->assign('errors',$errors);
									$this->assign('form_data',I('post.'));
									$this->display('User/register');
									return;
								} else {
									
									$uploadFileInfos =  $upload->getUploadFileInfo();
			
									//保存附件数据
									$Attachment = M('Attachment');
									$attachInfos['path'] =  $uploadFileInfos[0]['savename']; 
									$attachInfos['created_time'] = date('Y-m-d H:i:s');
									$Attachment->create($attachInfos);
									$attaId = $Attachment->add();
									if($attaId) {
										$General->idcard_attachment_id = $attaId; 
									}else {
										throw new \Exception("更新上传信息出错！");
									}	
								}
							}
							
							if($General->add()) {
								
								$User->commit();
								//跳转到注册成功页面 JQ Ajax 发邮件 
								//http://localhost/youweiCvn/index.php/User/msg4reg/email/thweapon%40sina.cn.html 
								$this->redirect('msg4reg',array('email'=>$data['account'],'uid'=>$userId));
								exit();
							} else {
								$User->rollback();// 事务回滚
								$this->error('服务器繁忙，注册失败，请稍后尝试。');
								exit();
							}
						}
					}

				} catch(\Exception $e) {		 
   					$User->rollback();// 事务回滚
   					$this->error('服务器繁忙，请稍后尝试。');
				}
			}
			
		} else {
			$this->display();//回显注册页面
		}
	}


	//注册成功提示页面 前端实现Ajax发送邮件
	public function msg4reg() {
		$this->email = I('get.email');
		$this->uid = I('get.uid');

		$this->display();
	}

	//发送激活邮件
	public function sentMail() {

		if(IS_AJAX) {

			//判断请求是否合法 账户是否存在且未激活
			$email = I('post.email','','trim');
			$uid = I('post.uid',0,'intval');



			$userInfo = M('User')->where(array('id'=>$uid,'account'=>$email))->find();
			if(!$userInfo) {//账户不存在
				return;
			}
			$generalInfo = M('General')->where(array('user_id'=>$uid,'email'=>$email))->find();
			if(!$generalInfo||$generalInfo['status']) {//不存在或已激活	
				return;
			}

			//未激活合法用户 生成激活链接 
			// 判断是否已经生成token 请求可能来自页面刷新
			$Token = M('token');
			$hasGeneralTk = $Token->where('user_id='.$uid)->order('id DESC')->find();
			if($hasGeneralTk) {//已有该用户token 有效性
				$isValid = time() - $hasGeneralTk['token_time'];
				if($isValid>0) {//tk失效
					$Token->where('id='.$hasGeneralTk['id'])->delete();
				}else {//tk有效 邮件已发送 
					$this->error('激活邮件已发送，请耐心等候');return;
				}
			}
			$tkInfos['token'] =  md5(date('YmdH').$userInfo['account'].'yw');
			$tkInfos['token_time'] = time()+20*60;
			$tkInfos['user_id'] = $userInfo['id'];

			$Token ->create($tkInfos);
			if(!$Token->add()) {
				return;
			}
								
			//发送邮件 等待用户激活 IP待修改   
			$link = 'http://'.$_SERVER['SERVER_NAME'].U('User/activate','','').'?ut='.$tkInfos['token'].'_'.$userInfo['id']; 
			$mailContent = '<p style="font-weight:bold">请点击下面链接进行账号激活：<br><br>
                <a href="'.$link.'" target="_blank">'.$link.'</a></p> ';

			//失败 再发送一次
			if(!SendMail($email,'有为用户激活邮件',$mailContent)) {
				SendMail($email,'有为用户激活邮件',$mailContent);
			}
			
			$this->ajaxReturn($email.' '.$uid,'邮件已发送',1);

		}
	}


	//激活账户
	public function  activate() {

		//处理已注册用户
		// 激活请求 http://127.0.0.1/youwei/index.php/User/activate?ut=2c60b89c1650e36f6b6b83c3c7e97b_2 
		// 2c60b89c1650e36f6b6b83c3c7e97b_2  token_userId	

		if(IS_GET) {

			//获取请求参数 a87df1565adf_007
			$token_userId = I('get.ut');

			//解析取得token 和 user_id
			$arr = explode('_', $token_userId);
			$token = $arr[0];
			$userId = $arr[1];

			//根据user_id token 查询获取 token token_time 
			$TokenDB = M('token');
			$result = $TokenDB->where(array('user_id' => $userId,'token'=>$token))->order('id DESC')->find();

			if($result) {//存在 判定链接是否有效 time>token_time
	
				$isValidLinked = time() - $result['token_time'];

				if($isValidLinked > 0) {//链接无效 token已过期

					//删除原有token  3.27
					$TokenDB->where(array('user_id' => $userId,'token'=>$token))->delete();

					$this->error('链接已过期',U('Home/Index/index'));
					return;

				} else {
					//设置激活状态
					M('General')->where(array('user_id'=>$userId))->setField('status',1);

					//删除原有token  3.27
					$TokenDB->where(array('user_id' => $userId,'token'=>$token))->delete();

					$this->success('激活成功',U('Home/Index/index'));
				}

			} else {	//不存在 exit
				$this->error('链接无效');
			}
		}
	}
	
	// 登陆
	public function login() {
		//登录成功后需要跳转的url 
		$jumpUrl = strrpos($_SERVER['HTTP_REFERER'],$_SERVER['PHP_SELF'])?U('Index/index'):$_SERVER['HTTP_REFERER'];
		 
		//已登录不显示登录页面 直接跳转至首页
		if($_SESSION && isset($_SESSION['user_id']) && isset($_SESSION['account']) && $_SESSION['type']!='Admin') {
			$this->redirect('Index/index');
			exit;
		}

		if(IS_AJAX) {//处理登陆请求
			
			if(I('code','','md5') != session('verify')) {
				$this->ajaxReturn('verify_error', "验证码错误", 0);
			}

			$account = I('email');
			$password = md5(md5(I('password').'youwei'));
			$jurl = I('jurl',U('Index/index'));
	
			$user = M('user')->where(array('account' => $account))->find();
			
			if(!$user || $user['password'] != $password  || $user['type']=='Admin') {	
				$this->ajaxReturn('account_error', "账号或密码错误", 0);
			}
			
			$isActived = M( $user['type'])->where(array('user_id' => $user['id']))->getField('status');
			if(!$user['status']) {
				$this->ajaxReturn('account_error', "用户被锁定", 0);
				return;
			}

			if(!$isActived) {
				$msg = '用户未激活,<a style="font-size:12px;" href='.U('User/msg4reg',array('email'=>$user['account'],'uid'=>$user['id'])).'>点此进行激活？</a>';
				$this->ajaxReturn('account_error', $msg, 0);
				return;
			}

			session('account',$user['account']);
			session('name',$user['name']);
			session('user_id',$user['id']);
			session('type',$user['type']);

			// $this->ajaxReturn(U('Index/index'),'',1);
			$this->ajaxReturn($jurl,'',1);


		} else {//显示登陆页面
			$this->jurl = $jumpUrl;
			$this->display();
		}
	}


	//退出
	public function logout () {
		session_unset();
		session_destroy();
		$this->redirect('Index/index');
	}

	//验证码
	public function verify() {
		import('ORG.Util.Image');
		Image::buildImageVerify();
	}

	//重置密码1 发送邮件
	public function reset_pwd() {

		if(IS_GET) {//显示忘记密码页面

			$this->display();
		
		}elseif(IS_AJAX) {

			// $this->ajaxReturn(null,"重置邮件已发送，注意查收。",1);
			// 检验验证码
			if(I('post.verifyCode','','md5') != session('verify')) {
				session('verify',null);
				$this->ajaxReturn(null, "验证码错误", 0);
			}

			//检验账户有效性
			$email = I('post.email');
			$user = M('User')->where(array('account'=>$email))->find();
			if(!$user || $user['type']=='Admin') {
				$this->ajaxReturn(null, "账号无效", 0);
			}

			//账号存在 生成链接 
			$Token = M('token');
			$hasGeneralTk = $Token->where('user_id='.$user['id'])->order('id DESC')->find();
			if($hasGeneralTk) {//已有该用户token 判断有效性
				$isValid = time() - $hasGeneralTk['token_time'];
				if($isValid>0) {//tk失效
					$Token->where('id='.$hasGeneralTk['id'])->delete();
				}else {//tk有效 邮件已发送 
					$this->ajaxReturn(null,"重置邮件已发送，注意查收。",1);
				}
			}

			$tkInfos['token'] =  md5(date('YmdH').$user['account'].'yw');
			$tkInfos['token_time'] = time()+20*60;
			$tkInfos['user_id'] = $user['id'];

			$Token ->create($tkInfos);
			if(!$Token->add()) {
				$this->ajaxReturn(null,"服务器繁忙，请稍后尝试。",0);
			}
								
			//发送邮件 等待用户激活 IP待修改   
			$link = 'http://'.$_SERVER['SERVER_NAME'].U('User/reset_pwd2','','').'?ut='.$tkInfos['token'].'_'.$tkInfos['user_id'] ; 
			$mailContent = '<p style="font-weight:bold">请点击下面链接重置账户密码：<br><br>
                <a href="'.$link.'" target="_blank">'.$link.'</a></p> ';

			//失败 再发送一次
			if(!SendMail($email,'有为用户重置邮件',$mailContent)) {
				if(!SendMail($email,'有为用户重置邮件',$mailContent)){
					$this->ajaxReturn(null,"服务器繁忙，请稍后尝试。",0);
				}
			}
			
			$this->ajaxReturn(null,"重置邮件已发送，注意查收。",1);

		}else{}
	}


	public function reset_pwd2() {
		//点击重置链接
		if(IS_GET) {
			$this->key = I('get.ut');
			$this->display();
		}elseif(IS_AJAX) {
			// 检验表单字段
			if(I('post.verifyCode','','md5') != session('verify')) {
				session('verify',null);
				$this->ajaxReturn(null, "验证码错误", 0);
			}

			$pwd = I('post.pwd','','trim');
			$repwd = I('post.repwd','','trim');

			if(!preg_match('/^[a-zA-Z0-9]{6,16}$/', $pwd)) {
				$this->ajaxReturn(null,'密码应由6~16字母或数字组成',0);
			}
			if($pwd!==$repwd) {
				$this->ajaxReturn(null,'两次密码不一致',0);
			}

			//获取token a87df1565adf_007 检验请求合法性
			$token_userId = I('post.key','');

			//解析取得token 和 user_id
			$arr = explode('_', $token_userId);
			$token = $arr[0];
			$userId = $arr[1];

			//根据user_id token 查询获取 token token_time 
			$TokenDB = M('token');
			$result = $TokenDB->where(array('user_id' => $userId,'token'=>$token))->order('id DESC')->find();

			if($result) {//存在 判定链接是否有效 time>token_time
	
				$isValidLinked = time() - $result['token_time'];

				//删除原有token  3.27
				$TokenDB->where(array('user_id' => $userId,'token'=>$token))->delete();

				if($isValidLinked > 0) {//token无效 t

					$this->ajaxReturn(null,'该邮箱尚未注册，<a href="'.U('User/register').'">立即注册？</a>',0);
					return;

				} else {//token有效
					//重置密码
					if(M('User')->where(array('id'=>$result['user_id']))->setField('password',md5(md5($pwd.'youwei')))) {
						$this->ajaxReturn(U('User/login'),'重置密码成功',1);
				  	}else{
				  		$this->ajaxReturn(null,'服务器繁忙，请稍后尝试。',0);
				  	}
				}

			}else{
				$this->ajaxReturn(null,'该邮箱尚未注册，<a href="'.U('User/register').'">立即注册？</a>',0);
			}

		}else {}
	}


	//密码设置
	public function pwdUpdate() {

		//尚未登录 重定向到登录页面
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
		} 
		if(IS_AJAX) {
			//前台验证加强
			
			$old_pwd = I('old_pwd');
			$new_pwd = I('new_pwd');
			$re_new_pwd = I('re_new_pwd');

			$id = session('user_id');

			$User = M('User');
			$passInDB = $User->where('id='.$id)->getField('password');
			
			if(md5(md5($old_pwd.'youwei')) != $passInDB){
				
				// $this->error('旧密码错误');
				$this->ajaxReturn('old_pwd', "旧密码错误", 0);

			} else {

				//验证密码格式
				if(!preg_match('/^[a-zA-Z0-9]{6,16}$/', $new_pwd))
					$this->ajaxReturn('new_pwd', "密码由6~16字母或数字组成", 0);

				if($new_pwd!==$re_new_pwd) {
					// $this->error('两次密码不一致');
					
					$this->ajaxReturn('new_pwd', "两次密码不一致", 0);
				} else {
					$password = md5(md5($new_pwd.'youwei'));
 					
 					if($User->where('id='.$id)->save(array('password'=> $password))){

						// $this->success('修改密码成功');
						$this->ajaxReturn(U('Index/index'),'修改密码成功',1);
				
					} else{
				
						// $this->error('修改密码失败');
						$this->ajaxReturn(null,'修改密码失败',1);
					}
				}
			} 

		} else{

			$this->display();
		}

	}



	//个人设置
	public function infoUpdate() {

		//尚未登录 重定向到登录页面
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
		} 

		//用户已登录

		if(IS_POST) {
			//处理用户信息更新请求  
			
			$user_id = session('user_id'); 

			$data['telephone'] = I('post.tele');
			$data['name'] = I('post.username');
			$data['gender'] = I('sex');
			$data['address'] = I('addr');
			$data['video_url'] = I('weibo_url');
			$data['resume']	=	I('intro');

			$General = M('General');
			$flag = $General->where('user_id='.$user_id)->save($data);
			if($flag) {
				msgBox('修改成功', U('User/infoUpdate'));
			} else {
				msgBox('修改失败,可能原因是您提交的数据无变动', U('User/infoUpdate'));
			}
		} else {
			//显示页面
			$user_id = session('user_id');
			$email = session('account');
			$name = session('name');
			
			$userInfos = M('General')->where(array('user_id'=>$user_id,'email'=>$email))->field('gender,address,video_url,resume,telephone,idcard,email,unit,position')->find();
			$userInfos['name'] = $name;
			
			$this->userInfos = $userInfos;
			$this->display();

		}
	}

	

	// 头像设置
	public function imageUpdate() {
		//尚未登录 重定向到登录页面
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
		} 

		if(IS_POST) {

			import('ORG.Net.UploadFile');
			$upload = new UploadFile();// 实例化上传类
			$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
			$upload->allowExts  = array('jpg', 'png', 'jpeg');// 设置附件上传类型
			$savePath = './Uploads/head_pics/';// 设置附件上传目录

			//上传目录不存在 则创建	3.27
			if(!file_exists($savePath)) {
				mkdir($savePath,0777);
			}

			$upload->savePath =  $savePath;
			$upload->autoSub = true;
			$upload->subType = 'date';
			$upload->dateFormat = 'Ym/dH';

			if(!$upload->upload()) {
				// 上传错误提示错误信息
				$this->error($upload->getErrorMsg());

			}else{
				// 上传成功 获取上传文件信息
				$info =  $upload->getUploadFileInfo();
				$savename = $info[0]['savename'];  //201503/2212/550e3f3648b5d.jpeg
		
				//保存附件数据	 
				$Attachment = M('Attachment');
				$Attachment->startTrans();

				$Attachment->create(
					array(
						'path'=>$savename,
						'created_time'=>date('Y-m-d H:i:s')
					));
				$attachment_id = $Attachment->add();
				if($attachment_id) {
					$user_id = session('user_id');

					$General = M('General');

					// 获取旧头像附件id
					$oldHeadpicAttaId = $General->where('user_id='.$user_id)->getField('headpic_attachment_id');
					$affectedNum = $General->where('user_id='.$user_id)->setField('headpic_attachment_id',$attachment_id);

					if($affectedNum) {//修改成功
						
						$result = $Attachment->where('id='.$oldHeadpicAttaId)->find();

						//删除原有头像附件记录及图片	3.27
						if($result) {
							$pathToDelete = $result['path'];
							$Attachment->where('id='.$oldHeadpicAttaId)->delete();
							@unlink('./Uploads/head_pics/'.$pathToDelete);
						}

						$Attachment->commit();
						$this->success('设置成功',U('User/imageUpdate'));

					} else {

						//修改失败
						$Attachment->rollback();
						//删除上传图片
						$this->error('设置失败');
						
					}
				}

			}

		} else if(IS_GET) {//显示上传页面

				$headpic_attachment_id = M('General')->where('user_id='.session('user_id'))->getField('headpic_attachment_id');
				
		 		$savePath = './Uploads/head_pics/';// 头像附件上传目录
				$savename = M('Attachment')->where('id='.$headpic_attachment_id)->getField('path');
				$this->headpic_path = __ROOT__.'/'.$savePath.$savename;
				$this->display();

		} else {}
		
	}



	// 个人中心
	public function percenter() {
		//尚未登录 重定向到登录页面
		if(!$_SESSION || !isset($_SESSION['user_id'])||!isset($_SESSION['account'])) {
			$this->redirect('User/login');
		} else {
			if(IS_GET) {
				//用户已登录 处理头像等数据
				$user_id = $_SESSION['user_id'];
				$type = M('User')->where('id='.$user_id)->getField('type');
				$Model = M($type);
				$object = $Model->where('user_id='.$user_id)->find();


				$headpic_attachment_id = $object['headpic_attachment_id'];
				$this->headpic_path = M('Attachment')->where('id='.$headpic_attachment_id)->getField('path');
				$this->phone = $object['telephone'];
				$this->resume = $object['resume'];

					//参与的活动
					$count_activity = M('ActivityInfo')->where(array('user_id' => $user_id))->count();
					$activitys_attend = M('Activity')
						->join('activity_info on activity_info.activity_id = activity.id')
						->join('attachment on attachment.id = activity.image_attachment_id')
						->field('count activity.id,activity.name,activity.published_time,activity.deadline,activity.isdead,attachment.path')
						->where('activity_info.user_id='.$user_id)
						->select();
					$this->assign('count_activity', $count_activity);
					$this->assign('activitys_attend', $activitys_attend);
				} else {

				//发起的项目
				$Project = M('Project');
				$projects_launch = $Project->where(array('user_id' => $user_id))
					->join('attachment on project.attachment_id = attachment.id')
					->field('project.id,project.name,commit_time,iscommited,attachment.path as p_path')
					->select();
				$count_launch = $Project->where(array('user_id' => $user_id))->count();
				$this->assign('project', $projects_launch);
				$this->assign('count', $count_launch);
				//支持的项目
				$projects_support = $Project
					->join('project_support on project.id = project_support.project_id')
					->join('attachment on project.attachment_id = attachment.id')
					->where(array('project_support.user_id' => $user_id))
					->field('project.id,project.name,commit_time,iscommited,attachment.path as p_path')
					->select();
				$count_support = M('ProjectSupport')->where(array('user_id' => $user_id))->count();
				$this->assign('project_support', $projects_support);
				$this->assign('count_support', $count_support);

				//参与的活动
				$count_activity = M('ActivityInfo')->where(array('user_id' => $user_id))->count();
				$activitys_attend = M('Activity')
					->join('activity_info on activity_info.activity_id = activity.id')
					->join('attachment on attachment.id = activity.image_attachment_id')
					->field('activity.id,activity.name,activity.published_time,activity.deadline,activity.isdead,attachment.path')
					->where('activity_info.user_id='.$user_id)
					->select();
				$this->assign('count_activity', $count_activity);
				$this->assign('activitys_attend', $activitys_attend);


			}
			$this->display();
		}
	}

	

	//发布活动
	public function launchActivity(){
		$this->display();
	}

	//发起的活动
	public function perLaunchActivity(){
		$this->display();
	}

	//参与的活动
	public function attendActivity(){
		$this->display();
	}

	//支持的项目
	public function perSupportProject(){
		$this->display();
	}

}
