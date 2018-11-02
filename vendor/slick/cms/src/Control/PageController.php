<?php

namespace Slick\CMS\Control;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\Control\Director;
use SilverStripe\i18n\Data\Locales;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

use Slick\CMS\Control\SearchPage;
use Slick\CMS\Forms\SDropdownField;
use Slick\CMS\Model\Enquiry;
use Slick\CMS\Model\Subscription;

class PageController extends ContentController
{
    protected static $min_ext = '';
    
    private static $allowed_actions = [
        'index',
        'EnquiryForm',
        'SearchForm',
        'SubscriptionForm',
    ];
    
    // Template casting - case sensitive based on function name in templates.
    private static $casting = [
        'SVG' => 'HTMLText',
        'Svg' => 'HTMLText',
        'svg' => 'HTMLText',
    ];
    
    /**
     * Enable use of minified files on live and load requirements.
     */
    protected function init()
    {
        if (Director::isLive()) {
            static::$min_ext = '.min';
        }
        
        parent::init();
        
        $this->baseRequirements();
    }
    
    /**
     * Load Javascript and CSS onto the page.
     */
    protected function baseRequirements()
    {
        // Font Loader - Raleway - manually minified
        Requirements::customScript(
            'WebFontConfig={' .
                'google:{' .
                    'families:["Raleway:400,500,700"]' .
                '}' .
            '};' .
            '(function(d){' .
                'var w=d.createElement("script"),s=d.scripts[0];' .
                'w.src="https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js";' .
                'w.async=!0;' .
                's.parentNode.insertBefore(w,s);' .
            '})(document);'
        , 'WebFontLoader');
        
        // jQuery - always minified - defer causes issues in IE9.
        Requirements::javascript(ThemeResourceLoader::inst()->findThemedJavascript('libs/jquery.min', SSViewer::get_themes()));
        // Bundled JS File
        Requirements::javascript(ThemeResourceLoader::inst()->findThemedJavascript('dist/bundle' . static::$min_ext, SSViewer::get_themes()), ['defer' => true]);
        
        // Master CSS File
        Requirements::themedCSS('master' . static::$min_ext);
    }
    
    ///*** Template Functions ***///
    
    /**
     * Load an SVG directly into the DOM.
     * 
     * @uses DOMDocument to load the SVG element and remove excess whitespace.
     * CSS and javascript will not be minified.
     * 
     * @param string $name File Name
     * @return string SVG tag string.
     */
    public function SVG($name)
    {
        $path = Director::baseFolder() . '/' . ThemeResourceLoader::inst()->findThemedResource('images/' . $name . '.svg', SSViewer::get_themes());
        if (!$path || !file_exists($path) || !is_file($path) || !filesize($path)) {
            return;
        }
        
        // Try loading as a DOMDocument - remove excess whitespace.
        $svg = new DOMDocument;
        $svg->preserveWhiteSpace = false;
        set_error_handler(function(){echo'<!--';var_dump(error_get_last(),func_get_args());echo'-->';});
        $loaded = $svg->load($path);
        restore_error_handler();
        if ($loaded) {
            if ('svg' === strtolower($svg->documentElement->tagName)) {
                // Its document element is an SVG. Return as string to template.
                return $svg->saveHTML();
            }
            $svgs = $svg->getElementsByTagName('svg');
            if ($svgs->length) {
                // Return the first SVG found.
                return $svgs[0]->ownerDocument->saveHTML($svgs[0]);
            }
        }
    }
    
    /**
     * Obtain a list of social links from the current site config.
     * 
     * @return \SilverStripe\ORM\HasManyList|\Slick\SocialLink[]
     */
    public function SocialLinks()
    {
        return SiteConfig::current_site_config()->SocialLinks();
    }
    
    /**
     * Obtain a copyright string from the SiteConfig.
     * 
     * @return string
     */
    public function Copyright()
    {
        $config = SiteConfig::current_site_config();
        return $config->Copyright ?: $config->DefaultCopyright();
    }
    
    ///*** Forms ***///
    
    /**
     * Search Form
     * 
     * Typically appears in the header and redirects to a search page.
     * 
     * @return Form
     */
    public function SearchForm()
    {
        $fields = new fieldList([
            TextField::create('s', 'Search')
                ->setAttribute('placeholder', 'What are you looking for?'),
            FormAction::create('search', '')
                ->setUseButtonTag(true)
                ->addExtraClass('icn-search')
                ->setIcon('search'),
        ]);
        
        // Action needs to be within the fieldset.
        $actions = new FieldList([]);
        
        $form = Form::create($this, 'SearchForm', $fields, $actions)
            ->disableSecurityToken()
            ->setFormMethod('GET')
            ->addExtraClass('search-form');
        
        $page = Versioned::get_one_by_stage(SearchPage::class, Versioned::get_stage());
        if ($page && $page->exists()) {
            $page = ModelAsController::controller_for($page);
            $form->setFormAction($page->Link());
        }
        
        return $form;
    }
    
