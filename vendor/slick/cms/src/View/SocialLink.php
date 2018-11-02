<?php

namespace Slick\CMS\View;

use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

class SocialLink extends DataObject
{
    private static $table_name = 'Slick_Page_SocialLink';
    private static $extensions = [
        Versioned::class,
    ];
    
    private static $db = [
        'Title'     => 'Varchar(255)',
        'Link'      => 'Varchar(255)',
        'Icon'      => 'Enum("Facebook,Twitter,LinkedIn","Facebook")',
        'SortOrder' => 'Int',
    ];
    
    private static $has_one = [
        'SiteConfig' => SiteConfig::class,
    ];
    
    private static $default_sort = 'SortOrder ASC';
    
    /**
     * Simplify CMS Fields
     * 
     * @return \SilverStripe\Forms\FieldList|\SilverStripe\Forms\FormField[]
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->removeByName('SortOrder');
        $fields->removeByName('SiteConfig');
        
        return $fields;
    }
}
