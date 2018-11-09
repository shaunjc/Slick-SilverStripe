<?php

namespace Slick\Control;

use SilverStripe\Control\HTTPResponse;

class MinifiedHTTPResponse extends HTTPResponse
{
    public function setBody($body)
    {
        // Generic whitespace removal - reduce all spaces and line breaks to a single space.
        $body = preg_replace('/[\s\t\r\n]+/i', ' ', $body);
        
        parent::setBody($body);
    }
}
