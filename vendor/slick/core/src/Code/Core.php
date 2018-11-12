<?php

namespace Slick\Code;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use LogicException;
use Serializable;

/**
 * Class: Arr
 * 
 * Reusable array for accessing and manipulating arrays
 * as objects, and objects as arrays.
 */
class Arr implements ArrayAccess, Iterator, Countable, JsonSerializable
{
    ///*** Properties ***///
    
    /**
     * Protected variable for storing
     * and manipulating an array.
     * 
     * @var array [mixed]
     */
    protected $_values = [];
    
    ///*** Constructor ***///
    
    /**
     * Constructor
     * 
     * @param array $values
     */
    public function __construct($values = null)
    {
        $this->values((array) $values);
    }
    
    ///*** Getters and Setters ***///
    
    /**
     * Sets and gets the current list of values as an array
     * 
     * @param array $values
     * @return array
     */
    public function values(array $values = null)
    {
        if ($values) {
            $this->_values = (array) $values;
        }
        return $this->_values;
    }
    
    /**
     * Magic Getter for string conversion
     * 
     * @uses Arr::current(); Implemented from Iterator.
     * 
     * @return string
     * The current array value as a string.
     */
    public function __toString()
    {
        return (string) $this->current();
    }
    
    /**
     * Alias of values()
     * 
     * @return array
     */
    public function __toArray()
    {
        return (array) $this->values();
    }
    
    /**
     * Magic Method GET
     * 
     * Obtains a value from the array as
     * though it is a property of the object
     * 
     * @uses Arr::offsetGet(); Implemented from ArrayAccess.
     * 
     * @param scalar $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->offsetGet($name);
    }
    
    /**
     * Magic Method SET
     * 
     * Sets the value to the array for the
     * specified offset.
     * 
     * @uses Arr::offsetSet(); Implemented from ArrayAccess.
     * 
     * @param scalar $name
     * @param mixed $value
     * @return mixed
     * Returns the set value for further processing.
     */
    public function __set($name, $value)
    {
        return $this->offsetSet($name, $value);
    }
    
	/**
     * Magic Method ISSET
     * 
     * Determines if the array key is set.
     * 
     * @uses Arr::offsetExists(); Implemented from ArrayAccess.
     * 
     * @param scalar $name
     * @return boolean
     */
	public function __isset($name)
    {
		return $this->offsetExists($name);
	}
	
    /**
     * Magic Method UNSET
     * 
     * Unsets the requested key.
     * 
     * @uses Arr::offsetUnset(); Implemented from ArrayAccess.
     * 
     * @param scalar $name
     */
	public function __unset($name)
    {
		$this->offsetUnset($name);
	}
    
    /**
     * Magic static constructor
     * 
     * @uses static::values() to determine
     * if values need to be set
     * 
     * @param array $properties
     * @return Arr
     */
    public static function __set_state($properties)
    {
        $self = new static;
        if (array_key_exists("_values", $properties)) {
            $self->values($properties["_values"]);
        }
        return $self;
    }
    
