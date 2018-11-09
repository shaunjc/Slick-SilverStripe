<?php

namespace Slick\Address\Code;

use Slick\Code\Source;

/**
 * Source object for obtaining Season library data.
 * 
 * Used to translate from a month in a given hemisphere to a season.
 * 
 * All method arguments and output are Capitalised English strings representing
 * either a single month, a single season, or one of the hemispheres.
 * 
 * Ensure you refer to the constants when comparing results to the two available
 * hemispheres, passing arguments to the functions or validating any input data.
 * 
 * Default Usage:
 * <pre>
 * $month = date('F', $timestamp);
 * $hemisphere = $latitude ? ($latitude > 0 ? \Slick\Seasons::NORTHERN_HEMISPHERE : \Slick\Seasons::SOUTHERN_HEMISPHERE) : \Slick\Seasons::DEFAULT_HEMISPHERE;
 * // Static method:
 * $season = \Slick\Seasons::get($month, $hemisphere);
 * // Instance:
 * $season = (string) new \Slick\Seasons($month, $hemisphere);
 * </pre>
 */
class Seasons extends Source
{
    ///*** Class constants ***///
    
    /**
     * @var string Southern hemisphere name constant.
     */
    const SOUTHERN_HEMISPHERE = 'Southern';
    /**
     * @var string Northern hemisphere name constant.
     */
    const NORTHERN_HEMISPHERE = 'Northern';
    /**
     * @var string Default hemisphere: \Slick\Address\Code\Seasons::SOUTHERN_HEMISPHERE.
     */
    const DEFAULT_HEMISPHERE = self::SOUTHERN_HEMISPHERE;
    /**
     * @var string Alternate hemisphere: \Slick\Address\Code\Seasons::NORTHERN_HEMISPHERE
     */
    const ROTATED_HEMISPHERE = self::NORTHERN_HEMISPHERE;
    
    /**
     * @var array list of months with their matching seasons.
     * This list should match the DEFAULT_HEMISPHERE constant.
     */
    protected static $values = [
        'January'   => 'Summer',
        'February'  => 'Summer',
        'March'     => 'Autumn',
        'April'     => 'Autumn',
        'May'       => 'Autumn',
        'June'      => 'Winter',
        'July'      => 'Winter',
        'August'    => 'Winter',
        'September' => 'Spring',
        'October'   => 'Spring',
        'November'  => 'Spring',
        'December'  => 'Summer',
    ];
    
    ///*** Properties ***///
    
    /**
     * @var boolean private flag to denote if the array has been rotated.
     * It can be thought of as the of matching the non-default hemisphere.
     */
    protected $rotated = false;
    
    ///*** Constructors ***///
    
    /**
     * Constructor: Set the hemisphere and rotate the seasons as necessary.
     * @param string $hemisphere One of the two class constants.
     */
    public function __construct($month = null, $hemisphere = null)
    {
        parent::__construct($month);
        $this->setHemisphere($hemisphere);
    }
    
    ///*** Getters Setters and Transformations ***///
    
    /**
     * Seasons can be rotated by 180° depending on the current hemisphere.
     * 
     * Array will only rotate 180° or with a distance of 6, and a flag will be
     * set denotating whether it's rotated or not.
     * 
     * @param int $distance Ignored.
     */
    public function rotate($distance = 6)
    {
        $this->_values = static::array_rotate_value($this->values(), $this->count() / 2);
        $this->rotated = !$this->rotated;
    }
    
    /**
     * Set the hemisphere by rotating the array to match the hemisphere string.
     * 
     * @param string $hemisphere One of the two class constants.
     */
    public function setHemisphere($hemisphere)
    {
        if ($this->rotated === (static::DEFAULT_HEMISPHERE === $hemisphere)) {
            $this->rotate();
        }
    }
    
    /**
     * Return the current hemisphere based on the rotated flag. Match with the
     * class constants for consistency.
     * 
     * @return string One of the two class constants.
     */
    public function getHemisphere()
    {
        return $this->rotated ? static::ROTATED_HEMISPHERE : static::DEFAULT_HEMISPHERE;
    }
    
    /**
     * Get the season for the provided month.
     * 
     * @param string $month Capitalised English name of the selected month.
     * Equivalent to <code>date( 'F', $timestamp );</code>.
     * @param string $hemisphere One of the two class constants.
     * @return string the Capitalised English spelling of the matching season.
     */
    public function getSeason($month = null, $hemisphere = null)
    {
        if (isset($month)) {
            $this->index($month);
        }
        if (isset($hemisphere)) {
            $this->setHemisphere($hemisphere);
        }
        return (string) $this;
    }
    
    /**
     * Static function for getting the season provided a month and hemisphere.
     * 
     * @param string $month Capitalised English name of the selected month.
     * Equivalent to <code>date('F', $timestamp);</code>.
     * @param string $hemisphere One of the two class constants.
     * @return string the Capitalised English spelling of the matching season.
     */
    public static function get($month = null, $hemisphere = null)
    {
        return (string) new static($month, $hemisphere);
    }
    
    ///*** Serializable ***///
    
    /**
     * Record both Month and Hemisphere as an array on serialization.
     * 
     * @return string Serialized data.
     */
    public function serialize()
    {
        return serialize([$this->index(), $this->getHemisphere()]);
    }
    
    /**
     * Load both month and hemisphere when unserialized.
     * 
     * @param string $month Capitalised English name of the selected month.
     * @param string $hemisphere One of the two class constants.
     */
    public function __wakeup($month = null, $hemisphere = null)
    {
        parent::__wakeup($month);
        $this->setHemisphere($hemisphere);
    }
}
