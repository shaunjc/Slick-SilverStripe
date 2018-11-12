<?php

namespace Slick\CMS\Control;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\FieldList;
use SilverStripe\Versioned\Versioned;

use Slick\CMS\Control\HomePage;
use Slick\CMS\View\Accordion;
use Slick\CMS\View\Icon;
use Slick\CMS\View\Layout;
use Slick\CMS\View\Logo;

class Page extends SiteTree
{
    private static $table_name = 'Slick_Page';
    
    private static $db = [
        'PageIconTitle'   => 'Varchar(255)',
        'PageIconColumns' => 'Int',
        'BottomContent'    => 'HTMLText',
    ];

    private static $has_one = [
        'BannerImage' => Image::class,
    ];
    
    private static $has_many = [
        'Accordion' => Accordion::class,
        'Icons'     => Icon::class,
        'Layouts'   => Layout::class,
        'Logos'     => Logo::class,
    ];
    
    private static $owns = [
        'Accordion',
        'BannerImage',
        'Icons',
        'Layouts',
        'Logos',
    ];
    
    private static $cascade_deletes = [
        'Accordion',
        'Icons',
        'Layouts',
        'Logos',
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldsToTab('Root.Main', FieldList::create([
            UploadField::create('BannerImage'),
        ]), 'Content');
        
        return $fields;
    }
    
    /**
     * Get Banner Image.
     * 
     * Get from matching page if it exists and check for ancestor banner image
     * as necessary. Fall back to the homepage banner where possible.
     * 
     * @return \SilverStripe\Assets\Image|null
     */
    public function TheBannerImage()
    {
        // Get Banner Image from current page.
        $banner = null;
        if ($this->BannerImageID && ($banner = $this->BannerImage()) && $banner->exists()) {
            return $banner;
        }
        
        // Loop through ancestory until banner image found.
        $parent = $this->Parent();
        while ($parent && $parent->exists() && (!$parent->BannerImageID || !($banner = $parent->BannerImage()) || !$banner->exists())) {
            $parent = $parent->Parent();
        }
        if ($banner && $banner->exists()) {
            return $banner;
        }
        
        // Find a homepage and get the first image from the banners.
        $homepage = Versioned::get_one_by_stage(HomePage::class, 'Live');
        if ($homepage && $homepage->exists() && ($banners = $homepage->BannerImages()) && $banners->exists()) {
            foreach ($banners as $banner) {
                if ($banner->BackgroundImageID && ($image = $banner->BackgroundImage()) && $image->exists()) {
                    return $image;
                }
            }
        }
    }
    
    /**
     * Logos extend Icons, so we'll just add an extra filter when getting
     * Icons, to ensure that Logos aren't included.
     * 
     * @return \SilverStripe\ORM\HasManyList
     */
    public function Icons()
    {
        return Icon::get()
            ->filter('PageID', $this->ID)
            ->filter('Classname', Icon::class);
    }
}
