function setImagePre() {  
    var docObj=document.getElementById("image");
    var path=document.getElementById("image").value; //获取上传的文件路径（名）
    var imgObjPreview=document.getElementById("preview");  
    if(docObj.files && docObj.files[0]){  
        //火狐下，直接设img属性  
        imgObjPreview.style.display = 'block';  
        imgObjPreview.style.width = '246px';  
        imgObjPreview.style.height = '185px';                      
        //imgObjPreview.src = docObj.files[0].getAsDataURL();  
          
        //火狐7以上版本不能用上面的getAsDataURL()方式获取，需要以下方式
        imgObjPreview.src = window.URL.createObjectURL(docObj.files[0]);
        document.getElementById('gray').innerHTML = getFileName(path);
    }else{  
        //IE下，使用滤镜  
        docObj.select();  
        var imgSrc = document.selection.createRange().text;  
        var localImagId = document.getElementById("localImag");  
        //必须设置初始大小  
        localImagId.style.width = "246px";  
        localImagId.style.height = "185px";  
        //图片异常的捕捉，防止用户修改后缀来伪造图片  
        try{  
            localImagId.style.filter="progid:DXImageTransform.Microsoft.AlphaImageLoader(sizingMethod=scale)";  
            localImagId.filters.item("DXImageTransform.Microsoft.AlphaImageLoader").src = imgSrc;  
        }catch(e){  
            alert("您上传的图片格式不正确，请重新选择!");  
            return false;  
        }
        imgObjPreview.style.display = 'none';  
        document.selection.empty();
        document.getElementById('gray').innerHTML = getFileName(path);
    }  
    return true;  
}
function setImagePreview() {  
    var docObj=document.getElementById("image");  
    var imgObjPreview=document.getElementById("preview");  
    if(docObj.files && docObj.files[0]){  
        //火狐下，直接设img属性  
        imgObjPreview.style.display = 'block';  
        imgObjPreview.style.width = '150px';  
        imgObjPreview.style.height = '150px';                      
        //imgObjPreview.src = docObj.files[0].getAsDataURL();  
          
        //火狐7以上版本不能用上面的getAsDataURL()方式获取，需要一下方式    
        imgObjPreview.src = window.URL.createObjectURL(docObj.files[0]);  
    }else{  
        //IE下，使用滤镜  
        docObj.select();  
        var imgSrc = document.selection.createRange().text;  
        var localImagId = document.getElementById("localImag");  
        //必须设置初始大小  
        localImagId.style.width = "150px";  
        localImagId.style.height = "150px";  
        //图片异常的捕捉，防止用户修改后缀来伪造图片  
        try{  
            localImagId.style.filter="progid:DXImageTransform.Microsoft.AlphaImageLoader(sizingMethod=scale)";  
            localImagId.filters.item("DXImageTransform.Microsoft.AlphaImageLoader").src = imgSrc;  
        }catch(e){  
            alert("您上传的图片格式不正确，请重新选择!");  
            return false;  
        }  
        imgObjPreview.style.display = 'none';  
        document.selection.empty();  
    }  
    return true;  
}

function setName(){
    var path=document.getElementById("upfile").value; //获取上传的文件路径（名）
    document.getElementById('showname').innerHTML = getFileName(path);
    $("#reset-file").css("display","inline");
}
//获取上传文件名
function getFileName(path){
    var pos1 = path.lastIndexOf('/');
    var pos2 = path.lastIndexOf('\\');
    var pos  = Math.max(pos1, pos2)
    if( pos<0 )
        return path;
    else
        return path.substring(pos+1);
}
function setPreDays(){
    var docObj=document.getElementById("days");
    var imgObjPreview=document.getElementById("predays");
    var daysValue = docObj.value();
    imgObjPreview.value = daysValue;
} 