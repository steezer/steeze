<?php
/**
 * 邮件发送函数库
 * 
 * @package default
 * @subpackage Helper
 */

/**
 * 发送日志记录
 *
 * @param string $mode 模式
 * @param string $message 消息
 * @param int $type 类型
 * @return void
 */
function mail_send_log($message='', $debugType=0){
    if($debugType){
        if($debugType===1){
            echo $message."\r\n";
        }else{
            fastlog($message, '[smtp]');
        }
    }
}

/**
 * 发送邮件
 *
 * @param string $toemail 收件人email
 * @param string $subject 邮件主题
 * @param string $message 正文
 * @param string $from 发件人
 * @param array $mail 邮件配置信息
 * @return bool
 */
function mail_send($toemail, $subject, $message, $mail=array()){
	if(empty($mail)){
        $mail=C('mail.*');          
    }
	// 邮件头的分隔符
	$delimiter="\r\n";
    // 端口
    $mailServer=$mail['server'];
    $mailPort=isset($mail['port']) ? $mail['port'] : 25;
    $mailUsername=isset($mail['username']) ? $mail['username'] : '';
    $mailPassword=isset($mail['password']) ? $mail['password'] : '';
    $mailAuth=$mailUsername!=='' && $mailPassword!=='';
    $mailFrom=$mail['from'];
    $debugType=isset($mail['debug']) ? $mail['debug'] : 0;
	
	$mailTo=preg_match('/^(.+?) \<(.+?)\>$/', $toemail, $mats) ? '=?utf-8?B?' . base64_encode($mats[1]) . "?= <$mats[2]>" : $toemail;
	
	$emailSubject='=?utf-8?B?' . base64_encode(preg_replace("/[\r|\n]/", '', $subject)) . '?=';
	$emailMessage=chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r", "\n", str_replace("\r\n", "\n", str_replace("\n\r", "\r", $message))))));
	
	$headers="From: $mailFrom{$delimiter}X-Priority: 3{$delimiter}X-Mailer: STEEZE-V1 {$delimiter}MIME-Version: 1.0{$delimiter}Content-type: text/html; charset=utf-8{$delimiter}Content-Transfer-Encoding: base64{$delimiter}";
	
	if(!$fp=fsockopen($mailServer, $mailPort, $errno, $errstr, 30)){
		mail_send_log($mailServer . '(:' . $mailPort . ') CONNECT - Unable to connect to the SMTP server', $debugType);
		return false;
	}
	stream_set_blocking($fp, true);
	
	$lastMessage=fgets($fp, 512);
	
	if(substr($lastMessage, 0, 3) != '220'){
		mail_send_log($mailServer . ':' . $mailPort . ' CONNECT - ' . $lastMessage, $debugType);
		return false;
	}
	
	fputs($fp, ($mailAuth ? 'EHLO' : 'HELO') . " STEEZE\r\n");
	$lastMessage=fgets($fp, 512);
	if(substr($lastMessage, 0, 3) != 220 && substr($lastMessage, 0, 3) != 250){
		mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' HELO/EHLO - ' . $lastMessage, $debugType);
		return false;
    }
    mail_send_log($lastMessage, $debugType);
	
	while(1){
		if(substr($lastMessage, 3, 1) != '-' || empty($lastMessage)){
			break;
		}
		$lastMessage=fgets($fp, 512);
	}
	
	if($mailAuth){
		fputs($fp, "AUTH LOGIN\r\n");
		$lastMessage=fgets($fp, 512);
		if(substr($lastMessage, 0, 3) != 334){
			mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' AUTH LOGIN - ' . $lastMessage, $debugType);
			return false;
		}
		
		fputs($fp, base64_encode($mailUsername) . "\r\n");
		$lastMessage=fgets($fp, 512);
		if(substr($lastMessage, 0, 3) != 334){
			mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' USERNAME - ' . $lastMessage, $debugType);
			return false;
		}
		
		fputs($fp, base64_encode($mailPassword) . "\r\n");
		$lastMessage=fgets($fp, 512);
		if(substr($lastMessage, 0, 3) != 235){
			mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' PASSWORD - ' . $lastMessage, $debugType);
			return false;
		}
	}
	
	fputs($fp, "MAIL FROM: <" . $mailFrom . ">\r\n");
	$lastMessage=fgets($fp, 512);
	if(substr($lastMessage, 0, 3) != 250){
		fputs($fp, "MAIL FROM: <" . $mailFrom . ">\r\n");
		$lastMessage=fgets($fp, 512);
		if(substr($lastMessage, 0, 3) != 250){
			mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' MAIL FROM - ' . $lastMessage, $debugType);
			return false;
		}
	}
	
	fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $toemail) . ">\r\n");
	$lastMessage=fgets($fp, 512);
	if(substr($lastMessage, 0, 3) != 250){
		fputs($fp, "RCPT TO: <" . preg_replace("/.*\<(.+?)\>.*/", "\\1", $toemail) . ">\r\n");
		$lastMessage=fgets($fp, 512);
		mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' RCPT TO - ' . $lastMessage, $debugType);
		return false;
	}
	
	fputs($fp, "DATA\r\n");
	$lastMessage=fgets($fp, 512);
	if(substr($lastMessage, 0, 3) != 354){
		mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' DATA - ' . $lastMessage, $debugType);
		return false;
	}
	
	$headers.='Message-ID: <' . gmdate('YmdHs') . '.' . substr(md5($emailMessage . microtime()), 0, 6) . rand(100000, 999999) . '@' . $_SERVER['HTTP_HOST'] . ">{$delimiter}";
	
	fputs($fp, "Date: " . gmdate('r') . "\r\n");
	fputs($fp, "To: " . $mailTo . "\r\n");
	fputs($fp, "Subject: " . $emailSubject . "\r\n");
	fputs($fp, $headers . "\r\n");
	fputs($fp, "\r\n\r\n");
	fputs($fp, "$emailMessage\r\n.\r\n");
	$lastMessage=fgets($fp, 512);
	if(substr($lastMessage, 0, 3) != 250){
		mail_send_log('(' . $mailServer . ':' . $mailPort . ')' . ' END - ' . $lastMessage, $debugType);
	}
	fputs($fp, "QUIT\r\n");
	return true;
}
