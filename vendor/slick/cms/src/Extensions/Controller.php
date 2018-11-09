<?php

namespace Slick\CMS\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

class Controller extends Extension
{
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
        set_error_handler(function(){/*Warnings and Notices Disabled*/});
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
     * Obtain a copyright string from the SiteConfig.
     * 
     * @return string
     */
    public function Copyright()
    {
        $config = SiteConfig::current_site_config();
        return $config->Copyright ?: $config->DefaultCopyright();
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
}
