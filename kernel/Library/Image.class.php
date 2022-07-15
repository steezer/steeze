<?php

namespace Library;

/**
 * 图像处理类
 * 
 * @package Library
 */
class Image
{

    /**
     * 设置
     *
     * @var array
     */
    private $setting = null;

    /**
     * 实例
     *
     * @var Image
     */
    private static $_instance = null;
    
    
    public function __construct($setting=null)
    {
        if(is_array($setting)){
            $this->setting=$setting;
        }
    }

    /**
     * 获取最佳按比例缩放的图片尺寸
     *
     * @param int $srcwidth
     * @param int $srcheight
     * @param int $maxwidth
     * @param int $maxheight
     * @return array 
     */
    function getpercent($srcwidth, $srcheight, $maxwidth, $maxheight)
    {
        if (($maxwidth <= 0 && $maxheight <= 0) || ($maxwidth >= $srcwidth && $maxheight >= $srcheight)) {
            $w = $srcwidth;
            $h = $srcheight;
        } elseif ($maxwidth <= 0) { // 固定高度，宽度缩小
            $h = $srcheight > $maxheight ? $maxheight : $srcheight;
            $w = round($h * ($srcwidth / $srcheight));
        } elseif ($maxheight <= 0) { // 固定宽度，高度缩小
            $w = $srcwidth > $maxwidth ? $maxwidth : $srcwidth;
            $h = round($w * ($srcheight / $srcwidth));
        } else {
            if (($srcwidth / $maxwidth) < ($srcheight / $maxheight)) { // 固定高度，宽度缩小
                $w = round($srcwidth * ($maxheight / $srcheight));
                $h = $maxheight;
            } elseif (($srcwidth / $maxwidth) > ($srcheight / $maxheight)) { // 固定宽度，高度缩小
                $w = $maxwidth;
                $h = round($srcheight * ($maxwidth / $srcwidth));
            } else { // 直接按照目标缩小
                $w = $maxwidth;
                $h = $maxheight;
            }
        }

        $array['w'] = $w;
        $array['h'] = $h;
        return $array;
    }

