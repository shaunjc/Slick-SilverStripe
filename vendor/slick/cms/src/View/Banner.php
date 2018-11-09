<?php

namespace Slick\CMS\View;

// SilverStripe Framework and CMS classes.
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

// Slick module classes.
use Slick\CMS\Control\Page;
use Slick\Extensions\Sortable;

class Banner extends DataObject
{
    // Extensions.
    private static $extensions = [
        Sortable::class,
        Versioned::class,
    ];
    
    // Database tables and columns.
    private static $table_name    = 'Slick_Page_Banner';
    private static $db = [
        'Title' => 'Varchar(255)',
        'Content' => 'HTMLText',
    ];
    
    // Relationships.
    private static $has_one = [
        'BackgroundImage' => Image::class,
        'Page' => Page::class,
    ];
    private static $owns = [
        'BackgroundImage'
    ];
    private static $owned_by = [
        'Page',
    ];
    
    // UI config.
    private static $default_sort  = 'SortOrder ASC';
    private static $singular_name = 'Banner';
    private static $plural_name   = 'Banners';
    
    /**
     * Simplify the CMS edit form and add help text.
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->dataFieldByName('Title')
            ->setRightTitle('This is only for your reference.');
        $fields->dataFieldByName('BackgroundImage')
            ->setRightTitle('This image will be resized, centered and cropped automatically based on the screen size.');
        
        $fields->removeByName([
            'PageID',
            'LinkTracking',
            'FileTracking',
        ]);
        
        return $fields;
    }
    
    /**
     * Render Banner object using the supplied template. The same template can
     * also be included within both loop and with tags for a similar effect.
     * @return string HTML string.
     */
    public function forTemplate() {
        return $this->renderWith('Banner');
    }
}
