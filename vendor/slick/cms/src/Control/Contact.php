<?php

namespace Slick\CMS\Control;

use Slick\CMS\Control\Page;

class Contact extends Page
{
    private static $table_name = 'Slick_Contact_Page';
    
    private static $db = [
        'FormTitle' => 'Varchar(255)',
    ];
}
