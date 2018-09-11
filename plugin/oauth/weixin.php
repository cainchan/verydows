<?php
/**
 * OAuth2.0 Tencent WeiXin
 * @author King
 */
class weixin extends abstract_oauth
{
    private $_api = 'https://api.weixin.qq.com';
    private $_open_url = 'https://open.weixin.qq.com';
    
    public function create_login_url($state)
    {
        $params = array
        (
            'response_type' => 'code',
            'appid' => $this->config['app_id'],
            'redirect_uri' => baseurl().'/api/oauth/callback/weixin',
            'state' => $this->set_session('STATE', $state),
            'scope' => 'snsapi_userinfo',
        );
        if($this->device == 'mobile') $params['display'] = 'mobile';
        return $this->_open_url.'/connect/qrconnect?'.http_build_query($params);
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
    
    public function get_user_info($access_token, $oauth_key)
    {
        $params = array
        (
            'oauth_consumer_key' => $this->config['app_id'],
            'access_token' => $access_token,
            'openid' => $oauth_key,
            'format' => 'json',
        );
        
        $uri = $this->_api.'/sns/userinfo?'.http_build_query($params);
        if($res = file_get_contents($uri))
        {
            $res = json_decode($res, TRUE);
            if($res['sex'] == '男') $res['sex'] = 1; elseif($res['sex'] == '女') $res['sex'] = 2; else $res['sex'] = 0;
            return array
            (
                'nickname' => $res['nickname'],
                'gender' => $res['sex'],
                'avatar' => $res['headimgurl'],
            );
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
}