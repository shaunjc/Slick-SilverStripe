<?php

namespace Slick\Extensions;

// SilverStripe framework and CMS classes.
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Parsers\URLSegmentFilter;

// Slick classes
use Slick\Extensions\DataExtension;

/**
 * Rewritable Class
 * 
 * Adds URL segments to DataObjects, ensures that they're unique,
 * and includes functions for getting the URL Path or the object
 * from a provided URL path.
 * 
 * May throw user errors when used to extend non-dataobjects
 */
class Rewritable extends DataExtension
{
    /**
     * Add URLSegment to the DataObject
     * @var array
     */
    private static $db = [
        'URLSegment' => 'Varchar(255)'
    ];
    
    /**
     * Ensure URL Segment is present and not an integer
     * 
     * Extra checks are present to ensure that URL Segment
     * is unique for the current class. This does not take
     * child classes into consideration.
     */
    public function onBeforeWrite()
    {
        // Generate from Title or Name as necessary - will not change in this manner once set
        if (!$this->owner->URLSegment && ($this->owner->Title || $this->owner->Name)) {
            $this->owner->URLSegment = $this->owner->Title ?: $this->owner->Name;
        }
        
        // Run through filter to ensure it is encoded correctly
        if ($this->owner->isChanged('URLSegment', 2)) {
            $this->owner->URLSegment = URLSegmentFilter::create()->filter($this->owner->URLSegment);
        }
        
        // Set to null if only an integer to prevent confusion when using IDs
        if (filter_var($this->owner->URLSegment, FILTER_VALIDATE_INT) == $this->owner->URLSegment) {
            $this->owner->URLSegment = null;
        }
        
        // Ensure URLSegment is unique (in relation to other DataObjects with the same class family)
        $index = 0;
        if (($urlSegment = $this->owner->URLSegment)) {
            $baseClass = DataObject::getSchema()->baseDataClass($this->owner->ClassName);
            while (($object = DataObject::get($baseClass)->exclude(['ID' => $this->owner->ID])->find('URLSegment', $urlSegment)) && $object->exists()) {
                $urlSegment = $this->owner->URLSegment . '-' . ++$index;
            }
        }
        
        // Update URLSegment to the last successful value if at least one iteration was used
        if ($index > 0) {
            $this->owner->URLSegment .= '-' . $index;
        }
    }
    
    /**
     * Reposition or Append the URLSegment field.
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // $create is true when field is not found.
        if(($create = !($urlSegment = $fields->dataFieldByName('URLSegment')))) {
            // Create a new field
            $urlSegment = TextField::create('URLSegment', 'URL Segment');
        }
        
        // Insert after a field called Title or Name.
        if ($fields->dataFieldByName('Title')) {
            $fields->insertAfter('Title', $urlSegment);
        } else
        if ($fields->dataFieldByName('Name')) {
            $fields->insertAfter('Name', $urlSegment);
        } else
        // Insert before a field called Content or Description.
        if ($fields->dataFieldByName('Content')) {
            $fields->insertBefore('Content', $urlSegment);
        } else
        if ($fields->dataFieldByName('Description')) {
            $fields->insertBefore('Description', $urlSegment);
        } else
        // Append to the Main tab or form when the field is newly created. Do not reposition otherwise.
        if ($create) {
            if ($fields->fieldByName('Root.Main')) {
                $fields->addFieldsToTab('Root.Main', [
                    $urlSegment,
                ]);
            }
            else
            {
                $fields->push($urlSegment);
            }
        }
    }
    
    /**
     * Template Function: Get URL Segment or ID for links
     * 
     * Use in conjunction with $Link, for example:
     * PHP: $controller_or_page->Link( "url/". $object->URLPath() );
     * SS:  $Top.Link()/url/$URLPath
     * 
     * @return int|string
     */
    public function URLPath($hierarchical = true, $databaseField = 'URLSegment')
    {
        // Standard path
        $path = $this->owner->$databaseField ? $this->owner->$databaseField : $this->owner->ID;
        // Current class is hierarchical and its parent is also rewritable.
        if ($hierarchical && $this->owner->hasExtension(Hierarchy::class)
            && ($parent = $this->owner->Parent()) && $parent->exists()
            && $parent->hasExtension(Rewritable::class)) {
            // Prepend parent URL Path. Should populate entire DataObject ancestory.
            $path = "{$parent->URLPath()}/{$path}";
        }
        return $path;
    }
    
    /**
     * Controller Function: Get URL Segment or ID for links
     * where the DataObject is not known to extend Rewritable
     * 
     * @param DataObject $object
     * @return string
     */
    public static function Path($object, $databaseField = 'URLSegment')
    {
        return $object->hasDatabaseField($databaseField) && $object->$databaseField
            ? $object->$databaseField
            : $object->ID;
    }
    
    /**
     * Static function to get the DataObject from the URL
     * 
     * Pass both ClassName and Path, for example:
     * PHP: Rewritable::get( $classname, $path );
     * 
     * @uses Rewritable::fromPath() Creates singleton
     * to prevent errors with table column not found.
     * 
     * @param string $classname
     * @param string|int $path
     * @return DataObject singleton if no path specified,
     * DataObject if found from path, or null if not found.
     */
    public static function get($classname, $path = '', $databaseField = 'URLSegment')
    {
        $singleton = singleton($classname);
        if ($singleton) {
            if ($singleton->hasMethod('fromPath') && $path) {
                // Is extended by a Rewritable class
                return $singleton->fromPath($path, $databaseField);
            }
            $objects = $singleton->hasExtension(Versioned::class)
                ? Versioned::get_by_stage($classname, Versioned::get_stage())
                : DataObject::get($classname);
            if ($singleton->hasDatabaseField($databaseField) && !($item = $objects->find($databaseField, $path)) && ! $item->exists()) {
                $item = $objects->byID($path);
            }
            return $item;
        }
        return $singleton;
    }
    
    /**
     * Function to get the DataObject from the URL
     * 
     * Use with singleton, for example:
     * PHP: singleton($classname)->fromPath($path);
     * 
     * @param string|int $path URLSegment or ID
     * @return \SilverStripe\ORM\DataObject|null
     */
    public function fromPath($path, $databaseField = 'URLSegment')
    {
        // Prepare a DataList based on whether the class extends Versioned
        $objects = $this->owner->hasExtension(Versioned::class)
            ? Versioned::get_by_stage($this->owner->ClassName, Versioned::get_stage())
            : DataObject::get($this->owner->ClassName);
        // URLSegment should not be an integer - interpret as ID
        if (($id = filter_var($path, FILTER_VALIDATE_INT)) && $id == $path) {
            return $objects->byID($id);
        }
        // Do not filter by URLSegment unless the class has it as a field.
        if ($this->owner->hasDatabaseField($databaseField)) {
            return $objects->find($databaseField, $path);
        }
        // Unknown path variable.
    }
}
