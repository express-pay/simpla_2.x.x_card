<?php 
require_once('api/Simpla.php');
require_once(dirname(__FILE__).'/api/ExpressPayHelper.php');
require_once(dirname(__FILE__).'/api/ExpressPayLog.php');

class ExpressPayCard extends Simpla
{
    
    public function checkout_form($order_id, $button_text = null)
	{  
        $logs = new ExpressPayLog();

        $logs->log_info('checkout_form','initialization checkout_form');
        if(empty($button_text)){
            $button_text = 'Перейти к оплате';
        }

        $order          = $this->orders->get_order(intval($order_id));
        $payment_method = $this->payment->get_payment_method($order->payment_method_id);
        $settings		= $this->payment->get_payment_settings($order->payment_method_id);	
        $purchases      = $this->orders->get_purchases(array('order_id'=>$order_id));

        $token          = $settings['token']; //API-ключ производителя услуг
        $serviceId      = $settings['service_id']; //Номер услуги производителя услуг
        $url            = (($settings['test_mode'])? $settings['url_sandbox_api'] : $settings['url_api'])
                            .'/v1/web_cardinvoices';
                        //.'/v1/cardinvoices?token='.$token;

        $accountNo      = intval($order_id);//Номер лицевого счета
        $amount         = str_replace( " ", "",$this->money->convert($order->total_price, $payment_method->currency_id, true));//	Сумма счета на оплату. Разделителем дробной и целой части является символ запятой
        $currency       = (date('y') > 16 || (date('y') >= 16 && date('n') >= 7)) ? '933' : '974';//Код валюты

        $info           = 'Оплата заказа номер '.$order_id.' в интернет-магазине '.$this->config->root_url;

        $logs->log_info('checkout_form','getting string info; info - '.$info);

        $return_url = $this->config->root_url.'/payment/ExpressPayCard/callback.php?result=success&accountno='.$accountNo;//Адрес для перенаправления пользователя в случае успешной оплаты
        $fail_url   = $this->config->root_url.'/payment/ExpressPayCard/callback.php?result=fail&accountno='.$accountNo;//Адрес для перенаправления пользователя в случае неуспешной оплаты

        $sessionTimeoutSecs = $settings['session_timeout_secs'];

        /*
            ServiceId 	Integer 	Номер услуги
            AccountNo 	String(30) 	Номер лицевого счета
            Amount 	Decimal(19,2) 	Сумма счета на оплату. Разделителем дробной и целой части является символ запятой
            Currency 	Integer 	Код валюты
            Signature 	String 	Цифровая подпись
            ReturnType 	String 	Тип ответа. Может принимать два значения:

                Redirect - перенаправляет пользователя по заданным адресам ReturnUrl или FailUrl. При выборе данного типа ответа
                Json - возвращает результат операции в формате json

            ReturnUrl 	String 	Адрес, на который происходит перенаправление после успешного выставления счета
            FailUrl 	String 	Адрес, на который происходит перенаправление при ошибке выставления счета
            Expiration 	String(8) 	Дата истечения срока действия выставлена счета на оплату. Формат - yyyyMMdd
            Info 	String(1024) 	Назначение платежа
            ReturnInvoiceUrl 	Integer 	Вернуть в ответе публичную ссылку на счет
            0 – нет, 1 – да (0 - по умолчанию)
            (Примечание: только для случая, когда ReturnType равен 2 (Json)) 
        */

        $secret_word = $settings['secret_key'];//Секретное слово для подписи счетов (Задается в панели express-pay.by)
        $logs->log_info('checkout_form','getting a secret word; secret_word - '.$secret_word);

        $request_params = array(
            'ServiceId'         => $serviceId,
            'AccountNo'         => $accountNo,
            'Amount'            => $amount,
            'Currency'          => $currency,
            'ReturnType'        => 'redirect',
            'ReturnUrl'         => $this->config->root_url.'/payment/ExpressPayCard/callback.php?result=success',
            'FailUrl'           => $this->config->root_url.'/payment/ExpressPayCard/callback.php?result=fail',
            'Expiration'        => '',
            'Info'              => $info
        );

        $request_params['Signature'] = $this->compute_signature_add_invoice($request_params, $token, $secret_word);

        $action = $this->config->root_url.'/payment/ExpressPayCard/callback.php';
       
        $logs->log_info('checkout_form','getting a action; action - '.$action);
        
        $button         = '<form method="POST" action="'.$url.'">';

        foreach($request_params as $key => $value)
        {
            $button .= "<input type='hidden' name='$key' value='$value'/>";
        }

        $button .= '<input type="submit" class="checkout_button" name="submit_button" value="'.$button_text.'" />';
        $button .= '</form>';

        $logs->log_info('checkout_form','getting a button; button - '.$button);
        
        return $button;
    }

    function amountFormat($amount){

        $amount_arr = explode(" ",$amount);
        $amount = '';
        foreach($amount_arr as $a){
            $amount .= $a;
        }
        return $amount;
    
    }

    private function compute_signature_add_invoice($request_params, $token, $secret_word) {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            "serviceid",
            "accountno",
            "expiration",
            "amount",
            "currency",
            "info",
            "returnurl",
            "failurl",
            "language",
            "sessiontimeoutsecs",
            "expirationdate",
            "returntype"
        );

        $result = $token;

        foreach ($api_method as $item)
            $result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';

        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

        return $hash;
    }
}
?>