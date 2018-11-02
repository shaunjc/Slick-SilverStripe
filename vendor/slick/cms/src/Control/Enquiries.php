<?php

namespace Slick\CMS\Control;

use SilverStripe\Admin\ModelAdmin;

use Slick\CMS\Model\Enquiry;

class Enquiries extends ModelAdmin
{
    private static $managed_models = [
        Enquiry::class,
    ];
    
    private static $url_segment = 'enquiries';
    
    private static $menu_title = 'Enquiries';
}
