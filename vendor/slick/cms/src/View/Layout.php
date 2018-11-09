<?php

namespace Slick\CMS\View;

// SilverStripe Framework and CMS classes.
use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Versioned\Versioned;

// Slick module classes.
use Slick\CMS\Control\Page;
use Slick\CMS\View\Icon;
use Slick\Extensions\Sortable;

// Third Party module classes.
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class Layout extends DataObject
{
    // Extensions.
    private static $extensions = [
        Sortable::class,
        Versioned::class,
    ];
    
    // Database tables and columns.
    private static $table_name = 'Slick_Page_Layout';
    private static $db = [
        'Name'         => 'Varchar(255)',
        'Content'      => 'HTMLText',
        'Template'     => 'Enum("Content,Content+Image,Image+Content,Icons,Listings,Services","Content")',
        'ImageStyle'   => 'Enum("Half,Full","Full")',
        'ContentWidth' => 'Enum("Grid,Wide,Full","Grid")',
    ];
    
    // Relationships.
    private static $has_one = [
        'BleedImage' => File::class,
        'Image'      => Image::class,
        'Page'       => Page::class,
    ];
    private static $has_many = [
        'Icons'     => Icon::class,
    ];
    private static $owns = [
        'BleedImage',
        'Icons',
        'Image',
    ];
    private static $owned_by = [
        'Page',
    ];
    private static $cascade_deletes = [
        'Icons',
    ];
    
    // UI config.
    private static $default_sort  = 'SortOrder ASC';
    private static $singular_name = 'Page Layout';
    private static $plural_name   = 'Page Layouts';
    private static $casting = [
        'EmbedOnly' => 'HTMLText',
    ];
    
    /**
     * Update GridField to a RecordEditor instead of RelationEditor.
     * 
     * @return \SilverStripe\Forms\FieldList|\SilverStripe\Forms\FormField[]
     */
    public function getCMSFields() {
        $fields = parent::getCMSFields();
        
        $fields->removeByName([
            'PageID',
            'LinkTracking',
            'FileTracking',
        ]);
        
        return $fields;
    }
    
    /**
     * Generate a list of classes for the current layout.
     * 
     * Most items would be added to a layout if they have
     * been entered or saved, but these classes will ensure
     * that each one is styled appropriately.
     * 
     * @param array $classes Default list of classes. New
     * classes will be appended. Scalars will be converted.
     * @param string $return Do not provide this argument,
     * or supply either the integer 0 or the string 'string'
     * to return the list of classes as a string joined by
     * spaces. Any other value would cause the list to be
     * returned as an array.
     * @return array|string List of classes. See the $return
     * parameter for more information about the returned vaues.
     */
    public function CSSClasses($classes = array(), $return = 'string')
    {
        settype($classes, 'array');
        $classes[] = 'layout';
        $classes[] = 'template-' . str_replace('+', '-', strtolower($this->Template));
        $classes[] = 'image-' . ($this->ImageFirst() ? 'left' : 'right');
        $classes[] = 'content-width-' . $this->ContentWidth;
        if ($this->HasImage()) {
            $classes[] = 'has-image';
        }
        if ($this->HasBleedImage()) {
            $classes[] = 'has-bleed-image';
        }
        return $return == 'string' ? implode(' ', $classes) : $classes;
    }
    
    /**
     * Template Function: Has Image.
     * 
     * @return boolean True if template option includes an image and
     * an image is published to the current stage. False otherwise.
     */
    public function HasImage()
    {
        return in_array($this->Template, array('Content+Image', 'Image+Content')) && $this->Image() && $this->Image()->exists();
    }
    
    /**
     * Template Function: Bleed Image Exists
     * 
     * @return boolean True if Bleed image exists
     * and has been published to the current stage.
     */
    public function HasBleedImage()
    {
        return $this->BleedImage() && $this->BleedImage()->exists();
    }
    
    /**
     * Template Function: Show Icons
     * 
     * @return boolean True if template option is set to Icons
     * and Icons have been published. Does not check to see if
     * each Icon has a published image. False otherwise.
     */
    public function HasIcons()
    {
        return in_array($this->Template, array('Icons')) && $this->Icons() && $this->Icons()->exists();
    }
    
    /**
     * Display a title in the CMS based on the name or content.
     * @return string
     */
    public function getTitle()
    {
        return $this->Name ?: $this->dbObject('Content')->FirstSentence() ?: $this->Template;
    }
    
    /**
     * Display a title in the CMS based on the name.
     * @return type
     */
    public function Title()
    {
        return $this->Name;
    }
    
    /**
     * Helper function to determine if content shows before the layout rows.
     * @return boolean
     */
    public function AdditionalContent()
    {
        return in_array($this->Template, ['Content','Icons','Listings','Services'])
            || ! $this->HasImage();
    }
    
    /**
     * Helper function to determine if the image is a background image or not.
     * @return Image|boolean
     */
    public function BackgroundImage()
    {
        return in_array($this->Template, ['Content','Icons','Listings','Services'])
            ? $this->Image() : false;
    }
    
    /**
     * Helper function to determine if the image appears before/after content.
     * @return boolean
     */
    public function ImageFirst()
    {
        return in_array($this->Template, ['Image+Content']);
    }
}
