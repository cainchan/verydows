<?php
class oauth_controller extends general_controller
{
    public function __construct(){
        $this->party = 'weixin';
        $oauth_model = new oauth_model();
        if($oauth = $oauth_model->find(array('party' => $this->party))){
            $this->oauth_obj = plugin::instance('oauth', $this->party, array($oauth['params']), TRUE);
        }
    }
    public function action_bind()
    {
        $party = sql_escape(request('party'));
        $oauth_model = new oauth_model();
        if($oauth = $oauth_model->find(array('party' => $party)))
        {
            $oauth_obj = plugin::instance('oauth', $party, array($oauth['params']), TRUE);
            if($access_token = $oauth_obj->check_callback($_GET))
            {
                if($oauth_key = $oauth_obj->get_oauth_key($access_token))
                {
                    $user_oauth_model = new user_oauth_model();
                    if($user_oauth_model->is_authorized($party, $oauth_key)) jump(url('mobile/user', 'index'));
                
                    $_SESSION['OAUTH']['KEY'] = $oauth_key;
                    $this->oauth = array('name' => $oauth['name'], 'party' => $party);
                    $error_model = new request_error_model();
                    $this->login_captcha = $error_model->check(get_ip(), $GLOBALS['cfg']['captcha_user_login']);
                    $this->compiler('oauth_bind.html');
                }
                else
                {
                    $this->prompt('error', '获取第三方授权登录身份标识失败!', url('mobile/user', 'login'), 5);
                }
            }
            else
            {
                $this->prompt('error', '第三方授权验证未通过!', url('mobile/user', 'login'), 5);
            }
        }
        else
        {
            jump(url('mobile/main', '404'));
        }
    }
    public function action_index(){
        $jump = empty($_GET["jump"])?'index':$_GET["jump"];
        // $appid=$this->oauth_obj->config['app_id'];
        $redirect_uri = baseurl().'/index.php?m=mobile&c=oauth&a=get_user_info&jump='.$jump;//urlencode();
        // $url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_base&state=1#wechat_redirect";
        $url = $this->oauth_obj->create_login_url($redirect_uri);
        header("Location:".$url);
    }
    public function action_get_user_info(){
        // $appid = $this->oauth_obj->config['app_id'];  
        // $secret = $this->oauth_obj->config['app_secret'];  
        $code = $_GET["code"];
        //第一步:取全局access_token
        // $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$secret";
        // $token = $this->getJson($url);
        $access_token = $this->oauth_obj->get_access_token();
         
        //第二步:取得openid
        // $oauth2Url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";
        // $oauth2 = $this->getJson($oauth2Url);
        $openid = $this->oauth_obj->get_openid($code);
          
        //第三步:根据全局access_token和openid查询用户信息  
        // $access_token = $token["access_token"];  
        // $openid = $oauth2['openid'];  
        // $get_user_info_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid&lang=zh_CN";
        // $userinfo = $this->getJson($get_user_info_url);
        $userinfo = $this->oauth_obj->get_user_info($access_token,$openid);
        //打印用户信息
        $user_model = new user_model();
        $user_model->weixin_login($userinfo);
        if ($_GET["jump"] == 'index'){
            jump(url('mobile/main', 'index'));
        }else{
            jump(url('mobile/user', 'index'));
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