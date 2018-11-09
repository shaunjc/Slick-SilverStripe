<?php

namespace Slick\CMS\View;

// SilverStripe Framework and CMS classes.
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

// Slick module classes.
use Slick\CMS\Control\Page;
use Slick\CMS\View\Layout;
use Slick\Extensions\Sortable;

/**
 * An Accordion DataObject used to display a collapsable section on the website.
 */
class Accordion extends DataObject
{
    // Extensions.
    private static $extensions = [
        Sortable::class,
        Versioned::class,
    ];
    
    // Database tables and columns.
    private static $table_name = 'Slick_Page_Accordion';
    private static $db = [
        'Title'     => 'Varchar(255)',
        'Content'   => 'HTMLText',
    ];
    
    // Relationships.
    private static $has_one = [
        'Page'   => Page::class,
        'Layout' => Layout::class,
    ];
    private static $owned_by = [
        'Page',
        'Layout',
    ];
    
    // UI config.
    private static $singular_name = 'Accordion Section';
    private static $plural_name   = 'Accordion';
    private static $default_sort  = 'SortOrder ASC';
    
    /**
     * Simplify CMS Fields.
     * @return \SilverStripe\Forms\FieldList|\SilverStripe\Forms\FormField[]
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->removeByName([
            'LayoutID',
            'PageID',
            'LinkTracking',
            'FileTracking',
        ]);
        
        return $fields;
    }
}
