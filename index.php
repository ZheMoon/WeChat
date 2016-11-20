<?php
	require('WeixinApi.class.php');
	header('Content-type:text');
	define("TOKEN","weixin");
	$appid = "";// 输入appid
	$secret = "";//输入密钥
	$wxobj = new WeixinApi($appid,$secret);
	if (!isset($_GET['echostr'])) {
		$wxobj->responseMsg();
	}else{
		$wxobj->valid();
	}
?>