    /**
     * 获取剪裁信息
     *
     * @param int $autocut
     * @param array $setting
     * @return array
     */
    private function getCutInfo($autocut, $setting)
    {
        $srcwidth=$setting['srcwidth'];
        $srcheight=$setting['srcheight'];
        $maxwidth=$setting['maxwidth'];
        $maxheight=$setting['maxheight'];

        // //执行剪裁功能前宽度、高度及限定点的设置////
        $reArr = array();
        if(!$autocut || ($maxwidth<=0 && $maxheight<=0)){
            return $reArr;
        }

        if (
            $maxwidth <= $srcwidth && 
            $maxheight <= $srcheight
        ) {
            // 【缩小裁剪，两种剪裁方式相同】
            if ($maxwidth > 0 && $maxheight > 0) {
                if ($maxwidth / $maxheight < $srcwidth / $srcheight) {
                    $reArr['cut_width'] = $srcheight * ($maxwidth / $maxheight);
                    $reArr['cut_height'] = $srcheight;
                    $reArr['psrc_x'] = ($srcwidth - $reArr['cut_width']) / 2;
                } elseif ($maxwidth / $maxheight > $srcwidth / $srcheight) {
                    $reArr['cut_width'] = $srcwidth;
                    $reArr['cut_height'] = $srcwidth * ($maxheight / $maxwidth);
                    $reArr['psrc_y'] = ($srcheight - $reArr['cut_height']) / 2;
                }
                $reArr['desc_width'] = $maxwidth;
                $reArr['desc_height'] = $maxheight;
            }
        }

        else if (
            $maxwidth >= $srcwidth && 
            $maxheight <= $srcheight
        ) {
            // 【宽度大于原始宽度，高度小于原始高度的剪裁】
            if ($maxheight <= 0) {
                if ($autocut == 2) { // 按原比例拉伸
                    $maxheight = $srcheight;
                    return $this->getCutInfo($autocut, $setting);
                }
            } else if ($autocut == 1) { // 直接居中投射，不放大
                $reArr['cut_width'] = $srcwidth;
                $reArr['cut_height'] = $maxheight;
                $reArr['psrc_y'] = ($srcheight - $reArr['cut_height']) / 2;
                $reArr['pdesc_x'] = ($maxwidth - $srcwidth) / 2;

                $reArr['desc_width'] = $reArr['cut_width'];
                $reArr['desc_height'] = $reArr['cut_height'];
            } else if ($autocut == 2) { // 放大填充投射
                $reArr['cut_width'] = $srcwidth;
                $reArr['cut_height'] = $reArr['cut_width'] * ($maxheight / $maxwidth);
                $reArr['psrc_y'] = ($srcheight - $reArr['cut_height']) / 2;

                $reArr['desc_width'] = $maxwidth;
                $reArr['desc_height'] = $maxheight;
            }
        }

        else if (
            $maxwidth <= $srcwidth && 
            $maxheight >= $srcheight
        ) {
            // 【宽度小于原始宽度，高度大于原始高度的剪裁】
            if ($maxwidth <= 0) {
                if ($autocut == 2) { // 按原比例拉伸
                    $maxwidth = $srcwidth;
                    return $this->getCutInfo($autocut, $setting);
                }
            } else if ($autocut == 1) { // 直接居中投射，不放大
                $reArr['cut_width'] = $maxwidth;
                $reArr['cut_height'] = $srcheight;
                $reArr['psrc_x'] = ($srcwidth - $reArr['cut_width']) / 2;
                $reArr['pdesc_y'] = ($maxheight - $reArr['cut_height']) / 2;
                $reArr['desc_width'] = $reArr['cut_width'];
                $reArr['desc_height'] = $reArr['cut_height'];
            } else if ($autocut == 2) { // //放大填充投射
                $reArr['cut_width'] = $srcheight * ($maxwidth / $maxheight);
                $reArr['cut_height'] = $srcheight;
                $reArr['psrc_x'] = ($srcwidth - $reArr['cut_width']) / 2;

                $reArr['desc_width'] = $maxwidth;
                $reArr['desc_height'] = $maxheight;
            }
        } else {
            // 【宽度和高度都大于或等于原始高度的剪裁】
            if ($autocut == 1) { // 直接将原图置于放大图中央
                $reArr['cut_width'] = $srcwidth;
                $reArr['cut_height'] = $srcheight;
                $reArr['pdesc_x'] = ($maxwidth - $reArr['cut_width']) / 2;
                $reArr['pdesc_y'] = ($maxheight - $reArr['cut_height']) / 2;

                $reArr['desc_width'] = $reArr['cut_width'];
                $reArr['desc_height'] = $reArr['cut_height'];
            } else if ($autocut == 2) { // 将原图按放大图比例检查，然后放大
                if ($maxwidth / $maxheight < $srcwidth / $srcheight) {
                    $reArr['cut_width'] = $srcheight * ($maxwidth / $maxheight);
                    $reArr['cut_height'] = $srcheight;
                    $reArr['psrc_x'] = ($srcwidth - $reArr['cut_width']) / 2;
                } elseif ($maxwidth / $maxheight > $srcwidth / $srcheight) {
                    $reArr['cut_width'] = $srcwidth;
                    $reArr['cut_height'] = $reArr['cut_width'] * ($maxheight / $maxwidth);
                    $reArr['psrc_y'] = ($srcheight - $reArr['cut_height']) / 2;
                }
                $reArr['desc_width'] = $maxwidth;
                $reArr['desc_height'] = $maxheight;
            }
        }
        $reArr['createwidth'] = $maxwidth;
        $reArr['createheight'] = $maxheight;
        return $reArr;
    }

