<?php

namespace Slick\Address\Control;

use SilverStripe\Admin\ModelAdmin;

use Slick\Address\Model\Address;
use Slick\Address\Model\Locality;

class Admin extends ModelAdmin
{
    private static $menu_title = 'Addresses';
    private static $url_segment = 'addresses';
    private static $managed_models = [
        Address::class,
        Locality::class,
    ];
}