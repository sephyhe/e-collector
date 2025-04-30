<?php

namespace Leochenftw\eCommerce\eCollector\Controller;
use PageController;
use SilverStripe\SiteConfig\SiteConfig;
use Leochenftw\eCommerce\eCollector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\Form;
use Leochenftw\Debugger;
use Leochenftw\eCommerce\eCollector\API\Paystation;
use Leochenftw\eCommerce\eCollector\Model\Order;
use SilverStripe\View\ArrayData;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use Leochenftw\eCommerce\eCollector\Model\Freight;
use SilverStripe\Security\Security;
use Page;
/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class CartController extends PageController
{
    public function index(HTTPRequest $request)
    {
        if ($this->request->isPost() && !empty($this->request->postVar('action'))) {
            $this->{'do_' . $this->request->postVar('action')}();
            $this->redirect($this->Link());
        }

        return parent::index($request);
    }

    public function getData()
    {

        $data = Page::create()->Data;

        $request = $this->request;

        if ($request->Param('second') == 'checkout') {
            $data['pagetype'] = 'Checkout';
            $data['type'] = 'Cart';
            $details = eCollector::get_cart($request->getVar('order_id'))->get_checkout_data();
            $data['title'] = $request->Param('Result') ? 'Order Details' : 'Checkout';
            $data['checkout'] = $details;
            $data['countries'] = $this->get_all_countries();
            $data['base_country'] = SiteConfig::current_site_config()->StoreCountry;
            $suggested_country = $request->getSession()->get('country');
            $suggested_country = empty($suggested_country) ? 'nz' : $suggested_country;
            if (empty($data['checkout']['shipping']['country'])) {
                $data['checkout']['shipping']['country']    =   $request->getSession()->get('country');
            }

            if (empty($data['checkout']['billing']['country'])) {
                $data['checkout']['billing']['country']    =   $request->getSession()->get('country');
            }
        } elseif (empty($request->Param('second'))) {
            $cart = eCollector::get_cart();
            $data['title'] = 'Shopping Cart';
            $data['cart'] = $cart ? $cart->Data : null;
            $member = Security::getCurrentUser();
            $data['can_discount'] = $member && $member->can_discount();
        }

        return $data;
    }

    private function get_all_countries()
    {
        $list       =   [];
        $zones      =   Config::inst()->get(Freight::class, 'allowed_countries');
        foreach ($zones as $zone => $countries) {
            foreach ($countries as $code => $country) {
                $list[$code]    =   $country;
            }
        }

        asort($list);

        return $list;
    }

    private function get_country()
    {
        return 'nz';
        $ip =   $_SERVER['REMOTE_ADDR'];
        // Debugger::inspect($ip);
        try {
            $client     =   new Client(['base_uri' => 'http://api.wipmania.com/']);
            $response   =   $client->request('GET',  $ip);
            $response   =   $response->getBody()->getContents();
            return strtolower($response);
        } catch (ClientException $e) {
            return 'nz';
        }
    }

    private function do_update()
    {
        $item = $this->getCart()->Items()->byID($this->request->postVar('id'));

        if ($this->request->postVar('qty') == 0) {
            $item->delete();
        } elseif ($item->Quantity != $this->request->postVar('qty')) {
            $item->Quantity =   $this->request->postVar('qty');
            $item->write();
        }
    }

    private function do_delete()
    {
        $item   =   $this->getCart()->Items()->byID($this->request->postVar('id'));
        $item->delete();
    }

    public function getCart()
    {
        return eCollector::get_cart();
    }

    public function Title()
    {
        $URIs   =   explode('/', ltrim($_SERVER['REQUEST_URI'], '/'));
        if (count($URIs) > 2) {
            $third  =   $URIs[count($URIs) - 1];
            $third  =   explode('?', $third)[0];
            return 'Payment ' . $third . ($third == 'cancel' ? 'led' : '');
        } elseif (count($URIs) > 1) {
            $second =   $URIs[count($URIs) - 1];
            $second =   explode('?', $second)[0];
            return ucwords($second);
        }

        return 'Cart' . ' | ' . SiteConfig::current_site_config()->Title;
    }

    public function Link($action = NULL)
    {
        return '/cart/';
    }

    public function init()
    {
        parent::init();
        $this->Form =   $this->CartForm();
    }

    private function CartForm()
    {
        $fields     =   new FieldList();
        $actions    =   new FieldList();

        $actions->push(FormAction::create('doCheckout', 'Checkout'));

        $form       =   new Form($this, 'ScriptPurchaseForm', $fields, $actions);

        $form->setFormMethod('POST')->setFormAction('/checkout');

        return $form;
    }
}
