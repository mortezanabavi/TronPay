<?php
error_reporting(0);

/*
 - Author : https://CheetahCloud.site - Morteza Nabavi
 - Module Designed For The : Tron TRC20 Payment
 - Mail : MortezaNabavi93@gmail.com
*/

use WHMCS\Database\Capsule;

function tronpay_req($url, array $parameters = []){
    return false;
}

function tronpay_MetaData(){
    return array(
        'DisplayName' => 'ماژول پرداخت آنلاین ترون برای WHMCS',
        'APIVersion'  => '1.0.0',
    );
}

function tronpay_config(){
    return array(
        'FriendlyName' => array(
            'Type'  => 'System',
            'Value' => 'پرداخت با ترون',
        ),
        'currencyType' => array(
            'FriendlyName' => 'واحد ارز',
            'Type'         => 'dropdown',
            'Options'      => array(
                'IRR' => 'ریال',
                'IRT' => 'تومان',
            ),
        ),
        'WalletAddress'   => array(
            'FriendlyName' => 'آدرس والت',
            'Type'         => 'text',
            'Size'         => '255',
            'Default'      => '',
            'Description'  => 'آدرس والت TRC20 ترون',
        ),
    );
}

function tronpay_activate() {
    try {
        Capsule::schema()
            ->create(
                'mod_tronpay_transactions',
                function ($table) {
                    /** @var \Illuminate\Database\Schema\Blueprint $table */
                    $table->increments('uuid');
                    $table->integer('user_id');
                    $table->integer('invoice_id');
                    $table->float('amount');
                    $table->string('wallet');
                    $table->string('privatekey');
                    $table->string('status');
                    $table->timestamps();
                }
            );
        return [
            'status' => 'success',
            'description' => 'module successfully activated',
        ];
    } catch (\Exception $e) {
        return [
            'status' => "error",
            'description' => 'Unable to create tables: ' . $e->getMessage(),
        ];
    }
}

function tronpay_link($params){
    $htmlOutput = '<form method="POST" action="modules/gateways/tronpay.php">';
    $htmlOutput .= '<input type="hidden" name="invoiceId" value="' . $params['invoiceid'] . '">';
    $htmlOutput .= '<input type="submit" id="tronpay" value="' . $params['langpaynow'] . ' "class="btn btn-green text-90"/>';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}

function get_trx_price() {
    $url = 'https://api.nobitex.ir/v2/orderbook/TRXIRT';
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($curl);
    if ($response === false) {
        die(curl_error($curl));
    }
    curl_close($curl);
    $data = json_decode($response, true);
    if (!isset($data['bids'][0][0])) {
        die("Error: Invalid response from API");
    }
    $result = intval($data['bids'][0][0]) / 10;
    return intval($result);
}

if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && is_numeric($_POST['invoiceId']) && is_numeric($_POST['check'])) {
    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';
    require_once dirname(__DIR__) . "/addons/Tronpay/vendor/autoload.php";
    tronpay_activate();
    if (isset($_SESSION['uid'])) {
        $fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        $solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        $eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        try {
            $tron = new \IEXBase\TronAPI\Tron($fullNode, $solidityNode, $eventServer);
        } catch (\IEXBase\TronAPI\Exception\TronException $e) {
            exit($e->getMessage());
        }
        $gatewayParams = getGatewayVariables('tronpay');
        $invoice = Capsule::table('tblinvoices')
            ->where('id', $_POST['invoiceId'])
            ->where('userid', $_SESSION['uid'])
            ->first();
        if (!$invoice) {
            die("Invoice not found");
        }
        else if ($invoice->status == 'Paid') {
            echo json_encode(['result' => true, 'status' => 200]);
            die();
        }
        $client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();
        $amount = ceil($invoice->total * ($gatewayParams['currencyType'] == 'IRT' ? 1 : 10));
        $transactionId = Capsule::table('mod_tronpay_transactions')
            ->where('user_id', $client->id)
            ->where('status', 'unpaid')
            ->where('invoice_id', $invoice->id)
            ->first();
        if (!$transactionId) {
            die("transaction not found");
        }
        $tron->setAddress($transactionId->wallet);
        $balance = $tron->getBalance(null, true);
        $balance = (float)$balance;
        if ($balance >= $transactionId->amount) {
            $tron->setPrivateKey($transactionId->privatekey);
            $transfer = $tron->send($gatewayParams['WalletAddress'], $balance-1.2);
            if (isset($transfer['txid'])) {
                Capsule::table('mod_tronpay_transactions')->where('id', $transactionId)->update([
                    'status'  => 'Paid',
                    'updated_at' => time(),
                ]);
                echo json_encode(['result' => true, 'status' => 200]);
            }
            else {
                echo json_encode(['result' => false, 'status' => 500]);
            }
        }
        else {
            echo json_encode(['result' => false, 'status' => 406]);
        }
    }
    else {
        echo json_encode(['result' => false, 'status' => 404]);
    }
}
else if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && is_numeric($_POST['invoiceId'])) {
    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';
    require_once dirname(__DIR__) . "/addons/Tronpay/vendor/autoload.php";
    tronpay_activate();
    if (is_numeric($_SESSION['uid'])) {
        $fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        $solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        $eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
        
        try {
            $tron = new \IEXBase\TronAPI\Tron($fullNode, $solidityNode, $eventServer);
        } catch (\IEXBase\TronAPI\Exception\TronException $e) {
            exit($e->getMessage());
        }
        $generateAddress = $tron->generateAddress();
        $address = $generateAddress->getAddress(true);
        $privatekey = $generateAddress->getPrivateKey();
        $publickey = $generateAddress->getPublicKey();
        $gatewayParams = getGatewayVariables('tronpay');
        $invoice = Capsule::table('tblinvoices')
            ->where('id', $_POST['invoiceId'])
            ->where('status', 'Unpaid')
            ->where('userid', $_SESSION['uid'])
            ->first();
    
        if (!$invoice) {
            die("Invoice not found");
        }
    
        $client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();
        $amount = ceil($invoice->total * ($gatewayParams['currencyType'] == 'IRT' ? 1 : 10));
	$trxPrice = get_trx_price();
        $amounttrx = round($amount/$trxPrice,2);
        $transactionId = Capsule::table('mod_tronpay_transactions')
        ->where('user_id', $client->id)
        ->where('status', 'unpaid')
        ->where('invoice_id', $invoice->id)
        ->where('amount', $amounttrx)
        ->first();
        if($transactionId) {
            $address = $transactionId->wallet;
            $amounttrx = $transactionId->amount;
            $privatekey = $transactionId->privatekey;
        }
        else {
            $transactionId = Capsule::table('mod_tronpay_transactions')
            ->insertGetId([
                'uuid'       => time()+$invoice->id,
                'user_id'    => $client->id,
                'invoice_id' => $invoice->id,
                'amount'     => $amounttrx,
                'wallet'     => $address,
                'privatekey' => $privatekey,
                'status'     => 'unpaid',
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        }
        $html = file_get_contents("tronpay/main.html");
        $html = str_replace('{amount}', number_format($amount), $html);
        $html = str_replace('{wallet}', $address, $html);
        $html = str_replace('{trxprice}', $trxPrice, $html);
        $html = str_replace('{trxamount}', $amounttrx, $html);
        $html = str_replace('{invoiceid}', $invoice->id, $html);
        echo $html;
        return;
    }
}
