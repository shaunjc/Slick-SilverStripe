<?php

namespace Slick\Forms\GridField;

// Core SilverStripe framework and CMS classes.
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\ORM\DataObject;

/**
 * Provides view and edit forms at GridField-specific URLs.
 *
 * Automatically uses GridFieldDetailFormExtra_ItemRequest to generate form
 * fields for each `$many_many_extraFields`.
 */
class GridFieldDetailFormExtra extends GridFieldDetailForm
{
    
}

/**
 * Automatically add in ManyMany extra fields when editing a relational object.
 * 
 * Can be used by either replacing the default GridFieldConfig_RelationEditor
 * config, replacing the default GridFieldDetailForm component or setting the
 * `$itemRequestClass` for the current GridFieldDetailForm component.
 * 
 * GridFieldConfig, GridFieldDetailForm and GridFieldDetailForm_ItemRequest are
 * all injectable.
 */
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
                // Create each field using standard scaffolding techniques.
                $field = Injector::inst()->create($fieldSpec, "ManyMany[{$fieldName}]")->scaffoldFormField($title);
                if ($fields->hasTabSet()) {
                    $fields->addFieldToTab('Root.Main', $field);
                }
                else {
                    $fields->add($field);
                }
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
