<?php

namespace Slick\CMS\View;

use DOMDocument;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;

use Slick\CMS\Control\Page;
use Slick\CMS\View\Layout;

class Icon extends DataObject
{
    // Table names and extensions.
    private static $table_name = 'Slick_Page_Icon';
    private static $extensions = [
        Versioned::class,
    ];
    
    // Database columns and relationships.
    private static $db = [
        'Title'       => 'Varchar(255)',
        'Link'        => 'Varchar(255)',
        'Description' => 'Text',
        'SortOrder'   => 'Int',
    ];
    private static $has_one = [
        'Layout' => Layout::class,
        'Page'   => Page::class,
        'Image'  => File::class,
    ];
    private static $owns = [
        'Image',
    ];
    
    // Other UI references.
    private static $casting = [
        'ImageTag' => 'HTMLText' 
    ];
    
    private static $singular_name = 'Icon';
    private static $plural_name   = 'Icons';
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->removeByName('LayoutID');
        $fields->removeByName('PageID');
        
        return $fields;
    }
    
    /**
     * Manually publish Images uploaded to this DataObject when it's published.
     */
    public function onBeforePublish()
    {
        if (($image = $this->Image()) && $image->exists()) {
            $image->publishRecursive();
        }
    }
    
    /**
     * Either generate an &lt;img/&gt; tag or place the SVG tag directly into
     * the page heirarchy.
     * @param type $ref
     * @return string
     */
    public function ImageTag($ref = '')
    {
        // Find Image.
        $image = $this->Image();
        if (!$image || !$image->exists()) {
            return '';
        }
        // Standard image - return tag using preview link.
        if ($image->IsImage) {
            return sprintf('<img src="%s" width="%s" height="%s" alt="%s" />',
                $image->PreviewLink,
                (int)$this->config()->get('asset_preview_width'),
                (int)$this->config()->get('asset_preview_height'),
                $this->Title
            );
        }
        // Mime Type indicates that it may possibly be an SVG.
        if (in_array($image->getMimeType(), ['text/html', 'text/xml', 'text/html+xml', 'text/svg', 'text/svg+xml', 'image/svg', 'image/xml', 'image/svg+xml'])) {
            // Try loading as a DOMDocument - remove excess whitespace.
            $svg = new DOMDocument;
            $svg->preserveWhiteSpace = false;
            $loaded = $svg->loadXML(
                // Most long winded way I've seen to load a simple file - no direct access to full file path :/
                Injector::inst()->get(AssetStore::class)->getAsString(
                    $image->getFilename(),
                    $image->getHash(),
                    $image->getVariant()
                )
            );
            if ($loaded) {
                if ('svg' === strtolower($svg->documentElement->tagName)) {
                    // Its document element is an SVG. Return as string to template.
                    return $svg->saveHTML();
                }
                $svgs = $svg->getElementsByTagName('svg');
                if ($svgs->length) {
                    foreach ($svgs as $svg) {
                        // Find an SVG with the supplied reference - return as string when found
                        if ($svg->localName === $ref) {
                            return $svg->ownerDocument->saveHTML($svg);
                        }
                    }
                    // Otherwise return the first SVG found.
                    return $svgs[0]->ownerDocument->saveHTML($svgs[0]);
                }
            }
        }
    }
    
    /**
     * Generate additional link attributes. External links will have the target
     * set to '_blank' and will also include a `rel="nofollow"` attribute.
     * @param boolean $return_string Default true return as a string. When false
     * the attributes will be returned as an associative array.
     * @return string|array|void Null when link is empty. An array when $return_string
     * is set to false, or a string otherwise. String or Array may be empty.
     */
    public function LinkAttributes($return_string = true)
    {
        // Break the Link into parts. Ignore empty links.
        $link = array_filter((array) parse_url($this->Link));
        if (!$link) {
            return;
        }
        
        // Initalise Attributes
        $attributes = [];
        if (array_key_exists('host', $link)) {
            // The host was supplied, so confirm it matches the site host.
            $url = array_filter((array) parse_url(Director::absoluteBaseURL()));
            $host = preg_replace('/www\./', '', array_key_exists('host', $url) ? $url['host'] : '');
            // Similar and subdomains should be ignored.
            if (false === stristr($link['host'], $host)) {
                // Different domains - Open new tab and nofollow.
                $attributes = [
                    'target' => '_blank',
                    'rel' => 'nofollow',
                ];
            }
        }
        
        // Generate attribute string or return array.
        return $return_string ? join(' ', array_map(function ($value, $key) {
            return sprintf('%s="%s"', $key, $value);
        }, $attributes)) : $attributes;
    }
}