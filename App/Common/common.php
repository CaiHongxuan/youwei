<?php
/**
 * 公用函数库
 */
//打印 测试用 
function p ($array) {
	dump($array,1,'<pre>',0);
}

// 自定义消息框---MsgBox 测试用 
function msgBox($strMsg,$url=null,$isTop=false){
	$StrScript =
	"<script type='text/javascript' charset='UTF-8' >
		alert('".$strMsg."');";
    if($url) {
        if($isTop) {

            $StrScript.="top.location.href='".$url."'";
        }else {
           
            $StrScript.= "window.location='".$url."'"; 
        }  
    } 
    $StrScript.="</script>";
	echo $StrScript;
}

/**
 * 分页函数
 * @param  对象 $Model      模型对象
 * @param  数组 $conditions 查询条件
 * @param  数组 $params     分页查询需要保持的条件
 * @param  INT  $theCount   分页记录数
 * @param  String  $order   排序条件
 * @param  String $field    域 
 * @return 数组             包含结果集(list)和分类(page)数据的二维数组
 */
function page($Model,$conditions=true,$params=null,$theCount=8,$order='id DESC',$field='*') {
    import('ORG.Util.Page');// 导入分页类
    $count = $Model->where($conditions)->count();// 查询满足要求的总记录数
    $Page  = new Page($count,$theCount);// 实例化分页类 传入总记录数和每页显示的记录数
    //分页跳转的时候保证查询条件
    if($params) {
         foreach($params as $key=>$val) {
            $Page->parameter   .=   "$key=".urlencode($val).'&';
        }
    }
     
    $result = $Model->where($conditions)->field($field)->order($order)->limit($Page->firstRow.','.$Page->listRows)->select();
    $array['list'] = $result;//赋值数据集
    $array['page'] = $Page->show();// 分页显示输出
    if(!$array['list']) {
        $array['list'] = null;
    }
    return $array;//返回一个包含结果集和分类数据的二维数组
}

/**
 * 分页函数(join)
 * @param  对象 $Model      模型对象
 * @param  数组 $conditions 查询条件
 * @param  数组 $params     分页查询需要保持的条件
 * @param  INT  $theCount   分页记录数
 * @param  String  $order   排序条件
 * @param  String $field    域
 * @return 数组             包含结果集(list)和分类(page)数据的二维数组
 */
function pageJoin($Model,$conditions=true,$params=null,$theCount=8,$order='id DESC',$field='*', $join) {
    import('ORG.Util.Page');// 导入分页类
    $count = $Model->where($conditions)->count();// 查询满足要求的总记录数
    $Page  = new Page($count,$theCount);// 实例化分页类 传入总记录数和每页显示的记录数
    //分页跳转的时候保证查询条件
    if($params) {
        foreach($params as $key=>$val) {
            $Page->parameter   .=   "$key=".urlencode($val).'&';
        }
    }
    $result = $Model->join($join)->where($conditions)->field($field)->order($order)->limit($Page->firstRow.','.$Page->listRows)->select();
    $array['list'] = $result;//赋值数据集
    $array['page'] = $Page->show();// 分页显示输出
    if(!$array['list']) {
        $array['list'] = null;
    }
    return $array;//返回一个包含结果集和分类数据的二维数组
}


/**
 * [SendMail description]
 * @param [type] $address [description]
 * @param [type] $title   [description]
 * @param [type] $message [description]
 */
function SendMail($address,$title,$message)
{
    vendor('PHPMailer.class#phpmailer');
    vendor('PHPMailer.class#smtp');
    // vendor('PHPMailer.class#PHPMailerAutoload');

    $mail=new PHPMailer();          // 设置PHPMailer使用SMTP服务器发送Email
    $mail->IsSMTP();                // 设置邮件的字符编码，若不指定，则为'UTF-8'
    $mail->CharSet='UTF-8';         // 添加收件人地址，可以多次使用来添加多个收件人
    $mail->AddAddress($address);    // 设置邮件正文
    
    $htm_page = '
<html>
<head>
<body>
    <table style="margin: 25px auto;" border="0" cellspacing="0" cellpadding="0" width="648" align="center">
        <tbody>
        <tr>
            <td style="color:#4cb1ca;">
                <h1 style="margin-bottom:10px;">有为</h1>
            </td>
        </tr>
        <tr>
            <td style="border-left: 1px solid #4cb1ca; padding: 20px 20px 0px; background: none repeat scroll 0% 0% #ffffff; border-top: 5px solid #4cb1ca; border-right: 1px solid #4cb1ca;">
                <p>尊敬的用户,您好</p>
            </td>
        </tr>
        <tr>
            <td style="border-left: 1px solid #4cb1ca; padding: 10px 20px; background: none repeat scroll 0% 0% #ffffff; border-right: 1px solid #4cb1ca;">'.$message.'</td>
        </tr>
        <tr>
            <td style="border-bottom: 1px solid #4cb1ca; border-left: 1px solid #4cb1ca; padding: 0px 20px 20px; background: none repeat scroll 0% 0% #ffffff; border-right: 1px solid #4cb1ca;">
                <hr style="color:#333;">
                <p style="color:#333;font-size:9pt;">想了解更多信息，请访问 
                <a href="http://'.$_SERVER['SERVER_NAME'].U('Index/index').'" target="_blank">
                http://'.$_SERVER['SERVER_NAME'].U('Index/index').'
                </a></p>
            </td>
        </tr>
    </tbody>
    </table>
</body>
</html>';




    $mail->Body=$htm_page;           // 设置邮件头的From字段。
    $mail->IsHTML(true);           // 以HTML形式发送 

    $mail->From=C('MAIL_ADDRESS');  // 设置发件人名字
    $mail->FromName='佛山市有为青年创业平台';  // 设置邮件标题
    $mail->Subject=$title;          // 设置SMTP服务器。
    $mail->Host=C('MAIL_SMTP');     // 设置为"需要验证" ThinkPHP 的C方法读取配置文件
    $mail->SMTPAuth=true;           // 设置用户名和密码。
    $mail->Username=C('MAIL_LOGINNAME');
    $mail->Password=C('MAIL_PASSWORD'); // 发送邮件。
    return($mail->Send());




}