    /**
     * Specialised Enquiry Form.
     * 
     * Override fields and actions etc using extensions.
     * Ensure to override all three functions as required.
     * 
     * @return Form
     */
    public function EnquiryForm()
    {
        $fields = new FieldList([
            CompositeField::create([ // s-dropdown
                SDropdownField::create('Interest', 'I\'m interested in a', ['wind turbine', 'solar power system', 'solar hot water system'])
                    ->setFieldHolderTemplate('HomeContactFormDropdownHolder')
                    ->setTemplate('SDropdownField'),
                SDropdownField::create('Location', 'for', ['residential', 'business'])
                    ->setFieldHolderTemplate('HomeContactFormDropdownHolder')
                    ->setTemplate('SDropdownField'),
            ])->addExtraClass('s-dropdown')
                ->setTemplate('HomeContactCompositeField'),
            CompositeField::create([ // row r-g-3
                LiteralField::create('Image', '<div class="col c-1-4 hide-sm"><div class="interest-image"></div></div>'),
                CompositeField::create([ // col c-3-4 main-fields row r-g-4
                    CompositeField::create([ // col c-1-2
                        TextField::create('Name', '')->setAttribute('placeholder', 'Name'),
                        EmailField::create('Email', '')->setAttribute('placeholder', 'Email'),
                        TextField::create('Phone', '')->setAttribute('placeholder', 'Phone'),
                        DropdownField::create('Country', '', singleton(Locales::class)->getCountries(), singleton(Locales::class)->countryName('AUS'))
                            ->setEmptyString('Country')
                            ->addExtraClass('select'),
                    ])->addExtraClass('col c-1-2'),
                    CompositeField::create([ // col c-1-2
                        TextareaField::create('CommentsAndNotes', '')->setAttribute('placeholder', 'Message'),
                        FormAction::create('contactus', 'Contact Us')
                            ->setUseButtonTag(true)
                            ->addExtraClass('btn btn-primary'),
                    ])->addExtraClass('col c-1-2'),
                ])->addExtraClass('col c-3-4 main-fields row r-g-4'),
            ])->addExtraClass('row r-g-3'),
        ]);
        $this->extend('EnquiryFormFields', $fields);
        
        $actions = new FieldList([]);
        $this->extend('EnquiryFormActions', $actions);
        
        $validator = new RequiredFields([
            'Interest',
            'Location',
            'Name',
            'Email',
        ]);
        $this->extend('EnquiryFormValidator', $validator);
        
        return Form::create($this, 'EnquiryForm', $fields, $actions, $validator)
            ->addExtraClass('home-contact-form');
    }
    
    /**
     * Subscription Form.
     * 
     * Override fields and actions etc using extensions.
     * Ensure to override all three functions as required.
     * 
     * @return Form
     */
    public function SubscriptionForm()
    {
        $fields = new FieldList([
            EmailField::create('Email', 'Newsletter')
                ->setAttribute('placeholder', 'EmailAddress'),
            FormAction::create('subscribe', '')
                ->setUseButtonTag(true)
                ->setIcon('carrot-right')
                ->addExtraClass('btn-square icn-carrot-right'),
        ]);
        $this->extend('SubscriptionFormFields', $fields);
        
        // Action needs to be within the fieldset.
        $actions = new FieldList([]);
        $this->extend('SubscriptionFormActions', $actions);
        
        $validator = new RequiredFields([
            'Email',
        ]);
        $this->extend('SubscriptionFormValidator', $validator);
        
        return Form::create($this, 'SubscriptionForm', $fields, $actions, $validator)
            ->addExtraClass('newsletter-signup');
    }
    
    ///*** form Actions ***///
    
    /**
     * Saves an enquiry, which should send an email notification by default.
     * 
     * @param array $data
     * @param Form $form
     * @return HTTP_Response
     */
    public function contactus($data, Form $form)
    {
        // Create and save Enquiry from Form data.
        $enquiry = Enquiry::create();
        $form->saveInto($enquiry);
        $enquiry->write();
        
        // Set Form session message based on sent status and redirect back to previous page.
        if ($enquiry && $enquiry->exists() && $enquiry->Sent) {
            $form->sessionMessage('Thank you for your enquiry. You should expect to hear back from us shortly.', ValidationResult::TYPE_GOOD);
        }
        else {
            $form->sessionMessage('There was an issue sending your request. Please check your details and try again.', ValidationResult::TYPE_WARNING);
        }
        return $this->redirectBack();
    }
    
    /**
     * Saves a subscription, which should subscribe the user by default.
     * 
     * Use the Injector API to override the default subscription class
     * and subsequent subscription functionality.
     * <pre>
     * SilverStripe\Core\Injector\Injector:
     * &nbsp;Slick\Subscription:
     * &nbsp;&nbsp;class: Custom\Subscription\Class
     * </pre>
     * 
     * @param array $data
     * @param Form $form
     * @return HTTP_Response
     */
    public function subscribe($data, Form $form)
    {
        $subscription = Subscription::create();
        $form->saveInto($subscription);
        $subscription->write();
        
        $form->sessionMessage('Thank you for subscribing.', ValidationResult::TYPE_GOOD);
        $this->redirectBack();
    }
}
