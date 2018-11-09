<?php

namespace Slick\Forms\GridField;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDecimal;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * This class is based off the default GridFieldAddExistingAutocompleter class
 * which comes along with most SilverStripe 3 frameworks, except it also has the
 * additional ability to record extra fields when the object is being added to
 * the list.
 * 
 * TODO:
 * Create matching GridField_FormAction, GridFieldDataColumns, GridFieldConfig,
 * and/or GridFieldDetailForm to modify extra fields when objects have already
 * been linked to the Foreign Key, as well as an injector to use the new classes
 * by default.
 */
class GridFieldAddExistingAutocompleterExtra extends GridFieldAddExistingAutocompleter
{
    /**
     * @var array List of DBField classes which can be modified via a TestField.
     */
    private static $allowed_data_types = [
        DBDecimal::class,
        DBInt::class,
        DBVarchar::class,
    ];
    
    /**
     * Overload GridFieldAddExistingAutocompleter::getHTMLFragments to insert
     * additional TextFields for each applicable $many_many_extraField.
     * 
     * @param GridField $gridField
     * @return string[] - HTML
     */
    public function getHTMLFragments($gridField)
    {
        // Copied directly from parent::getHTMLFragments();
        $dataClass = $gridField->getModelClass();

        $forTemplate = new ArrayData(array());
        $forTemplate->Fields = new FieldList();

        // Get all Extra fields from the ManyManyList, if avaiable.
        if ($gridField->getList()->hasMethod('getExtraFields') && ($extraFields = $gridField->getList()->getExtraFields())) {
            foreach ($extraFields as $fieldName => $fieldSpec) {
                try {
                    $dbObject = Injector::inst()->create($fieldSpec, $fieldName);
                    // Use Object::create_from_string to determine if this field is appropriate for a plain textbox.
                    if (in_array(get_class($dbObject), self::$allowed_data_types)) {
                        // Add directly to field with all attributes and classes set.
                        $forTemplate->Fields->push(
                            $dbObject->scaffoldFormField('')
                                ->setName('extraFields[' . Convert::raw2sql($fieldName) . ']')
                                ->setAttribute('placeholder', Convert::raw2htmlatt($fieldName))
                                ->addExtraClass('relation-extradata no-change-track')
                        );
                    }
                }
                catch ( Exception $e ) {
                    // In case there's an issue with the Object::create_from_string->class,
                    // although, it should always work as long as dev/build also works.
                }
            }
        }
        
        // Remainer of parent::getHTMLFragments();
        $searchField = new TextField('gridfield_relationsearch', _t('SilverStripe\\Forms\\GridField\\GridField.RelationSearch', "Relation search"));

        $searchField->setAttribute('data-search-url', Controller::join_links($gridField->Link('search')));
        $searchField->setAttribute('placeholder', $this->getPlaceholderText($dataClass));
        $searchField->addExtraClass('relation-search no-change-track action_gridfield_relationsearch');

        $findAction = new GridField_FormAction(
            $gridField,
            'gridfield_relationfind',
            _t('SilverStripe\\Forms\\GridField\\GridField.Find', "Find"),
            'find',
            'find'
        );
        $findAction->setAttribute('data-icon', 'relationfind');
        $findAction->addExtraClass('action_gridfield_relationfind');

        $addAction = new GridField_FormAction(
            $gridField,
            'gridfield_relationadd',
            _t('SilverStripe\\Forms\\GridField\\GridField.LinkExisting', "Link Existing"),
            'addto',
            'addto'
        );
        $addAction->setAttribute('data-icon', 'chain--plus');
        $addAction->addExtraClass('btn btn-outline-secondary font-icon-link action_gridfield_relationadd');

        // If an object is not found, disable the action
        if (!is_int($gridField->State->GridFieldAddRelation(null))) {
            $addAction->setReadonly(true);
        }

        $forTemplate->Fields->push($searchField);
        $forTemplate->Fields->push($findAction);
        $forTemplate->Fields->push($addAction);
        if ($form = $gridField->getForm()) {
            $forTemplate->Fields->setForm($form);
        }

        $template = SSViewer::get_templates_by_class($this, '', GridFieldAddExistingAutocompleter::class);
        return array(
            $this->targetFragment => $forTemplate->renderWith($template)
        );
    }

    /**
     * Manipulate the state to add a new relation with Extra fields.
     *
     * @param GridField $gridField
     * @param string $actionName Action identifier, see {@link getActions()}.
     * @param array $arguments Arguments relevant for this
     * @param array $data All form data
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        switch ($actionName) {
            case 'addto':
                if (isset($data['relationID']) && $data['relationID']){
                    $gridField->State->GridFieldAddRelation = $data['relationID'];
                    // Initialise as an Empty array, and replace with submitted form data (should always be an array).
                    $gridField->State->GridFieldExtraFields = array();
                    if (!empty($data['extraFields'])) {
                        $gridField->State->GridFieldExtraFields = (array) $data['extraFields'];
                    }
                }
                break;
            default:
                // The following statment should not produce any results, but it's here for good practice.
                parent::handleAction($gridField, $actionName, $arguments, $data);
        }
    }

    /**
     * If an object ID is set, add the object to the list, and include the gathered extra fields.
     *
     * @param GridField $gridField
     * @param SS_List $dataList
     * @return SS_List
     */
    public function getManipulatedData(GridField $gridField, SS_List $dataList)
    {
        $objectID = $gridField->State->GridFieldAddRelation(null);
        if(empty($objectID)) {
            return $dataList;
        }
        $object = DataObject::get_by_id($gridField->getModelClass(), $objectID);
        if($object) {
            // Convert GridState_Data to an array - should already be in array format.
            $dataList->add($object, $gridField->State->GridFieldExtraFields->toArray());
        }
        $gridField->State->GridFieldAddRelation = null;
        $gridField->State->GridFieldExtraFields = null;
        return $dataList;
    }
}
