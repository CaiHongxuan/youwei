<?php 
 
class ProjectAction extends InitializeAction {

	/**
	 * 项目列表
	 * @return [type] [description]
	 */
	public function projectList() {
		
		$ispassed = I('param.ispassed',0);
		$keyword = I('param.keyword','');
			
		if($keyword!=='') {// 模糊查询 名称 序号 地点
			$data['name'] = array('like','%'.$keyword.'%');
			$data['number'] = array('like','%'.$keyword.'%');
			$data['address'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
		}

		if($data) {//模糊查询
			$condition = array('ispassed'=>$ispassed,'iscommited'=>1,$data);
		}else {//无查询
			$condition = array('ispassed'=>$ispassed,'iscommited'=>1);
		}

		$Project = M('Project');
		//分页跳转的时候保证查询条件
		$map = array('ispassed' => $ispassed ,'keyword'=>$keyword );
		$listAndPage = page($Project,$condition,$map);//分页处理

		$projects = $listAndPage['list'];	
		//处理发起人 
		foreach ($projects as $i => $perPro) {
			$projects[$i]['organiser']=M('User')->where('id='.$perPro['user_id'])->getField('name');
		}

		//模板赋值
		$this->key = $keyword;	//页面回显关键词
		$this->ispassed = $ispassed;//返回下拉框的值
		$this->projects = $projects;
		$this->page = $listAndPage['page'];
		$this->display();
	}




	/**
	 * 发起人信息 发起者可以是 企业 导师 普通用户
	 * @return [type] [description]
	 */
	public function organiserInfos() {

		$user_id = I('get.id');
		
		$typeOfUser = M('User')->where('id='.$user_id)->getField('type');

		if($typeOfUser && $typeOfUser!='Admin') {
			$this->redirect('User/detail'.$typeOfUser, array('user_id' => $user_id));
		} else {
			msgBox("查看失败".$typeOfUser);
		}

	}

	/**
	 * 审核项目
	 * @return boolean [description]
	 */
	public function isPassed() {

		$id = I('get.id');
		$action = I('get.action');
		$Project = M('Project');

		$data = array(
			'ispassed' => I('setStatusTo','0',intval),
			'pass_time' => date('Y-m-d H:i:s'),
		);
		
		//将状态置为通过时 生成项目编号 
		// KJ150423001 标志+时间+三位数序号.
		if($data['ispassed'] == 1){

			//获取项目分类标志
			$category_id = $Project->where('id='.$id)->getField('category_id');
			$cate_sign = M('Category')->where('id='.$category_id)->getField('sign');
				
			//查询当天已审核的项目个数
			$passnum = M('Project')->where(array('pass_time'=>array('like',date('Y-m-d').'%')))->count();
				
			$passnum++;
			if($passnum<10) {
				$passnum = '00'.$passnum;
			}elseif($passnum >=10 && $passnum < 100) {
				$passnum = '0'.$passnum;
			}else {
				$passnum = ''.$passnum;
			}
			//生成项目序号
			$number = $cate_sign.date('ymd').$passnum;			
			$data['number'] = $number;		

		} else {//当项目由已通过改为未通过 序号置为EmptyString
			$data['number'] = '';
		}

		if($Project->where('id='.$id)->save($data)) {
			// msgBox("操作成功",U('Project/projectList'));
			$this->success('操作成功',$action);
		} else {
			// msgBox("操作失败",U('Project/projectList'));
			$this->error('操作失败',$action);
		}
	}


	/**
	 * 查看项目详细信息
	 * @return [type] [description] 
	 */
	public function detailProject() {

		$id = I('get.id','',intval);
		$theProject = M('Project')->where('id='.$id)->find();
		if($theProject) {
			//处理附件 项目封面图片
			$attaInfos = M('Attachment')->where('id='.$theProject['attachment_id'])->find();
			if($attaInfos) {
				$theProject['atta_url']=$attaInfos['path'];
			}
			
			$cateInfos = M('Category')->where('id='.$theProject['category_id'])->find();
			if($cateInfos) {
				$theProject['cate_name'] = $cateInfos['name'];
				$theProject['cate_sign'] = $cateInfos['sign'];
			}

			$theProject['organiser']=M('User')->where('id='.$theProject['user_id'])->getField('name');

			//项目通过审核
			if($theProject['ispassed']==1) {
				//计算点赞数
				$theProject['favor'] = count(explode(",",$theProject['favor']));
				//支持人数
				$theProject['supportNum'] = M('ProjectSupport')->where('project_id='.$theProject['id'])->count(); 	
				//进度 评论 完成的项目有评分
				//Code...
				 
			}
		}
		$this->rewards = $rewards;
		$this->theProject = $theProject;
		$this->display();
	}

	/**
	 * 查看项目回报
	 * @return [type] [description]
	 */
	public function rewardsOfPro() {
		$project_id = I('get.pro_id',0,intval);
		// $project_name = I('get.pro_name');

		$project_name = M('Project')->where('id='.$project_id)->getField('name');
		
		//项目回报 
	 
		$ProjectReward = M('projectReward');

		$condition = array('project_id'=>$project_id);
		
		$result = page($ProjectReward,$condition,null,3);//分页处理
    
		$rewards = $result['list'];
		if($rewards) {
			//处理回报图片 
			// $Attachment = M('Attachment');
			foreach ($rewards as $i => $per) {
				// $rewards[$i]['reward_pic'] = __ROOT__.'/Uploads/pro_pics/'.$Attachment->where('id='.$per['attachment_id'])->getField('path');
				
				// 统计支持人数
				$rewards[$i]['supporterNum']=M('ProjectSupport')->where(array('reward_id'=>$per['id']))->count();
				
			}


		}
		

		$this->page = $result['page'];
		$this->project_name = $project_name;
		$this->rewards = $rewards;
		$this->display();

	}

	/**
	 * 查看支持者名单
	 * @return [type] [description]
	 */
	public  function  supportersList() {
		
		$project_id = I('get.project_id');
		$project_name = I('get.project_name');

		$ProjectSupport = M('ProjectSupport');

		import('ORG.Util.Page');// 导入分页类
    	$count = $ProjectSupport->where('project_id='.$project_id)->count();// 查询满足要求的总记录数
    	$Page  = new Page($count,5);// 实例化分页类 传入总记录数和每页显示的记录数
		//连接查询
		$result = $ProjectSupport
			->join('user ON project_support.user_id=user.id')
			->join('project_reward ON project_support.reward_id=project_reward.id')
			->where('project_support.project_id='.$project_id)
			->field('user.id,user.name,user.type,project_support.user_id,project_reward.reward_content,project_support.created_time')
			->order('project_support.created_time DESC')
			->limit($Page->firstRow.','.$Page->listRows)
			->select();

		$this->page = $Page->show();
		$this->project_name = $project_name;
		$this->supportersInfo = $result;
		$this->display();
	}

	//查看项目进度
	public function progressOfPro() {
		$project_id = I('get.project_id','0','intval');

		$ProjectProgress = M('ProjectProgress');

		import('ORG.Util.Page');// 导入分页类
    	$count = $ProjectProgress->where('project_id='.$project_id)->count();// 查询满足要求的总记录数
    	$Page  = new Page($count,4);// 实例化分页类 传入总记录数和每页显示的记录数
		//连接查询
		
		$result = $ProjectProgress
			->join('project ON project.id = project_progress.project_id')
			->join('attachment ON attachment.id = project_progress.attachment_id')
			->where(array('project_progress.project_id'=>$project_id))
			->field('project.id as project_id,project.name,project_progress.*,attachment.id as attachment_id,attachment.path ')
			->order('created_time DESC')
			->limit($Page->firstRow.','.$Page->listRows)
			->select();

		if($result)
			$this->project_name = $result[0]['name'];
		$this->page = $Page->show();
		$this->progressInfos = $result;
		$this->display();

	}


	/**
	 * 删除项目
	 * 项目相关数据一并删除 项目 评论 评分 进度 回报 支持 附件
	 * @return [type] [description]
	 */
	public function delProject() {

		//获取待删除项目ID
		$project_id = I('get.id');
		$action = I('get.action');

		//项目相关数据一并删除 项目 评论 评分 进度 回报 支持 附件
		//Project ProjectComment ProjectGrade ProjectProgress ProjectSupport ProjectReward Attachment
		$Project = M('Project');
		$projectInfos = $Project->join('Attachment ON Attachment.id = Project.attachment_id')->field('attachment.id as attachment_id,attachment.path,project.id')->where(array('project.id'=>$project_id))->find();
		if(!$projectInfos) {
			$Project->rollback();
			msgBox('资源不存在或已被删除',U('Project/projectList'));
			return;
		}

		try {
			$allAttaIds = array();//保存附件id 
			$allAttaPaths = array();// 保存附件路径 

			$Project->startTrans();//开启事务
			
			//保存待删除的附件id和路径
			$allAttaIds[] = $projectInfos['attachment_id'];
			$allAttaPaths[] = './Uploads/pro_pics/'.$projectInfos['path'];
			
			//删除项目记录
			if(!$Project->where(array('id'=>$project_id))->delete()){
				throw_exception('删除项目信息失败!');
			}	
			
			//获得项目进度 
			$ProjectProgress = M('ProjectProgress');
			$progressInfos = $ProjectProgress->join('attachment ON attachment.id=project_progress.attachment_id')
							->where(array('project_progress.project_id'=>$project_id))
							->field('attachment.id as attachment_id,attachment.path,project_progress.id as progress_id')
							->select();
			 

			if($progressInfos) {//有进程信息
				
				foreach ($progressInfos as $i => $prog) {
					//保存待删除的附件id和路径
					if($prog['attachment_id']) {
						$allAttaIds[] = $prog['attachment_id'];
						$allAttaPaths[] = './Uploads/pro_pics/'.$prog['path']; 
					}
				}

				//删除项目进度记录
				if(!$ProjectProgress->where(array('project_id'=>$project_id))->delete()) {
					throw_exception('删除进程信息失败!');
				}
			}


			//删除项目回报信息 
			M('ProjectReward')->where(array('project_id'=>$project_id))->delete();

			//删除项目支持信息
			M('ProjectSupport')->where(array('project_id'=>$project_id))->delete();

			//删除项目评分
			M('ProjectGrade')->where(array('project_id'=>$project_id))->delete();

			// 删除项目评论
			M('ProjectComment')->where(array('project_id'=>$project_id))->delete();

			//删除项目相关附件记录以及文件
			//allAttaIds数组至少有一个元素 因为项目封面必须
			if(M('Attachment')->where(array('id'=>array('in',$allAttaIds)))->delete()) {
				foreach ($allAttaPaths as $apath) {
					if(file_exists($apath)) {
						@unlink($apath);
					}
				}
			}else {
				throw_exception('删除附件信息失败!');
			}

			$Project->commit();//提交事务
			// msgBox('删除成功',U('Project/projectList'));
			$this->success('删除成功',$action);
			exit();
			
		} catch (Exception $e) {
			
			$Project->rollback();//删除失败 回滚
			// msgBox('删除失败',U('Project/projectList'));
			$this->error('删除失败',$action);
			exit();
		}
	
	}


	//设置为项目精选或者取消项目精选
	public function packedOrNot() {

		$id = I('get.id',0,'intval');
		$setTo = I('get.setTo',0,'intval');
		$Project = M('Project');
		
		if($Project->where(array('id'=>$id))->getField('ispassed')) {

			if($setTo) {
				if(!M('Project')->where(array('id'=>$id))->setField('packed',1)){
					$this->error('操作失败');return;
				}
			}else{
				if(!M('Project')->where(array('id'=>$id))->setField('packed',0)) {
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

	//项目精选列表
	public function packedList() {

		$keyword = I('param.keyword','');
			
		if($keyword!=='') {// 模糊查询 名称 序号 地点
			$data['project.name'] = array('like','%'.$keyword.'%');
			$data['project.number'] = array('like','%'.$keyword.'%');
			$data['project.address'] = array('like','%'.$keyword.'%');
			$data['_logic'] = 'OR';
		}

		if($data) {//模糊查询
			$condition = array('project.packed'=>1,$data);
		}else {//无查询
			$condition = array('project.packed'=>1);
		}

		$Project = M('Project');
		//分页跳转的时候保证查询条件
		$params = array('keyword'=>$keyword );
		$listAndPage = page($Project,$condition,$map);//分页处理
	
		import('ORG.Util.Page');// 导入分页类
	    $count = $Project->where($condition)->count();// 查询满足要求的总记录数
	    $Page  = new Page($count,8);// 实例化分页类 传入总记录数和每页显示的记录数
	    //分页跳转的时候保证查询条件
	    if($params) {
	         foreach($params as $key=>$val) {
	            $Page->parameter   .=   "$key=".urlencode($val).'&';
	        }
	    }
	     
	   	$this->projects  = $Project->join('user ON user.id=project.user_id')
	    		->field('project.id,project.number,project.name,project.days,project.address,user.id as user_id,user.name as organiser')
	    		->where($condition)->limit($Page->firstRow.','.$Page->listRows)->select();

		$this->key = $keyword;	//页面回显关键词
		$this->page = $Page->show();// 分页显示输出
		$this->display();
	}

}