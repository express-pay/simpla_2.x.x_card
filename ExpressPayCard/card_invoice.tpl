{* ExpressPay(Интернет-эквайринг) *}

{if $data.status == 'payment' }
    <div style="font-size:16px;">
        <h2><b style="color:#00a12d;">Счет добавлен в систему</b></h2>
        <h3><b>Номер вашего заказа: {$data.order_id}</b></h3>
        <h3><a href=\"{$data.form_url}\">Перейти к оплате</a></h3>
    </div>
{elseif $data.status == 'success'}
    <div style="font-size:16px;">
        <h2><b style="color:#00a12d;">Оплата прошла успешно</b></h2>
    </div>
{else}
    <div style="color:#D1001D; font-size:20px; font-weight:bold; padding-bottom:15px;">
        {$data.message}
    </div>
{/if}