    /**
     * Calls any array_{$func}($arr, ...$args) function by calling either
     * $arr->{$func}(...$args) or $arr->{"array_$func"}(...$args).
     * 
     * Inserts the $_values property as the first parameter of the function.
     * Core functions don't typically declare parameter types, so we can't use
     * reflection classes to determine which argument needs to be an array.
     * 
     * The result will not be an Arr instance, as not all array functions will
     * always return an array by default, for instace `array_sum` should always
     * return an integer.
     * 
     * Some array functions can manipulate the original data. Care should be
     * taken when the data should not be changed. Example: `array_pop`.
     * 
     * Example:
     * <pre>
     * $result = $arr->array_reverse();
     * $result === array_reverse($arr->values()); // True
     * is_a($result, Arr::class); // False.
     * is_array($result); // True or False.
     * </pre>
     * 
     * Extend the class to create additional array methods as necessary.
     * 
     * @throws LogicException When method does not equate to an array function
     * or insufficient arguments are supplied.
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (function_exists(($func = "array_$name")) || (function_exists($name) && stristr(($func = $name), 'array_') === 0)) {
            $reflection = new ReflectionFunction($func);
            if (($required = $reflection->getNumberOfRequiredParameters()) > ($supplied = count($arguments) + 1)) {
                // Throw a LogicException if there are insufficient arguments for the array function.
                throw new LogicException(sprintf(
                    'Not enough arguments to call function %1$s. '
                    . 'Required Parameters: %2$s. '
                    . 'Total parameters including source array: %3$s.',
                    $func,
                    $required,
                    $supplied
                ));
            }
            // Prepend $_values to the list of arguments.
            array_unshift($arguments, $this->_values);
            return call_user_func_array($func, $arguments);
        }
        // Throw a LogicException when the function does not exist.
        throw new LogicException(sprintf(
            'The methods and functions %1$s:%2$s(), %2$s() and array_%2$s() do not exist or are not callable using this method.',
            get_called_class(),
            $name
        ));
    }
    
    /**
     * Get Keys that match the specified value(s)
     * 
     * @param mixed $values
     * @return array|scalar[]
     */
    public function search($values)
    {
        return array_keys(array_intersect($this->_values, (array) $values));
    }
    
    /**
     * Find the position a given key.
     * 
     * Performs a strict search to match types with values.
     * 
     * @param scalar $key The key to search for.
     * @return int The position of the key in the array.
     * Defaults to -1 if not found.
     */
    public function offsetIndex($key)
    {
        return array_search($key, array_keys($this->_values), true);
    }
    
    /**
     * Use implode to join the array parts into a single string.
     * 
     * @param string $glue
     * @return string
     */
    public function implode($glue = ', ')
    {
        return implode($glue, $this->_values);
    }
    
    ///*** Array Access ***///
    
    /**
     * Check to see if key exists.
     * 
     * @uses static::offsetIndex(); Forces strict checking in order to
     * prevent matching strings with 0 and empty strings with false etc
     * as well as prevent warnings from being thrown if a non-scalar is
     * supplied.
     * 
     * @param scalar $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->offsetIndex($offset) !== false;
    }
    
    /**
     * Obtain value for a given offset
     * 
     * @param scalar $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->_values[$offset];
    }
    
    /**
     * Sets the value for the specific offset and
     * returns the value for further processing.
     * 
     * @param scalar $offset
     * @param mixed $value
     * @return mixed
     * The original value being set
     */
    public function offsetSet($offset, $value)
    {
		return ($this->_values[$offset] = $value);
	}
    
    /**
     * Unsets the value for the specified offset
     * 
     * @param scalar $offset
     * @return void|null
     */
    public function offsetUnset($offset)
    {
		if (array_key_exists($offset, $this->_values)) {
            unset($this->_values[$offset]);
        }
	}
    
    ///*** Iterator ***///
    
    /**
     * Uses standard php array implementation
     * for obtaining the current value within
     * loops.
     * 
     * @return mixed
     */
    public function current()
    {
        return current($this->_values);
    }
    
    /**
     * Uses standard php array implementation
     * for obtaining the current key within
     * loops.
     * 
     * @return scalar
     */
    public function key()
    {
        return key($this->_values);
    }
    
    /**
     * Uses standard php array implementation
     * for progressing the array to the next
     * iteration.
     * 
     * @return mixed
     * The next value for use with processing
     * when this function is called directly.
     */
    public function next()
    {
        return next($this->_values);
    }
    
    /**
     * Uses standard php array implementation
     * for rewinding the array to the first
     * element, for use with iteration.
     * 
     * @return mixed
     * The first value for use with processing
     * when this function is called directly.
     */
    public function rewind()
    {
        return reset($this->_values);
    }
    
    /**
     * Helper function to provide similar access
     * to reset as rewind, and other native array
     * functions. Wrapper function for rewind().
     * 
     * @return mixed
     */
    public function reset()
    {
        return $this->rewind();
    }
    
    /**
     * Uses standard php array implementation
     * for checking to see if the current item
     * in the loop is valid.
     * 
     * @uses Arr::key(); Implemented from Iterator.
     * 
     * @return boolean
     */
    public function valid()
    {
        return null !== $this->key();
    }
    
