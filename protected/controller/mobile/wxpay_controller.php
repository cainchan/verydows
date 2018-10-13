<?php
use Yansongda\Pay\Pay;
use Yansongda\Pay\Log;

class wxpay_controller extends general_controller
{
    
    function __construct(){
        parent::__construct();
        $this->config = $this->_get_config();
    }
    private function _get_config()
    {
        $pcode = 'wxpay';
        $payment_model = new payment_method_model();
        if($payment = $payment_model->find(array('pcode' => $pcode, 'enable' => 1), null, 'params'))
        {
            $plugin = plugin::instance('payment', $pcode, array($payment['params']));
            $config = $plugin->_get_config();
            $config['notify_url'] = baseurl(). '/m/wxpay/notify.html';
            $config['log'] = [ // optional
                'file' => 'log/wechat.log',
                'level' => 'debug', // 建议生产环境等级调整为 info，开发环境为 debug
                'type' => 'single', // optional, 可选 daily.
                'max_file' => 30, // optional, 当 type 为 daily 时有效，默认 30 天
            ];
            return $config;
        }
        else
        {
            jump(url('mobile/main', '400'));
        }
    }

    public function action_index()
    {
        $order_id = request('order_id','');
        $order_model = new order_model();
        $this->order = $order_model->find(array('order_id' => $order_id));
        $order = [
            'out_trade_no' => $order_id,
            'total_fee' => intval($this->order['order_amount']*100), // **单位：分**
            'body' => "{$GLOBALS['cfg']['site_name']}订单-{$order_id}",
            'openid' => $_SESSION['USER']['OPEN_ID'],
        ];

        $pay = Pay::wechat($this->config)->mp($order);
        $this->order_url = baseurl().'/m/order/view.html?id='.$order_id;
        $this->pay = $pay;
        $this->status = 'success';
        $this->message = '请完成支付';
        $this->compiler('paying.html');
    }

    public function action_notify()
    {
        Log::debug('Wechat notify1', $_GET);
        $pay = Pay::wechat($this->config);

        try{
            $data = $pay->verify(); // 是的，验签就这么简单！
            Log::debug('Wechat notify', $data->all());
            if ($data->return_code == 'SUCCESS'){
                $order_model = new order_model();
                $order_model->update(
                    array('order_id' => $data->out_trade_no), 
                    array('order_status' => 2,
                        'thirdparty_trade_id' => $data->transaction_id,
                        'payment_date' => time(),
                    )
                );
            }
        } catch (Exception $e) {
            Log::error('Wechat notify error', (array)$e);
            $e->getMessage();
        }
        
        return $pay->success()->send();// laravel 框架中请直接 `return $pay->success()`
    }
}