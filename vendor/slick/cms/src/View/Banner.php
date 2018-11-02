<?php

namespace Slick\CMS\View;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

use Slick\CMS\Control\Page;

class Banner extends DataObject
{
    private static $table_name = 'Slick_Page_Banner';
    
    private static $extensions = [
        Versioned::class,
    ];
    
    private static $db = [
        'Title' => 'Varchar(255)',
        'Content' => 'HTMLText',
        'SortOrder' => 'Int',
    ];
    
    private static $has_one = [
        'BackgroundImage' => Image::class,
        'Page' => Page::class,
    ];
    
    private static $owns = [
        'BackgroundImage'
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->fieldByName('Root.Main.Title')->setRightTitle('This is only for your reference.');
        $fields->fieldByName('Root.Main.BackgroundImage')->setRightTitle('This image will be resized, centered and cropped automatically based on the screen size.');
        
        $fields->removeByName('SortOrder');
        $fields->removeByName('PageID');
        
        return $fields;
    }
    
    public function onBeforePublish()
    {
        if ($this->BackgroundImage() && $this->BackgroundImage()->exists()) {
            $this->BackgroundImage()->publishRecursive();
        }
    }
    
    public function forTemplate() {
        return $this->renderWith( 'banner' );
    }
}
