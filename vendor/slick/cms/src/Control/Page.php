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
        'Icons',
        'Layouts',
        'Logos',
        'BannerImage',
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldsToTab('Root.Main', new FieldList(array(
            UploadField::create('BannerImage'),
        )), 'Content');
        
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
        
        // Find a homepage and get its banner image.
        $homepage = Versioned::get_one_by_stage(HomePage::class, 'Live');
        if ($homepage && $homepage->exists() && $homepage->BannerImageID && ($banner = $homepage->BannerImage()) && $banner->exists()) {
            return $banner;
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
    
    /**
     * Publish sub items when page published.
     */
    public function onAfterWrite()
    {
        if (is_callable('parent::onAfterWrite')) {
            parent::onAfterWrite();
        }
        // Only push items when Draft does not exist or isn't different to Live.
        if (!$this->isModifiedOnDraft()) {
            foreach (self::$owns as $name) {
                if (($items = $this->$name()) && $items->exists()) {
                    foreach ($items as $item) {
                        // Only publish sub items that require publishing.
                        if ($item->hasMethod('isModifiedOnDraft') && $item->isModifiedOnDraft()) {
                            $item->publishSingle();
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Cleanup sub-items on page deletion.
     * 
     * May not trigger as pages will typically be archived instead.
     */
    public function onBeforeDelete()
    {
        if (is_callable('parent::onBeforeDelete')) {
            parent::onBeforeDelete();
        }
        
        foreach (self::$has_many as $name => $class) {
            if (($items = $this->$name()) && $items->exists()) {
                foreach ($items as $item) {
                    $item->delete();
                }
            }
        }
    }
    
    /**
     * Archive sub-items when page archived.
     * 
     * May not be necessary as items should not be accessible otherwise.
     */
    public function onBeforeArchive()
    {
        if (is_callable('parent::onBeforeArchive')) {
            parent::onBeforeArchive();
        }
        
        foreach (self::$has_many as $name => $class) {
            if (singleton($class)->hasMethod('doArchive')
            && ($items = $this->$name()) && $items->exists()) {
                foreach ($items as $item) {
                    $item->doArchive();
                }
            }
        }
    }
}
