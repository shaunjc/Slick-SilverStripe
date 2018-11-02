<?php
namespace Slick\Address\Extensions;

use SilverStripe\ORM\DataExtension;

use Slick\Address\Model\Address;

class Member extends DataExtension
{
    // Database and Relationships.
    private static $has_many = [
        'Addresses' => Address::class,
    ];
    
    /**
     * Allows a Default Address to be set and obtained.
     * 
     * @param string $type Address Type. Default: 'billing'.
     * @return \Slick\Adress
     */
    public function DefaultAddress($type = 'billing')
    {
        $address = Address::get()->filter('MemberID', $this->owner->ID)->find('Type', $type);
        
        if (!$address || !$address->exists()) {
            $address = Address::create()->update([
                'MemberID' => $this->owner->ID,
                'Type' => $type,
                'Default' => true,
            ]);
            $address->write();
        }
        
        return $address;
    }
}
