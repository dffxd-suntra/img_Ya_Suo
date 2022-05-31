<?php
// 应用配置
// 路径相对目录绝对目录皆可
// 图片路径 (string)
$imgSrc = "./sjhs_jh/";
// 压缩后保存路径 (string)
$imgDst = "./sjhs/";
// 图片质量 (int) (最大100)
$imgQuality = 75;

/**
 * 获取一个路径(文件夹&文件) 父目录
 * /test/11/==>/test/   /test/1.c ==>/www/test/
 */
function get_path_father($path){
	$path = str_replace('\\','/', rtrim($path,'/'));
	$pos = strrpos($path,'/');
	if($pos === false){
		return $path;
	}
	return substr($path, 0,$pos+1);
}

/**
 * 文件大小格式化
 *
 * @param  $ :$bytes, int 文件大小
 * @param  $ :$precision int  保留小数点
 * @return :string
 */
function size_format($bytes, $precision = 2){
	if ($bytes == 0) return "0 B";
	$unit = array(
		'TB' => 1099511627776,  // pow( 1024, 4)
		'GB' => 1073741824,		// pow( 1024, 3)
		'MB' => 1048576,		// pow( 1024, 2)
		'kB' => 1024,			// pow( 1024, 1)
		'B ' => 1,				// pow( 1024, 0)
	);
	foreach ($unit as $un => $mag) {
		if (doubleval($bytes) >= $mag)
			return round($bytes / $mag, $precision).' '.$un;
	}
}

/**
 * 创建目录
 *
 * @param string $dir
 * @param int $mode
 * @return bool
 */
function mk_dir($dir, $mode = 0777){
	if (!$dir) return false;
	if (is_dir($dir) || @mkdir($dir, $mode)){
		return true;
	}
	if (!mk_dir(dirname($dir), $mode)){
		return false;
	}
	return @mkdir($dir, $mode);
}

/**
 * 获取扩展名
 */
function get_path_ext($path){
	$name = get_path_this($path);
	$ext = '';
	if(strstr($name,'.')){
		$ext = substr($name,strrpos($name,'.')+1);
		$ext = strtolower($ext);
	}
	if (strlen($ext)>3 && preg_match("/([\x81-\xfe][\x40-\xfe])/", $ext, $match)) {
		$ext = '';
	}
	return htmlspecialchars($ext);
}

// 传入参数为程序编码时，有传出，则用程序编码，
// 传入参数没有和输出无关时，则传入时处理成系统编码。
function iconv_app($str){
	global $config;
	$result = iconv_to($str,$config['systemCharset'], $config['appCharset']);
	return $result;
}
function iconv_to($str,$from,$to){
	if (strtolower($from) == strtolower($to)){
		return $str;
	}
	if (!function_exists('iconv')){
		return $str;
	}
	//尝试用mb转换；android环境部分问题解决
	if(function_exists('mb_convert_encoding')){
		$result = @mb_convert_encoding($str,$to,$from);
	}else{
		$result = @iconv($from, $to, $str);
	}
	if(strlen($result)==0){
		return $str;
	}
	return $result;
}

/**
 * 获取一个路径(文件夹&文件) 当前文件[夹]名
 * test/11/ ==>11 test/1.c  ==>1.c
 */
function get_path_this($path){
	$path = str_replace('\\','/', rtrim($path,'/'));
	$pos = strrpos($path,'/');
	if($pos === false){
		return $path;
	}
	return substr($path,$pos+1);
}

/**
 * 获取文件详细信息
 * 文件名从程序编码转换成系统编码,传入utf8，系统函数需要为gbk
 */
function file_info($path){
	$info = array(
		'name'			=> iconv_app(get_path_this($path)),
		'path'			=> iconv_app($path),
		'ext'			=> get_path_ext($path),
		'type' 			=> 'file',
		'size'			=> filesize($path)
	);
	return $info;
}
/**
 * 获取文件夹细信息
 */
function folder_info($path){
	$info = array(
		'name'			=> iconv_app(get_path_this($path)),
		'path'			=> iconv_app(rtrim($path,'/').'/'),
		'type' 			=> 'folder'
	);
	return $info;
}

/**
 * 获取文件夹下列表信息
 * dir 包含结尾/   d:/wwwroot/test/
 * 传入需要读取的文件夹路径,为程序编码
 */
function path_list($dir){
	$dir = rtrim($dir,'/').'/';
	if (!is_dir($dir) || !($dh = @opendir($dir))){
		return array('folderList'=>array(),'fileList'=>array());
	}
	$folderList = array();$fileList = array();//文件夹与文件
	while (($file = readdir($dh)) !== false) {
		if ($file =='.' || $file =='..' || $file == ".svn") continue;
		$fullpath = $dir . $file;
		if (is_dir($fullpath)) {
			$info = folder_info($fullpath);
			$folderList[] = $info;
		}
		else {//是否列出文件
			$info = file_info($fullpath);
			$fileList[] = $info;
		}
	}
	closedir($dh);
	return array('folderList' => $folderList,'fileList' => $fileList);
}

/**
 * desription 判断是否是图片（符合文件压缩格式的）
 * @param sting $ext 文件后缀
 * @return boolean true 是 false 否
 */
