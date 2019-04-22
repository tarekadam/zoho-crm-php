<?php

namespace Zoho\Crm\Entities;

use Doctrine\Common\Inflector\Inflector;
use Zoho\Crm\Client;
use Zoho\Crm\Support\Helper;
use Zoho\Crm\Support\ClassShortNameTrait;
use Zoho\Crm\Support\Arrayable;
use Zoho\Crm\Exceptions\UnsupportedEntityPropertyException;
use Zoho\Crm\Api\Response;

abstract class AbstractEntity implements Arrayable
{
    use ClassShortNameTrait;

    protected static $name;

    protected static $module_name;

    protected static $property_aliases = [];

    protected $client;

    protected $properties = [];

    public function __construct(array $data = [], Client $client = null)
    {
        $this->properties = $this->unaliasProperties($data);
        $this->client = $client;
    }

    public static function name()
    {
        return isset(static::$name) ? static::$name : self::getClassShortName();
    }

    public static function moduleName()
    {
        if (isset(static::$module_name)) {
            return static::$module_name;
        }

        return Inflector::pluralize(static::name());
    }

    public static function supportedProperties()
    {
        return array_values(static::$property_aliases);
    }

    public static function supports($property)
    {
        return in_array($property, static::supportedProperties());
    }

    public function has($property)
    {
        $clean = array_key_exists($property, static::$property_aliases) &&
                 isset($this->properties[static::$property_aliases[$property]]);
        $raw   = isset($this->properties[$property]);
        return $clean || $raw;
    }

    public function get($property)
    {
        // Permissive mode: allows raw and clean property names
        if (array_key_exists($property, static::$property_aliases)) {
            $property = static::$property_aliases[$property];
        }

        return isset($this->properties[$property]) ? $this->properties[$property] : null;
    }

    public function set($property, $value)
    {
        // Permissive mode: allows raw and clean property names
        if (array_key_exists($property, static::$property_aliases)) {
            $property = static::$property_aliases[$property];
        }

        $this->properties[$property] = $value;
    }

    public function hasAlias($property)
    {
        return in_array($property, static::$property_aliases);
    }

    public function isAlias($alias)
    {
        return array_key_exists($alias, static::$property_aliases);
    }

    public function unalias($alias)
    {
        return static::$property_aliases[$alias];
    }

    private function unaliasProperties(array $properties)
    {
        $unaliased_keys = array_map(function ($prop) {
            return $this->isAlias($prop) ? $this->unalias($prop) : $prop;
        }, array_keys($properties));

        return array_combine($unaliased_keys, $properties);
    }

    public function key()
    {
        return $this->get($this->module()->primaryKey());
    }

    public function toArray()
    {
        return $this->properties;
    }

    public function toAliasArray()
    {
        $hash = [];

        // Reverse the property aliases mapping,
        // from ['clean_name' => 'ZOHO NAME'] to ['ZOHO NAME' => 'clean_name']
        $reversed_property_aliases = array_flip(static::$property_aliases);

        // Generate a new hashmap with the entity's property aliases as keys
        foreach ($reversed_property_aliases as $prop => $alias) {
            if (array_key_exists($prop, $this->properties)) {
                $hash[$alias] = $this->properties[$prop];
            }
        }

        return $hash;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function setClient(?Client $client)
    {
        $this->client = $client;
    }

    public function isDetached()
    {
        return is_null($this->client);
    }

    public function module()
    {
        if ($this->isDetached()) {
            return null;
        }

        return $this->client->module(static::moduleName());
    }

    public function copy()
    {
        // Just a simple shallow copy because entities only have primitives attributes
        return clone $this;
    }

    public function __get($alias)
    {
        if (array_key_exists($alias, static::$property_aliases)) {
            if (isset($this->properties[static::$property_aliases[$alias]])) {
                return $this->properties[static::$property_aliases[$alias]];
            } else {
                return null;
            }
        } else {
            throw new UnsupportedEntityPropertyException($this->name(), $alias);
        }
    }

    public function __set($alias, $value)
    {
        if (array_key_exists($alias, static::$property_aliases)) {
            $this->properties[static::$property_aliases[$alias]] = $value;
        } else {
            throw new UnsupportedEntityPropertyException($this->name(), $alias);
        }
    }

    public function __isset($alias)
    {
        return array_key_exists($alias, static::$property_aliases);
    }

    public function __toString()
    {
        return print_r($this->toArray(), true);
    }

    public function __sleep()
    {
        // $properties is the only member that need to be serialized
        return ['properties'];
    }
}