    ///*** Countable ***///
    
    /**
     * Counts the number of items in the array
     * 
     * @param int $recursive
     * Flag used to count recursively. Defaults to
     * COUNT_NORMAL if called via the native count()
     * function even if a flag was supplied. Call this
     * function directly if the flag COUNT_RECURSIVE
     * should be used. Even if supplied it may not
     * recursively count any values that implement
     * Countable.
     * 
     * @return int
     */
    public function count($recursive = COUNT_NORMAL)
    {
        return count($this->_values, $recursive);
    }
    
    ///*** JsonSerializable ***///
    
    /**
     * Returns the values so it can be serialized into a JSON string.
     * 
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->_values;
    }
    
    ///*** Transforms and Array Manipulation ***///
	
    /**
     * Implements a copy of the standard array_map function,
     * but without the need to supply the second argument.
     * 
     * @param callable $callback
     * @param array $_ 
     * @return Arr A new Arr object with the updated array.
     */
	public function array_map(callable $callback, array $_ = null)
    {
		$arguments = func_get_args();
        // Add current array to the list of arguments.
		array_splice($arguments, 1, 0, [$this->_values]);
        // Original Arr or Source object will remain unchanged.
		return new static(call_user_func_array('array_map', $arguments));
	}
    
    /**
     * Implements a copy of the standard array_filter function,
     * but without the need to supply the first argument.
     * 
     * Accepts the same flags as array_filter:
     * ARRAY_FILTER_USE_BOTH (1) and ARRAY_FILTER_USE_KEY (2)
     * 
     * @param callable $callback
     * @param int $flag 
     * @return Arr A new Arr object with the updated array.
     */
    public function array_filter(callable $callback = null, $flag = 0)
    {
		$arguments = func_get_args();
        // Prepend current array to the list of arguments.
		array_unshift($arguments, $this->_values);
        // Original Arr or Source object will remain unchanged.
        return new static(call_user_func_array('array_filter', $arguments));
    }
    
    /**
     * Rotates the $_values property array.
     * 
     * @uses static::array_rotate();
     * @see static::array_rotate(); for further documentation.
     */
    public function rotate($distance = 1)
    {
        return new static(static::array_rotate($this->_values, $distance));
    }
    
    /**
     * Rotates the keys of the $_values property array.
     * 
     * @uses static::array_rotate_key();
     * @see static::array_rotate_key();
     *  and static::array_rotate();
     *  for further documentation.
     */
    public function rotate_key($distance = 1)
    {
        return new static(static::array_rotate_key($this->_values, $distance));
    }
    
    /**
     * Rotates the values of the $_values property array.
     * 
     * @uses static::array_rotate_value();
     * @see static::array_rotate_value();
     *  and static::array_rotate();
     *  for further documentation.
     */
    public function rotate_value($distance = 1)
    {
        return new static(static::array_rotate_value($this->_values, $distance));
    }
    
    /**
     * Rotates the $_values property array.
     * 
     * @uses static::array_rotate_assoc();
     * @see static::array_rotate_assoc();
     *  and static::array_rotate();
     *  for further documentation.
     */
    public function rotate_assoc($distance = 1)
    {
        return new static(static::array_rotate_assoc($this->_values, $distance));
    }
    
    ///*** Static library functions ***///
    
    /**
     * Rotates an array.
     * 
     * Numerical indexes will be renumbered automatically.
     * Associations will be kept for keys which are strings.
     * 
     * Rotations will always occur similar to shift and push,
     * where the number of items denoted by the distance are
     * removed from the start of the array and are appended.
     * 
     * Negative distances work in reverse, and are similar to
     * pop and unshift instead.
     * 
     * Distance magnitudes greater than the length of the array
     * can be interpreted as rotating an array more than a full
     * rotation. This will be reduced to calculate the remaining
     * rotation after all full rotations.
     * 
     * @param array $array The original array to rotate.
     * Passing a reference may cause the original array to be truncated.
     * @param int $distance The number of elements to move to the end.
     * Distance is automatically interpreted as an integer.
     * @return array The modified array.
     */
    public static function array_rotate($array, $distance = 1)
    {
        // Ensure $array is an array.
        settype($array, 'array');
        // Ensure the magnitude of distance is less than the length of the array.
        $distance %= count($array);
        return array_merge(
            array_splice($array, $distance), // Last elements  - moved to the start
            $array                          //  First elements - appended to the end
        );
    }
    
