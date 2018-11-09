<?php

namespace Slick\Forms\GridField;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;

/**
 * Automatically add in ManyMany extra fields when editing a relational object.
 * 
 * You can either replace the default GridFieldDetailForm with a new
 * GridFieldDetailFormExtra, or update protected $itemRequestClass
 * as illustrated in the default usage below.
 * 
 * Default Usage:
 * $gridField
 *  ->getConfig()
 *  ->getComponentByType('GridFieldDetailForm')
 *  ->setItemRequestClass('GridFieldDetailFormExtra_ItemRequest');
 */
class GridFieldDetailFormExtra extends GridFieldDetailForm
{
    
}

class GridFieldDetailFormExtra_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    /** @var array **/
    private static $allowed_actions = [
        'ItemEditForm'
    ];
    
    /**
     * Adds Extra fields to the bottom of the Main Tab,
     * used for updating many_many_extraFields.
     * 
     * @return Form
     */
    public function ItemEditForm()
    {
        // Collect Form, Fields, and ManyManyList.
        $form = parent::ItemEditForm();
        if (!is_a($form, Form::class)) {
            return $form;
        }
		$fields = $form->Fields();
        $list = $this->gridField->getList();
        
        // Can also test to see if $list is a ManyManyList.
		if($list->hasMethod('getExtraFields') && ($extraFields = $list->getExtraFields())) {
            // Get the class name and/or Foreign Object if available.
            $key = $list->getForeignKey();
            $id = $list->getForeignID();
            $class = DataObject::getSchema()->manyManyComponent(get_class($this->record), $key);
            $foreignObject = (class_exists($class['childClass']) && is_a($class, DataObject::class, true)) ? $class::get()->byID($id) : null;
            
            foreach ($extraFields as $fieldName => $fieldSpec) {
                $title = sprintf(
                    '%s (%s)',
                    $fieldName,
                    $foreignObject && $foreignObject->exists() && $foreignObject->Title ? $foreignObject->Title : "$key:$id"
                );
                $fields->addFieldsToTab( 'Root.Main', [
                    // Create each field using standard scaffolding techniques.
                    Injector::inst()->create($fieldSpec, "ManyMany[$fieldName]")->scaffoldFormField($title),
                ]);
            }
            
            // Prevent NumericField localisation issues by converting all numeric values to strings.
			$extraData = array_map(function($data) {
			    return (string) $data;
			}, (array) $list->getExtraData('', $this->record->ID));
            // Re-load data from form.
			$form->loadDataFrom([
                'ManyMany' => $extraData,
            ]);
		}
        
        return $form;
    }
}
