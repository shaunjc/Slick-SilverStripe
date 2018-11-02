<?php

namespace Slick\CMS\Control;

use SilverStripe\Forms\NumericField;

use Slick\CMS\Control\Page;

class Search extends Page
{
    private static $table_name = 'Slick_Search_Page';
    
    private static $db = [
        'ResultsPerPage' => 'Int',
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldToTab('Root.Main', NumericField::create('ResultsPerPage')->setAttribute('placeholder', 20), 'Metadata');
        
        return $fields;
    }
}
