<?php
namespace Slick\Address\Model;

use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Versioned\Versioned;

use Slick\Address\Code\States;
use Slick\Address\Model\Locality;

class Address extends DataObject
{
    // Class constants.
    const GOOGLE_MAP_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
    const IP_API_URL         = 'http://api.ipstack.com';
    
    // Table names and extensions.
    private static $table_name = 'Slick_Address';
    private static $extensions = [
        Versioned::class . '.versioned',
    ];
    
    // Database columns and relationships.
    private static $db = [
        // Address Lines - Choose to display either Line1 or StreetNumber and StreetName
        'Line1' => 'Varchar(255)',
        'Line2' => 'Varchar(255)',
        
        // Address parts
        'StreetNumber' => 'Varchar(255)',
        'StreetName'   => 'Varchar(255)',
        'Suburb'       => 'Varchar(255)',
        'Postcode'     => 'Varchar(255)',
        'State'        => 'Varchar(255)',
        'Country'      => 'Varchar(255)',
        
        // Geocoded data
        'Longitude'        => 'Varchar(255)',
        'Latitude'         => 'Varchar(255)',
        'FormattedAddress' => 'Varchar(255)',
        
        // Type
        'Type'        => 'Enum("billing,physical,postal,pickup,delivery,query,session","billing")',
        'AddressType' => 'Enum("Home,Office,Resturant,Train Station,Bus Stop,Landmark,Location","Home")',
        'Default'     => 'Boolean(0)',
        'SortOrder'   => 'Int',
        
        // Extra Data
        'Raw'                 => 'Text',
        'Notes'               => 'Text',
        'SpecialInstructions' => 'Text',
        
        // IP Details
        'IP'       => 'Varchar(255)',
        'HostName' => 'Varchar(255)',
        'ISP'      => 'Varchar(255)',
    ];
    private static $has_one = [
        'Member'   => Member::class,
        'Locality' => Locality::class,
    ];
    
    // Other UI references.
    private static $default_sort   = 'Default DESC, SortOrder ASC';
    private static $singular_name  = 'Address';
    private static $plural_name    = 'Addresss';
    private static $summary_fields = [
        'FormattedAddress',
        'Type',
    ];
    
    public function canView($member = null)
    {
        return parent::canView($member) || ($member && $member->ID == $this->MemberID);
    }
    
    public function canEdit($member = null)
    {
        return parent::canEdit($member) || ($member && $member->ID == $this->MemberID);
    }
    
    public function canDelete($member = null)
    {
        return parent::canDelete($member) || ($member && $member->ID == $this->MemberID);
    }
    
    public function canCreate($member = null, $context = array())
    {
        return parent::canCreate($member, $context);
    }
    