function is_pic_file($ext) {
	$ext_arr = array(
		"jpg","jpeg","png","gif"
	);
	if(in_array($ext,$ext_arr)) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * desription 判断是否gif动画
 * @param sting $image_file图片路径
 * @return boolean true 是 false 否
 */
function check_gifcartoon($image_file){
	$fp = fopen($image_file,'rb');
	$image_head = fread($fp,1024);
	fclose($fp);
	return preg_match("/".chr(0x21).chr(0xff).chr(0x0b).'NETSCAPE2.0'."/",$image_head)?false:true;
}

/**
 * desription 压缩图片
 * @param sting $imgsrc 图片路径
 * @param string $imgdst 压缩后保存路径
 * * @param int $quality 图片质量
 */
 function compressed_image($imgsrc,$imgdst, $quality) {
	mk_dir(get_path_father($imgdst));
	list($width,$height,$type)=getimagesize($imgsrc);
	//这里如果写的是图片大小不变 如果要改变图片大小，如:$new_width = $width * 0.5; 宽度减少一半
	$new_width = $width;
	$new_height = $height;
	switch($type){
		case 1:
			$giftype = check_gifcartoon($imgsrc);
			if($giftype){
				header('Content-Type:image/gif');
				$image_wp=imagecreatetruecolor($new_width, $new_height);
				$image = imagecreatefromgif($imgsrc);
				imagecopyresampled($image_wp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
				//$quality代表的是质量、压缩图片容量大小
				imagejpeg($image_wp, $imgdst,$quality);
				imagedestroy($image_wp);
			}
			break;
		case 2:
			header('Content-Type:image/jpeg');
			$image_wp=imagecreatetruecolor($new_width, $new_height);
			$image = imagecreatefromjpeg($imgsrc);
			imagecopyresampled($image_wp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			//$quality代表的是质量、压缩图片容量大小
			imagejpeg($image_wp, $imgdst,$quality);
			imagedestroy($image_wp);
			break;
		case 3:
			header('Content-Type:image/png');
			$image_wp=imagecreatetruecolor($new_width, $new_height);
			$image = imagecreatefrompng($imgsrc);
			imagecopyresampled($image_wp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
			//$quality代表的是质量、压缩图片容量大小
			imagejpeg($image_wp, $imgdst,$quality);
			imagedestroy($image_wp);
			break;
	}
}

/**
 * desription 返回文件夹下所有图片的路径
 * @param sting $path 文件夹路径
 * @return array 所有图片的路径
 */
function tpys($path) {
    $data=array();
    $files=path_list($path);
    // print_r($files);
    foreach ($files["folderList"] as $k => $v) {
        $data = array_merge($data,tpys($v["path"]));
    }
    foreach ($files["fileList"] as $k => $v) {
        if(!is_pic_file($v["ext"])) {
            continue;
        }
        array_push($data,$v);
    }
    echo "<tr><td>path=".$path."</td><th>num=".count($data)."</th></tr>";
    ob_flush();
    flush();
    return $data;
}
ini_set('memory_limit','512M');
set_time_limit(0);
ob_end_clean();
clearstatcache();
// include "comic/function.php";
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=8">
        <meta http-equiv="Expires" content="0">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Cache-control" content="no-cache">
        <meta http-equiv="Cache" content="no-cache">
        <title>图片压缩</title>
        <script>
            function gun() {
                document.documentElement.scrollHeight = document.documentElement.offsetHeight;
            }
        </script>
    </head>
    <body>
<?php
ob_flush();
flush();
// 计算文件夹个数
echo "<h1>计算文件夹个数</h1><table border=\"1\" width=\"100%\">
<tr>
<th>路径</th>
<th>个数</th>
</tr>";
ob_flush();
flush();
$t1 = microtime(true);
$data = tpys($imgSrc);
// print_r($data);
$t2 = microtime(true);
echo "<tr colspan=\"2\"><th colspan=\"5\">计算文件夹个数耗时".round($t2-$t1,5)."秒 共".count($data)."张图片</th></tr></table>";
ob_flush();
flush();

// 图片压缩
echo "<h1>图片压缩</h1><table border=\"1\" width=\"100%\">
<tr>
<th>进度</th>
<th>模式</th>
<th>路径</th>
<th>宽度</th>
<th>高度</th>
<th>大小</th>
</tr>";
ob_flush();
flush();
$t1 = microtime(true);
$i=0;
foreach ($data as $k => $v) {
    // 获取图片的地址
    $imgsc = str_replace($imgSrc,$imgDst,$v["path"]);
    // 获取图片的宽高
    $imgInfo = getimagesize($v["path"]);
    echo "<tr><th rowspan=\"2\">".round($i*100/count($data),2)."%</th><th>输入:</th><td>path=".$v["path"]."</td><th>w=".$imgInfo[0]."</th><th>h=".$imgInfo[1]."</th><th>size=".size_format($v["size"])."</th></tr>";
    ob_flush();
    flush();
    if(!file_exists($imgsc)) {
        compressed_image($v["path"],$imgsc,$imgQuality);
    }
    echo "<tr><th>输出:</th><td>path=".$imgsc."</td><th>w=".$imgInfo[0]."</th><th>h=".$imgInfo[1]."</th><th>size=".size_format(filesize($imgsc))."</th></tr>";
    ob_flush();
    flush();
    $i++;
}
$t2 = microtime(true);
echo "<tr colspan=\"2\"><th>100%</th><th colspan=\"5\">图片压缩耗时".round($t2-$t1,5)."秒 共".count($data)."张图片</th></tr></table>";
?>
    </body>
</html>