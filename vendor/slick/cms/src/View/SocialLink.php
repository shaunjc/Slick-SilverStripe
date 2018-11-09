<?php

namespace Slick\CMS\View;

// Silverstripe framework and CMS classes.
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;

use Slick\Extensions\Sortable;

class SocialLink extends DataObject
{
    // Extensions.
    private static $extensions = [
        Sortable::class,
        Versioned::class,
    ];
    
    // Database tables and columns.
    private static $table_name    = 'Slick_Page_SocialLink';
    private static $singular_name = 'Social Link';
    private static $plural_name   = 'Social Links';
    
    private static $db = [
        'Title'     => 'Varchar(255)',
        'Link'      => 'Varchar(255)',
        'Icon'      => 'Enum("Facebook,Twitter,LinkedIn","Facebook")',
    ];
    
    // Relationships.
    private static $has_one = [
        'SiteConfig' => SiteConfig::class,
    ];
    
    // UI config.
    private static $default_sort = 'SortOrder ASC';
    
    /**
     * Simplify CMS Fields
     * 
     * @return \SilverStripe\Forms\FieldList|\SilverStripe\Forms\FormField[]
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->removeByName([
            'SiteConfig',
            'LinkTracking',
            'Filetracking',
        ]);
        
        return $fields;
    }
}