    /**
     * Process before saving to Database
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        
        // Update Line1 to be 'StreetName Streetnumber' if those values have been changed
        if (($this->isChanged('StreetNumber', 2) || $this->isChanged('StreetName', 2))
            && !$this->isChanged('Line1', 2)
        ) {
            $this->Line1 = "{$this->StreetNumber} {$this->StreetName}";
        }
        
        // GeoCode new address if any of the main parts have been modified
        if ((!$this->Latitude || !$this->Longitude ||
            $this->isChanged('Line1', 2)  || $this->isChanged('Line2', 2)    ||
            $this->isChanged('Suburb', 2) || $this->isChanged('Postcode', 2) ||
            $this->isChanged('State', 2)  || $this->isChanged('Country', 2))
            && $this->Line1 && $this->State
            && ($this->Suburb || $this->Postcode)
        ) {
            $this->geoCode();
        }
        
        if ($this->isChanged('IP')) {
            $this->geoCodeIP();
        }
        
        // Find or create a locality if the address changes or no locality has been saved.
        if ((!$this->LocalityID || !($locality = $this->Locality()) || ! $locality->exists() ||
            $this->isChanged('Suburb', 2) || $this->isChanged('Postcode', 2) ||
            $this->isChanged('State', 2)  || $this->isChanged('Country', 2)) &&
            ($this->Suburb && $this->Postcode && $this->State && $this->Country)
        ) {
            $args = [
                'Suburb'   => $this->Suburb,
                'Postcode' => $this->Postcode,
                'State'    => $this->State,
                'Country'  => $this->Country,
            ];
            $locality = Locality::get()->filter($args)->first();
            $this->LocalityID = $locality && $locality->exists()
                ? $locality->ID
                : Locality::create($args)->write();
        }
    }
    
    /**
     * Fields used when updating an address
     * 
     * @return FieldList
     */
    public function getAppFields($useLine1and2 = false)
    {
        $states = new States();
        $index = $states->search($this->State);
        $states->index(reset($index));
        
        $fields = new FieldList([
            $useLine1and2 ? TextField::create("Address[{$this->ID}][Line1]", 'Address Line 1', $this->Line1) : TextField::create("Address[{$this->ID}][StreetNumber]", 'Street Number', $this->StreetNumber),
            $useLine1and2 ? TextField::create("Address[{$this->ID}][Line2]", 'Address Line 2', $this->Line2) : TextField::create("Address[{$this->ID}][StreetName]", 'Street Name', $this->StreetName),
            TextField::create("Address[{$this->ID}][Suburb]", 'City / Suburb', $this->Suburb),
            TextField::create("Address[{$this->ID}][Postcode]", 'Postcode', $this->Postcode),
            DropdownField::create("Address[{$this->ID}][State]", 'State', $states->values(), reset($index))
                ->setEmptyString('- Select State -'),
            LiteralField::create("Address[{$this->ID}][Country]", 'Country: Australia')
        ]);
        
        if ( $this->Type == 'delivery' ) {
            $fields->push(TextareaField::create( "Address[{$this->ID}][SpecialInstructions]", 'Special Delivery Instructions', $this->SpecialInstructions));
        }
        
        return $fields;
    }
    
    /**
     * Use to set all fields to required.
     * Excludes Line2 and SpecialInstructions.
     * 
     * @param boolean $useLine1 needs to match $useLine1and2 from Address::getAppFields();
     * @return array List of primary address fields.
     */
    public function getRequiredFields($useLine1 = false)
    {
        return array_merge(
            $useLine1
                ? ["Address[{$this->ID}][Line1]"]
                : ["Address[{$this->ID}][StreetNumber]", "Address[{$this->ID}][StreetName]"],
            [
                "Address[{$this->ID}][Suburb]",
                "Address[{$this->ID}][Postcode]",
                "Address[{$this->ID}][State]",
                "Address[{$this->ID}][Country]",
            ]
        );
    }

    /**
     * Display Address object as a comma separated string with all parts.
     * 
     * Empty fields will be ignored.
     * 
     * @return string
     */
    public function __toString()
    {
        return implode(', ', array_filter(array(
            $this->Line1,
            $this->Line2,
            $this->Suburb,
            $this->Postcode,
            $this->State,
            $this->Country
        )));
    }
    
    /**
     * Function to use when displaying Address.
     * 
     * Will either show the formatted Address or 'Empty {$Type} Address'.
     * @return string.
     */
    public function Title()
    {
        return "{$this}" ?: sprintf('Empty %s Address', ucwords($this->Type));
    }
    
    /**
     * Connect to the Google API to obtain GeoCoded data
     */
    public function geoCode()
    {
        $url = self::GOOGLE_MAP_API_URL;
        $params = 'address=' . urlencode("{$this}");
        
        $result = Convert::json2obj(file_get_contents("{$url}?{$params}"));
        
        if ($result && is_object($result)
            && property_exists($result, 'status') && $result->status == 'OK'
            && property_exists($result, 'results') && $result->results)
        {
            while (($address = current($result->results)) && stristr($address->formatted_address, 'Australia') === false &&
                next($result->results));
            if (!$address) {
                $address = reset($result->results);
            }
            
            $this->Latitude = $address->geometry->location->lat;
            $this->Longitude = $address->geometry->location->lng;
            
            $this->FormattedAddress = $address->formatted_address;
        }
        
        return $this;
    }
    