    /**
     * 生成缩略图，并提供最佳位置截取功能 
     * @param string $image string 原始图片地址
     * @param string $filename='' string 生成后存放地址
     * @param int $maxwidth=200 int 最大宽度
     * @param int $maxheight=200 int 最大高度
     * @param int $autocut=0 int 是否自动剪裁
     * @param int $forece=0 int 是否强制执行
     * @param int $delOld=0 int 是否处理后删除原始图片) 
     * 说明： 
     * 1. 如果第二个参数为空则发送缩略图至浏览器，否则保存至路径； 
     * 2. 如果图片是gif格式，只能处理单帧的gif； 
     * 3. 自动剪裁将会根据图片尺寸自动寻找最佳位置检测； 
     * 4. 强制执行检查实在原始文件宽高都小于或等于生成的宽高时强制执行
     */
    public function thumbImg($image, $filename = '', $maxwidth = 200, $maxheight = 200, $autocut = 0, $forece = 0, $delOld = 0)
    {
        // //获取图片信息////
        $info = self::info($image);
        if ($info === false) {
            return 0;
        }
        $srcwidth = $info['width'];
        $srcheight = $info['height'];
        $type = $info['type'];
        $otype = !empty($filename) ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : $type;
        if (!function_exists('imagecreatefrom' . $type)) {
            return false;
        }
        unset($info);

        // 缩小模式忽略条件
        if (
            $type == $otype && 
            !$autocut && 
            (
                (!$maxwidth && $srcheight <= $maxheight) || 
                (!$maxheight && $srcwidth <= $maxwidth)
            )
        ) {
            return false;
        }

        // 原始宽度和高度都大于或等于设定范围，强制重新设置宽度和高度
        if (
            $type == $otype && 
            $srcwidth <= $maxwidth && 
            $srcheight <= $maxheight && 
            !empty($filename) && 
            !$forece
        ) {
            return false;
        }

        $setting = array(
            'type' => $type, 
            'otype' => $otype,
            'srcwidth' => $srcwidth, 
            'srcheight' => $srcheight, 
            'maxwidth' => $maxwidth, 
            'maxheight' => $maxheight
        );
        return $this->cutImg($image, $filename, $setting, $autocut, $delOld);
    }
    
    /**
     * 图片剪裁
     *
     * @param string $image
     * @param string $filename
     * @param array $setting
     * @param int $autocut
     * @param int $delOld
     */
    private function cutImg($image, $filename, $setting=array(), $autocut = 0, $delOld = 0)
    {
        $type=$setting['type'];
        $otype=$setting['otype'];
        $srcwidth=$setting['srcwidth'];
        $srcheight=$setting['srcheight'];
        $maxwidth=$setting['maxwidth'];
        $maxheight=$setting['maxheight'];

        // //计算图片比例////
        $creat_arr = $this->getpercent($srcwidth, $srcheight, $maxwidth, $maxheight);
        $pdesc_x = $pdesc_y = $psrc_x = $psrc_y = 0;
        $createwidth = $desc_width = $creat_arr['w'];
        $createheight = $desc_height = $creat_arr['h'];
        $cut_width = $srcwidth;
        $cut_height = $srcheight;
        
        // //执行剪裁功能前宽度、高度及限定点的设置////
        if ($autocut && ($maxwidth > 0 || $maxheight > 0)) {
            $cutSetting = $this->getCutInfo($autocut, $setting);
            extract($cutSetting);
            unset($cutSetting);
        }

        // 在非转换格式的情况下，如果宽度和高度都为0则不处理
        if (!$createwidth && !$createheight) {
            if ($otype != $type) {
                $createwidth = $srcwidth;
                $createheight = $srcheight;
            } else {
                return false;
            }
        }

        // 如果为gif图片，且为多帧动画则不处理
        if ($type == 'gif' && self::is_animation($image)) {
            return false; // 多帧动画不处理
        }
        
        // //执行缩略图操作////
        $createfun = 'imagecreatefrom' . ($type == 'jpg' ? 'jpeg' : $type);
        if(!function_exists($createfun)){
            return false;
        }
        $srcImage = $createfun($image);
        

        if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
            $distImage = imagecreatetruecolor($createwidth, $createheight);
        } else {
            $distImage = imagecreate($createwidth, $createheight);
        }

