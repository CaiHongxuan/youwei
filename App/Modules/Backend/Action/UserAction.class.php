<?php

class UserAction extends InitializeAction {

	/**
	 * 用户列表
	 * @return [type] [description]
	 */
	public function userList() {
		//自动判断请求类型获取参数 
		$status = I('param.status',1);//默认显示所有 激活用户列表
		$keyword = I('param.keyword','');

		if($keyword !== '') {
				$data['email'] = array('like','%'.$keyword.'%');
				$data['telephone'] = array('like','%'.$keyword.'%');
				$data['position'] = array('like','%'.$keyword.'%');
				$data['unit'] = array('like','%'.$keyword.'%');
				$data['address'] = array('like','%'.$keyword.'%');
				$data['_logic'] = 'OR';
		}
		if($data) {//是否模糊查询
			$condition = array('status'=>$status,$data);
		}else {
			$condition = array('status'=>$status);
		}

		$General = M('General');

		//分页跳转的时候保证查询条件
		$params = array('status' => $status ,'keyword'=>$keyword );
		$listAndPage = page($General,$condition,$params,8,'user_id DESC');//分页处理

		$users = $listAndPage['list'];	
		
		//获取用户名 用户是否锁定状态 user表里 
		foreach ($users as $i => $perUser) {
			$userIds[$i] = $perUser['user_id'];
		}

		$map['id']  = array('in',$userIds);
		$usrInfos = M('user')->where($map)->field('name,status')->order('id DESC')->select();
	 

		//模板赋值	
		$this->usrInfos = $usrInfos;
		$this->key = $keyword;	//页面回显关键词
		$this->status = $status;//返回下拉框的值
		$this->users = $users;
		$this->page = $listAndPage['page'];
		$this->display();
	}

	/**
	 * 一键删除未激活
	 * @return [type] [description]
	 */
	public function deleteNagetive() {

		//查询general表里status字段为0的记录
		$General  = M('General');
		//获取未激活用户
		$negatives = $General->where('status=0')->field('user_id,idcard_attachment_id')->select();

		if(!$negatives){//不需要清理 没有未激活用户 结束
			$this->success('无需清理');
			return;
		} 

		foreach ($negatives as $key => $value) {
			//获取所有待删除的usr_id
			$usrIdsDel[] = $value['user_id'];
			//获取所有待删除的身份证附件id
			if($value['idcard_attachment_id']) {
				$attaIdsDel[] = $value['idcard_attachment_id'];
			}
		}

		// 应删除的记录数
		$delnum = count($usrIdsDel);

		// 开启事务	
		$General->startTrans();

		// 删除token表里过期的记录 modified by oneMan 5.28 
		M('Token')->where('token_time < '.time())->delete();
		
	/*	//删除token表里相应的记录 
		// $tknum = M('token')->where(array('user_id'=>array('in',$usrIdsDel)))->delete();
		if($tknum != $delnum) {
			$General->rollback();
			$this->error('服务器繁忙，操作失败，请稍后尝试。');
			return;
		} else*/
		{

			//删除User表里的记录 
			if($delnum != M('user')->where(array('id'=>array('in',$usrIdsDel)))->delete()) {
				$General->rollback();
				$this->error('服务器繁忙，操作失败，请稍后尝试。');
				return;

			} else {

				//General表里的记录
				if(!$General->where('status=0')->delete()) {
					$General->rollback();
					$this->error('服务器繁忙，操作失败，请稍后尝试。');
					return;
				}
				
				if(count($attaIdsDel)!= 0) {

					$Attachment = M('attachement');
					$filepahts = $Attachment->where(array('id'=>array('in',$attaIdsDel)))->field('path')->select();

					if(!$Attachment->where(array('id'=>array('in',$attaIdsDel)))->delete()){
						$General->commit();		//提交事务

						//删除attachment记录 及 附件
						foreach ($filepahts as $key => $file) {
							$pathDeleted = './Uploads/id_pics/'.$file['path'];
							if(file_exists($pathDeleted)) {
								@unlink($pathDeleted);
							} 	
						}
						$this->success('清理成功');
					
					} else {
						$General->rollback();
						$this->error('服务器繁忙，操作失败，请稍后尝试。');
					}

				} else {
					$General->commit();		//提交事务
					$this->success('清理成功');
				}
			}
		}			
	}