    /**
     * Convert IP address to a physical address where possible.
     */
    public function geoCodeIP()
    {
        $url = static::IP_API_URL;
        
        $geoCode = Convert::json2array(file_get_contents("{$url}/{$this->IP}?access_key=" . SiteConfig::current_site_config()->IpStackApiKey));
        
        if ($geoCode) {
            if (array_key_exists('latitude', $geoCode)) {
                $this->Latitude = $geoCode['latitude'];
            }
            if (array_key_exists('longitude', $geoCode)) {
                $this->Longitude = $geoCode['longitude'];
            }
            if (array_key_exists('country_name', $geoCode)) {
                $this->Country = $geoCode['country_name'];
            }
            if (array_key_exists('region_name', $geoCode)) {
                $this->State = $geoCode['region_name'];
            }
            if (array_key_exists('city', $geoCode)) {
                $this->Suburb = $geoCode['city'];
            }
            if (array_key_exists('zip', $geoCode)) {
                $this->Postcode = $geoCode['zip'];
            }
        }
        
        return $this;
    }
    
    /**
     * Find Addresses within a certain range by including the calculations in
     * the SQL statement. Does not take vertical distance or driving on roads
     * into consideration
     * 
     * Use in conjunction with whereStatement() to improve performance.
     * Example:
     * <pre>
     * $addresses = $address->nearbyAddresses($distance)->where($address->whereStatement($distance));
     * </pre>
     * 
     * @param float $distance Range in KM between address locations
     * @return \SilverStripe\ORM\DataList|\Slick\Address[]
     */
    public function nearbyAddresses($distance = 1)
    {
        $addresses = static::get()->where(
#           "( 6371 * 2 * ATAN2( "
#               . "SQRT( POW( SIN( ( Address.Latitude - {$this->Latitude} ) * PI() / 360 ), 2 ) + COS( {$this->Latitude} * PI() / 180 ) * COS( Address.Latitude * PI() / 180 ) * POW( SIN( ( Address.Longitude - {$this->Longitude} ) * PI() / 360 ), 2 ) ), "
#               . "SQRT( 1 - ( POW( SIN( ( Address.Latitude - {$this->Latitude} ) * PI() / 360 ), 2 ) + COS( {$this->Latitude} * PI() / 180 ) * COS( Address.Latitude * PI() / 180 ) * POW( SIN( ( Address.Longitude - {$this->Longitude} ) * PI() / 360 ), 2 ) ) ) "
#           . ") ) <= {$distance}"
            sprintf(
                '(%5$s * ATAN2('
                    . 'SQRT(POW(SIN((Address.Latitude - %1$s) * PI() / 360), 2) + %4$s * COS(Address.Latitude * PI() / 180) * POW(SIN((Address.Longitude - %2$s) * PI() / 360), 2 )), '
                    . 'SQRT(1 - (POW(SIN((Address.Latitude - %1$s) * PI() / 360), 2) + %4$s * COS(Address.Latitude * PI() / 180) * POW(SIN((Address.Longitude - %2$s) * PI() / 360), 2)))'
                . ')) <= %3$s',
                (float) $this->Latitude,
                (float) $this->Longitude,
                (float) $distance,
                cos(static::deg2rad($this->Latitude)), // Take calculation out of SQL statement - unsure if SQL optimiser would take care of it automatically.
                6371 * 2
            )
        );
        
        return $addresses;
    }
    
    /**
     * Distance between current address and either another address or a set of lat/lng
     * 
     * This function accepts one or more arguments. The first argument will
     * either need to be an Address object, or the Latitude value. If the Latitude
     * is supplied, then the longitude needs to be submitted as the last argument.
     * 
     * @param \Slick\Address|float|float[] $address
     * @param float $lng
     * @return float
     */
    public function calculateDistance($address, $lng = null)
    {
        $args = func_get_args();
        if (!is_a($address, self::class)) {
            $address = null;
        }
        if (is_array($args[0])) {
            $args = $args[0];
        }
        
        $lat = $address ? $address->Latitude : reset($args);
        $lng = $address ? $address->Longitude : end($args);
        
        return static::getDistanceFromLatLonInKm($this->Latitude, $this->Longitude, $lat, $lng);
    }
    
