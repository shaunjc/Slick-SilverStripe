<?php

namespace Slick\View;

use SilverStripe\View\SSTemplateParser;

/**
 * Extends the machine generated SSTemplateParser in order to minify cached
 * templates. Oerride the existing SSTemplateParser using the Injector API.
 * 
 * Run the query ?flush=1 after making changes or after the class is enabled
 * or disabled, in order to rebuild the templates.
 */
class MinifiedTemplateParser extends SSTemplateParser
{
    /**
     * the method compileString is called from SSViewer directly, and should
     * be included in every version of the generated SSTemplateParser class.
     * 
     * Uses the machine generated class to compile the code before it trims the
     * HTML code that is inserted using the following: <code>$val .= '';</code>
     * 
     * Excess whitespace and line breaks in the HTML code are reduced to a single
     * space. WYSIWYG content, and HTML inserted by variables should be ignored.
     * 
     * @see \SilverStripe\View\SSTemplateParser::compileString(); for the return
     * and parameter descriptions, as well as the full method description.
     * 
     * @param string $string
     * @param string $templateName
     * @param boolean $includeDebuggingComments
     * @param boolean $topTemplate
     * @return string
     */
    public function compileString($string, $templateName = "", $includeDebuggingComments = false, $topTemplate = true)
    {
        $code = parent::compileString($string, $templateName, $includeDebuggingComments, $topTemplate);
        
        // Minify HTML code from templates. Ignores content from WYSIWYG editors etc.
        $minified = preg_replace_callback("/(\\\$val \.= ')((?:\\\'|[\s\t\r\n]|[^'])*)(';)/", function($matches) {
            // Double preg_replace used to make sure we only replace whitespace within the html.
            $matches[2] = preg_replace('/[\s\t\r\n]+/', ' ', $matches[2]);
            return $matches[1] . $matches[2] . $matches[3];
        }, $code) ?: preg_replace('/[\s\t\r\n]+/', ' ', $code); // Fall back when preg_replace_callback returns an empty string.
        
        // Fall back in case preg_replace also returns an empty string.
        return $minified ?: $code;
    }
}
