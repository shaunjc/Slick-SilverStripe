<?php

namespace Slick\Forms\GridField;

// Core SilverStripe framework and CMS classes.
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\VersionProvider;
use SilverStripe\Dev\Deprecation;
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
 * which comes along with the SilverStripe 4 framework, except it also has the
 * additional ability to record extra fields when the object is being added to
 * the list.
 * 
 * Can be used by either replacing the default GridFieldAddExistingAutocompleter
 * component or replacing the default GridFieldConfig_RelationEditor config.
 * 
 * GridFieldConfig is injectable, but GridFieldAddExistingAutocompleter is not.
 * 
 * Each of the methods below are copied from the parent getHTMLFragments method
 * and may not be comparable to GridFieldAddExistingAutocompleter from other
 * versions of silverStripe. A Deprecation message will display for examples
 * where the version does not exactly matchand the default functionality will be
 * used if possible.
 * 
 * GridFieldAddExistingAutocompleterExtra is configurable, so the allowed data
 * types and compatible versions can be adjusted via the Config API.
 */
class GridFieldAddExistingAutocompleterExtra extends GridFieldAddExistingAutocompleter
{
    use Configurable;
    
    /**
     * @var array min and max versions compatible with this class.
     */
    private static $compatible_versions = [
        'min' => '4.0.0',
        'max' => '4.3.0',
    ];
    
    /**
     * @var array List of DBField classes which can be modified via a TextField.
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
        if ($this->isDeprecated(__FUNCTION__)) {
            return parent::getHTMLFragments($gridField);
        }
        
        // Copied directly from parent::getHTMLFragments();
        $dataClass = $gridField->getModelClass();

        $forTemplate = ArrayData::create([]);
        $forTemplate->Fields = FieldList::create();

        // Get all Extra fields from the ManyManyList, if avaiable.
        if ($gridField->getList()->hasMethod('getExtraFields') && ($extraFields = $gridField->getList()->getExtraFields())) {
            $allowed_data_types = $this->config()->get('allowed_data_types');
            foreach ($extraFields as $fieldName => $fieldSpec) {
                // Get the DBField instance to determine if this field is appropriate for a plain textbox.
                $dbObject = Injector::inst()->create($fieldSpec, "extraFields[{$fieldName}]");
                if (in_array(get_class($dbObject), $allowed_data_types)) {
                    // Add directly to field with all attributes and classes set.
                    $forTemplate->Fields->push(
                        $dbObject->scaffoldFormField('')
                            ->setAttribute('placeholder', $fieldName)
                            ->addExtraClass('relation-extradata no-change-track')
                    );
                }
            }
        }

        // Remainer of parent::getHTMLFragments();
        $searchField = TextField::create(
            'gridfield_relationsearch',
            _t('SilverStripe\\Forms\\GridField\\GridField.RelationSearch', "Relation search")
        );

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

        $template = SSViewer::get_templates_by_class($this, '', GridFieldAddExistingAutocompleter::class); // adjusted to use existing templates.
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
        if ($this->isDeprecated(__FUNCTION__)) {
            return parent::handleAction($gridField, $actionName, $arguments, $data);
        }
        
        switch ($actionName) {
            case 'addto':
                if (isset($data['relationID']) && $data['relationID']) {
                    $gridField->State->GridFieldAddRelation = $data['relationID'];
                    // Initialise as an Empty array, and replace with submitted form data (should always be an array).
                    $gridField->State->GridFieldExtraFields = [];
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
        if ($this->isDeprecated(__FUNCTION__)) {
            return parent::getManipulatedData($gridField, $dataList);
        }
        
        $objectID = $gridField->State->GridFieldAddRelation(null);
        if (empty($objectID)) {
            return $dataList;
        }
        $object = DataObject::get_by_id($gridField->getModelClass(), $objectID);
        if ($object) {
            // Convert GridState_Data to an array - should already be in array format.
            $dataList->add($object, $gridField->State->GridFieldExtraFields->toArray());
        }
        $gridField->State->GridFieldAddRelation = null;
        $gridField->State->GridFieldExtraFields = null;
        return $dataList;
    }

    /**
     * Functions may change over time. Since they're pretty much a copy/paste
     * from the parent function they may cease to be compatible in future.
     * @param string $function Function name being checked.
     * @return boolean
     */
    protected function isDeprecated($function = '')
    {
        $version = new VersionProvider;
        $current_version = $version->getVersion();
        $compatible_versions = $this->config()->get('compatible_versions');
        if (version_compare($compatible_versions['min'], $current_version) > 0
         || version_compare($compatible_versions['max'], $current_version) < 0) {
            // Display Deprecation message.
            Deprecation::notice($current_version, "GridFieldAddExistingAutocompleterExtra::{$function} method may not be compatible. Min Version: {$compatible_versions['min']}, Max Version: {$compatible_versions['max']}.");
            return true;
        }
    }
}
