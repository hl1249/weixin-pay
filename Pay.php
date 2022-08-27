<?php

namespace app\api\controller;

use app\common\controller\Api;

/**
 * 支付接口
 */
class Pay extends Api
{

    protected $noNeedLogin = ['login','pay'];
    
    public function getRequest($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 10);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }
    
    public function login(){
        $appid = 'wx6f294b5ce2ee22b7';
        $secret = '521af35c525e3e1c108c1af6048ccddd';
        $code = $this->request->get('code');
        return $this->getRequest('https://api.weixin.qq.com/sns/jscode2session?appid='.$appid.'&secret='.$secret.'&js_code='.$code.'&grant_type=authorization_code');
    }
    
    public function pay(){
        $openid = $this->request->post('open_id');//用户openid，前一步获取的
        $appid = 'wx6f294b5ce2ee22b7';//微信小程序appid
        $mchid= '1630217662';//商户id
        $xlid = '52A270222CC03DA2900662A7619B93D0A30FF6E6';//证书序列号
        $apiclient_key = 'https://'.$_SERVER['HTTP_HOST'].'/certificate/apiclient_cert.pem';//证书签名，官网下载，存放于服务器本地，注意路径
        $time = time(); //时间戳
        $orderid = 'orderid_1234567890abcdefghijsad';//订单编号
        $noncestr = md5($orderid.$time.rand());//随机字符串，可以将订单编号存于此处
        $ordertotal = 88;//支付宝以元为单位，微信以分为单位
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';//生成预支序号所提交路径
        $urlarr = parse_url($url); //路径拆解为：[scheme=>https,host=>api.mch.weixin.qq.com,path=>/v3/pay/transactions/jsapi]
        
        //2.格式化信息
        $data = array();
        $data['appid'] = $appid;
        $data['mchid'] = $mchid;
        $data['description'] = '我的商品大又壮';//商品描述
        $data['out_trade_no'] = $orderid;//订单编号
        $data['notify_url'] = "http://www.csdn.net/www/notify.html";//回调接口，可以为空
        $data['amount']['total'] = (integer)$ordertotal;//金额 单位 分
        $data['scene_info']['payer_client_ip'] = '0.0.0.0';//场景：ip
        $data['payer']['openid'] =$openid;//openid
        $jsonData = json_encode($data); //变为json格式
        
        //3.签名一：后端获取prepay_id时所需的参数，通过header提交
        //包含了微信指定地址、时间戳、随机字符串和具体内容
        $str = "POST"."\n".$urlarr['path']."\n".$time."\n".$noncestr."\n".$jsonData."\n";
        $signHead = $this->getSign($str);
        
        //4.头部信息
        //包含了商户信息、证书序列号、随机字符串、时间戳、签名
        //注意：这里只能使用$mchid，不能使用$data['mchid']，否则php会提示格式错误
        $token = sprintf('mchid="%s",serial_no="%s",nonce_str="%s",timestamp="%d",signature="%s"',$mchid,$xlid,$noncestr,$time,$signHead);
        $header  = array(
        	'Content-Type:application/json; charset=UTF-8',
        	'Accept:application/json',
        	'User-Agent:*/*',
        	'Authorization:WECHATPAY2-SHA256-RSA2048 '.$token
        );  
        
        //4.下单
        //向微信接口地址提交json格式的$data和header的头部信息，得到预支编号
        $res = $this->httpRequest($url,$jsonData,$header);
        //取出prepay_id
        $data = json_decode($res,true);
        // 报错信息
        // return json_encode($data);
        $prepayID = $data['prepay_id'];
        
        //5、签名二：前端支付时所需的参数
        //包含了小程序appId + 时间戳 + 随机字符串 + 订单详情扩展字符串（预支序号）
         //注意：格式为prepay_id=aabbcc
        $prepay = 'prepay_id='.$prepayID;
        $str = $appid."\n".$time."\n".$noncestr."\n".$prepay."\n";
        $signPay = $this->getSign($str);
        
        //6.支付
        //生成返回值提供给前端
        $array = array(
        	'paySign' => $signPay,
        	'nonceStr' => $noncestr,
        	'timeStamp' => $time,
        	'package' => $prepay,
        );
        return json_encode($array);
        
    }
    
    //7.涉及方法
    /**
     * http请求
     * @param string $url 请求接口的url，需要url编码
     * @param string $data 请求时传递的数据，GET时为null
     * @param string $header 请求时传递的头部数据
     * @return 返回请求接口返回的数据
     */  
    public function httpRequest($url='',$data='',$header='')
    {
    	$curl = curl_init(); // 启动一个CURL会话
    	curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在，如果出错则修改为0，默认为1
    	curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
    	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    	curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer	
    	if(!empty($data)){
    		curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    		curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
    	}
    	curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
    	curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回 
    	if(!empty($header)){
    		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);//$header以array格式
    	}
    	
    	$response = curl_exec($curl); // 执行操作
    	
    	if (curl_errno($curl)){
    		echo 'Error:'.curl_error($curl);//捕抓异常
    	}
    	
    	curl_close($curl); // 关闭CURL会话
    	return $response; // 返回数据，json格式
    }
    
    /**
     * 生成签名
     * @param string $content 需要结合的内容
     * @return 返回请求接口返回的数据
     */  
    public function getSign($content)
    {
        
        $apiclient_key = 'https://'.$_SERVER['HTTP_HOST'].'/certificate/apiclient_cert.pem';//证书签名，官网下载，存放于服务器本地，注意路径
    	$binary_signature = "";
    	$privateKey = file_get_contents($apiclient_key);//证书	
    	$algo = "SHA256";
    	//将上传内容与api证书结合生成签名
    	openssl_sign($content, $binary_signature, $privateKey, $algo);
    	return base64_encode($binary_signature);
    }
}
