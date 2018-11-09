<?php

namespace Slick\Forms;

use SilverStripe\Forms\FormScaffolder as FS;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\ORM\DataObject;

use Slick\Extensions\Sortable;

use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class FormScaffolder extends FS
{
    public function getFieldList()
    {
        $fields = parent::getFieldList();
        // only add relational fields if an ID is present
        if ($this->obj->ID) {
            // add has_many relation fields
            if ($this->obj->hasMany()
                    && ($this->includeRelations === true || isset($this->includeRelations['has_many']))) {
                foreach ($this->obj->hasMany() as $relationship => $component) {
                    // Set one-many relations using the Record Editor by default (not relation editor).
                    $fields->dataFieldByName($relationship)
                        ->setConfig(GridFieldConfig_RecordEditor::create());
                    // Confirm if the GridFieldSortableRows class is present and that sub class is sortable.
                    if (!class_exists(GridFieldSortableRows::class)) {
                        continue;
                    }
                    $hasOneClass = DataObject::getSchema()->hasManyComponent(get_class($this->obj), $relationship);
                    $hasOneSingleton = singleton($hasOneClass);
                    if ($hasOneSingleton->hasExtension(Sortable::class)) {
                        // Additionally add GridFieldSortableRows.
                        $fields->dataFieldByName($relationship)->getConfig()
                            ->removeComponentsByType(GridFieldSortableRows::class)
                            ->addComponent(new GridFieldSortableRows(Sortable::SORTABLE_COLUMN_NAME));
                    }
                }
            }
            // Confirm if the GridFieldSortableRows class is present.
            if (false && class_exists(GridFieldSortableRows::class) && $this->obj->manyMany()
                    && ($this->includeRelations === true || isset($this->includeRelations['many_many']))) {
                foreach ($this->obj->manyMany() as $relationship => $component) {
                    // Confirm if the sub class is sortable.
                    $hasOneClass = DataObject::getSchema()->manyManyComponent(get_class($this->obj), $relationship);
                    $hasOneSingleton = singleton($hasOneClass['childClass']);
                    if ($hasOneSingleton->hasExtension(Sortable::class)) {
//                        // Additionally add GridFieldSortableRows.
//                        $fields->dataFieldByName($relationship)->getConfig()
//                            ->removeComponentsByType(GridFieldSortableRows::class)
//                            ->addComponent(new GridFieldSortableRows(Sortable::SORTABLE_COLUMN_NAME));
                    }
                }
            }
        }
        return $fields;
    }
}
