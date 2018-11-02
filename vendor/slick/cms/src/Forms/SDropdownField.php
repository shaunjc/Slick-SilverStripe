<?php

namespace Slick\CMS\Forms;

use SilverStripe\Forms\DropdownField;

class SDropdownField extends DropdownField
{
    public function getHasEmptyDefault()
    {
        return $this->hasEmptyDefault;
    }
}
