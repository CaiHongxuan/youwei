<?php

/**
 * 政策后台控制器
 */
class PolicyAction extends InitializeAction {

	//政策列表
	public function plist() {
		//获取已发布的政策分类 
		$category = M('Category')->where(array('type'=>'policy','status'=>1))->field('id,name')->select();
	
		if(!$category) {//当前尚无政策分类
			msgBox('当前尚无已发布的政策分类，请先添加或发布分类',U('Category/clist',array('type'=>'policy')));
			exit();
		}
		$this->category = $category;

		//自动判断请求类型获取参数 
		$category_id = I('param.category_id','','intval');
		$keyword = I('param.keyword','','trim');

		if($keyword!=='') {
			$data['policy.title'] = array('like','%'.$keyword.'%');
			$data['policy.description'] = array('like','%'.$keyword.'%');	
			$data['_logic'] = 'OR';
		}

		if($data) {//是否模糊查询
			if($category_id) {
				$condition = array('category_id'=>$category_id,$data);		
			} else {
				$condition = $data;
			}
		}else {
			if($category_id) {
				$condition = array('category_id'=>$category_id);				
			} else {
				$condition = true;
			}		
		}  

		$Policy = M('Policy');

		import('ORG.Util.Page');// 导入分页类
    	$count = $Policy->where($condition)->count();// 查询满足要求的总记录数
    	$Page  = new Page($count,8);
		
		//分页跳转的时候保证查询条件
		$params = array('category_id' => $category_id ,'keyword'=>$keyword );
        //分页跳转的时候保证查询条件
        foreach($params as $key=>$val) {
            $Page->parameter   .=   "$key=".urlencode($val).'&';
        }
    	$this->policy = $Policy
    	->join('category ON category.id = policy.category_id')
    	->join('attachment ON attachment.id = policy.attachment_id')
    	->order('policy.created_time DESC')
    	->field('policy.*,attachment.id  aid,attachment.path,category.name cate_name')
    	->where($condition)
    	->limit($Page->firstRow.','.$Page->listRows)->select();
    	
   		$this->page = $Page->show();// 分页显示输出
		$this->keyword = $keyword;	//页面回显关键词
		
		$this->category_id = $category_id;//返回下拉框的值
    
		$this->display();

	}


	// 删除政策
	public function  delete() {

		$delId = I('get.id');
		$action = I('get.action');
		// p($action);die;
		$Policy = M('Policy');
		$policyInfos =
			 $Policy->join('attachment ON attachment.id = policy.attachment_id')
				->where('policy.id='.$delId)
				->field('attachment.id aid,attachment.path apath,policy.id pid')
				->find();

		if($policyInfos) {
			//删除记录
			if($Policy->where('id='.$policyInfos['pid'])->delete()){
				if(M('Attachment')->where('id='.$policyInfos['aid'])->delete()) {
					// 删除附件
					$atta_path = './Uploads/policy/'.$policyInfos['apath'];
					if(file_exists($atta_path)) {
						unlink($atta_path);
					}
					// msgBox('删除成功',U('Policy/plist'));
					$this->success('删除成功',$action);
					exit();
				} 
			} else {
				// msgBox('删除失败',U('Policy/plist'));
				$this->error('删除失败');
				exit();
			}
		}else {
			// msgBox('访问的资源不存在或已被删除',U('Policy/plist'));
			$this->error('访问的资源不存在或已被删除');
			exit();
		}
	}

	//新增政策
	public function add() {
		//获取已发布的政策分类 
		$category = M('Category')->where(array('type'=>'policy','status'=>1))->field('id,name')->select();
	
		if(!$category) {//当前尚无政策分类
			msgBox('当前尚无已发布的政策分类，请先添加或发布分类',U('Category/clist',array('type'=>'policy')));
			exit();
		}
		$this->category = $category;


		//处理表单
		if(IS_POST) {
			//获取表单字段
			$data = array(
				'title' => I('title','','trim'),
				'description' => I('description','','trim'),
				'category_id' => I('category_id'),
				'attachment_id' => 0, 
				'crated_time' => date('Y-m-d H:i:s'),
				);
			$Policy = D('Policy');
			// 动态验证
			$validate = array(
				array('title','require','政策标题必须！'), // 仅仅需要进行验证码的验证
				array('description','require','政策简介必须！')
			);
			$Policy->setProperty("_validate",$validate);
			$Policy->setProperty("patchValidate",true);
			$result = $Policy->create($data);
			if(!$result) {//表单检验

				$this->errors = $Policy->getError();
				$this->formdata = $data;
				$this->display();
				exit();
			
			}else {
				//处理附件上传
				import('ORG.Net.UploadFile');
				$upload = new UploadFile();

				$upload->maxSize  = 1024*1024*10 ;// 设置附件上传大小
				$upload->allowExts  = array('doc', 'pdf', 'rar','ppt','xlsx','docx','zip');// 设置附件上传类型
				
				$uploadpath = './Uploads/policy/';
				//上传目录不存在 则创建
				if(!file_exists($uploadpath)) {
					mkdir($uploadpath,0777);
				}

				$upload->savePath = $uploadpath;
				$upload->autoSub = true;
				$upload->subType = 'date';
				$upload->dateFormat = 'Ym/dH';

				if(!$upload->upload()) {// 上传错误提示错误信息
								
					$this->errors = array('upfile' => $upload->getErrorMsg());
					$this->formdata = $data;
					$this->display();
					exit();
				}

				$uploadFileInfos =  $upload->getUploadFileInfo();
			

				// 保存表单数据 包括附件数据
				$Attachment = M('Attachment');
				$Attachment->startTrans();

				$Policy->attachment_id = $Attachment->add(array('path'=> $uploadFileInfos[0]['savename'],'created_time'=>date('Y-m-d H:i:s')));
				
				if($Policy->add()) {

					$Attachment->commit();
					// msgBox('添加成功',U('Policy/plist'));
					$this->success('添加成功',U('Policy/plist'));
					exit();

				}else{

					$Attachment->rollback();
					
					unlink($uploadpath.$uploadFileInfos[0]['savename']);//删除附件
					// msgBox('添加失败',U('Policy/add'));
					$this->error('添加失败');
					exit();
				}
			}

		} else {//提供表单
			$this->display();
		}
	}

