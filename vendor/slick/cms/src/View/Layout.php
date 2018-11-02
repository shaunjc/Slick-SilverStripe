<?php

namespace Slick\CMS\View;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Versioned\Versioned;

use Slick\CMS\Control\Page;
use Slick\CMS\View\Icon;

use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class Layout extends DataObject
{
    // Table names and extensions.
    private static $table_name = 'Slick_Page_Layout';
    private static $extensions = [
        Versioned::class,
    ];
    
    // Database columns and relationships.
    private static $db = [
        'Name'         => 'Varchar(255)',
        'Content'      => 'HTMLText',
        'Template'     => 'Enum("Content,Content+Image,Image+Content,Icons,Listings,Services","Content")',
        'ImageStyle'   => 'Enum("Half,Full","Full")',
        'ContentWidth' => 'Enum("Grid,Wide,Full","Grid")',
        'SortOrder'          => 'Int',
    ];
    private static $has_one = [
        'Page'       => Page::class,
        'Image'      => Image::class,
        'BleedImage' => File::class,
    ];
    private static $has_many = [
        'Icons'     => Icon::class,
    ];
    private static $owns = [
        'Image',
        'Icons',
        'BleedImage',
    ];
    private static $owned_by = [
        'Page',
    ];
    
    // Other UI references.
    private static $default_sort = 'SortOrder ASC';
    private static $casting      = [
        'EmbedOnly' => 'HTMLText',
    ];
    
    /**
     * Update GridField to a RecordEditor instead of RelationEditor.
     * 
     * @return \SilverStripe\Forms\FieldList|\SilverStripe\Forms\FormField[]
     */
    public function getCMSFields() {
        $fields = parent::getCMSFields();
        
        $icons = $fields->fieldByName('Root.Icons.Icons');
        if ($icons && $icons instanceof GridField) {
            $icons->setConfig(
                GridFieldConfig_RecordEditor::create()
                    ->addComponent(
                        new GridFieldSortableRows('SortOrder')
                    )
            );
        }
        
        return $fields;
    }
    
    public function onBeforePublish()
    {
        if (($image = $this->Image()) && $image->exists()) {
            $image->publishRecursive();
        }
        if (($image = $this->BleedImage()) && $image->exists()) {
            $image->publishRecursive();
        }
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
     * Template Function: Show Call To Action
     * 
     * @return boolean True if both call to action text and
     * linkhave been saved. False otherwise.
     */
    public function HasCallToAction()
    {
        return $this->CallToActionText || $this->CallToActionLink;
    }
    
    public function IsFullWidth()
    {
        return in_array($this->Template, array('Embed'));
    }
    
    public function ShowWind()
    {
        return $this->BottomWindStyle && $this->BottomWindStyle !== 'None';
    }
    
    public function EmbedOnly()
    {
        if (preg_match('#(?:[htpsf]+:)?//(?:[a-z0-9_][a-z0-9_-]*[a-z0-9_]\.?|[a-z0-9_]+\.?)+(?:/[^\s\t\r\n\'"/\?]+)*(?:\?(?:[^\s\t\r\n\'"]*)){0,1}#i', $this->Content, $content)) {
            while (is_array($content)) {
                $content = reset($content);
            }
            return sprintf( '<iframe src="%s"></iframe>', $content );
        }
    }
    
    public function getTitle()
    {
        return $this->Name ?: $this->dbObject('Content')->FirstSentence() ?: $this->Template;
    }
    
    public function Title()
    {
        return $this->Name;
    }
    
    public function AdditionalContent()
    {
        return in_array($this->Template, ['Content','Icons','Listings','Services'])
            || ! $this->HasImage();
    }
    
    public function BackgroundImage()
    {
        return in_array($this->Template, ['Content','Icons','Listings','Services'])
            ? $this->Image() : false;
    }
    
    public function ImageFirst()
    {
        return in_array($this->Template, ['Image+Content']);
    }
}
