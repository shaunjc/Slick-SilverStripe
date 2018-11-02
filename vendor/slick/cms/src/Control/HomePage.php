<?php

namespace Slick\CMS\Control;

use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

use Slick\CMS\Control\Page;
use Slick\CMS\View\Banner;

use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class HomePage extends Page
{
    // Table names and extensions.
    private static $table_name = 'Slick_Home_Page';
    
    // Database columns and relationships.
    private static $db = [];
    private static $has_one = [
        'BannerVideo' => File::class,
    ];
    private static $has_many = [
        'BannerImages' => Banner::class,
    ];
    private static $owns = [
        'BannerVideo',
        'BannerImages',
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldsToTab('Root.Main', new FieldList(array(
//            UploadField::create('BannerVideo'),
            GridField::create(
                'BannerImages',
                'Banner Images',
                $this->BannerImages(),
                GridFieldConfig_RecordEditor::create()
                    ->addComponent(new GridFieldSortableRows('SortOrder'))
            ),
        )), 'BannerImage');
        
        $fields->addFieldsToTab('Root.Main', new FieldList(array(
            GridField::create(
                'Layouts',
                'Layout Sections',
                $this->Layouts(),
                $layout_config = GridFieldConfig_RecordEditor::create()
            ),
        )), 'Metadata');
        
        $layout_config->getComponentByType(GridFieldDataColumns::class)
            ->setDisplayFields(array(
                'Title'    => 'Title',
                'Template' => 'Template',
            ));
        
        $fields->removeByName('Content');
        $fields->removeByName('BannerImage');
        
        $fields->removeByName('PageIconTitle');
        $fields->removeByName('PageIconColumns');
        $fields->removeByName('Icons');
        $fields->removeByName('Logos');
        $fields->removeByName('Accordion');
        $fields->removeByName('BottomContent');
        
        return $fields;
    }
    
    public function IsHomePage()
    {
        return true;
    }
}
