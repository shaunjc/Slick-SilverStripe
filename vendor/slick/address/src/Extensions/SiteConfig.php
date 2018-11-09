<?php

namespace Slick\Address\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\TextField;

class SiteConfig extends Extension
{
    private static $db = [
        'IpStackApiKey' => 'Varchar(255)',
    ];
    
    public function updateCMSFields($fields) {
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('IpStackApiKey', 'ipstack API Key')
                ->setRightTitle('This key is necessary in order to determine the location of users. Register for an API key from ipstack.com.')
        ]);
        
        return $fields;
    }
}