	/**
	 * 普通用户详细页
	 * @return [type] [description]
	 */
	public function detailGeneral() {
		
		$user_id = I('get.user_id');
		
		//获取基本信息 User表
		$this->userInfos = M('user')->where('id='.$user_id)->find();
		
		//获取详细信息
		$General = M('General');
		$generalInfos = $General->where('user_id='.$user_id)->find();
		
		//获取附件路径 头像和身份证合照 
		$idcard_attachment_id = $generalInfos['idcard_attachment_id'];
		$headpic_attachment_id = $generalInfos['headpic_attachment_id'];

		$Attachment = M('Attachment');
		//附件路径处理
		if($idcard_attachment_id) {
			$generalInfos['idcard_path'] = './Uploads/id_pics/'.$Attachment->where('id='.$idcard_attachment_id)->getField('path');
		}
		if($headpic_attachment_id) {
			$generalInfos['headpic_path'] = './Uploads/head_pics/'.$Attachment->where('id='.$headpic_attachment_id)->getField('path');
		}

		$this->generalInfos = $generalInfos;
		$this->display();

	}

	/**
	 * 锁定或解锁用户 
	 * @return [type] [description]
	 */
	public function lockOrUnlock() {
		$userId = I('get.id','',intval);
		$islock = I('get.islock','',intval);
		$action = I('get.action');
		// p(I('get.'));die();
		if($islock===1) {//锁定用户
			if(!M('User')->where('id='.$userId)->setField('status',0)) {
				$this->error('操作出错！',$action);return;
			}

		}else if($islock===0) {//解除锁定
			
			if(!M('User')->where('id='.$userId)->setField('status',1)) {
				$this->error('操作出错！',$action);return;
			}
		}

		$this->success('操作成功',$action);
		 
	}



