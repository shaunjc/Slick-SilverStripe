<?php

namespace Slick\ORM\FieldType;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBForeignKey;
use SilverStripe\ORM\Hierarchy\Hierarchy;

class HierarchicalForeignKey extends DBForeignKey
{
    public function scaffoldFormField($title = null, $params = null)
    {
        // Default field generation.
        $field = parent::scaffoldFormField($title, $params);
        
        // Numeric or Dropdown field means it's not a file and all class names are valid.
        if (is_a($field, NumericField::class) || is_a($field, DropdownField::class)) {
            // Re-initiaise class names and singleton instance.
            $relationName = substr($this->name, 0, -2);
            $hasOneClass = DataObject::getSchema()->hasOneComponent(get_class($this->object), $relationName);
            $hasOneSingleton = singleton($hasOneClass);
            if ($hasOneSingleton->hasExtension(Hierarchy::class)) {
                // The hasOneClass uses the Hierarchy extension. Replace the field with a new TreeDropdownField.
                $field = TreeDropdownField::create($this->name, $title, $hasOneClass);
            }
        }
        
        return $field;
    }
}