	//修改政策
	public function edit() {
		//获取已发布的政策分类 
		$category = M('Category')->where(array('type'=>'policy','status'=>1))->field('id,name')->select();
	
		if(!$category) {//当前尚无政策分类
			msgBox('当前尚无已发布的政策分类，请先添加或发布分类',U('Category/clist',array('type'=>'policy')));
			exit();
		}
		$this->category = $category;

		if(IS_POST) {

			//获取表单字段
			$data = array(
				'id' => I('post.id'),
				'title' => I('post.title','','trim'),
				'description' => I('post.description','','trim'),
				'category_id' => I('post.category_id'),
				'crated_time' => date('Y-m-d H:i:s'),
				);
			$Policy = D('Policy');
			// 动态验证
			$validate = array(
				array('title','require','政策标题必须！'), // 仅仅需要进行验证码的验证
				array('description','require','政策简介必须！')
			);
			$Policy->setProperty("_validate",$validate);
			$Policy->setProperty("patchValidate",true);
			$result = $Policy->create($data);
			if(!$result) {//表单检验

				$this->errors = $Policy->getError();
				$this->policyInfos = $data;
				$this->display();
				exit();
			} else {
				if($Policy->save($data)) {
					// msgBox('修改成功',U('Policy/plist'));
					$this->success('修改成功',U('Policy/plist'));
					return;
				} else {
					// msgBox('修改失败',U('Policy/edit',array('id'=>$id)));
					$this->error('修改失败');
					return;
				}
			}


		} else if(IS_GET) {
			$id = I('get.id',0,'intval');
			$policyInfos = M('policy')->join('attachment ON attachment.id = policy.attachment_id')
					->field('policy.*,attachment.id as aid,attachment.path')->where('policy.id='.$id)->find();
			
			$strarr = explode('.',$policyInfos['path']);
			$atta_ext = $strarr[count($strarr)-1];
			$policyInfos['atta_name'] = $policyInfos['title'].'.'.$atta_ext;

			$this->policyInfos = $policyInfos;
			$this->display();
		}

	}

	//下载政策附件
	public function download() {
		$policy_id = I('get.id');
		$result = M('Policy')->join('attachment ON attachment.id = policy.attachment_id')
							->where('policy.id='.$policy_id)->find();
		if($result) {
			$filepath = $result['path'];
			$strarr = explode('.',$result['path']);
			$file_ext = $strarr[count($strarr)-1];
			
			$filename = $result['title'].'.'.$file_ext;

			$filepath = './Uploads/policy/'.$filepath;

			if (file_exists($filepath)){
				//打开文件
				$file = fopen($filepath,"r");
				//返回的文件类型
				Header("Content-type: application/octet-stream");
				//按照字节大小返回
				Header("Accept-Ranges: bytes");
				//返回文件的大小
				Header("Accept-Length: ".filesize($filepath));
				//这里对客户端的弹出对话框，对应的文件名
				Header("Content-Disposition: attachment; filename=".$filename);
				
				//echo fread($file, filesize($filepath));// 一次性将数据传输给客户端
			 
				//向客户端回送数据 
				$buffer=1024;//一次只传输1024个字节的数据给客户端
				//判断文件是否读完
				while (!feof($file)) {
					//将文件读入内存
					$file_data=fread($file,$buffer);
					//每次向客户端回送1024个字节的数据
					echo $file_data;
				}
				fclose($file);
				return;

		 	} else {
				msgBox('资源已被删除',U('Policy/plist'));
		 		return;
	 	   }	

		} else {
			msgBox('下载失败',U('Policy/plist'));
	 	   	return;
		}

	} 

}