        if ($type != 'gif' && $type != 'png') {
            // 指定白色背景
            $bgColor = imagecolorallocate($distImage, 255, 255, 255);
            imagefill($distImage, 0, 0, $bgColor);
        }else{
            
            $tranIndex = imagecolortransparent($srcImage);
            if ($tranIndex >= 0) {
                $tranColor = imagecolorsforindex($srcImage, $tranIndex);
                $tranIndex = imagecolorallocate($distImage, $tranColor['red'], $tranColor['green'], $tranColor['blue']);
                imagefill($distImage, 0, 0, $tranIndex);
                imagecolortransparent($distImage, $tranIndex);
            }
            elseif ($type == 'png') {
                imagealphablending($distImage, false);
                $color = imagecolorallocatealpha($distImage, 0, 0, 0, 127);
                imagefill($distImage, 0, 0, $color);
                imagesavealpha($distImage, true);
            }

        }
        
        if (function_exists('imagecopyresampled')) {
            imagecopyresampled($distImage, $srcImage, $pdesc_x, $pdesc_y, $psrc_x, $psrc_y, $desc_width, $desc_height, $cut_width, $cut_height);
        } else {
            imagecopyresized($distImage, $srcImage, $pdesc_x, $pdesc_y, $psrc_x, $psrc_y, $desc_width, $desc_height, $cut_width, $cut_height);
        }
        
        imagedestroy($srcImage);

        // //后期处理////
        if (!empty($otype)) {
            $type = $otype;
        }
        $type = ($type == 'jpg' ? 'jpeg' : $type);
        if ($type == 'jpeg') {
            imageinterlace($distImage, 0);
        }

        //输出图片
        $imagefun = 'image' . $type;
        if (function_exists($imagefun)) {
            if (empty($filename)) {
                header('Content-type: image/' . $type);
                $imagefun($distImage);
            } else {
                $dirName = dirname($filename);
                if (!is_dir($dirName)) {
                    @mkdir($dirName, 0777, true);
                }
                $imagefun($distImage, $filename);
            }
        }

