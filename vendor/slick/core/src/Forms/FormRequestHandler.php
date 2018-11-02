<?php

namespace Slick\Form;

use SilverStripe\Control\HTTPResponse_Exception;

/**
 * Updated Form Handler - Replaces existing handler using the Injector API.
 * Kept same class name for consistency.
 */
class FormRequestHandler extends \SilverStripe\Forms\FormRequestHandler
{
    /**
     * Replaces the existing \SilverStripe\Forms\FormRequestHandler::httpError()
     * method to pass $errorMessage to 'onBeforeHTTPError' extension methods.
     * 
     * @see \SilverStripe\Forms\FormRequestHandler for method arguments, return,
     * and other documentation.
     */
    public function httpError($errorCode, $errorMessage = null)
    {
        $request = $this->getRequest();
        
        // Adjust extend method calls to include $errorMessage.
        $this->extend("onBeforeHTTPError{$errorCode}", $request, $errorMessage);
        $this->extend('onBeforeHTTPError', $errorCode, $request, $errorMessage);
        
        throw new HTTPResponse_Exception($errorMessage, $errorCode);
    }
}
