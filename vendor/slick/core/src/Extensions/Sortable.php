<?php

namespace Slick\Extensions;

use SilverStripe\Forms\FieldList;

// Slick classes
use Slick\Extensions\DataExtension;

class Sortable extends DataExtension
{
    const SORTABLE_COLUMN_NAME = 'SortOrder';
    
    private static $db = [
        self::SORTABLE_COLUMN_NAME => 'Int',
    ];
    
    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName(self::SORTABLE_COLUMN_NAME);
        
        return $fields;
    }
}