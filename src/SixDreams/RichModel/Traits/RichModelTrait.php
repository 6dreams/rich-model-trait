<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Traits;

use SixDreams\RichModel\Exception\RichModelCollectionException;
use SixDreams\RichModel\Exception\RichModelFieldException;
use SixDreams\RichModel\Interfaces\RichModelInterface;

/**
 * Trait RichEntityTrait
 * Trait for helping make your models more anemic, and remove helps to remove functions that will only perform
 *  operations with properties and do not have additional logic.
 * @package SixDreams\RichModel\Traits
 */
trait RichModelTrait
{
    /**
     * Flag: is model hava richAccessMap? Or null if we dont check it before.
     * @var bool|null
     */
    private $richAccessMapExists;

    /**
     * @var \ReflectionClass
     */
    private $richClassReflection;

    /**
     * @var \ReflectionProperty[]
     */
    private $richPropertiesReflection;

    /**
     * Internal method for checking existing "rich" field in model, uses Reflection::getProperties to get
     *  all properties include private ones, because few frameworks make lazy-load.
     *
     * @param string $name
     * @return bool
     */
    private function hasRichField(string $name): bool
    {
        foreach ($this->richPropertiesReflection as $reflection) {
            if ($reflection->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Getting field name from function name. Also uses static RichModelInterface::RICH_MAP_NAME static array
     *  to remap fields for user readable function names.
     *
     * @param string $name
     * @return string
     * @throws RichModelFieldException
     */
    public function getRichFieldName(string $name): string
    {
        $this->richClassReflection = $this->richClassReflection ?? new \ReflectionClass($this);
        $this->richPropertiesReflection = $this->richPropertiesReflection ?? $this->richClassReflection->getProperties();
        $exceptedName = \lcfirst($name);

        // Checking and saving information about richAccessMap.
        if (null === $this->richAccessMapExists) {
            $this->richAccessMapExists = false;
            if ($this->richClassReflection->hasProperty(RichModelInterface::RICH_MAP_NAME)) {
                if (!$this->richClassReflection->getProperty(RichModelInterface::RICH_MAP_NAME)->isStatic()) {
                    throw new RichModelFieldException(RichModelInterface::RICH_MAP_NAME . ' must be static!');
                }

                $this->richAccessMapExists = true;
            }
        }

        $field = $this->richAccessMapExists ? null : $exceptedName;

        if ($this->richAccessMapExists) {
            $mapReflection = $this->richClassReflection->getProperty(RichModelInterface::RICH_MAP_NAME);
            $mapReflection->setAccessible(true);
            $map = $mapReflection->getValue();

            if (\array_key_exists($exceptedName, $map)) {
                $field = $map[$exceptedName];
            } elseif (!\array_key_exists(RichModelInterface::RICH_STRICT, $map)) {
                $field = $exceptedName;
            }
        }

        if (null === $field || !$this->hasRichField($field)) {
            throw new RichModelFieldException(\sprintf('Field "%s" (remapped: %s) not found!', $name, $field));
        }

        return $field;
    }

    /**
     * Magic method for handing functions with prefixes "get", "set", "add", "remove" and "is".
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return null|mixed
     * @throws RichModelFieldException
     * @throws RichModelCollectionException
     */
    public function __call($name, array $arguments = [])
    {
        // Getting value from model.
        if (0 === \strpos($name, 'get')) {
            return $this->{$this->getRichFieldName(\substr($name, 3))};
        }

        // Getting boolean value from model.
        if (0 === \strpos($name, 'is')) {
            return $this->{$this->getRichFieldName(\substr($name, 2))} === true;
        }

        // Setting value to model.
        if (0 === \strpos($name, 'set') && \count($arguments) === 1) {
            $this->{$this->getRichFieldName(\substr($name, 3))} = $arguments[0];

            return $this;
        }

        // Adding new element to array, collection in model.
        if (0 === \strpos($name, 'add') && \count($arguments) === 1) {
            $propertyName = $this->getRichFieldName(\substr($name, 3));
            if ($this->{$propertyName} instanceof \ArrayAccess) {
                $this->{$propertyName}[] = $arguments[0];

                return $this;
            }

            throw new RichModelCollectionException();
        }

        // Removing element from array, collection in model.
        if (0 === \strpos($name, 'remove') && \count($arguments) === 1) {
            $propertyName = $this->getRichFieldName(\substr($name, 6));
            if ($this->{$propertyName} instanceof \ArrayAccess) {
                foreach ($this->{$propertyName} as $key => $value) {
                    if ($value === $arguments[0]) {
                        unset($this->{$propertyName}[$key]);
                        break;
                    }
                }

                return $this;
            }

            throw new RichModelCollectionException();
        }

        // This is fix for Sonata Project.
        if (\property_exists($this, $name)) {
            return $this->{$name};
        } elseif (\count($arguments) === 0) {
            return null;
        }

        throw new RichModelFieldException(\sprintf('Unrecognized function "%s", arguments count %d.', $name, \count($arguments)));
    }

    /**
     * Getting field value by it's name.
     *
     * @param string $name
     * @return mixed
     * @throws RichModelFieldException
     */
    public function __get($name)
    {
        $name = $this->getRichFieldName($name);

        return $this->{$name};
    }

    /**
     * Set new value to model field.
     *
     * @param string $name  field name
     * @param mixed  $value new field value
     * @throws RichModelFieldException
     */
    public function __set($name, $value)
    {
        $name = $this->getRichFieldName($name);

        if (\property_exists($this, $name)) {
            $this->{$name} = $value;
        }
    }

    /**
     * Returns true, if field exists in model.
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        try {
            return \property_exists($this, $this->getRichFieldName($name));
        } catch (RichModelFieldException $e) {
            return false;
        }
    }
}
