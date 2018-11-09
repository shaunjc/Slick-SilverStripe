<?php

namespace Slick\CMS\Control;

use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Validator;
use SilverStripe\ORM\DataObject;

use Slick\CMS\Control\PageController;
use Slick\Extensions\Rewritable;

/**
 * Model Page Controller
 * 
 * Controller responsible for displaying Model information
 * on the front end, in a similar fashion to ModelAdmin.
 */
class ModelPageController extends PageController
{
    /** @var array URL Handlers tells the controller which action to load **/
    private static $url_handlers = [
        'edit/$ID'   => 'edit',
        'page/$Page' => 'index',
        '$ID'        => 'index',
    ];
    
    /** @var array Allowed Actions confirms which actions are loadable by the request handler **/
    private static $allowed_actions = [
        "index",
        "edit",
        "Form",
    ];
    
    /**
     * Init Function
     * 
     * Throw a 404 error page if the requested DataObject doesn't exist
     */
    public function init()
    {
        parent::init();
        
        if (in_array($this->action, array('edit', 'view', 'Form')) && (!$this->CurrentObject() || !$this->CurrentObject()->exists())) {
            return $this->httpError(404, $this->NoObjectFoundMessage ?: 'No object found with that id.');
        }
    }
    
    /** Controller Actions **/
    
    /**
     * Action: Index
     * 
     * Display a list of DataObjects
     * - use $PagedObjects and $Pagination( $spread )
     * 
     * @return array
     */
    public function index()
    {
        return array(
            
        );
    }
    
    /**
     * Action: Edit
     * 
     * Display an Edit form for the current
     * DataObject - use $EditForm
     * 
     * @return array
     */
    public function edit()
    {
        return array(
            
        );
    }
    
    /** Form Actions **/
    
    /**
     * Form: Edit Form
     * 
     * Either displays the form when called within a template,
     * or processes the form when called directly.
     * 
     * @return Form|FrontEndForm
     */
    public function Form()
    {
        // Object needs to exist and be editable
        if (!($object = $this->CurrentObject()) || !$object->exists() || !$object->canEdit()) {
            return;
        }
        
        // Fields from either getAppFields or getCMSFields functions
        $fields = $object->AppFields ?: $object->CMSFields;
        if (!$fields || !is_a($fields, 'FieldList')) {
            // Needs to be a FieldList
            return;
        }
        if (!$fields->fieldByName('ID')) {
            $fields->add(HiddenField::create('ID')); // Needs to include an ID field
        }
        
        // Save Action
        $actions = FieldList::create([
            FormAction::create('save','Update'),
        ]);
        
        // Validator from either getAppRequiredFields, getAppValidator, or getValidator functions
        $validator = $object->AppRequiredFields ?: $object->AppValidator ?: $object->Validator;
        if (is_array($validator)) {
            // Convert to RequiredFields if an array
            $validator = RequiredFields::create($validator);
        }
        if (!is_a($validator, Validator::class)) {
            // Fall back to null if not acceptable
            $validator = null;
        }
        
        $form = Form::create($this, 'Form', $fields, $actions, $validator);
        
        // Populate form with current information from the DataObject and return it
        return $form->loadDataFrom($object);
    }
    
    /**
     * Form Action: Save
     * 
     * Saves the Form data and redirects the user back to the previous screen.
     * 
     * @param array $data
     * @param Form|FrontEndForm $form
     */
    public function save($data = array(), $form = null)
    {
        // Ensure form was submitted correctly, object exists, and is editable
        if (!$data || !$form || !($object = $this->CurrentObject()) || !$object->exists() || !$object->canEdit()) {
            return $this->redirectBack();
        }
        
        // Save form data into object and commit changes
        $form->saveInto($object);
        $object->write();
        
        // Redirect back to previous page
        return $this->redirectBack();
    }
    
    /** Template and Helper Functions **/
    
    /**
     * Obtain the Search Query if provided
     * 
     * @return string|null
     */
    public function SearchQuery()
    {
        return Convert::raw2sql($this->request->requestVar('q'));
    }
    
    /**
     * Obtain a list of All Objects
     * - Filtered to those that match the search query if provided
     * - Filtered to those which return true for the function canView
     * 
     * @return ArrayList due to filterByCallback - may cause issues
     * if page is extended and database queries need to be adjusted
     */
    public function AllObjects()
    {
        if (!$this->AllObjects && $this->hasValidModelClass()) {
            $this->AllObjects = DataObject::get($this->ModelClass);
            if (($q = $this->SearchQuery())) {
                $s = singleton($this->ModelClass);
                if ($s->hasDatabaseField('Title')) {
                    $this->AllObjects = $this->AllObjects->filterAny(['Title:partialMatch' => $q]);
                }
                if ($s->hasDatabaseField('Description')) {
                    $this->AllObjects = $this->AllObjects->filterAny(['Description:partialMatch' => $q]);
                }
                if ($s->hasDatabaseField('Content')) {
                    $this->AllObjects = $this->AllObjects->filterAny(['Content:partialMatch' => $q]);
                }
            }
            $this->extend('AllObjectsFilter', $this->AllObjects);
            $this->AllObjects = $this->AllObjects->filterByCallback(function($a) {
                return $a->canView();
            });
        }
        return $this->AllObjects;
    }
    