    /**
     * Distance function given two points
     * 
     * Based off the Haversine formula to calculate distance along a straight
     * line between two points in km
     * 
     * Does not take roads or elevation distance into consideration
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float
     */
    private static function getDistanceFromLatLonInKm($lat1, $lon1, $lat2, $lon2)
    {
        $R = 6371; // Radius of the earth in km
        $dLat = static::deg2rad($lat2 - $lat1);
        $dLon = static::deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(static::deg2rad($lat1)) * cos(static::deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $d = $R * $c; // Distance in km
        return $d;
    }
    
    /**
     * Helper function to convert degrees into radians
     * 
     * @param float $deg
     * @return float
     */
    public static function deg2rad($deg)
    {
        return $deg * (pi() / 180);
    }
    
    /**
     * Helper function to get min and max latitude given a distance.
     * 
     * Examples:
     * <pre>
     * // Default usage:
     * list($min, $max) = $address->getMinMaxLat($distance);
     * // Alt Usage:
     * list($min, $max) = singleton(Address::class)->getMinMaxLat($distance, $latitude);
     * </pre>
     * 
     * @uses static::MinMaxLat(); to calculate the new coords.
     * 
     * @param float $distance Distance in km. Default: 1km.
     * @param float $latitude Original Latitude value to measure from.
     * Defaults to current Address's Latitude value.
     * @return array|float[] Array with the minimum and maximum latitude values.
     */
    public function getMinMaxLat($distance = 1, $latitude = null)
    {
        // Fall back to current Address's latitude value.
        if (!$latitude) {
            $latitude = $this->Latitude;
        }
        // Calculate and return min and max values.
        return static::MinMaxLat($distance, $latitude);
    }
    
    /**
     * Helper function to calculate the min and max latitude
     * given both a distance and starting latitude value.
     * 
     * Assumes values are calculated on Earth, which has a
     * radius of approximately 6371 km.
     * 
     * @param float $distance Distance in km.
     * @param float $latitude Original Latitude value to measure from.
     * @return array|float[] Array with the minimum and maximum latitude values.
     */
    public static function MinMaxLat($distance, $latitude)
    {
        // Latitude values are equal spaced.
        $latdiff = abs($distance / 6371) * (180 / pi());
        
        // Ensure latitude is not less than -180˚ (South pole)
        $min = max($latitude - $latdiff, -180);
        
        // Ensure latitude is not greater than 180˚ (North pole)
        $max = min($latitude + $latdiff, 180);
        
        return array($min, $max);
    }
    
    /**
     * Helper function to get min and max Longitude given a distance.
     * 
     * Examples:
     * <pre>
     * // Default usage:
     * list($min, $max) = $address->getMinMaxLng($distance);
     * // Alt Usage:
     * list($min, $max) = singleton(Address::class)->getMinMaxLng($distance, $latitude, $longitude);
     * </pre>
     * 
     * @uses static::MinMaxLng(); to calculate the new coords.
     * 
     * @param float $distance Distance in km.
     * @param float $latitude Original Latitude value to measure from.
     * Defaults to current Address's Latitude value unless
     * both $latitude and $longitude are supplied.
     * @param float $longitude Original Longitude value to measure from.
     * Defaults to current Address's Longitude value unless
     * both $latitude and $longitude are supplied.
     * @return array|float[] Array with the minimum and maximum latitude values.
     */
    public function getMinMaxLng($distance = 1, $latitude = null, $longitude = null)
    {
        // Fall back to current Address's latitude and longitude values if one is missing.
        if (!$latitude || !$longitude) {
            $latitude = $this->Latitude;
            $longitude = $this->Longitude;
        }
        
        return static::MinMaxLng($distance, $latitude, $longitude);
    }
    
    /**
     * Helper function to get min and max Longitude given a distance.
     * 
     * @param float $distance Distance in km.
     * @param float $latitude Original Latitude value to measure from.
     * @param float $longitude Original Longitude value to measure from.
     * @return array|float[] Array with the minimum and maximum latitude values.
     */
    public static function MinMaxLng($distance, $latitude, $longitude)
    {
        // Difference is proportional to the distance from equator.
        $lngdiff = ($distance / 6371) * (180 / pi()) / cos(static::deg2rad($latitude));
        
        // Ensure Longitude is not less than -180˚ - restart at 180 and continue round (international date line)
        $min = $longitude - $lngdiff;
        if ($min <= -180) {
            $min += 360;
        }
        
        // Ensure Latitude is not greater than 180˚ - restart at -180 and continue round (international date line)
        $max = $longitude + $lngdiff;
        if ($max > 180) {
            $max -= 360;
        }
        
        return array($min, $max);
    }
    
    /**
     * Generate an SQL WHERE statement to simplify locating
     * other Addresses within a distance.
     * 
     * Area formed by latitude and longitude values will form
     * a 'square' shape, and the distance to the corners will
     * be slightly higher than the distance to the edge by a
     * factor of approximately √2.
     * 
     * @param float $distance Distance to calculate min and max
     * latitude and longitude values.
     * @return string Fully qualified SQL statement.
     */
    public function whereStatement($distance = 1)
    {
        // These variables will always be an array with 2 floats.
        $lats = $this->getMinMaxLat($distance);
        $lngs = $this->getMinMaxLng($distance);
        
        // Ensure Address fits within latitude
        $where = "Address.Latitude >= {$lats[0]} AND Address.Latitude <= {$lats[1]} AND ";
        // Error correction for Latitude close to the poles (accept all Longitude values)
        if ($lats[0] != -180 && $lats[1] != 180) {
            $where .= $lngs[0] > $lngs[1] ?
                // Only check for higher than max or less than min for cases near the international date line
                "( Address.Longitude <= {$lngs[1]} OR Address.Longitude >= {$lngs[0]} )" :
                // Standard check for less than min and greater than max otherwise
                "Address.Longitude >= {$lngs[0]} AND Address.Longitude <= {$lngs[1]}";
        }
                
        return $where;
    }
    
    /**
     * Template function: Output address string.
     * 
     * @uses Address::__toString();
     * 
     * @return string May be empty. Use Title() instead where appropriate.
     */
    public function forTemplate()
    {
        return (string) $this;
    }
    
    /**
     * Find or Create an address based on a search query.
     * 
     * Supplied search query will be saved as Line1 of
     * the address, then Geo Coded as necessary.
     * 
     * @param string $s Address search query.
     * @return static New or existing Address.
     */
    public static function fromQuery($s)
    {
        $address = static::get()->filter('Type', 'query')->find('Line1', $s);
        
        if (!$address || !$address->exists()) {
            $address = static::create()->update(array(
                'Type' => 'query',
                'Line1' => $s
            ));
        }
        
        if (!$address->Latitude || !$address->Longitude) {
            $address->geoCode();
            $address->write();
        }
        
        return $address;
    }
    
    /**
     * Helper function to set selected Address as default, while removing the
     * Default flag from the others.
     */
    public function setAsDefault()
    {
        $member = $this->Member();
        if ($member && $member->exists()) {
            $addresses = $member->Addresses()->filter('Type', $this->Type)->filter('Default', true);
            if ($addresses && $addresses->exists()) {
                foreach ($addresses as $address) {
                    $address->Default = false;
                    $address->write();
                }
            }
        }
        $this->Default = true;
        $this->write();
    }
    
    /**
     * Helper function to get the current user's IP Address.
     * May cause issues when testing Locally.
     * @return string
     */
    public static function getIP()
    {
        $server_ip = $_SERVER['SERVER_ADDR'];
        foreach ([
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ] as $key) {
            if (array_key_exists($key, $_SERVER) === true && $_SERVER[$key] != $server_ip) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false && $ip !== $server_ip) {
                        return $ip;
                    }
                }
            }
        }
        // Return server IP address or local office static IP for now.
        if ( ( $ip = filter_var( $server_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) {
            return $ip;
        }
        return '211.26.34.121';
    }
}