    /**
     * Rotates the keys of an array while keeping values in the same order.
     * 
     * @see array_rotate(); for function arguments and output.
     */
    public static function array_rotate_key($array, $distance = 1)
    {
        $keys = array_keys((array) $array);
        return array_combine(
            static::array_rotate($keys, $distance), // Rotated keys
            array_values((array)$array)    //  Values
        );
    }
    
    /**
     * Rotates the values of an array while keeping keys in the same order.
     * 
     * @see array_rotate(); for function arguments and output.
     */
    public static function array_rotate_value($array, $distance = 1)
    {
        $values = array_values((array)$array);
        return array_combine(
            array_keys((array)$array),        // Keys
            static::array_rotate($values, $distance) //  Rotated values
        );
    }
    
    /**
     * Rotates an array while keeping all key and value association.
     * 
     * @see array_rotate(); for function arguments and output.
     */
    public static function array_rotate_assoc($array, $distance = 1)
    {
        $keys = array_keys((array)$array);
        $values = array_values((array)$array);
        return array_combine(
            static::array_rotate($keys, $distance),   // Rotated keys
            static::array_rotate($values, $distance) //  Rotated values
        );
    }
}

/**
 * Arr constructor.
 * 
 * Allows Arr object to be created without the keyword `new` and therefore
 * it also allows methods to be called without assigning the object first.
 * Example:
 * <pre>
 * $arr1 = new \Slick\Code\Arr($array);
 * $arr->search($search) === \Slick\Code\Arr($array)->search($search); // True
 * </pre>
 * 
 * @param array $values
 * @return Arr
 */
function Arr($values)
{
    return new Arr((array) $values);
}

/**
 * Abstract Class: Source
 * 
 * Parent Class for creating a static array library.
 * Prevents overriding the values where possible and
 * adds methods and functions for obtaining a single
 * value.
 */
abstract class Source extends Arr implements Serializable
{
    ///*** Properties ***///
    
    /**
     * If supplied, the static variable $values will be used to
     * populate the $_values property on construct, regardless
     * of whether $_values has already been set.
     * 
     * @var array
     * @abstract
     */
    protected static $values;
    
    /**
     * Property $_values is classed as abstract and
     * will need to be defined in child classes, or
     * generated dynamically via the contructor, in
     * order to prevent Exceptions from being thrown.
     * 
     * @var array
     * @abstract
     */
    protected $_values;
    
    /**
     * Index value used for obtaining a specific value.
     * 
     * @var scalar
     */
    protected $_index;
    
    ///*** Constructors ***///
    
    /**
     * Magic Constructor: Apply value to $_index if supplied in correct format
     * 
     * @throws LogicException When the values array is undefined.
     * 
     * @param scalar $index
     */
    public function __construct($index = null)
    {
        // Load values from static variable if present.
        if (static::$values && is_array(static::$values)) {
            $this->_values = static::$values;
        }
        // Ensure any class extensions have the $_values property defined as a non-empty array.
        if (!$this->_values || !is_array($this->_values)) {
            throw new LogicException(sprintf(
                'Class %s needs to define either the protected abstract property $_values or the static variable $values as a non-empty array.',
                get_called_class()
            ));
        }
        
        $this->index($index);
    }
    
    ///*** Getters and Setters ***///
    
    /**
     * Static constructor to return just the selected value.
     * 
     * @param scalar $index
     * @return string
     */
    public static function get($index = null)
    {
        return (string) new static($index);
    }
    
    /**
     * Returns the current values as an array
     * Overrided to prevent setting a new list
     * 
     * @param array $values Unused
     * @return array
     */
    public function values(array $values = null)
    {
        return $this->_values;
    }
    
