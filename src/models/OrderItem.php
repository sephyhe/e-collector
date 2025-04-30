<?php

namespace Leochenftw\eCommerce\eCollector\Model;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use Leochenftw\eCommerce\eCollector\Model\Order;
use Leochenftw\eCommerce\eCollector\Extensions\ProductOrderItemCommonFields;
use Leochenftw\Debugger;

/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class OrderItem extends DataObject
{
    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'ECollectorOrderItem';
    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'Quantity'      =>  'Int',
        'Subtotal'      =>  'Currency',
        'Subweight'     =>  'Decimal',
        'isRefunded'    =>  'Boolean',
        'PayableTotal'  =>  'Currency'
    ];

    /**
     * Defines extension names and parameters to be applied
     * to this object upon construction.
     * @var array
     */
    private static $extensions = [
        ProductOrderItemCommonFields::class
    ];

    /**
     * Defines a default list of filters for the search context
     * @var array
     */
    private static $searchable_fields = [
        'ID'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'Order'     =>  Order::class,
        'Discount'  =>  Discount::class
    ];

    public function populateDefaults()
    {
        $this->Quantity =   1;
        if ($member = Security::getCurrentUser()) {
            if ($group = $member->Groups()->first()) {
                if ($group->Discount()->exists()) {
                    $this->DiscountID   =   $group->DiscountID;
                }
            }
        }
    }
}