	/**
	 * 导师列表
	 * @return [type] [description]
	 */
	public function tutorList() {

		$keyword = I('param.keyword','');

		if($keyword!=='') {//是否模糊查询
			$data['mail'] = array('like','%'.$keyword.'%');
			$data['tele'] = array('like','%'.$keyword.'%');
			$data['position'] = array('like','%'.$keyword.'%');
			$data['unit'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
		} else {
			$data = true;
		}

		$Tutor =  M('Tutor');

		//分页跳转的时候保证查询条件
		$params = array('keyword'=>$keyword);
		$listAndPage = page($Tutor,$data,$params,8,'user_id DESC');//分页处理

		$tutors = $listAndPage['list'];	
		
			
		//获取用户名 用户是否锁定状态 user表里 
		foreach ($tutors as $i => $tur) {
			$userIds[$i] = $tur['user_id'];
		}
		$map['id']  = array('in',$userIds);
		$usrInfos = M('user')->where($map)->field('id,name,status')->order('id DESC')->select();
		
		//模板赋值	
		$this->usrInfos = $usrInfos;
		$this->key = $keyword;	//页面回显关键词
		$this->tutors = $tutors;
		$this->page = $listAndPage['page'];
		$this->display();
	}

	/**
	 * 新增导师
	 */
	public function addTutor() {

		if(IS_POST) {
			//处理表单提交
			$formdata =  M('Tutor')->create();
			$formdata['name'] = I('post.name'); 
			$Tutor = D('Tutor');
			if(!$Tutor->create()) {//自动验证格式
				$this->errors = $Tutor->getError();
				$this->tutor = $formdata;
				$this->display();
				return;
			}
			else{
				//验证邮箱地址是否唯一 可用
				if(M('User')->where(array('account'=>$Tutor->mail))->find()) {
					//邮箱地址不可用
					// $this->errors['mail'] = '邮箱已被注册';
					// $this->tutor = $formdata;
					$this->error('邮箱已被注册');
					return;
				}

				$User = M('user');
				$User->startTrans();//开启事务
				
				$data = array(
					//密码加密规则 明文+'youwei' 两次md5
					'name' => I('post.name'),
					'account' => $Tutor->mail,
					'password' => md5(md5(I('post.password').'youwei')),
					'type' => 'Tutor',
					'created_time' => date('Y-m-d H:i:s')
					);
				
				// 保存数据到User表
				$User->create($data);
				$userId = $User->add();
				if(!$userId) {
					$User->rollback();
					$this->error('新增失败');
					return;
				}

				//保存数据到Tutor表
				$Tutor->user_id = $userId;
				$Tutor->create_time = date('Y-m-d H:i:s');

				if($Tutor->add()) {
					$User->commit();
					$this->success('新增成功');
				}else{
					$User->rollback();
					$this->error('新增失败');
				}
			}
		}else{
			//提供新增页面
			$this->display();
		}

	}


	/**
	 * 企业列表
	 */
	public function enterpriseList() {

		$status = I('param.status',0);
		$keyword = I('param.keyword','');

		if($keyword !== '') {
			$data['mail'] = array('like','%'.$keyword.'%');
			$data['linkman'] = array('like','%'.$keyword.'%');
			$data['linkman_tele'] = array('like','%'.$keyword.'%');
			$data['address'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
		}
		if($data) {//是否模糊查询
			$condition = array('status'=>$status,$data);
		}else {
			$condition = array('status'=>$status);
		}
		$Enterprise =  M('Enterprise'); 
		//分页跳转的时候保证查询条件
		$params = array('status' => $status ,'keyword'=>$keyword );
		$listAndPage = page($Enterprise,$condition,$params,8);//分页处理

		$companys = $listAndPage['list'];	
			 
		//获取用户名 用户是否锁定状态 user表里 
		foreach ($companys as $i => $perComp) {
			$userIds[$i] = $perComp['user_id'];
		}

		$map['id']  = array('in',$userIds);
		$usrInfos = M('user')->where($map)->field('name,status')->select();
		
		$this->usrInfos = $usrInfos;$this->key = $keyword;
		$this->status = $status;

		$this->companys = $companys;
		$this->page = $listAndPage['page'];
		$this->display();
		
	}

	/**
	 * 审核企业 Enterprise表 status 0待审核 1通过审核 2未通过神恶化
	 * @return [type] [description]
	 */
	public function examineEnter() {

		$setStatusTo = I('get.setStatusTo','0',intval);
		$id = I('get.id');
		$action = I('get.action');//跳转的url
		
		$flag = M('Enterprise')->where('id='.$id)->setField('status',$setStatusTo);
		if($flag) {
			$this->success('操作成功',$action);
		}else{
			$this->error('操作失败',$action);
		}
	}

	/**
	 * 查看企业详细信息
	 * @return [type] [description]
	 */
	public function detailEnterprise() {
		//获取Enterprise_Id User_Id
		// $id = I('get.id');
		$user_id = I('get.user_id');

		$userInfos = M('User')->where('id='.$user_id)->find();
		$enterpriseInfos = M('Enterprise')->where('user_id='.$user_id)->find();

		//获取企业附件信息
		$Attachment = M('Attachment');
		// 企业营业执照图片
		if($enterpriseInfos['license_attachment_id']) {
			$enterpriseInfos['license_path'] = './Uploads/comp_pics/'.$Attachment->where('id='.$enterpriseInfos['license_attachment_id'])->getField('path');	
		}
		//组织机构代码证电子版（图）
		if($enterpriseInfos['code_attachment_id']) {

			$enterpriseInfos['code_path'] = './Uploads/comp_pics/'.$Attachment->where('id='.$enterpriseInfos['code_attachment_id'])->getField('path');	
		
		}
		$this->userInfos = $userInfos;
		$this->enterpriseInfos = $enterpriseInfos;
		$this->display();

	}



	/**
	 * 查看导师详细资料 也是编辑页面
	 * @return [type] [description]
	 */
	public function detailTutor() {

		if(IS_POST) {//处理修改请求
			//Code for Edit Tutor

		} else {//详细页面(编辑页面)
			// $id = I('get.id');
			$user_id = I('get.user_id');
			$this->userInfos = M('User')->where('id='.$user_id)->find();
			$this->tutorInfos = M('Tutor')->where('user_id='.$user_id)->find();
			$this->display();
		}
	}

 

	/**
	 * 删除用户 包括普通用户 企业 导师
	 * @return [type] [description]
	 */
	public function  deleteUser() {

		//获取参数
		$user_id = I('get.id','');
		$type = I('get.type','');
		// p(I('get.'));die();
		$User = M('User'); 
		$info = $User->where(array('id'=>$user_id,'type'=>$type))->find();
		// p($info);die();
		// p($User->getLastSql());die();
		if(!$info) { 
			// msgBox('用户不存在或已被删除');
			$this->error('用户不存在或已被删除');
			exit();
			
		} 

		try {

			$User->startTrans();

			$attaIds = null;//用于保存待删除的附件ID
			$attaPaths = null;//用于保存待删除的附件路径

			switch ($type) {

				case 'General':
				{//删除普通用户
					
					$info = $User->join('General ON General.user_id = User.id')->where(array('user.id' => $user_id,'type'=> 'General' ))
								->field('user.id as user_id,user.type,general.id as general_id,general.idcard_attachment_id,headpic_attachment_id,general.status')->find();
					if($info['status']==0) {//用户尚未激活 附件只考虑idcard_attachment_id
				
						//删除User表记录
						if($User->where(array('id'=>$user_id))->delete()) {
							//删除General表记录
							if(M('General')->where(array('id'=>$info['general_id']))->delete()) {
								if($info['idcard_attachment_id']) {//存在IDCard附件
									$Attachment = M('Attachment');
									$atta_path = './Uploads/id_pics/'.$Attachment->where(array('id'=>$info['idcard_attachment_id']))->getField('path');
									//删除附件记录
									if($Attachment->where(array('id'=>$info['idcard_attachment_id']))->delete()) {
										//删除文件
										if(file_exists($atta_path)) {
											@unlink($atta_path);
										}
										$User->commit();
										msgBox('用户已删除',U('User/userList'));
										exit();	
									} else {
										//5.26 oneMan
										throw_exception('删除未激活用户失败');
									}
								}
							}
						} else {
							//5.26 oneMan
							throw_exception('删除未激活用户失败');
						}

						


					}else {//已激活用户 可能包含其他数据

						$user_id = $info['user_id'];
						$general_id = $info['general_id'];

						$Attachment = M('Attachment');

						if($info['idcard_attachment_id'] ) {
							$attaIds[] = $info['idcard_attachment_id'];
							$attaPaths[] = './Uploads/id_pics/'.$Attachment->where(array('id'=>$info['idcard_attachment_id']))->getField('path');
						} 

						if($info['headpic_attachment_id']) {
							$attaIds[] = $info['headpic_attachment_id'];
							$attaPaths[] = './Uploads/head_pics/'.$Attachment->where(array('id'=>$info['headpic_attachment_id']))->getField('path');
						} 

						//删除用户信息 User、General
						if($User->where(array('id'=>$user_id))->delete()) {
							//删除General表记录
							if(!M('General')->where(array('id'=>$general_id))->delete()) {
								throw_exception('删除General异常');
							}

						}else{
							throw_exception('删除User异常');
						}
					}
					break;
								
				}

				case 'Enterprise':
				{
					 
					$info = $User->join('Enterprise ON Enterprise.user_id = User.id')
					->where(array('user.id' => $user_id,'type'=> 'Enterprise' ))
					->field('user.id as user_id,user.type,enterprise.id as enterprise_id,license_attachment_id,code_attachment_id')->find();

	
					$user_id = $info['user_id'];
					$enterprise_id = $info['enterprise_id'];

					 
					$Attachment = M('Attachment');

					if($info['license_attachment_id'] ) {
						$attaIds[] = $info['license_attachment_id'];
						$attaPaths[] = './Uploads/comp_pics/'.$Attachment->where(array('id'=>$info['license_attachment_id']))->getField('path');
					} 

					if($info['code_attachment_id']) {
						$attaIds[] = $info['code_attachment_id'];
						$attaPaths[] = './Uploads/comp_pics/'.$Attachment->where(array('id'=>$info['code_attachment_id']))->getField('path');
					} 

					//删除用户信息 User、 Enterprise
					if($User->where(array('id'=>$user_id))->delete()) {
						//删除 Enterprise 表记录
						if(!M('Enterprise')->where(array('id'=>$enterprise_id))->delete()) {
							throw_exception('删除Enterprise异常');
						}

					}else{
						throw_exception('删除User异常');
					}

					//删除企业用户发布的活动
					$Activity = M('Activity');
					$activities = $Activity->where(array('user_id'=>$user_id))->select();
					if($activities) {
						//保存附件信息到待删除数组中
						$atta_temp = array();
						foreach ($activities as $act) {
							$atta_temp[] = $act['imag_attachment_id'];
							if($act['file_attachment_id']) {
								$atta_temp[] = $act['file_attachment_id'];
							} 
						}
						if($atta_temp) {
							$atta_temp_res = M('Attachment')->where(array('id'=>array('in',$atta_temp)))->field('id,path')->select();
							foreach ($atta_temp_res as $att) {
								$attaIds[] = $att['id'];
								$attaPaths[] = './Uploads/comp_pics/'.$att['path'];
							}
						}
						//删除记录
						if(!$Activity->where(array('user_id'=>$user_id))->delete()) {
							throw_exception('删除活动异常');
						}


					}
					break;
				}
				case 'Tutor':
				{	
					$info = $User->join('Tutor ON Tutor.user_id = User.id')
					->where(array('user.id' => $user_id,'type'=> 'Tutor' ))
					->field('user.id as user_id,user.type,Tutor.id as tutor_id')->find();


					$tutor_id = $info['tutor_id'];

					//删除用户信息 User、 Tutor
					if($User->where(array('id'=>$user_id))->delete()) {
						//删除 Enterprise 表记录
						if(!M('Tutor')->where(array('id'=>$tutor_id))->delete()) {
							throw_exception('删除Tutor异常');
						}
					}else{
						throw_exception('删除User异常');
					}

					$ProjectGrade = M('ProjectGrade');
					if($ProjectGrade->where(array('user_id'=>$user_id))->select()) {
						if(!$ProjectGrade->where(array('user_id'=>$user_id))->delete()) {
							throw_exception('删除ProjectGrade异常');
						}
					}
					break;
				}
				default:
					throw_exception('删除失败');
					break;
			}//End Switch

			
			//以下为三种类型删除雷同操作
			
			//删除Token相关记录 若存在 
				$Token = M('Token');
				if($Token->where(array('user_id'=>$user_id))->select()) {
					if(!$Token->where(array('user_id'=>$user_id))->delete()) {
						throw_exception('删除Token异常');
					}
				}


				//删除ProjectSupport相关记录 若存在 
				$ProjectSupport = M('ProjectSupport');
				if($ProjectSupport->where(array('user_id'=>$user_id))->select()) {
					if(!$ProjectSupport->where(array('user_id'=>$user_id))->delete()) {
						throw_exception('删除项目支持信息异常');
					}
				}
  
				//删除该用户的评论
				$ProjectComment = M('ProjectComment');
				if($ProjectComment->where(array('user_id'=>$user_id))->select()) {
					if(!$ProjectComment->where(array('user_id'=>$user_id))->delete()) {
						throw_exception("删除用户评论异常");
					}
				} 

				//删除用户参加过的活动记录 ActivityInfo
				$ActivityInfo = M('ActivityInfo');
				if($ActivityInfo->where(array('user_id'=>$user_id))->select()){
					if(!$ActivityInfo->where(array('user_id'=>$user_id))->delete()) {
						throw_exception("删除活动记录错误");
					}
				}

				//删除用户发起的项目
				$Project = M('Project');
			
				//获取改用户发起的项目集合
				$projectsOfUser = $Project->join('Attachment ON Attachment.id = Project.attachment_id')->field('attachment.id as attachment_id,attachment.path,project.id')->where(array('project.user_id'=>$user_id))->select();
				
				//删除项目相关 
				if($projectsOfUser) {

					foreach ($projectsOfUser as $pro) {
						$pro['attachment_id'] && $attaIds[] = $pro['attachment_id'];
						$pro['path'] && $attaPaths[] = './Uploads/pro_pics/'.$pro['path'];
						$proIdsOfUser[] = $pro['id'];
					}

					//删除项目记录
					if(!$Project->where(array('project.user_id'=>$user_id))->delete()){
						throw_exception('删除项目信息失败!');
					}	
							
					//获得项目进度 
					$ProjectProgress = M('ProjectProgress');
					$progressInfos = $ProjectProgress->join('attachment ON attachment.id=project_progress.attachment_id')
							->where(array('project_progress.project_id'=>array('in',$proIdsOfUser)))
							->field('attachment.id as attachment_id,attachment.path,project_progress.id as progress_id')
							->select();
					

					if($progressInfos) {//有进程信息
				
						foreach ($progressInfos as $i => $prog) {
							//保存待删除的附件id和路径
							if($prog['attachment_id']) {
								$attaIds[] = $prog['attachment_id'];
								$attaPaths[] = './Uploads/pro_pics/'.$prog['path']; 
							}
						}

						//删除项目进度记录
						if(!$ProjectProgress->where(array('project_id'=>array('in',$proIdsOfUser)))->delete()) {
							throw_exception('删除进程信息失败!');
						}
					}			
					
					//删除项目回报信息 
					M('ProjectReward')->where(array('project_id'=>array('in',$proIdsOfUser)))->delete();

					//删除项目支持信息
					M('ProjectSupport')->where(array('project_id'=>array('in',$proIdsOfUser)))->delete();

					//删除项目评分
					M('ProjectGrade')->where(array('project_id'=>array('in',$proIdsOfUser)))->delete();

					// 删除项目评论
					M('ProjectComment')->where(array('project_id'=>array('in',$proIdsOfUser)))->delete();

				}

						
		 		//删除相关附件和文件
				if($attaIds) {
					$flag = $Attachment->where(array('id'=>array('in',$attaIds)))->delete();
					if($flag) {
						//删除文件
						foreach ($attaPaths as $apath) {

							if(file_exists($apath)) {
								unlink($apath);
							} 
						}
					} else {
						throw_exception('删除附件信息失败!');
					}
				}
				
				 

				$User->commit();//提交事务

				$type=='General' ? $url=U('User/userList') : $url=U('User/'.strtolower($type).'List');
				// msgBox('删除成功',$url);
				$this->success('删除成功',$url);
				exit();
			
		} catch (Exception $e) {
			$User->rollback();
			// msgBox($e.msg);
			// p($e.msg);
			$this->error('删除失败');
			exit();

		}//End Try


	}


}
