<?php

namespace Slick\Address\Code;

use Slick\Code\Source;

class States extends Source
{
    ///*** Class constants ***///
    
    /**
     * Primary array. Will be copied to the property when object is initialised.
     * 
     * @var array List of Australian States and Territories with matching codes.
     */
    protected static $values = [
        "ACT" => "Australia Capital Teritory",
        "NSW" => "New South Wales",
        "NT"  => "Northern Territory",
        "QLD" => "Queensland",
        "SA"  => "South Australia",
        "TAS" => "Tasmania",
        "VIC" => "Victoria",
        "WA"  => "Western Australia",
    ];
    
    ///*** Setters and Getters ***///
    
    /**
     * Sets the index and rotates the array to match.
     * 
     * @param scalar $key
     * @return string The index for further processing.
     */
    public function index($key = null)
    {
        $this->rotate(parent::index(strtoupper((string) $key)));
        return $key;
    }
    
    /**
     * Rotate array so that the selected index is the first one in the list.
     * 
     * @param scalar $key The selected state code.
     * @return \Slick\Address\AustralianStates Current object for further processing.
     */
    public function rotate($key = null)
    {
        // Default to current index if not supplied.
        if (is_null($key)) {
            $key = $this->_index;
        }
        // Double check the key exists and it's not the first one.
        if (($index = $this->offsetIndex($key)) > 0) {
            $this->_values = static::array_rotate_assoc($this->_values, $index);
        }
        return $this;
    }
}