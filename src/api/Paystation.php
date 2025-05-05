<?php
namespace Leochenftw\eCommerce\eCollector\API;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Leochenftw\Debugger;

class Paystation
{
    public static function initiate($amount, $ref)
    {
        $gateway_endpoint   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'API')['Paystation'];
        $settings           =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'GatewaySettings')['Paystation'];


        $params             =   $settings;

        $endpoint           =   $gateway_endpoint;
        $params['pstn_am']  =   $amount * 100;
        $params['pstn_mr']  =   $ref;
        $params['pstn_ms']  =   sha1(mt_rand() . '-' . microtime(true) * 1000 . '-' . session_id());
        $params['pstn_du']  =   Director::absoluteBaseURL() . '/e-collector/paystation-complete';
        $params['pstn_dp']  =   Director::absoluteBaseURL() . '/e-collector/paystation-complete';
        $params['pstn_cu']  =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'DefaultCurrency');
        $params['pstn_rf']  =   'JSON';

        // if (!empty($register_future_pay)) {
        //     $params['pstn_fp']      =   't';
        //     if (empty($immediate_future_pay)) {
        //         unset($params['pstn_am']);
        //         $params['pstn_fs']  =   't';
        //     }
        // }

        $time               =   time();
        $hmac               =   $params['pstn_HMAC'];

        unset($params['pstn_HMAC']);

        $query_params       =   http_build_query($params);

        $params             =   [
                                    'pstn_HMACTimestamp'    =>  $time,
                                    'pstn_HMAC'             =>  hash_hmac('sha512', "{$time}paystation$query_params", $hmac)
                                ];

        $url = $gateway_endpoint . '?' . http_build_query($params);

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST'
            ]
        ];

        if ($query_params) {
            $options['http']['content'] = $query_params;
            $options['http']['header'] .= "Content-Length: " . strlen($query_params) . "\r\n";
        }

        $response = file_get_contents($url, false, stream_context_create($options));
        
        return $response;
    }

    public static function process($amount, $ref)
    {
        $response   =   self::initiate($amount, $ref);
        return json_decode($response,TRUE);
    }

    public static function fetch($token)
    {
        $endpoint   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'API')['PaystationLookup'];
        $settings   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'GatewaySettings')['Paystation'];


        $ch         =   curl_init($endpoint);
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query(['pi' => $settings['pstn_pi'], 'ti' => $token]));
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response   =   curl_exec( $ch );

        curl_close ($ch);

        $xml        =   simplexml_load_string($response);
        $json       =   json_encode($xml);

        return json_decode($json,TRUE);
    }

    // private function lookupTransaction($transactionId) {
    //     $result = $this->post($this->lookupURL, ['pi' => $this->paystationId, 'ti' => $transactionId]);
    //     $xml = new SimpleXMLElement($result);
    //     $txn = new Transaction();
    //     if (isset($xml->LookupResponse->PaystationTransactionID) && $xml->LookupResponse->PaystationTransactionID == $transactionId) {
    //         $txn->transactionId = "{$xml->LookupResponse->PaystationTransactionID}";
    //         $txn->amount = "{$xml->LookupResponse->PurchaseAmount}";
    //         $txn->transactionTime = "{$xml->LookupResponse->TransactionTime}";
    //         $txn->hasError = false;
    //         $txn->errorCode = "{$xml->LookupResponse->PaystationErrorCode}";
    //         $txn->errorMessage = "{$xml->LookupResponse->PaystationErrorMessage}";
    //         $txn->cardType = "{$xml->LookupResponse->CardType}";
    //         $txn->merchantSession = "{$xml->LookupResponse->MerchantSession}";
    //         $txn->merchantReference = "{$xml->LookupResponse->MerchantReference}";
    //         $txn->requestIp = "{$xml->LookupStatus->RemoteHostAddress}";
    //         $txn->timeout = isset($xml->LookupResponse->Timeout) ? "{$xml->LookupResponse->Timeout}" == 'Y' : null;
    //         if ($txn->errorCode == '') {
    //             $txn->errorCode = -1;
    //         }
    //         if ($txn->timeout) {
    //             $txn->hasError = true;
    //             $txn->errorMessage = 'Payment link has expired.';
    //         }
    //         $this->db->save($txn);
    //         return $txn;
    //     }
    //     elseif (isset($xml->LookupStatus)) { // There was an error calling the API.
    //         $txn->transactionId = $transactionId;
    //         $txn->hasError = true;
    //         $txn->errorCode = -1;
    //         $txn->lookupErrorCode = "{$xml->LookupStatus->LookupCode}";
    //         $txn->errorMessage = "{$xml->LookupStatus->LookupMessage}";
    //         $this->db->save($txn); // Save the failed lookup with the original error message.
    //         // The lookup message can contain your account ID. Hide that from the user.
    //         $txn->errorMessage = "(Lookup Error Code: {$xml->LookupStatus->LookupCode}) An error occurred while looking up transaction details.";
    //         // Show a more useful message for debugging purposes.
    //         if ($this->testMode) {
    //             $txn->errorMessage .= " Please check that the correct HMAC key is set in your config. Paystation Error Message: {$xml->LookupStatus->LookupMessage}";
    //         }
    //         return $txn;
    //     }
    //     return null;
    // }
}