        //销毁图片
        imagedestroy($distImage);
        if ($delOld) {
            unlink($image);
        }
        return true;
    }

    /**
     * 增加图片水印
     *
     * @param string $source
     * @param string $target
     * @param array $setting
     */
    public function watermark($source, $target = '', $setting = array())
    {
        $setting = $this->get_setting($setting);
        if (!$setting['w_type']) {
            return false;
        }

        if (!self::check($source)) {
            return false;
        }
        if (!$target) {
            $target = $source;
        }

        $setting['w_img'] = UPLOAD_PATH . 'watermark' . DS . $setting['w_img'];
        $source_info = getimagesize($source);
        $source_w = $source_info[0];
        $source_h = $source_info[1];
        $font_path = DATA_PATH . 'system' . DS . 'font' . DS . 'elephant.ttf';

        // 添加水印条件
        if ($source_w < $setting['w_minwidth'] || $source_h < $setting['w_minheight']) {
            return false;
        }

        // 生成水印原图
        switch ($source_info[2]) {
            case 1:
                $source_img = imagecreatefromgif($source);
                break;
            case 2:
                $source_img = imagecreatefromjpeg($source);
                break;
            case 3:
                $source_img = imagecreatefrompng($source);
                break;
            default:
                return false;
        }

        // 判断水印模式
        if ($setting['w_type'] == 1 && !empty($setting['w_img']) && is_file($setting['w_img'])) {
            // 图片水印模式
            $ifwaterimage = 1;
            $water_info = getimagesize($setting['w_img']);
            $width = $water_info[0];
            $height = $water_info[1];
            switch ($water_info[2]) {
                case 1:
                    $water_img = imagecreatefromgif($setting['w_img']);
                    break;
                case 2:
                    $water_img = imagecreatefromjpeg($setting['w_img']);
                    break;
                case 3:
                    $water_img = imagecreatefrompng($setting['w_img']);
                    break;
                default:
                    return;
            }
        } else {
            // 文字水印模式
            $ifwaterimage = 0;
            $temp = imagettfbbox($setting['w_fontsize'], 0, $font_path, $setting['w_text']);
            $width = $temp[2] - $temp[6];
            $height = $temp[3] - $temp[7];
            unset($temp);
        }

        // 水印位置
        switch ($setting['w_pos']) {
            case 1:
                $wx = 5;
                $wy = 5;
                break;
            case 2:
                $wx = ($source_w - $width) / 2;
                $wy = 0;
                break;
            case 3:
                $wx = $source_w - $width;
                $wy = 0;
                break;
            case 4:
                $wx = 0;
                $wy = ($source_h - $height) / 2;
                break;
            case 5:
                $wx = ($source_w - $width) / 2;
                $wy = ($source_h - $height) / 2;
                break;
            case 6:
                $wx = $source_w - $width;
                $wy = ($source_h - $height) / 2;
                break;
            case 7:
                $wx = 0;
                $wy = $source_h - $height;
                break;
            case 8:
                $wx = ($source_w - $width) / 2;
                $wy = $source_h - $height;
                break;
            case 9:
                $wx = $source_w - $width;
                $wy = $source_h - $height;
                break;
            case 10:
                $wx = rand(0, ($source_w - $width));
                $wy = rand(0, ($source_h - $height));
                break;
            default:
                $wx = rand(0, ($source_w - $width));
                $wy = rand(0, ($source_h - $height));
                break;
        }

        if ($ifwaterimage) {
            if ($water_info[2] == 3) { // png
                imagecopy($source_img, $water_img, $wx, $wy, 0, 0, $width, $height);
            } else {
                imagecopymerge($source_img, $water_img, $wx, $wy, 0, 0, $width, $height, $setting['w_pct']);
            }
        } else {
            if (!empty($setting['w_color']) && (strlen($setting['w_color']) == 7)) {
                $r = hexdec(substr($setting['w_color'], 1, 2));
                $g = hexdec(substr($setting['w_color'], 3, 2));
                $b = hexdec(substr($setting['w_color'], 5));
            } else {
                return;
            }
            imagettftext($source_img, $setting['w_fontsize'], 0, $wx, $wy, imagecolorallocate($source_img, $r, $g, $b), $font_path, $setting['w_text']);
        }

        switch ($source_info[2]) {
            case 1:
                imagegif($source_img, $target);
                break;
            case 2:
                imagejpeg($source_img, $target, $setting['w_quality']);
                break;
            case 3:
                imagepng($source_img, $target);
                break;
            default:
                return;
        }

        if (isset($water_info)) {
            unset($water_info);
        }
        if (isset($water_img)) {
            imagedestroy($water_img);
        }
        unset($source_info);
        imagedestroy($source_img);
        return true;
    }
    
    /**
     * 获取设置
     *
     * @param array $setting
     */
    function get_setting($setting = array())
    {
        if (is_null($this->setting)) {
            $this->setting = getcache('setting', 'setting', 'array', 'attachment');
        }
        $setting = array_merge($this->setting, $setting);
        foreach ($setting as $k => $v) {
            if (is_numeric($v)) {
                $setting[$k] = intval($v);
            }
            if ($k == 'w_text' && empty($v)) {
                $setting[$k] = 'www.stwms.cn';
            }
            if ($k == 'w_color' && empty($v)) {
                $setting[$k] = '#000000';
            }
            if ($k == 'w_fontsize') {
                $v = intval($v);
                $setting[$k] = empty($v) ? 8 : $v;
            }
        }
        return $setting;
    }
    
    /**
     * 检查系统是否需要处理图片
     *
     * @param string $image
     */
    public static function check($image)
    {
        return extension_loaded('gd') && 
               preg_match("/\.(jpg|jpeg|gif|png)/i", $image, $m) && 
               (strpos($image, '://') || is_file($image)) && 
               function_exists('imagecreatefrom' . ($m[1] == 'jpg' ? 'jpeg' : $m[1]));
    }
    
    /**
     * 获取图片文件信息
     *
     * @param string $img
     * @return array
     * 返回信息：
     * {
     *   width : IMG_WIDTH, //高度
     *   height : IMG_HEIGHT, //宽度
     *   type : IMG_TYPE, //类型，如: jpg
     *   size : IMG_SIZE, //大小
     *   mime : MIME_TYPE, //MIME类型，如: image/jpeg
     * }
     */
    public static function info($img)
    {
        $imageinfo = getimagesize($img);
        if ($imageinfo === false) {
            return false;
        }
        $imagesize = filesize($img);
        $type = strtolower(substr($imageinfo['mime'], strpos($imageinfo['mime'], '/') + 1));
        $info = array(
                'width' => $imageinfo[0], 
                'height' => $imageinfo[1], 
                'type' => $type, 
                'size' => $imagesize, 
                'mime' => $imageinfo['mime']
            );
        return $info;
    }
    
    /**
     * 判断是否为gif动画
     *
     * @param string $image_file
     * @return boolean
     */
    public static function is_animation($image_file)
    {
        $fp = fopen($image_file, 'rb');
        $image_head = fread($fp, 1024);
        fclose($fp);
        return preg_match("/" . chr(0x21) . chr(0xff) . chr(0x0b) . 'NETSCAPE2.0' . "/", $image_head) ? true : false;
    }

    //剪裁
    public static function thumb($imgUrl, $maxWidth = 0, $maxHeight = 0, $cutType = 0, $defaultImg = '', $isGetRemot = 0)
    {
        $isGetRemot = is_int($defaultImg) ? $defaultImg : $isGetRemot;
        $smallpic = is_string($defaultImg) && $defaultImg ? $defaultImg : basename(C('default_thumb'));
        $defaultImg = strpos($smallpic, '/') === false ? dirname(C('default_thumb')) . '/' . $smallpic : $smallpic;
        if (empty($imgUrl)) {
            return $defaultImg;
        }
        $upload_url = env('UPLOAD_URL', '');
        $root_url = env('ROOT_URL', '');

        $isUpload = strpos($imgUrl, $upload_url) === 0;
        $oldimgurl = ($isUpload ? substr($imgUrl, strlen($upload_url)) : (strpos($imgUrl, $root_url) === 0 ? substr($imgUrl, strlen($root_url)) : $imgUrl));
        $isRemot = strpos($oldimgurl, '://');

        // 此参数会导致强制执行，会对图片进行缩放
        $forceExec = !$isRemot ? ($cutType > 0) : $isGetRemot;

        $IMG_PATH = $isUpload || $isRemot ? UPLOAD_PATH : ROOT_PATH; // 本地文件的路径
        $IMG_URL = $isUpload || $isRemot ? $upload_url : $root_url; // 本地文件的地址

        if (!extension_loaded('gd') || ($isRemot && !$isGetRemot)) {
            // gd库没有加载或外链时不获取远程则不处理
            return $imgUrl;
        }
        $oldimg_path = ($isRemot ? '' : $IMG_PATH) . $oldimgurl; // 最初文件路径
        $oldimg_url = ($isRemot ? '' : $IMG_URL) . $oldimgurl; // 最初文件地址

        if ($isRemot) {
            $newDirname = str_replace('.', '_', substr(dirname($oldimgurl), $isRemot + 3));
            $newFilename = basename($oldimgurl);

            // 如果图片为动态地址，则尝试提取图片扩展名，否则直接图片文件中获取扩展名
            $type = '';
            if (preg_match("/\.(jpg|jpeg|gif|png)/i", $newFilename, $m)) {
                $type = $m[1];
            }
            if (empty($type) || strpos($newFilename, '?') !== false) {
                if (empty($type)) {
                    $imgInfo = getimagesize($oldimgurl);
                    if ($imgInfo === false) {
                        return $defaultImg;
                    }
                    $type = substr($imgInfo['mime'], strpos($imgInfo['mime'], '/') + 1);
                    if (!$maxWidth && !$maxHeight) {
                        list($maxWidth, $maxHeight) = $imgInfo;
                    }
                    unset($imgInfo);
                }
                $newFilename = (strpos($newFilename, '?') !== false ? base64($newFilename, 'encode', 1) : $newFilename) . '.' . $type;
            }

            if (is_file($IMG_PATH . $newDirname . '/' . $newFilename)) {
                $srcInfo = getimagesize($IMG_PATH . $newDirname . '/' . $newFilename);
            }

            if (!$maxWidth && !$maxHeight) {
                $newimgurl = $newDirname . '/' . $newFilename;
                list($maxWidth, $maxHeight) = (isset($srcInfo) ? $srcInfo : getimagesize($oldimgurl));
            } else if (isset($srcInfo) && $srcInfo[0] <= $maxWidth && $srcInfo[1] <= $maxHeight) {
                $newimgurl = $newDirname . '/' . $newFilename;
            } else {
                if (strpos($newFilename, 'thumb') === 0 && strpos($newFilename, '_') == 6 && substr_count($newFilename, '_') >= 3) {
                    $offset = strpos($newFilename, '_') + 1;
                    $offset = strpos($newFilename, '_', $offset) + 1;
                    $newFilename = substr($newFilename, strpos($newFilename, '_', $offset) + 1);
                }
                $newimgurl = $newDirname . '/thumb' . $cutType . '_' . $maxWidth . '_' . $maxHeight . '_' . $newFilename;
            }
            unset($newDirname, $newFilename);
        } else {
            if (!is_file($oldimg_path)) {
                return $defaultImg;
            }
            list($src_width, $src_height) = getimagesize($oldimg_path);
            if (($src_width == $maxWidth && $src_height == $maxHeight) || ($src_width <= $maxWidth && $src_height <= $maxHeight && !$forceExec)) {
                return $oldimg_url;
            }
            $baseimgurl = basename($oldimgurl);
            if (strpos($baseimgurl, 'thumb') === 0 && strpos($baseimgurl, '_') == 6 && substr_count($baseimgurl, '_') >= 3) {
                $offset = strpos($baseimgurl, '_') + 1;
                $offset = strpos($baseimgurl, '_', $offset) + 1;
                $baseimgurl = substr($baseimgurl, strpos($baseimgurl, '_', $offset) + 1);
            }
            $newimgurl = dirname($oldimgurl) . '/thumb' . $cutType . '_' . $maxWidth . '_' . $maxHeight . '_' . $baseimgurl;
        }
        if ((!$maxWidth && !$maxHeight)) {
            return $imgUrl;
        }

        if (is_file($IMG_PATH . $newimgurl)) {
            return $IMG_URL . $newimgurl;
        }

        if (self::$_instance == null) {
            self::$_instance = new Image();
        }

        $res = self::$_instance->thumbImg($oldimg_path, $IMG_PATH . $newimgurl, $maxWidth, $maxHeight, $cutType, $forceExec);
        return $res ? $IMG_URL . $newimgurl : ($res === false ? $oldimg_url : $defaultImg);
    }
    
}
