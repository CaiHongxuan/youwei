<?php
/**
 * 政策
 */
Class PolicyAction extends CommonAction{
	
	public function index(){

		//获取已发布的政策分类
		$Category = M('Category'); 
		$this->category = $Category->where(array('type'=>'policy','status'=>1))->field('id,name')->select();
		

		$category_id = I('get.category_id','0','intval');
    	if($category_id) {//判断是否带分类参数
    		$condition = array('policy.category_id' => $category_id );

    	}else {
    		$condition = true;
    	}
		//获取全部政策
		
		$Policy = M('Policy');
		import('ORG.Util.Page'); 
    	$count = $Policy->where($condition)->count(); 
    	$Page  = new Page($count,8);

		 
    	$policyInfos = $Policy->join('category ON category.id = policy.category_id')->join('attachment ON attachment.id = policy.attachment_id')->order('policy.created_time DESC')->field('policy.*,attachment.id  aid,attachment.path,category.name cate_name')->where($condition)->limit($Page->firstRow.','.$Page->listRows)->select();
    	
    	//截取过长的标题 优化显示
    	foreach ($policyInfos as $i => $val) {	
    		if(mb_strlen($val['title']) > 20) {
				$policyInfos[$i]['title'] =  mb_substr($val['title'], 0, 20, 'utf-8').'...';
			}
			if(mb_strlen($val['description']) > 35) {

				$policyInfos[$i]['description'] =  
					mb_substr($val['description'], 0, 35, 'utf-8').'...';
			}
		}

    	$this->policyInfos = $policyInfos;
    	$this->page = $Page->show();// 分页显示输出
		
		$this->display();
	}


	/**
	 * 政策详情
	 * @return [type] [description]
	 */
	public function message(){

		$id = I('get.id','0','intval');
		$Policy = M('Policy');

		$policyInfos = $Policy->join('category ON category.id = policy.category_id')
						->field('policy.*,category.name cate_name')
						->where('policy.id='.$id)->find();

		if(!$policyInfos) {
			msgBox('页面不存在',U('Policy/index'));
			return;
		}

		$this->policyInfos = $policyInfos;
		$this->display();
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

?>