<?php

namespace Leochenftw\eCommerce\eCollector\API;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Leochenftw\Debugger;

class DPS
{
    public static function initiate($amount, $ref)
    {
        $endpoint   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'API')['DPS'];
        $settings   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'GatewaySettings')['DPS'];
        $id         =   $settings['ID'];
        $key        =   $settings['Key'];
        $currency   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'DefaultCurrency');

        $request    =   '<GenerateRequest>
                            <PxPayUserId>' . $id . '</PxPayUserId>
                            <PxPayKey>' . $key . '</PxPayKey>
                            <TxnType>Purchase</TxnType>
                            <AmountInput>' . $amount . '</AmountInput>
                            <CurrencyInput>' . $currency . '</CurrencyInput>
                            <MerchantReference>' . $ref . '</MerchantReference>
                            <UrlSuccess>' . Director::absoluteBaseURL() . '/e-collector/dps-complete</UrlSuccess>
                            <UrlFail>' . Director::absoluteBaseURL() . '/e-collector/dps-complete</UrlFail>
                        </GenerateRequest>';

        $ch         =   curl_init($endpoint);
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response   =   curl_exec( $ch );

        curl_close ($ch);

        return $response;
    }

    public static function process($amount, $ref)
    {
        $response   =   self::initiate($amount, $ref);
        $xml        =   simplexml_load_string($response);
        $json       =   json_encode($xml);

        return json_decode($json,TRUE);
    }

    public static function fetch($token)
    {
        //00001100050427380b31937aaa42d259
        $endpoint   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'API')['DPS'];
        $settings   =   Config::inst()->get('Leochenftw\eCommerce\eCollector', 'GatewaySettings')['DPS'];
        $id         =   $settings['ID'];
        $key        =   $settings['Key'];

        $request    =   '<ProcessResponse>
                            <PxPayUserId>' . $id . '</PxPayUserId>
                            <PxPayKey>' . $key . '</PxPayKey>
                            <Response>' . $token . '</Response>
                        </ProcessResponse>';

        $ch         =   curl_init($endpoint);
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response   =   curl_exec( $ch );

        curl_close ($ch);

        $xml        =   simplexml_load_string($response);
        $json       =   json_encode($xml);

        return json_decode($json,TRUE);

    }
}
