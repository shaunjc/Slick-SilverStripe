<?php

namespace Slick\Address\Model;

use SilverStripe\ORM\DataObject;

use Slick\Address\Model\Address;

class Locality extends DataObject
{
    private static $table_name = 'Slick_Address_Locality';
    
    private static $db = [
        // Address parts
        'Suburb'       => 'Varchar(255)',
        'Postcode'     => 'Varchar(255)',
        'State'        => 'Varchar(255)',
        'Country'      => 'Varchar(255)',
        
        // Geocoded data
        'Longitude'        => 'Varchar(255)',
        'Latitude'         => 'Varchar(255)',
    ];
    
    private static $has_many = [
        'Addresses' => Address::class,
    ];
    
    private static $default_sort = 'Country ASC, State ASC, Suburb ASC, Postcode ASC, ID ASC';
    
    private static $singular_name = 'Locality';
    private static $plural_name = 'Localities';
    
    private static $summary_fields = [
        'Suburb',
        'Postcode',
        'State',
        'Country',
    ];
    
    public function __toString()
    {
        return join(', ', [
            $this->Suburb,
            $this->Postcode,
            $this->State,
            $this->Country,
        ]);
    }
    
    public function Title()
    {
        return "{$this}";
    }
}
