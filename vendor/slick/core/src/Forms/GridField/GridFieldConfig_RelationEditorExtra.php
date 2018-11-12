<?php

namespace Slick\Forms\GridField;

// Core SilverStripe framework and CMS classes.
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldDetailForm;

// Slick module classes.
use Slick\Forms\GridField\GridFieldAddExistingAutocompleterExtra;
use Slick\Forms\GridField\GridFieldDetailFormExtra_ItemRequest;

/**
 * GridFieldConfig class used to automatically display extra fields when editing
 * relational objects through the ItemEditForm or when adding items via the
 * GridFieldAddExistingAutocompleter.
 * 
 * This can be set as the default Relation Editor by setting the Injector config
 * in the yaml files. The fields can also be enabled or disabled individually by
 * passing in additional arguments or by replacing the individual components.
 */
class GridFieldConfig_RelationEditorExtra extends GridFieldConfig_RelationEditor
{
    /**
     * @var boolean Default true.
     */
    private static $automatic_auto_completer = true;
    
    /**
     * @var boolean Default true.
     */
    private static $automatic_item_edit_form = true;
    
	/**
     * @param int $itemsPerPage - How many items per page should show up
     * @param boolean $auto_completer Specifically set the config to load the
     * GridFieldAddExistingAutocompleterExtra class. Leave this argument as NULL
     * to default to the config value.
     * @param boolean $item_edit_form Specifically set the config to load the
     * GridFieldDetailFormExtra_ItemRequest class. Leave this argument as NULL
     * to default to the config value.
	 */
	public function __construct($itemsPerPage = null, $auto_completer = null, $item_edit_form = null)
    {
        // Load default configuration
		parent::__construct($itemsPerPage);
        
        if ((is_null($auto_completer) && $this->config()->get('automatic_auto_completer')) || $auto_completer) {
            $this->setAutocompleterExtra();
        }
        
        if ((is_null($item_edit_form) && $this->config()->get('automatic_item_edit_form')) || $item_edit_form) {
            $this->setItemRequestExtra();
        }
        
        // Secondary update function
		$this->extend('updateExtraConfig');
	}
    
    /**
     * Replace GridFieldAddExistingAutocompleter with
     * similar GridFieldAddExistingAutocompleterExtra
     * 
     * @param string $targetFragment
     * @param array|null $searchFields
     */
    public function setAutocompleterExtra($targetFragment = 'buttons-before-right', $searchFields = null)
    {
        $this->removeComponentsByType(GridFieldAddExistingAutocompleter::class)
            ->addComponent(new GridFieldAddExistingAutocompleterExtra($targetFragment, $searchFields));
    }
    
    /**
     * Set Item Request Class on GridFieldDetailForm.
     */
    public function setItemRequestExtra()
    {
        $this->getComponentByType(GridFieldDetailForm::class)
            ->setItemRequestClass(GridFieldDetailFormExtra_ItemRequest::class);
    }
}
