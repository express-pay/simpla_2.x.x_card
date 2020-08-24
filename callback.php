<?php
chdir('../../');
require_once('api/Simpla.php');


require_once(dirname(__FILE__).'/ExpressPayCardView.php');


require_once(dirname(__FILE__).'/api/ExpressPayLog.php');
$logs = new ExpressPayLog();

require_once(dirname(__FILE__).'/api/ExpressPayHelper.php');


callback($logs);

function callback($logs){
	$logs->log_info('callback','start processing data from the server');

	$logs->log_info('callback','REQUEST - '.implode(',',$_REQUEST));
	

	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'notify')
		{
			callbackNotify($logs);
		}
		else{
			callbackPost($logs);
			return;
		}
	}
	else if($_SERVER['REQUEST_METHOD'] == 'GET')
	{
		if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'success')
		{
			callbackSuccess($logs);
		}
		else if(isset($_REQUEST['result']) && $_REQUEST['result'] == 'fail')
		{
			callbackFail($logs);
		}
	}
	else{

		header("HTTP/1.0 200 OK");
		$logs->log_error('callback', '$_SERVER["REQUEST_METHOD"] !== "POST"');
	}
}

function callbackPost($logs,$data)
{
	$url = $data['url'];
	$token = $data['token'];
	$accountNo = $data['accountNo'];
	$expiration = $data['Expiration'];
	$amount = $data['amount'];
	$currency = $data['currency'];
	$info = $data['info'];
	$return_url = $data['ReturnUrl'];
	$fail_url = $data['FailUrl'];
	$language = $data['Language'];
	$sessionTimeoutSecs = $data['SessionTimeoutSecs'];
	$expirationdate = '';

	if(!isset($url) || $url == '' ){
		$logs->log_error('callbackPost','$url is null');
		return;
	}

	$logs->log_info('callbackPost','retrieving data from a POST request; url - '.$url.'; accountNo - '.$accountNo.'; expiration - '.$expiration.'; amount - '.$amount
					.'; currency - '.$currency.'; info - '.$info.'; return_url - '.$return_url.' fail_url - '.$fail_url.'; language - '.$language
					.'; sessionTimeoutSecs - '.$sessionTimeoutSecs.'; expirationdate - '.$expirationdate);

	$response = addInvoice($url,$accountNo,$expiration,$amount,$currency,$info,$return_url,$fail_url,$language,
							$sessionTimeoutSecs,$expirationdate);


	print("<br/>");
	$logs->log_info('callbackPost','Received response from the server; response - '.$response);

	try {
		$response = json_decode($response, true);//Преобразование ответа из json в array
		$logs->log_info('callbackPost','converting data from json to array : RESPONSE - '.implode(',',$response));
	} catch(Exception $e) {
		$logs->log_error('callbackPost', "Fail to parse the server response; RESPONSE - " . $response);
		$logs->notify_fail($response);
	}

	if(isset($response['ErrorCode'])){
		$logs->log_error('callbackPost', 'error response; message - ' . $response->Error['Message']);
		fail($logs);
		return;
	}

	$simpla = new Simpla();
	
	$order          = $simpla->orders->get_order(intval($accountNo));
	$payment_method = $simpla->payment->get_payment_method($order->payment_method_id);
	$settings		= $simpla->payment->get_payment_settings($order->payment_method_id);

	
	$form_url = (($settings['test_mode'])? $settings['url_sandbox_api'] : $settings['url_api'])
	.'/v1/cardinvoices/'.$response['CardInvoiceNo'].'/payment?token='.$token;
	
	$logs->log_info('callbackPost','getting url - '.$form_url);

	$response = '';

	try {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $form_url);
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);
	} catch (Exception $e) {
		$logs->log_error_exception('callbackPost', 'Get response; RESPONSE - ' . $response, $e);
		fail($logs);
		return;
	}

	$logs->log_info('callbackPost', 'Get response; RESPONSE - ' . $response);
	try {
		$response = json_decode($response, true);
	} catch (Exception $e) {
		$logs->log_error_exception('callbackPost', 'Get response; RESPONSE - ' . $response, $e);
	}

	if(isset($response['ErrorCode'])){
		$logs->log_error('callbackPost', 'Error response; RESPONSE - ' . $response['ErrorMessage']);
		fail($logs);
		return ;
	}
	$returnUrl = str_replace("https://192.168.10.95","https://192.168.10.95:9090",$response['FormUrl']);

	$message = '<script>window.location.replace("'.$returnUrl.'");</script>'
				.'<p>Если вы не были перенаправлены нажмите <a href="'.$returnUrl.'">здесь</a></p>';

	success($message, $logs);
}

function addInvoice($url,$accountNo,$expiration='',$amount,$currency,$info,$return_url,$fail_url,$language='',
$sessionTimeoutSecs='',$expirationdate='')
{
	$logs = new ExpressPayLog();
	$amount = str_replace( " ", "",$amount);
	$logs->log_info('addInvoice','converting data amount: amount - '.$amount);
	$requestParams = array(
		"accountno" => $accountNo,                 
		"expiration" => '',             
		"amount" => $amount,                  
		"currency" => $currency,
		"info" => $info,      
		"returnurl" => $return_url,
		"failurl" => $fail_url,
		"language" => $language,
		"sessiontimeoutsecs" => $sessionTimeoutSecs,
		"expirationdate" => $expirationdate
	);
	$logs->log_info('addInvoice','converting data from json to an array : requestParams - '.implode(' , ',$requestParams));
	foreach($requestParams as $param){
		$param = (isset($param) ? $param : '');
	}
	return sendRequestPOST($url, $requestParams); 
}

