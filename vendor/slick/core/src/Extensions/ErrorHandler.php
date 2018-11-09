<?php

namespace Slick\Extensions;

use SilverStripe\CMS\Controllers\ModelAsController;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;

class ErrorHandler extends Extension
{
    /**
     * Core function: Fires before the HTTPError() code executes
     * 
     * Locates an error page, updates some of the visible information
     * on the page, dumps the rendered html to the browser and stops
     * further processing.
     * 
     * @param int $errorCode Default: 404
     * @param \SilverStripe\Control\HTTPRequest $request
     * @param string $errorMessage Error message string.
     */
    public function onBeforeHTTPError($errorCode = 404, $request = null, $errorMessage = '')
    {
        // Get all error pages
        $pages = Versioned::get_by_stage(ErrorPage::class, Versioned::get_stage());
        if (!$pages || !$pages->exists()) {
            return;
        }
        // Find one with a matching error code
        $page = $pages->find('ErrorCode', $errorCode);
        if (!$page || !$page->exists()) {
            // Or at least a similar error code.
            $backup = floor($errorCode / 100);
            switch ($backup) {
                case 4:
                    $backup = 404;
                    break;
                case 5:
                    $backup = 500;
                    break;
            }
            $page = $pages->find('ErrorCode', $backup);
        }
        if (!$page || !$page->exists()) {
            // Fall back to any error page.
            $page = $pages->first();
        }
        if ($page && $page->exists()) {
            // Ensure theme is loaded
            Config::modify();
            Config::inst()->set(SSViewer::class, 'theme_enabled', true);
            Config::inst()->set(SSViewer::class, 'theme', SiteConfig::current_site_config()->Theme);
            
            // Set response message and prevent Breadcrumbs
            $page->Breadcrumbs = '';
            if (is_string($errorMessage) || is_a($errorMessage, DBField::class)) {
                $page->Content = $errorMessage;
            }
            
            // Add Error Page Controller to stack
            $controller = ModelAsController::controller_for($page);
            $controller->setRequest($request);
            $controller->pushCurrent();
            // Manually render the template and output the HTTPResponse.
            $controller->getResponse()
                ->setStatusCode($errorCode)
                ->setBody($controller->render()->value)
                ->output();
            exit;
        }
    }
}
