<?php
/**
 * 系统发送错误或异常时访问的页面文件
 * 
 * <pre>
 * 用户可以在tmpl_exception_tpl配置项自定义错误显示页面，
 * 例如：C(['tmpl_exception_tpl'=>'/page/error']) .
 * 用户自定义的错误页面支持模版标签，支持以下模板变量：
 * $type: 错误类型，有exception（异常）和error（错误）两种
 * $code: 错误码
 * $message: 错误消息
 * $file: 发生错误的文件
 * $line: 发送错误所在的文件行数
 * $url: 客户端访问的URL地址
 * $e: 错误类型exception时是异常对象，用户可以自己获取异常信息；为error是发生错误的上下文符号表数组
 * </pre>
 * 
 * @package default
 * @subpackage View
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta content="text/html; charset=utf-8" http-equiv="Content-Type">
<title>系统发生<?php echo $type=='error'? '错误' : '异常';?></title>
<style type="text/css">
    *{ padding: 0; margin: 0; }
    html{ overflow-y: scroll; }
    body{ background: #fff; font-family: '微软雅黑'; color: #333; font-size: 16px; }
    .error{ padding: 24px 48px; }
    .face{ font-size: 100px; font-weight: normal; line-height: 120px; margin-bottom: 12px; }
    h1{ font-size: 32px; line-height: 48px; }
    .error .content{ padding-top: 10px}
<?php
    if(defined('APP_DEBUG') && APP_DEBUG){
?>
    .error .info{ margin-bottom: 12px; }
    .error .info .title{ margin-bottom: 3px; }
    .error .info .title h3{ color: #000; font-weight: 700; font-size: 16px; }
    .error .info .text{ line-height: 24px; }
    .copyright{ padding: 12px 48px; color: #999; }
    .copyright a{ color: #000; text-decoration: none; }
    img{ border: 0; }
<?php 
    }
?>
</style>
</head>
<body>
<div class="error">
<p class="face">:(</p>
<h1><?php echo strip_tags($message);?> <?php echo strip_tags($code);?></h1>
<div class="content">
<?php
if(defined('APP_DEBUG') && APP_DEBUG){
	if(null!==$file) {
?>
	<div class="info">
		<div class="title">
			<h3><?php echo $type=='error'? '错误' : '异常';?>位置</h3>
		</div>
		<div class="text">
			<p>FILE: <?php echo $file ;?> &#12288;LINE: <?php echo $line;?></p>
            <p>URL: <?php echo $url ;?></p>
		</div>
	</div>
<?php 
	}
	if(is_object($e) && null!==$e->getTraceAsString()) {
?>
	<div class="info">
		<div class="title">
			<h3>TRACE</h3>
		</div>
		<div class="text">
			<p><?php echo nl2br($e->getTraceAsString());?></p>
		</div>
	</div>
<?php 
	}else if(is_array($e)){
?>
	<div class="info">
		<div class="title">
			<h3>TRACE</h3>
		</div>
		<div class="text">
			<p>
            <?php 
                foreach($e as &$item){
                    echo strval(is_object($item)?get_class($item):$item).'<br/>';
                }
            ?>
            </p>
		</div>
	</div>
<?php
    }
}
?>
</div>
</div>
</body>
</html>