function sendRequestPOST($url, $params) {
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function fail($logs)
{
	$logs->log_info('fail', 'starting fail function');

	//Вывести данные пользователю
	$message = '<div style="text-align:center;">
					<div style="color:#D1001D; font-size:20px; font-weight:bold; padding-bottom:15px;">При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</div>
				</div>';

	$logs->log_info('fail','display result');
	$expressPayView = new ExpressPayCardView();
	$data['message'] = $message;
	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);
	print $expressPayView->fetch();
}

function success($message, $logs)
{
	$logs->log_info('success', 'starting success function');

	//Вывести данные пользователю

	$logs->log_info('success','display result');
	$expressPayView = new ExpressPayCardView();
	$data['message'] = $message;
	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);
	print $expressPayView->fetch();

	

}

function callbackRequest($logs, $data, $simpla){
	$logs->log_info('callbackRequest','processing of notifications from the server');
	$cmdType = $data['CmdType'];
	$status    = $data['Status'];
	$accountNo = $data['AccountNo'];
	$invoiceNo = $data['InvoiceNo'];
	$amount = $data['Amount'];
	$created = $data['Created'];
	$service = $data['Service'];
	$payer = $data['Payer'];
	$address = $data['Address'];

	
	$logs->log_info('callbackRequest','Received POST response; CmdType - '.$cmdType.'; Status - '.$status.'; AccountNo - '.$accountNo
	.'; InvoiceNo - '.$invoiceNo.'; Amount - '.$amount.'; Created - '.$created.'; Service - '.$service.'; Payer - '.$payer.'; Address - '.$address);
	switch($cmdType)
	{
		case 1: 
				$order = $simpla->orders->get_order(intval($accountNo));
				if($order->paid != 1)
				{
					$simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
					$simpla->orders->close(intval($order->id));// Спишем товары
					$logs->log_info('callbackRequest','status processing "Paid"');
				}
				else
				{
					$logs->log_info('callbackRequest',"status don't change");
				}
				return;
		case 2: 
				$order = $simpla->orders->get_order(intval($accountNo));
				$simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
				$logs->log_info('callbackRequest','status processing "Canceled"');
				header("HTTP/1.0 200 OK");
				print $st= 'OK | the notice is processed';
				return;
		case 3: 
			break;
	}
	if(isset($status)){
		switch($status){
			case 1: //Ожидает оплату
				$order = $simpla->orders->get_order(intval($accountNo));
				$simpla->orders->update_order(intval($order->id), array('status'=>1));// Установим статус не оплачен
				$logs->log_info('callbackRequest','status processing "Pending payment" ');
				break;
			case 2: //Просрочен
				$logs->log_info('callbackRequest','status processing "Expired" ');
				break;
			case 3://Оплачен
				$order = $simpla->orders->get_order(intval($accountNo));
				if($order->paid != 1)
				{
					$simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
					$simpla->orders->close(intval($order->id));// Спишем товары
					$logs->log_info('callbackRequest','status processing "Paid"');
				}
				else
				{
					$logs->log_info('callbackRequest',"status don't change");
				}
				break;
			case 4: //Оплачен частично 
				$logs->log_info('callbackRequest','status processing "Partially paid"');
				break;
			case 5: // Отменен
				$order = $simpla->orders->get_order(intval($accountNo));
				$simpla->orders->update_order(intval($order->id), array('paid'=>0));// Установим статус не оплачен
				$logs->log_info('callbackRequest','status processing "Canceled"');
				break;
			default:
			header("HTTP/1.0 200 OK");
				print $st = 'FAILED | the notice is not processed'; //Ошибка в параметрах
				$logs->log_error('callbackRequest','FAILED | the notice is not processed; Status - '.$status);
				return;
		}
		header("HTTP/1.0 200 OK");
		print $st= 'OK | the notice is processed';
	}
	else
	{
		$logs->log_error('callbackRequest','POST not received');
		header("HTTP/1.0 200 OK");
		print $st = 'FAILED | the notice is not processed'; //Ошибка в параметрах
	}
}

function callbackSuccess($logs)
{

	$simpla = new Simpla();

	$accountNo = $_REQUEST['ExpressPayAccountNumber'];

	$order = $simpla->orders->get_order(intval($accountNo));
	if($order->paid != 1)
	{
		$simpla->orders->update_order(intval($order->id), array('paid'=>1));// Установим статус оплачен
		$simpla->orders->close(intval($order->id));// Спишем товары
		$logs->log_info('callbackSuccess','status processing "Paid"');
	}
	else
	{
		$logs->log_info('callbackSuccess',"status don't change");
	}

	$logs->log_info('callbackSuccess', 'starting success function');

	//Вывести данные пользователю

	$logs->log_info('callbackSuccess','display result');

	$message = '<div style="text-align:center;">
			<div style="color:#00a12d; font-size:20px; font-weight:bold;">Заказ номер '.$accountNo.' успешно оплачен.</div>
		</div>';

	$expressPayView = new ExpressPayCardView();
	$data['message'] = $message;
	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);
	print $expressPayView->fetch();
}

function callbackFail($logs)
{
	$logs->log_info('callbackFail', 'starting success function');

	//Вывести данные пользователю

	$logs->log_info('callbackFail','display result');

	$message = '<div style="text-align:center;">
			<div style="color:#D1001D; font-size:20px; font-weight:bold; padding-bottom:15px;">Произошла ошибка при оплате</div>
		</div>';

	$expressPayView = new ExpressPayCardView();
	$data['message'] = $message;
	//Вывод сообщения в шаблон
	$expressPayView->design->assign('data', $data);
	print $expressPayView->fetch();
}