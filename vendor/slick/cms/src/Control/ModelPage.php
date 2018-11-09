<?php

namespace Slick\CMS\Control;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataObject;

use Slick\CMS\Control\Page;

/**
 * Model Page
 * 
 * CMS Page designed to handle model information on
 * the FrontEnd in a similar fashion to ModelAdmin
 */
class ModelPage extends Page
{
    private static $table_name    = 'Slick_Model_Page';
    private static $singular_name = 'Objects Page';
    private static $plural_name   = 'Object Pages';
    
    /** @var array Database **/
    private static $db = [
        'ModelClass'           => 'Varchar(255)',
        'ObjectsPerPage'       => 'Int',
        'NoObjectFoundMessage' => 'Text'
    ];
    
    /**
     * CMS Fields for manipulating the Database
     * 
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('ModelClass', 'Model', ClassInfo::subclassesFor(DataObject::class)),
            NumericField::create('ObjectsPerPage'),
            TextareaField::create('NoObjectFoundMessage')
                ->setAttribute('placeholder', 'No object found with that id.')
        ]);
        
        return $fields;
    }
    
    /**
     * Confirms if a valid class name is saved by checking to
     * see that ModelClass is a string that extends DataObject
     * 
     * @return boolean
     */
    public function hasValidModelClass()
    {
        return $this->ModelClass && $this->ModelClass != DataObject::class && is_a( $this->ModelClass, DataObject::class, true );
    }
}