    /**
     * Sets the index and retuns it for further processing.
     * 
     * @uses Arr::offsetIndex(); Implemented from ArrayAccess.
     * 
     * @param scalar $key
     * @return scalar
     */
    public function index($key = null)
    {
        if ($this->offsetIndex($key) !== false) {
            $this->_index = $key;
        }
        return $this->_index;
    }
    
    /**
     * Magic Getter for string conversion
     * 
     * @uses Arr::offsetGet()|Arr::offsetIndex(); Implemented from ArrayAccess.
     * @uses Arr::current(); Implemented from Iterator.
     * 
     * @return string
     * The value associated with the current index
     */
    public function __toString()
    {
        return $this->offsetIndex($this->_index) !== false ? (string) $this->offsetGet($this->_index) : (string) $this->current();
    }
    
    /**
     * Do not set value but return the original value
     * for further processing, when called directly.
     * 
     * @param scalar $name 
     * @param mixed $value Unused
     * @return mixed The matching value
     */
    public function __set($name, $value)
    {
        return $this->__get($name);
    }
    
    /**
     * Do not unset value, but instead return the original
     * value when called directly for further processing.
     * 
     * @param scalar $name
     * @return mixed The matching value
     */
    public function __unset($name)
    {
        return $this->__get($name);
    }
    
    /**
     * Magic static constructor
     * 
     * @uses static::index() to set the index
     * 
     * @param array $properties
     * @return Source
     */
    public static function __set_state($properties)
    {
        $self = new static;
        if (array_key_exists("_index", $properties)) {
            $self->index($properties["_index"]);
        }
        return $self;
    }
    
    ///*** Array Access ***///
    
    /**
     * Source values are unchangeable.
     * 
     * @return mixed The value matching the offset,
     * for further processing when called directly.
     */
    public function offsetSet($offset, $value)
    {
        return $this->offsetGet($offset);
    }
    
    /**
     * Source values are unchangeable.
     * 
     * @return mixed The value matching the offset,
     * for further processing when called directly.
     */
    public function offsetUnset($offset)
    {
        return $this->offsetGet($offset);
    }
    
    ///*** Serializable ***///
    
    /**
     * Serializes the object to prevent
     * values from being modified while
     * retaining the index.
     * 
     * @return string
     * Index (scalar) as a serialized string
     */
    public function serialize()
    {
        return serialize($this->_index);
    }
    
    /**
     * Unserializes the object and updates
     * the index from the serialized data.
     * 
     * Called when the C:%d:ClassName notation
     * is used. Generated using serialize();
     * 
     * @param string $data
     * Index (scalar) as a serialized string
     */
    public function unserialize($data)
    {
        call_user_func_array(array($this, '__wakeup'), (array) unserialize($data));
    }
    
    /**
     * Unserializes the object and reverts
     * the values to their default values
     * obtained after the constructor is run.
     * 
     * Called when the O:%dClassName notation
     * is used. Not generated using serialize();
     */
    public function __wakeup($index = null)
    {
        if (static::$values && is_array(static::$values)) {
            $this->_values = static::$values;
            $index = $this->index($index);
        }
        else {
            $new = new static($this->index($index));
            $this->_values = $new->values();
        }
    }
    
    ///*** Disable Rotation Functions ***///
    
    /**
     * Disabled by default.
     * 
     * @param int $distance Ignored.
     * @return Source Return self for further processing.
     */
    public function rotate($distance = 1)
    {
        return $this;
    }
    
    /**
     * Defaults to the object's rotate function which is disabled by default.
     * 
     * @see static::rotate();
     */
    public function rotate_key($distance = 1)
    {
        return call_user_func_array([$this, 'rotate'], func_get_args());
    }
    
    /**
     * Defaults to the object's rotate function which is disabled by default.
     * 
     * @see static::rotate();
     */
    public function rotate_value($distance = 1)
    {
        return call_user_func_array([$this, 'rotate'], func_get_args());
    }
    
    /**
     * Defaults to the object's rotate function which is disabled by default.
     * 
     * @see static::rotate();
     */
    public function rotate_assoc($distance = 1)
    {
        return call_user_func_array([$this, 'rotate'], func_get_args());
    }
}
