<?php

namespace Slick\CMS\Extensions;

use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\TextField;

use Slick\CMS\View\SocialLink;
use Slick\Extensions\DataExtension;

use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class SiteConfig extends DataExtension
{
    private static $db = [
        'AdminNotificationEmail' => 'Varchar(255)',
        'Copyright' => 'Varchar(255)',
    ];
    
    private static $has_many = [
        'SocialLinks' => SocialLink::class,
    ];
    
    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);
        
        $fields->addFieldsToTab('Root.Main', [
            EmailField::create('AdminNotificationEmail'),
            TextField::create('Copyright')->setAttribute('placeholder', $this->DefaultCopyright()),
        ]);
        
        $fields->addFieldToTab('Root.Social', GridField::create(
            'SocialLinks',
            'Social Links',
            $this->owner->SocialLinks()
        )->setConfig(GridFieldConfig_RecordEditor::create()
            ->addComponent(new GridFieldSortableRows('SortOrder'))));
        
        return $fields;
    }
    
    public function DefaultCopyright()
    {
        return sprintf( '%s %s %s, %s', '©', 'Copyright', date('Y'), $this->owner->Title );
    }
}
