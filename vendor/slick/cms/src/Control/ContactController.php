<?php

namespace Slick\CMS\Control;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;

use Slick\CMS\Control\PageController;
use Slick\CMS\Model\Enquiry;

class ContactController extends PageController
{
    private $allowed_actions = [
        'Form',
    ];
    
    public function Form()
    {
        return Form::create(
            $this,
            'Form',
            Enquiry::inst()->getFrontEndFields(),
            FieldList::create([
                FormAction::create('Submit', 'Submit'),
            ]),
            RequiredFields::create([
                'Name',
                'Company',
                'Email',
                'Message',
            ])
        );
    }
    
    public function Submit($data = array(), $form = null)
    {
        if (!is_a($form, Form::class)) {
            $form = $this->Form();
        }
        
        $enquiry = Enquiry::create();
        $form->saveInto($enquiry);
        $enquiry->write();
        
        return $this->redirect($this->Link());
    }
}
