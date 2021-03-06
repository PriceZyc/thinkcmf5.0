<?php
namespace app\user\controller;
use think\Db;
use cmf\controller\HomeBaseController;
class AiPayController extends HomeBaseController
{
    function create_noncestr() {
        $str = date('YmdHis').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 4);
        return $str;
    }
    public function pay(){
        ini_set('include_path','app/user/Controller/Alipay');
//        error_reporting(E_ERROR);
        require_once "Alipay/Submit.php";
        require_once "Alipay/Notify.php";
        $alipay_config=config('alipay_config');
        /**************************请求参数**************************/
        $payment_type = "1"; //支付类型 //必填，不能修改
        $notify_url = config('alipay.notify_url'); //服务器异步通知页面路径
        $return_url = config('alipay.return_url'); //页面跳转同步通知页面路径
        $seller_email = config('alipay.seller_email');//卖家支付宝帐户必填
        $out_trade_no = $this->create_noncestr();//商户订单号 通过支付页面的表单进行传递，注意要唯一！
        $subject = '丝袜';  //订单名称 //必填 通过支付页面的表单进行传递
        $total_fee = 1.0;   //付款金额  //必填 通过支付页面的表单进行传递
        $body = '白色透明丝袜';  //订单描述 通过支付页面的表单进行传递
        $show_url = 'http://localhost/user/ai_pay/pay.html';  //商品展示地址 通过支付页面的表单进行传递
        $anti_phishing_key = "";//防钓鱼时间戳 //若要使用请调用类文件submit中的query_timestamp函数
        $exter_invoke_ip = get_client_ip(); //客户端的IP地址
        /************************************************************/

        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "create_direct_pay_by_user",
            "partner" => trim($alipay_config['partner']),
            "payment_type"    => $payment_type,
            "notify_url"    => $notify_url,
            "return_url"    => $return_url,
            "seller_email"    => $seller_email,
            "out_trade_no"    => $out_trade_no,
            "subject"    => $subject,
            "total_fee"    => $total_fee,
            "body"            => $body,
            "show_url"    => $show_url,
            "anti_phishing_key"    => $anti_phishing_key,
            "exter_invoke_ip"    => $exter_invoke_ip,
            "_input_charset"    => trim(strtolower($alipay_config['input_charset']))
        );
        dump($parameter);
        //建立请求
        $alipaySubmit = new \AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestForm($parameter,"post", "确认");
        echo $html_text;

    }


    /******************************
    服务器异步通知页面方法
    其实这里就是将notify_url.php文件中的代码复制过来进行处理

     *******************************/
    function notifyurl(){
        ini_set('include_path','app/user/Controller/Alipay');
//        error_reporting(E_ERROR);
        require_once "Alipay/Submit.php";
        require_once "Alipay/Notify.php";
        //这里还是通过C函数来读取配置项，赋值给$alipay_config
        $alipay_config=config('alipay_config');
        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($alipay_config);
        $verify_result = $alipayNotify->verifyNotify();
        if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            $out_trade_no   = $_POST['out_trade_no'];      //商户订单号
            $trade_no       = $_POST['trade_no'];          //支付宝交易号
            $trade_status   = $_POST['trade_status'];      //交易状态
            $total_fee      = $_POST['total_fee'];         //交易金额
            $notify_id      = $_POST['notify_id'];         //通知校验ID。
            $notify_time    = $_POST['notify_time'];       //通知的发送时间。格式为yyyy-MM-dd HH:mm:ss。
            $buyer_email    = $_POST['buyer_email'];       //买家支付宝帐号；
            $parameter = array(
                "out_trade_no"     => $out_trade_no, //商户订单编号；
                "trade_no"     => $trade_no,     //支付宝交易号；
                "total_fee"     => $total_fee,    //交易金额；
                "trade_status"     => $trade_status, //交易状态
                "notify_id"     => $notify_id,    //通知校验ID。
                "notify_time"   => $notify_time,  //通知的发送时间。
                "buyer_email"   => $buyer_email,  //买家支付宝帐号；
            );
            if($_POST['trade_status'] == 'TRADE_FINISHED') {
                //
            }else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                if(!$this->checkorderstatus($out_trade_no)){
                    $this->orderhandle($parameter);
                //进行订单处理，并传送从支付宝返回的参数；
            }
            }
            echo "success";        //请不要修改或删除
        }else {
            //验证失败
            echo "fail";
        }
    }


    /*
        页面跳转处理方法；
        这里其实就是将return_url.php这个文件中的代码复制过来，进行处理；
        */
    function returnurl(){
        ini_set('include_path','app/user/Controller/Alipay');
//        error_reporting(E_ERROR);
        require_once "Alipay/Submit.php";
        require_once "Alipay/Notify.php";
        //这里还是通过C函数来读取配置项，赋值给$alipay_config
        $alipay_config=config('alipay_config');
        $alipayNotify = new \AlipayNotify($alipay_config);//计算得出通知验证结果
        $verify_result = $alipayNotify->verifyReturn();
        if($verify_result) {
            //验证成功
            //获取支付宝的通知返回参数，可参考技术文档中页面跳转同步通知参数列表
            $out_trade_no   = $_GET['out_trade_no'];      //商户订单号
            $trade_no       = $_GET['trade_no'];          //支付宝交易号
            $trade_status   = $_GET['trade_status'];      //交易状态
            $total_fee      = $_GET['total_fee'];         //交易金额
            $notify_id      = $_GET['notify_id'];         //通知校验ID。
            $notify_time    = $_GET['notify_time'];       //通知的发送时间。
            $buyer_email    = $_GET['buyer_email'];       //买家支付宝帐号；

            $parameter = array(
                "out_trade_no"     => $out_trade_no,      //商户订单编号；
                "trade_no"     => $trade_no,          //支付宝交易号；
                "total_fee"      => $total_fee,         //交易金额；
                "trade_status"     => $trade_status,      //交易状态
                "notify_id"      => $notify_id,         //通知校验ID。
                "notify_time"    => $notify_time,       //通知的发送时间。
                "buyer_email"    => $buyer_email,       //买家支付宝帐号
            );

            if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
                if(!$this->checkorderstatus($out_trade_no)){
                    $this->orderhandle($parameter);  //进行订单处理，并传送从支付宝返回的参数；
                }
                $this->redirect(C('alipay.successpage'));//跳转到配置项中配置的支付成功页面；
            }else {
                echo "trade_status=".$_GET['trade_status'];
                $this->redirect(C('alipay.errorpage'));//跳转到配置项中配置的支付失败页面；
            }
        }else {
            //验证失败
            //如要调试，请看alipay_notify.php页面的verifyReturn函数
            echo "支付失败！";
        }
    }


    //在线交易订单支付处理函数
    //函数功能：根据支付接口传回的数据判断该订单是否已经支付成功；
    //返回值：如果订单已经成功支付，返回true，否则返回false；
    function checkorderstatus($ordid){
        $Ord=M('Orderlist');
        $ordstatus=$Ord->where('ordid='.$ordid)->getField('ordstatus');
        if($ordstatus==1){
            return true;
        }else{
            return false;
        }
    }

    //处理订单函数
    //更新订单状态，写入订单支付后返回的数据
    function orderhandle($parameter){
        $ordid=$parameter['out_trade_no'];
        $data['payment_trade_no']      =$parameter['trade_no'];
        $data['payment_trade_status']  =$parameter['trade_status'];
        $data['payment_notify_id']     =$parameter['notify_id'];
        $data['payment_notify_time']   =$parameter['notify_time'];
        $data['payment_buyer_email']   =$parameter['buyer_email'];
        $data['ordstatus']             =1;
        $Ord=M('Orderlist');
        $Ord->where('ordid='.$ordid)->save($data);
    }
}




