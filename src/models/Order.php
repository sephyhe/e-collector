<?php

namespace Leochenftw\eCommerce\eCollector\Model;
use Leochenftw\eCommerce\eCollector;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Security\Security;
use Leochenftw\eCommerce\eCollector\Model\OrderItem;
use Leochenftw\eCommerce\eCollector\Model\Customer;
use Leochenftw\eCommerce\eCollector\Payment\Payment;
use Leochenftw\Debugger;
use SilverStripe\Core\Config\Config;
use Leochenftw\eCommerce\eCollector\API\DPS;
use Leochenftw\eCommerce\eCollector\API\Poli;
use Leochenftw\eCommerce\eCollector\API\Paystation;
use Leochenftw\eCommerce\eCollector\Model\Freight;
use SilverStripe\Control\Cookie;
use Konekt\PdfInvoice\InvoicePrinter;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Director;
use Leochenftw\Grid;
use Dynamic\CountryDropdownField\Fields\CountryDropdownField;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class Order extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'ECollectorOrder';
    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'MerchantReference'     =>  'Varchar(64)',
        'Status'                =>  'Enum("Pending,Invoice Pending,Debit Pending,Payment Received,Shipped,Cancelled,Refunded,Completed")',
        'AnonymousCustomer'     =>  'Varchar(128)',
        'TotalAmount'           =>  'Currency',
        'TotalWeight'           =>  'Decimal',
        'PayableTotal'          =>  'Currency',
        'Email'                 =>  'Varchar(256)',
        'Phone'                 =>  'Varchar(128)',
        'ShippingFirstname'     =>  'Varchar(128)',
        'ShippingSurname'       =>  'Varchar(128)',
        'ShippingAddress'       =>  'Text',
        'ShippingOrganisation'  =>  'Varchar(128)',
        'ShippingApartment'     =>  'Varchar(64)',
        'ShippingSuburb'        =>  'Varchar(128)',
        'ShippingTown'          =>  'Varchar(128)',
        'ShippingRegion'        =>  'Varchar(128)',
        'ShippingCountry'       =>  'Varchar(128)',
        'ShippingPostcode'      =>  'Varchar(128)',
        'ShippingPhone'         =>  'Varchar(128)',
        'SameBilling'           =>  'Boolean',
        'BillingFirstname'      =>  'Varchar(128)',
        'BillingSurname'        =>  'Varchar(128)',
        'BillingAddress'        =>  'Text',
        'BillingOrganisation'   =>  'Varchar(128)',
        'BillingApartment'      =>  'Varchar(64)',
        'BillingSuburb'         =>  'Varchar(128)',
        'BillingTown'           =>  'Varchar(128)',
        'BillingRegion'         =>  'Varchar(128)',
        'BillingCountry'        =>  'Varchar(128)',
        'BillingPostcode'       =>  'Varchar(128)',
        'BillingPhone'          =>  'Varchar(128)',
        'Comment'               =>  'Text',
        'TrackingNumber'        =>  'Varchar(128)'
    ];

    private static $indexes = [
        'MerchantReference'     =>  true,
        'MerchantReference'     =>  [
                                        'type'      =>  'index',
                                        'columns'   =>  ['MerchantReference']
                                    ]
    ];

    private static $cascade_deletes = [
        'Items',
        'Payments'
    ];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'ID'                    =>  'ID',
        'ItemCount'             =>  'Item(s)',
        'Status'                =>  'Status'
    ];

    /**
     * Defines a default list of filters for the search context
     * @var array
     */
    private static $searchable_fields = [
        'MerchantReference',
        'Status'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'Customer'  =>  Customer::class,
        'Freight'   =>  Freight::class
    ];

    /**
     * Default sort ordering
     * @var array
     */
    private static $default_sort = ['ID' => 'DESC'];

    public function populateDefaults()
    {
        $this->SameBilling  =   true;
        $member             =   Security::getCurrentUser();
        $cookie             =   Cookie::get('eCollectorCookie');
        if (empty($cookie)) {
            $cookie =   session_id();
            Cookie::set('eCollectorCookie', $cookie, $expiry = 30);
        }
        if (!empty($member) && $member->ClassName == Customer::class) {
            $this->CustomerID           =   $member->ID;
        } elseif (empty($member)) {
            $this->AnonymousCustomer   =   $cookie;
        } else {
            $this->AnonymousCustomer   =   'Admin_' . $cookie;
        }

        $this->MerchantReference        =   sha1(md5(round(microtime(true) * 1000) . '-' . session_id()));
    }

    /**
     * Has_many relationship
     * @var array
     */
    private static $has_many = [
        'Items'     =>  OrderItem::class,
        'Payments'  =>  Payment::class
    ];

    public function getSuccessPayment()
    {
        if ($this->exists() && $this->Payments()->exists()) {
            return $this->Payments()->filter(['Status' => 'Success'])->first();
        }

        return null;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields =   parent::getCMSFields();

        if ($this->exists()) {
            $items  =   $fields->fieldByName('Root.Items.Items');

            $fields->removeByName([
                'Items'
            ]);

            $fields->addFieldToTab(
                'Root.OrderItems',
                Grid::make('Items', 'Order Items', $this->Items(), false, 'GridFieldConfig_RecordViewer')
            );
        }

        $fields->addFieldsToTab(
            'Root.Shipping',
            [
                TextField::create('ShippingFirstname', 'First Name'),
                TextField::create('ShippingSurname', 'Surname'),
                TextField::create('ShippingOrganisation', 'Organisation'),
                TextField::create('ShippingApartment', 'Apartment'),
                TextareaField::create('ShippingAddress', 'Address'),
                TextField::create('ShippingSuburb', 'Suburb'),
                TextField::create('ShippingTown', 'Town'),
                TextField::create('ShippingRegion', 'Region'),
                CountryDropdownField::create('ShippingCountry', 'Country')->setEmptyString('- select one -'),
                TextField::create('ShippingPostcode', 'Postcode'),
                TextField::create('ShippingPhone', 'Phone'),
            ]
        );

        $fields->addFieldsToTab(
            'Root.Billing',
            [
                TextField::create('BillingFirstname', 'First Name'),
                TextField::create('BillingSurname', 'Surname'),
                TextField::create('BillingOrganisation', 'Organisation'),
                TextField::create('BillingApartment', 'Apartment'),
                TextareaField::create('BillingAddress', 'Address'),
                TextField::create('BillingSuburb', 'Suburb'),
                TextField::create('BillingTown', 'Town'),
                TextField::create('BillingRegion', 'Region'),
                CountryDropdownField::create('BillingCountry', 'Country')->setEmptyString('- select one -'),
                TextField::create('BillingPostcode', 'Postcode'),
                TextField::create('BillingPhone', 'Phone'),
            ]
        );

        $fields->addFieldsToTab(
            'Root.Freight & Tracking',
            [
                $fields->fieldByName('Root.Main.FreightID')->setTitle('Freight Provider'),
                $tracking_field =   TextField::create('TrackingNumber', 'Tracking Number')
            ]
        );

        if (empty($this->TrackingNumber) || empty($this->FreightID)) {
            $tracking_field->setDescription('To send tracking number to the customer, please choose a freight provide, fill the tracking number, and then Apply Changes. <br />You will see the button after page refresh');
        }

        $frozen =   $fields->fieldByName('Root.Main.Status')->performReadonlyTransformation();
        $fields->replaceField('Status', $frozen);

        return $fields;
    }

    protected function prep_pdf()
    {
        $siteconfig =   SiteConfig::current_site_config();
        $payment    =   $this->getSuccessPayment();

        $invoice = new InvoicePrinter();

        $logo = Config::inst()->get('StoreLogo','filePath');
        if ($logo && file_exists(getcwd() . $logo)) {
            try {
                $invoice->setLogo(getcwd() . $logo);   //logo image path
            } catch (\DivisionByZeroError $e) {
                // swallow error silently
            } catch(\ErrorException $e) {
                Injector::inst()->get(LoggerInterface::class)->error($e);
                // swallow error silently
            } catch(\Exception $e) {
                Injector::inst()->get(LoggerInterface::class)->error($e);
                // swallow error silently
            }
        }

        $invoice->setColor("#000000");      // pdf color scheme
        $invoice->setType("Sale Invoice");    // Invoice Type
        $invoice->setReference("Invoice#" . $this->ID);   // Reference
        $invoice->setDate(date('d/m/Y',time()));   //Billing Date
        $invoice->setTime(date('h:i:s A',time()));   //Billing Time

        $billing_from   =   [];
        if (!empty($siteconfig->TradingName)) {
            $billing_from[] =   $siteconfig->TradingName;
        }

        if (!empty($siteconfig->GST)) {
            $billing_from[] =   'GST Number: ' . $siteconfig->GST;
        }

        if (!empty($siteconfig->StoreLocation)) {
            $chunks         =   explode("\n", $siteconfig->StoreLocation);
            $billing_from   =   array_merge($billing_from, $chunks);
        }

        if (!empty($siteconfig->ContactEmail)) {
            $billing_from[] =   $siteconfig->ContactEmail . (
                !empty($siteconfig->ContactNumber) ?
                (', ' . $siteconfig->ContactNumber) : ''
            );
        }

        $billing_to         =   [];
        $billing_to[]       =   trim((
            !empty($this->BillingFirstname) ?
            $this->BillingFirstname : '') . ' ' . (!empty($this->BillingSurname) ? $this->BillingSurname : ''));

        if (!empty($this->BillingOrganisation)) {
            $billing_to[]   =   $this->BillingOrganisation;
        }

        if (!empty($this->BillingApartment)) {
            $billing_to[]   =   $this->BillingApartment;
        }

        if (!empty($this->BillingAddress)) {
            $billing_to[]   =   $this->BillingAddress;
        }

        if (!empty($this->BillingSuburb)) {
            $billing_to[]   =   $this->BillingSuburb;
        }

        if (!empty($this->BillingTown)) {
            $billing_to[]   =   $this->BillingTown . (!empty($this->BillingRegion) ? (', ' . $this->BillingRegion) : '');
        }

        if (!empty($this->BillingCountry)) {
            $billing_to[]   =   $this->BillingCountry . (!empty($this->BillingPostcode) ? (', ' . $this->BillingPostcode) : '');
        }

        $billing_to[]   =   $this->Email . (
            !empty($this->Phone) ?
            (', ' . $this->Phone) :
            (!empty($this->BillingPhone) ?
            (', ' . $this->BillingPhone) : '')
        );

        $size           =   count($billing_from) > count($billing_to) ? count($billing_from) : count($billing_to);

        $billing_from   =   array_pad($billing_from, $size, '');
        $billing_to     =   array_pad($billing_to, $size, '');

        $invoice->setFrom($billing_from);
        $invoice->setTo($billing_to);

        $this->extend('createInvoiceRows', $invoice);

        if ($payment) {
            $invoice->addBadge("Payment Received");
        } else {
            $invoice->addBadge("Payment Outstanding");
            $invoice->addTitle("Cheque payment");
        }

        $this->extend('createInvoiceParagraph', $invoice);

        $invoice->setFooternote(SiteConfig::current_site_config()->Title);

        return $invoice;
    }

    public function download_invoice()
    {
        $invoice    =   $this->prep_pdf();
        $siteconfig =   SiteConfig::current_site_config();

        return  $invoice->render($siteconfig->TradingName . ' Invoice #' . $this->ID . '.pdf', 'D');
    }

    public function send_invoice()
    {
        $siteconfig =   SiteConfig::current_site_config();
        $invoice    =   $this->prep_pdf();
        $str        =   $invoice->render($siteconfig->TradingName . ' Invoice #' . $this->ID . '.pdf','S');
        $from       =   Config::inst()->get(Email::class, 'noreply_email');
        $to         =   $this->Email;

        $customer_sent_flag =   ['sent' => false];
        $this->extend('SendCustomerEmail', $from, $to, $str, $customer_sent_flag);

        if (!$customer_sent_flag['sent']) {
            $subject    =   $siteconfig->Title . ': order invoice #' . $this->ID;
            $email      =   new Email($from, $to, $subject);
            $email->setBody('Hi, <br /><br />Please find your order invoice in the attachment.<br /><br />Kind regards<br />' . $siteconfig->Title . ' team');

            $email->addAttachmentFromData($str, $siteconfig->TradingName . ' Invoice #' . $this->ID . '.pdf');
            $email->send();
        }

        // -----

        $admin_sent_flag    =   ['sent' => false];
        $to_admin           =   !empty($siteconfig->OrderEmail) ? explode(',', $siteconfig->OrderEmail) : $siteconfig->ContactEmail;
        if (!empty($to_admin)) {
            $this->extend('SendAdminEmail', $from, $to_admin, $str, $admin_sent_flag);
            if (!$admin_sent_flag['sent']) {
                $admin_email    =   new Email($from, $to_admin, $siteconfig->TradingName . ': New order received (#' . $this->ID . ')');
                $admin_email->setBody('Hi, <br /><br />There is a new order. Please <a target="_blank" href="' . Director::absoluteBaseURL() .  'admin/orders/Leochenftw-eCommerce-eCollector-Model-Order/EditForm/field/Leochenftw-eCommerce-eCollector-Model-Order/item/' . $this->ID . '/edit' . '">click here</a> to view the details. <br /><br />' . $siteconfig->TradingName);

                $admin_email->send();
            }
        }
    }

    public function ItemCount()
    {
        $items  =   $this->Items();
        $n      =   0;
        foreach ($items as $item) {
            $n  +=  $item->Quantity;
        }
        return $n;
    }

    public function ShippableItemCount()
    {
        $items  =   $this->Items()->filter(['isDigital' => false]);
        $n      =   0;
        foreach ($items as $item) {
            $n  +=  $item->Quantity;
        }
        return $n;
    }

    public function UpdateAmountWeight()
    {
        $amount     =   0;
        $weight     =   0;
        $payable    =   0;

        foreach ($this->Items() as $item) {
            // $item->write(); // trigger onBeforeWrite
            $amount     +=  $item->Subtotal;
            $weight     +=  $item->Subweight;
            $payable    +=  $item->PayableTotal;
        }

        $this->TotalAmount  =   $amount;
        $this->TotalWeight  =   $weight;
        $this->PayableTotal =   $payable;
        $this->write();
    }

    public function onPaymentUpdate($status)
    {
        if ($status == 'Success') {
            $this->Status   =   'Payment Received';
            $this->send_invoice();
        } elseif ($status == 'Invoice Pending' || $status == 'Debit Pending') {
            $this->Status   =   $status;
            $this->send_invoice();
        } else {
            $this->Status   =   'Pending';
        }

        $this->extend('doPaymentupdateActions', $this);

        $this->write();
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if ($this->SameBilling) {
            $this->BillingOrganisation =   $this->ShippingOrganisation;
            $this->BillingApartment    =   $this->ShippingApartment;
            $this->BillingAddress      =   $this->ShippingAddress;
            $this->BillingSuburb       =   $this->ShippingSuburb;
            $this->BillingTown         =   $this->ShippingTown;
            $this->BillingRegion       =   $this->ShippingRegion;
            $this->BillingCountry      =   $this->ShippingCountry;
            $this->BillingPostcode     =   $this->ShippingPostcode;
            $this->BillingPhone        =   $this->ShippingPhone;
        }

        if (!empty($this->ShippingCountry)) {
            if ($freight = Freight::find_zone_freight($this->ShippingCountry)) {
                $this->FreightID    =   $freight->ID;
            }
        }
    }

     public function getGST()
     {
         $gst   =   0;
         $rate  =   (float) Config::inst()->get(eCollector::class, 'GSTRate');
         $items =   $this->Items()->filter(['isExempt' => false]);
         foreach ($items as $item) {
             $gst   +=  $item->PayableTotal * $rate;
         }

         return round($gst * 10) / 10;
     }

     public function Discount()
     {
         $list  =   [];
         $items =   $this->Items()->filter(['NoDiscount' => false]);
         foreach ($items as $item) {
             if ($item->Discount()->exists()) {
                 if (empty($list['id_' . $item->Discount()->ID])) {
                     $list['id_' . $item->Discount()->ID]    =   [
                         'title'     =>  $item->Discount()->Title,
                         'amount'    =>  $item->Discount()->calc_discount($item->Subtotal)
                     ];
                 } else {
                     $list['id_' . $item->Discount()->ID]['amount'] += $item->Discount()->calc_discount($item->Subtotal);
                 }
             }
         }

         return array_values($list);
     }

     public function send_tracking()
     {
         if ($this->Status == 'Payment Received') {
             $this->Status  =   'Shipped';
             $this->write();
         }

         $siteconfig =   SiteConfig::current_site_config();
         $from       =   Config::inst()->get(Email::class, 'noreply_email');
         $to         =   $this->Email;

         $customer_sent_flag =   ['sent' => false];
         $this->extend('SendTracking', $from, $to, $customer_sent_flag);

         if (!$customer_sent_flag['sent']) {
             $subject    =   $siteconfig->Title . ': order #' . $this->ID . ' has been dispatched';
             $email      =   new Email($from, $to, $subject);
             $email->setBody('Hi, <br /><br />The trakcing number for your order #' . $this->ID . ' is: ' . $this->TrackingNumber . '.<br /><br />Kind regards<br />' . $siteconfig->Title . ' team');
             $email->send();
         }
     }

     public function refund()
     {
         if ($this->Status == 'Payment Received') {
             $this->Status  =   'Refunded';
             $this->write();
         }

         $this->extend('doRefund');
     }

     public function cheque_cleared()
     {
         if ($this->exists() && $this->Payments()->exists()) {
            $payment            =   $this->Payments()->filter(['Status' => 'Invoice Pending'])->first();
            $payment->Status    =   'Success';
            $payment->write();
         }

         $this->onPaymentUpdate('Success');
     }

     public function debit_cleared()
     {
         if ($this->exists() && $this->Payments()->exists()) {
            $payment            =   $this->Payments()->filter(['Status' => 'Debit Pending'])->first();
            $payment->Status    =   'Success';
            $payment->write();
         }

         $this->onPaymentUpdate('Success');
     }
}
