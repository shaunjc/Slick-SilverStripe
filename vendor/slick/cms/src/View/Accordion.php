<?php

namespace Slick\CMS\View;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

use Slick\CMS\Control\Page;
use Slick\CMS\View\Layout;

class Accordion extends DataObject
{
    private static $table_name = 'Slick_Page_Accordion';
    private static $extensions  = [
        Versioned::class,
    ];
    private static $db = [
        'Title'     => 'Varchar(255)',
        'Content'   => 'HTMLText',
        'SortOrder' => 'Int',
    ];
    private static $has_one = [
        'Page'   => Page::class,
        'Layout' => Layout::class,
    ];
    private static $owned_by = [
        'Page',
        'Layout',
    ];
    
    private static $singular_name = 'Accordion Section';
    private static $plural_name   = 'Accordion';
    private static $default_sort  = 'SortOrder ASC';
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->removeByName('SortOrder');
        $fields->removeByName('LayoutID');
        $fields->removeByName('PageID');
        
        return $fields;
    }
}
