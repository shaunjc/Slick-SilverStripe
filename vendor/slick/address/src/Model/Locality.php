<?php

namespace Slick\Address\Model;

use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

use Slick\Address\Model\Address;

class Locality extends DataObject
{
    // Table names and extensions.
    private static $table_name = 'Slick_Address_Locality';
    
    // Database columns and relationships.
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
    
    // Other UI variables.
    private static $default_sort = 'Country ASC, State ASC, Suburb ASC, Postcode ASC, ID ASC';
    private static $singular_name = 'Locality';
    private static $plural_name = 'Localities';
    private static $summary_fields = [
        'Suburb',
        'Postcode',
        'State',
        'Country',
    ];
    
    //** Getters **//
    
    /**
     * Joins Suburb, Postcode, State and Country into a comma separated string.
     * @return string
     */
    public function __toString()
    {
        return join(', ', [
            $this->Suburb,
            $this->Postcode,
            $this->State,
            $this->Country,
        ]);
    }
    
    /**
     * Use magic method __toString() to create a title.
     * @return string
     */
    public function Title()
    {
        return "{$this}";
    }
    
    //** Overridden Methods **//
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->dataFieldByName('Addresses')
            ->setConfig(GridFieldConfig_RecordViewer::create());
        
        $fields->removeByName([
            'LinkTracking',
            'FileTracking',
        ]);
        
        return $fields;
    }
    
    /**
     * Import new Localities as needed.
     * 
     * Existing localities are classed as soft-deprecated and remain unaffected.
     */
    public function requireDefaultRecords()
    {
        // Load CSV containing all Australian localities.
        $path = realpath(dirname(dirname(dirname(__FILE__))) . '/resources/Localities.csv');
        if (file_exists($path)) {
            $db = [];
            $t = 0;
            $fh = fopen($path, 'r');
            while(($line = fgetcsv($fh))) {
                if (!$db) {
                    // Heading line.
                    $db = $line;
                }
                else {
                    // Generate a record and commit it to the database if it does not already exist.
                    $record = array_combine($db, $line);
                    if (!static::get()->filter($record)->exists()) {
                        static::create($record)->write();
                        $t++;
                    }
                }
            }
            fclose($fh);
            if ($t) {
                DB::alteration_message("{$t} Locality records created.", "created");
            }
        }
        parent::requireDefaultRecords();
    }
}