    /**
     * Count the total number of Objects viewable
     * 
     * @return int
     */
    public function TotalObjects()
    {
        return $this->AllObjects() && $this->AllObjects()->exists() ? $this->AllObjects()->count() : 0;
    }
    
    /**
     * Obtain the current Page number
     * 
     * @return int
     */
    public function CurrentPage()
    {
        // Page starts at 1 - needs to be an integer
        return filter_var($this->request->param("Page"), FILTER_VALIDATE_INT) ?: 1;
    }
    
    /**
     * Obtain the max page number
     * 
     * @return int
     */
    public function MaxPage()
    {
        $opp = 0 + filter_var($this->ObjectsPerPage, FILTER_VALIDATE_INT);
        return $opp > 0 ? ceil($this->TotalObjects() / $opp) : 1;
    }
    
    /**
     * Pagination for navigating to other pages.
     * 
     * @param type $spread Provide a buffer to dictate
     * how many pages around the current page number are
     * clickable, including the ellipses. Set to zero to
     * display no ellipses, 1 to display only ellipses,
     * and anything higher to display numbers between the
     * ellipses and the current page
     * @return \ArrayList
     */
    public function Pagination(int $spread = null)
    {
        if (!is_int($spread)) {
            $spread = filter_var($spread, FILTER_VALIDATE_INT);
        }
        if ($spread === false) {
            $spread = 5;
        }
        // Initialise the list and obtain the current page
        $pageList = [];
        $current = $this->CurrentPage();
        // Only supply pagination if max page is higher than 1
        if (($max = $this->MaxPage()) > 1) {
            // Add a prev link if current page is higher than 1
            if ($current > 1) {
                $prev = $current - 1;
                $pageList[] = [
                    'Page'  => '&larr;',
                    'Link'  => $this->Link("page/{$prev}", true),
                    'Class' => 'previous prev link'
                ];
            }
            // Loop through all possible page numbers 
            //** TODO: make more efficient by adding only specific situations **//
            for ($i = 1; $i <= $max; $i++) {
                // Current page - don't provide link
                if ($i == $current) {
                    $pageList[] = [
                        'Page'  => $i,
                        'Link'  => false,
                        'Class' => 'active current'
                    ];
                }
                // Links for First, last, and surrounding pages
                else if ($i == 1 || $i == $max || ($i > $current - $spread && $i < $current + $spread)) {
                    $pageList[] = [
                        'Page'  => $i,
                        'Link'  => $this->Link("page/{$i}", true),
                        'Class' => 'link'
                    ];
                }
                // Ellipses between surounding links and first/last links - display the number instead if it next to first or last
                else if (($i == $current - $spread || $i == $current + $spread) && $i != 1 && $i != $max) {
                    $pageList[] = [
                        'Page'  => ($i == 2 || $i == $max - 1) ? $i : '&hellip;',
                        'Link'  => $this->Link("page/{$i}", true),
                        'Class' => ($i == 2 || $i == $max - 1) ? 'link' : 'ellipses link'
                    ];
                }
            }
            // Add a next link if the current page is less than the max
            if ($current < $max) {
                $next = $current + 1;
                $pageList[] = [
                    'Page'  => '&rarr;',
                    'Link'  => $this->Link("page/{$next}", true),
                    'Class' => 'next link'
                ];
            }
        }
        
        // Convert array to an ArrayList for use within templates
        return new ArrayList($pageList);
    }
    
    /**
     * Obtain a paginated list of Objects
     * - Will be cached per request
     * 
     * @return ArrayList
     */
    public function PagedObjects()
    {
        if (!$this->PagedObjects) {
            $this->PagedObjects = $this->AllObjects();
            
            $page = $this->CurrentPage();
            $opp  = filter_var($this->ObjectsPerPage, FILTER_VALIDATE_INT);
            
            // calculate offset from page and number per page
            if ($this->PagedObjects && $this->PagedObjects->exists() && $opp > 0) {
                $this->PagedObjects  = $this->PagedObjects->limit($opp, $opp * ($page - 1));
            }
        }
        return $this->PagedObjects;
    }
    
    /**
     * 
     * @return DataObject
     */
    public function CurrentObject()
    {
        if (!$this->CurrentObject && $this->hasValidModelClass()) {
            $id = $this->request->param('ID') ?: $this->request->postVar('ID');
            // Add support for Rewritable
            $this->CurrentObject = Rewritable::get($this->ModelClass, $id);
        }
        return $this->CurrentObject;
    }
    
    public function Link($action = null, $includeSearchQuery = false)
    {
        $link = parent::Link($action);
        // Append search query to link if provided - for use with pagination
        if ($includeSearchQuery && ($q = $this->SearchQuery())) {
            $link .= '?q=' . Convert::raw2url($q);
        }
        return $link;
    }
}
