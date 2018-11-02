<?php

namespace Slick\CMS\Model;

use SilverStripe\Control\Email\Email;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

class Enquiry extends DataObject
{
    private static $table_name = 'Slick_Enquiry';
    
    // Database Fields and 
    private static $db = [
        'Name'    => 'Varchar(255)',
        'Company' => 'Varchar(255)',
        'Email'   => 'Varchar(255)',
        'ContactTelephone' => 'Varchar(255)',
        'Message' => 'Text',
        'Status'  => 'Enum("new,sent,read","new")',
        'Sent'    => 'Boolean',
    ];
    private static $has_one = [
        'Member' => Member::class,
    ];
    
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        
        if (!$this->MemberID) {
            $member = Security::getCurrentUser();
            if ($member && $member->exists()) {
                $this->MemberID = $member->ID;
            }
        }
        if (!$this->MemberID && SiteConfig::current_site_config()->LinkOrCreateCustomerFromEnquiry) {
            $this->MemberID = ($member = Member::get()->find('Email', $this->Email)) ? $member->ID :  Member::create([
                'Name' => $this->Name,
                'Email' => $this->Email,
            ])->write();
        }
        
        if (!$this->Status || $this->Status === 'new') {
            $this->send();
        }
    }
    
    public function getFrontEndFields($params = null)
    {
        $fields = parent::getFrontEndFields($params);
        
        $fields->removeByName([
            'Sent',
            'Status',
        ]);
        
        return $fields;
    }
    
    public function send()
    {
        $site_config = SiteConfig::current_site_config();
        $from = $site_config->AdminNotificationEmail;
        $body = $this->renderWith('email/Enquiry');
        $subject = 'New ' . $site_config->Title . ' Customer Enquiry';
        
        $this->Sent = Email::create($from, $from, $subject, $body)->setReplyTo($this->Email, $this->Name)->send() === true;
        
        $this->Status = 'sent';
    }
}
