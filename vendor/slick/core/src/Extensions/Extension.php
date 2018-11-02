<?php

namespace Slick\Extensions;

/**
 * Expand upon the standard DataExtension class.
 * 
 * Enables the ability to refer to the owner's properties and methods by calling
 * or accessing them directly from the extension class.
 * <pre>
 * // Owner properties and methods are accessible directly.
 * $this->owner->$property === $this->$property; // True
 * $this->owner->$method() === $this->$method(); // true
 * 
 * // This is not the case when it's a property of the extension.
 * $this->$property = !$this->owner->$property;
 * $this->owner->$property === $this->$property; // False
 * // The same goes for the extension's methods.
 * $this->canEdit() !== $this->owner->canEdit();
 * </pre>
 */
class DataExtension extends \SilverStripe\ORM\DataExtension {
    /**
     * The object this extension is applied to.
     *
     * @var Object
     */
    protected $owner;
    
    /**
     * Magic Getter.
     * 
     * Access data from $this->owner if not a property of the extension itself.
     * @param string $name The property name to access.
     * @return mixed the value if the owner's property is accessible.
     */
    public function __get($name)
    {
        return $this->owner->$name;
    }
    
    /**
     * Magic Method.
     * 
     * Process the method and return the value as though it were called against
     * the object itself.
     * @param string $method The method called on the extension class.
     * @param array $arguments The list of arguments supplied to the method.
     * @return mixed The result after processing the method on the owner object.
     */
    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->owner, $method), $arguments);
    }
}
