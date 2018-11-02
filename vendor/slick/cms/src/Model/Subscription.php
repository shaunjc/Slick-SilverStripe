<?php

namespace Slick\CMS\Model;

use SilverStripe\ORM\DataObject;

class Subscription extends DataObject
{
    /**
     * Status successful constant. Use when validating Statuses. Example:
     * <code>return $subscription->Status === Subscription::STATUS_GOOD</code>
     * @var string
     */
    const STATUS_GOOD = 'Subscribed';
    /**
     * Status unsuccessful constant. Use when validating Statuses. Example:
     * <code>return $subscription->Status === Subscription::STATUS_BAD</code>
     * @var string
     */
    const STATUS_BAD  = 'Error';
    
    private static $table_name = 'Slick_Subscription';
    
    private static $db = [
        'Email'      => 'Varchar(255)',
        'Status'     => 'Enum("New,Subscribed,Error","New")',
        'Subscribed' => 'Boolean(0)',
    ];
    
    /**
     * Subscribe the user when Status is new and Subscription is being saved.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        
        if ($this->Status === 'New') {
            $this->subscribe();
        }
    }
    
    /**
     * Subscribe the user and set the status based on the result.
     */
    public function subscribe()
    {
        try {
            ///*** Do subscription process here ***///
            $this->extend('doSubscription');
        } catch (Exception $ex) {
            $this->Subscribed = false;
        }
        
        $this->Status = $this->Subscribed ? static::STATUS_GOOD : static::STATUS_BAD;
    }
}
