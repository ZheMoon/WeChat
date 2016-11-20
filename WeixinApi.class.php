<?php

/*
 *	代码封装--类
 *
 */
	class WeixinApi{
		private $appid;
		private $secret;

		//构造方法赋值成员属性
		public function __construct($appid="",$secret=""){
			$this->appid = $appid;
			$this->secret = $secret;
		}


		//检验微信加密签名
		public function valid(){
			if ($this->checkSignature()) {
				echo $_GET['echostr'];
			}
			else
			{
				echo "Error";
				exit;
			}
		}
		

		//获取接口调用凭证access_token
		public function getAccessToken(){
			$data = json_decode(file_get_contents("access_token.txt"));//存储access_token
			if ($data->expires_in < time()) {
				$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->secret}";
				$result = $this->https_request($url);
				$access_token = $result['access_token'];
				$data->access_token = $access_token;
				$data->expires_in = time()+3600;
				$f = fopen("access_token.txt", "w");
				fwrite($f, json_encode($data));
				fclose($f);
			}else{
				$access_token = $data->access_token;
			}
			return $access_token;
		}


		//响应消息
		public function responseMsg(){
			$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//获取post原数据
			if (!empty($postStr)) {
				$postObj = simplexml_load_string($postStr,'SimpleXMLElement',LIBXML_NOCDATA);
				$RX_TYPE = trim($postObj->MsgType);

				//消息类型分离
				switch ($RX_TYPE){
					case 'event':
						$result = $this->receiveEvent($postObj);
						break;
					case 'text':
						$result = $this->receiveText($postObj);
						break;
					case 'image':
						$result = $this->receiveImage($postObj);
						break;
					case 'location':
						$result = $this->receiveLocation($postObj);
						break;
					case 'voice':
						$result = $this->receiveVoice($postObj);
						break;
					case 'shortvideo':
					case 'video':
						$result = $this->receiveVideo($postObj);
						break;
					case 'link':
						$result = $this->receiveLink($postObj);
						break;
					default:
						$result = "unknown msg type:".$RX_TYPE;
						break;
				}

				echo $result;
			}else{
				echo "";
				exit;
			}
		}

		//自定义菜单创建
		public function menuCreate($post){
			$access_token = $this->getAccessToken();
			$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token={$access_token}";
			return $this->https_request($url, $post);
		}

		//自定义菜单查询
		public function menuSelect(){
			$access_token = $this->getAccessToken();
			$url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token={$access_token}";
			return $this->https_request($url);
		}

		//自定义菜单删除
		public function menuDelete(){
			$access_token = $this->getAccessToken();
			$url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token={$access_token}";
			return $this->https_request();
		}

		//base型授权
		public function snsapi_base($redirect_uri){
			$redirect_uri = urlencode($redirect_uri);
			//授权页面准备
			$snsapi_base_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
			$code = $_GET['code'];
			//判断是否静默授权
			if (!isset($code)) {
				header("Location:{$snsapi_base_url}");
			}
			//通过code换取页面授权access_token
			$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->secret}&code={$code}&grant_type=authorization_code";
			return $this->https_request($url);
		}

		//userinfo型授权
		public function snsapi_userinfo($redirect_uri){
			$redirect_uri = urlencode($redirect_uri);
			//授权页面
			$snsapi_userinfo_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect";
			$code = $_GET['code'];
			if (!isset($code)) {
				header("Location:{$snsapi_userinfo_url}");
			}

			$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->secret}&code={$code}&grant_type=authorization_code";
			$result = $this->https_request($url);
			$access_token = $result['access_token'];
			$openid = $result['openid'];
			$userinfo_url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang=zh_CN";
			return $this->https_request($userinfo_url);
		}

		//token验证过程
		private function checkSignature(){
			$signature  = $_GET['signature'];
			$timestamp = $_GET['timestamp'];
			$nonce = $_GET['nonce'];
			
			//2.加密/校验
			$tmpArr = array(TOKEN,$timestamp,$nonce);
			sort($tmpArr,SORT_STRING);
			$tmpStr = implode($tmpArr);
			$tmpStr = sha1($tmpStr);
			if($tmpStr == $signature)
			{
				return true;
			}
			else
			{
				return false;
			}
		}

		//接收事件消息
		private function receiveEvent($object){
			$content = "";
			switch ($object->Event) {
				case 'subscribe':
					$content = "欢迎使用ByPupil开发的平台！";
					$content .= (!empty($object->EventKey))?("\n来自二维码场景".str_replace("qrscene_", "", $object->EventKey)):"";
					$content .= "\n\n".'<a href="http://pythink.top">支持ByPupil</a>';
					break;
				case 'unsubscribe':
					$content = "取消关注";
					break;
				case 'CLICK':
					switch ($object->EventKey) {
						case 'COMPANY':
							$content = array();
                        $content[] = array("Title"=>"方倍工作室", "Description"=>"", "PicUrl"=>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" =>"http://pythink.top");
							break;
						default:
							$content = "点击菜单：".$object->EventKey;
							break;
					}
					break;
				case "VIEW":
                $content = "跳转链接 ".$object->EventKey;
                break;
            case "SCAN":
                $content = "扫描场景 ".$object->EventKey;
                break;
            case "LOCATION":
                $content = "上传位置：纬度 ".$object->Latitude.";经度 ".$object->Longitude;
                break;
            case "scancode_waitmsg":
                if ($object->ScanCodeInfo->ScanType == "qrcode"){
                    $content = "扫码带提示：类型 二维码 结果：".$object->ScanCodeInfo->ScanResult;
                }else if ($object->ScanCodeInfo->ScanType == "barcode"){
                    $codeinfo = explode(",",strval($object->ScanCodeInfo->ScanResult));
                    $codeValue = $codeinfo[1];
                    $content = "扫码带提示：类型 条形码 结果：".$codeValue;
                }else{
                    $content = "扫码带提示：类型 ".$object->ScanCodeInfo->ScanType." 结果：".$object->ScanCodeInfo->ScanResult;
                }
                break;
            case "scancode_push":
                $content = "扫码推事件";
                break;
            case "pic_sysphoto":
                $content = "系统拍照";
                break;
            case "pic_weixin":
                $content = "相册发图：数量 ".$object->SendPicsInfo->Count;
                break;
            case "pic_photo_or_album":
                $content = "拍照或者相册：数量 ".$object->SendPicsInfo->Count;
                break;
            case "location_select":
                $content = "发送位置：标签 ".$object->SendLocationInfo->Label;
                break;
				default:
					$content = "receive a new event：".$object->Event;
					break;
			}

			if (is_array($content)) {
				$result = $this->transmitNews($object,$content);
			}else{
				$result = $this->transmitText($object,$content);
			}
			return $result;
		}


		//接收文本消息
		private function receiveText($object){
			$keyWrod = trim($object->Content);
			//多客服人工回复模式
			if (strstr($keyWrod,"在吗") || strstr($keyWrod, "在线客服")) {
				$result = $this->transmitService($object);
				return $result;
			}
			//自动回复
			if (strstr($keyWrod, "文本")) {
				$content = "这是个文本消息";
			}else if (strstr($keyWrod,"表情")) {
				$content = "微笑：/::)\n乒乓：/:oo\n中国：".$this->bytes_to_emoji(0x1F1E8).$this->bytes_to_emoji(0x1F1F3)."\n仙人掌：".$this->bytes_to_emoji(0x1F335);
			}else if (strstr($keyWrod,"单图文")){
				$content = array();
				$content[] = array("Title"=>"单图文标题","Description"=>"单图文内容","PicUrl"=>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg","Url" =>"http://pythink.top");
			}else if (strstr($keyWrod, "图文") || strstr($keyWrod, "多图文")) {
				$content = array();
				$content[] = array("Title"=>"多图文1标题", "Description"=>"", "PicUrl"=>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" =>"http://pythink.top");
				$content[] = array("Title"=>"多图文2标题", "Description"=>"", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://pythink.top");
            	$content[] = array("Title"=>"多图文3标题", "Description"=>"", "PicUrl"=>"http://g.hiphotos.bdimg.com/wisegame/pic/item/18cb0a46f21fbe090d338acc6a600c338644adfd.jpg", "Url" =>"http://pythink.top");
			}else{
				$content = date("Y-m-d H:i:s",time())."\n\n".'<a href="http://pythink.top">技术支持</a>';
			}

			if (is_array($content)) {
				if(isset($content[0])){
					$result = $this->transmitNews($object,$content);
				}else if (isset($content['MusicUrl'])) {
					$result = $this->transmitMusic($object,$content);
				}
			}else{
				$result = $this->transmitText($object,$content);
			}
			return $result;
		}


		//接收图片消息
		private function receiveImage($object){
			$content = array("MediaId"=>$object->MediaId);
			$result = $this->transmitImage($object,$content);
			return $result;
		}

		//接收位置消息
		private function receiveLocation($object){
			$content = "你发送的是位置，经度为：".$object->Location_Y."；纬度为：".$object->Location_X."；缩放级别为：".$object->Scale."；位置为：".$object->Label;
			$result = $this->transmitText($object,$content);
			return $result;
		}

		//接收语音消息
		private function receiveVoice($object){
			if (isset($object->Recognition) && !empty($object->Recognition)) {
				$content = "你刚才说的是：".$object->Recognition;
				$result = $this->transmitText($object,$content);
			}
			return $result;
		}

		//接收视频消息
		private function receiveVideo($object){
			$content = "上传视频类型：".$object->MsgType;
			$result = $this->transmitText($object,$content);
			return $result;
		}

		//接收链接消息
		private function receiveLink($object){
			$content = "你发送的是链接，标题为：".$object->Title."；内容为：".$object->Description."；链接地址为：".$object->Url;
			$result = $this->transmitText($object,$content);
			return $result;
		}

		//回复文本消息
		private function transmitText($object,$content){
			if (!isset($content) || empty($content)) {
				return "";
			}

			$xmlTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[text]]></MsgType>
							<Content><![CDATA[%s]]></Content>
						</xml>";
			$result = sprintf($xmlTpl,$object->FromUserName,$object->ToUserName,time(),$content);
			return $result;
		}
		//回复图文消息
		private function transmitNews($object,$newsArray){
			if (!is_array($newsArray)) {
				return "";
			}
			$itemTpl = "<item>
							<Title><![CDATA[%s]]></Title> 
							<Description><![CDATA[%s]]></Description>
							<PicUrl><![CDATA[%s]]></PicUrl>
							<Url><![CDATA[%s]]></Url>
						</item>";
			$item_str = "";
			foreach ($newsArray as $item) {
				$item_str .= sprintf($itemTpl,$item['Title'],$item['Description'],$item['PicUrl'],$item['Url']);
			}
			$xmlTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[news]]></MsgType>
							<ArticleCount>%s</ArticleCount>
							<Articles>
							".$item_str."
							</Articles>
						</xml>";
			$result = sprintf($xmlTpl,$object->FromUserName,$object->ToUserName,time(),count($newsArray));
			return $result;
		}

		//回复音乐消息
		private function transmitMusic($object,$musicArray){
			if (!is_array($musicArray)) {
				return "";
			}
			$itemTpl = "<Music>
							<Title><![CDATA[TITLE]]></Title>
							<Description><![CDATA[DESCRIPTION]]></Description>
							<MusicUrl><![CDATA[MUSIC_Url]]></MusicUrl>
							<HQMusicUrl><![CDATA[HQ_MUSIC_Url]]></HQMusicUrl>
							<ThumbMediaId><![CDATA[media_id]]></ThumbMediaId>
						</Music>";
			$item_str = sprintf($itemTpl,$musicArray['Title'],$musicArray['Description'],$musicArray['MusicUrl'],$musicArray['HQMusicUrl']);
			$xmlTpl = "<xml>
							<ToUserName><![CDATA[toUser]]></ToUserName>
							<FromUserName><![CDATA[fromUser]]></FromUserName>
							<CreateTime>12345678</CreateTime>
							<MsgType><![CDATA[music]]></MsgType>
							".$item_str."
						</xml>";
			$result = sprintf($xmlTpl,$object->FromUserName,$object->ToUserName,time());
			return $result;
		}

		//回复图片消息
		private function transmitImage($object,$imageArray){
			$itemTpl = "<Image>
        <MediaId><![CDATA[%s]]></MediaId>
    </Image>";

	        $item_str = sprintf($itemTpl, $imageArray['MediaId']);

	        $xmlTpl = "<xml>
					    <ToUserName><![CDATA[%s]]></ToUserName>
					    <FromUserName><![CDATA[%s]]></FromUserName>
					    <CreateTime>%s</CreateTime>
					    <MsgType><![CDATA[image]]></MsgType>
					    ".$item_str."
					</xml>";

	        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
	        return $result;
		}

		//回复语音消息
		private function transmitVoice($object,$voiceArray){
			$itemTpl = "<Voice>
				        <MediaId><![CDATA[%s]]></MediaId>
				    </Voice>";

			$item_str = sprintf($itemTpl, $voiceArray['MediaId']);
				        $xmlTpl = "<xml>
				    <ToUserName><![CDATA[%s]]></ToUserName>
				    <FromUserName><![CDATA[%s]]></FromUserName>
				    <CreateTime>%s</CreateTime>
				    <MsgType><![CDATA[voice]]></MsgType>
				    ".$item_str."
				</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
		}

		    //回复视频消息
    private function transmitVideo($object, $videoArray)
    {
        $itemTpl = "<Video>
        <MediaId><![CDATA[%s]]></MediaId>
        <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
    </Video>";

        $item_str = sprintf($itemTpl, $videoArray['MediaId'], $videoArray['ThumbMediaId'], $videoArray['Title'], $videoArray['Description']);

        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[video]]></MsgType>"
    .$item_str.
"</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

//回复多客服消息
    private function transmitService($object)
    {
        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[transfer_customer_service]]></MsgType>
</xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //字节转Emoji表情
    function bytes_to_emoji($cp)
    {
        if ($cp > 0x10000){       # 4 bytes
            return chr(0xF0 | (($cp & 0x1C0000) >> 18)).chr(0x80 | (($cp & 0x3F000) >> 12)).chr(0x80 | (($cp & 0xFC0) >> 6)).chr(0x80 | ($cp & 0x3F));
        }else if ($cp > 0x800){   # 3 bytes
            return chr(0xE0 | (($cp & 0xF000) >> 12)).chr(0x80 | (($cp & 0xFC0) >> 6)).chr(0x80 | ($cp & 0x3F));
        }else if ($cp > 0x80){    # 2 bytes
            return chr(0xC0 | (($cp & 0x7C0) >> 6)).chr(0x80 | ($cp & 0x3F));
        }else{                    # 1 byte
            return chr($cp);
        }
    }


    //https请求（GET和POST）
	private function https_request($url,$data=null){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//将页面以文件流的形式保存
		if (!empty($data)) {
			curl_setopt($ch, CURLOPT_POST, 1);//模拟post请求
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//post提交内容
		}
		$output = curl_exec($ch);
		curl_close($ch);
		return json_decode($output,true);
	}

	//日志记录
	private function logger($log_content){

	}
		
	}
?>
