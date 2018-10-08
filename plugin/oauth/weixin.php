<?php
/**
 * OAuth2.0 Tencent WeiXin
 * @author King
 */
class weixin extends abstract_oauth
{
    private $_api = 'https://api.weixin.qq.com';
    private $_open_url = 'https://open.weixin.qq.com';
    
    public function create_login_url($redirect_uri)
    {
        $params = array
        (
            'response_type' => 'code',
            'appid' => $this->config['app_id'],
            'redirect_uri' => $redirect_uri,
            'state' => 1,
            'scope' => 'snsapi_base',
        );
        if($this->device == 'mobile') $params['display'] = 'mobile';
        return $this->_open_url.'/connect/oauth2/authorize?'.http_build_query($params).'#wechat_redirect';
    }
    public function get_access_token(){
        //第一步:取全局access_token
        $url = sprintf("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s",$this->config['app_id'],$this->config['app_secret']);
        $token = $this->getJson($url);
        return $token["access_token"];
    }
    public function get_openid($code){
        //第二步:取得openid
        $url = sprintf("%s/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code",$this->_api,$this->config['app_id'],$this->config['app_secret'],$code);
        $oauth2 = $this->getJson($url);
        return $oauth2['openid'];
    }
    public function get_user_info($access_token,$openid){
        //第三步:根据全局access_token和openid查询用户信息  
        $url = sprintf("https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN",$access_token,$openid);
        $userinfo = $this->getJson($url);
        return $userinfo;
    }
    
    public function check_callback($args)
    {
        if(empty($args['state']) || $args['state'] != $this->get_session('STATE') || empty($args['code'])) return FALSE;
        
        $params = array
        (
            'grant_type' => 'authorization_code',
            'appid' => $this->config['app_id'],
            'redirect_uri' => baseurl().'/api/oauth/callback/weixin',
            'client_secret' => $this->config['app_key'],
            'scope' => 'snsapi_userinfo',
            'code' => $args['code'],
        );
        
        $uri = $this->_open_url.'/connect/qrconnect?'.http_build_query($params);
        if($str = file_get_contents($uri))
        {
            if(strpos($str, 'callback') !== FALSE)
            {
                $lpos = strpos($str, "(");
                $rpos = strrpos($str, ")");
                $str = substr($str, $lpos + 1, $rpos - $lpos -1);
            }
            
            $res = array();
            parse_str($str, $res);
            if(!empty($res['access_token'])) return $res['access_token'];
        }
        return FALSE;
    }
    
    public function get_oauth_key($access_token)
    {
        $uri = $this->_api.'/oauth2.0/me?access_token='.$access_token;
        $res = file_get_contents($uri);
        if(strpos($res, 'callback') !== FALSE)
        {
            $lpos = strpos($res, "(");
            $rpos = strrpos($res, ")");
            $res = substr($res, $lpos + 1, $rpos - $lpos -1);
        }
        $res = json_decode($res, TRUE);
        if(empty($res['code']) && !empty($res['openid']) && !empty($res['client_id']) && $res['client_id'] == $this->config['app_id'])
        {
            return $res['openid'];
        }
        return FALSE;
    }
    
    public function check_signature($args)
    {
        //获取微信服务器传过来的signature、token、nonce和timestamp
        $token = "kaychen"; //这是我自己设置的token值
        $timestamp = $args['timestamp'];
        $nonce = $args['nonce'];
        $signature = $args['signature'];
        //对token、nonce和timestamp按字典序排序
        $tmpArr = array($token, $nonce, $timestamp);
        sort($tmpArr,SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        //与singnature进行对比校验
        if($tmpStr == $signature){
            return true;
        } else {
            return false;
        }       
    }
    public function getJson($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }
}