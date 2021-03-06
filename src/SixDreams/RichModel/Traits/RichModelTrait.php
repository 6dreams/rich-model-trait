<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Traits;

use SixDreams\RichModel\Exception\RichModelCollectionException;
use SixDreams\RichModel\Exception\RichModelFieldException;
use SixDreams\RichModel\Interfaces\RichModelInterface;

/**
 * Trait RichEntityTrait
 * Trait for helping make your models less anemic, and helps to remove functions that will only perform
 *  operations with properties and do not have additional logic.
 */
trait RichModelTrait
{
    /**
     * Flag: is model have richAccessMap? Or null if we don't check it before.
     * @var bool|null
     */
    private $richAccessMapExists;

    /**
     * @var \ReflectionClass
     */
    private $richClassReflection;

    /**
     * @var array
     */
    private $richAccessMapArray;

    /**
     * @var bool
     */
    private $richIsReadOnly;


    /**
     * Initialize rich model tools.
     *
     * @throws RichModelFieldException
     */
    private function initRichModelUtils(): void
    {
        $this->richClassReflection = $this->richClassReflection ?? new \ReflectionClass($this);

        // Checking and saving information about richAccessMap.
        if ($this->richAccessMapExists === null) {
            $this->richAccessMapExists = false;
            if ($this->richClassReflection->hasProperty(RichModelInterface::RICH_MAP_NAME)) {
                if (!$this->richClassReflection->getProperty(RichModelInterface::RICH_MAP_NAME)->isStatic()) {
                    throw new RichModelFieldException(\sprintf('%s must be static!', RichModelInterface::RICH_MAP_NAME));
                }

                $this->richAccessMapExists = true;
                $mapReflection = $this->richClassReflection->getProperty(RichModelInterface::RICH_MAP_NAME);
                $mapReflection->setAccessible(true);
                $this->richAccessMapArray = $mapReflection->getValue();
                $this->richIsReadOnly = \array_key_exists(RichModelInterface::RICH_READONLY, $this->richAccessMapArray);
            }
        }
    }

    /**
     * Throws exception if model is in read-only mode.
     *
     * @param string $name
     *
     * @throws RichModelFieldException
     */
    private function throwRichModelReadOnlyException(string $name): void
    {
        if ($this->richIsReadOnly) {
            throw new RichModelFieldException(\sprintf('Cant write to execute method %s, model is readonly', $name));
        }
    }

    /**
     * Getting field name from function name. Also uses static RichModelInterface::RICH_MAP_NAME static array
     *  to remap fields for user readable function names.
     *
     * @param string $name
     *
     * @return string
     *
     * @throws RichModelFieldException
     */
    private function getRichFieldName(string $name): string
    {
        $this->initRichModelUtils();
        $exceptedName = \lcfirst($name);

        $field = $this->richAccessMapExists ? null : $exceptedName;

        if ($this->richAccessMapExists) {
            if (\array_key_exists($exceptedName, $this->richAccessMapArray)) {
                $field = $this->richAccessMapArray[$exceptedName];
            } elseif (!\array_key_exists(RichModelInterface::RICH_STRICT, $this->richAccessMapArray)) {
                $field = $exceptedName;
            }
        }

        if (!$field || !$this->richClassReflection->hasProperty($field)) {
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
     *
     * @throws RichModelFieldException
     * @throws RichModelCollectionException
     */
    public function __call($name, array $arguments = [])
    {
        $this->initRichModelUtils();

        // Getting value from model.
        if (\strncmp($name, 'get', 3) === 0) {
            return $this->{$this->getRichFieldName(\substr($name, 3))};
        }

        // Getting boolean value from model.
        if (\strncmp($name, 'is', 2) === 0) {
            return $this->{$this->getRichFieldName(\substr($name, 2))} === true;
        }

        // Setting value to model.
        if (\strncmp($name, 'set', 3) === 0 && \count($arguments) === 1) {
            $this->throwRichModelReadOnlyException($name);
            $this->{$this->getRichFieldName(\substr($name, 3))} = $arguments[0];

            return $this;
        }

        // Adding new element to array, collection in model.
        if (\strncmp($name, 'add', 3) === 0 && \count($arguments) === 1) {
            $this->throwRichModelReadOnlyException($name);
            $propertyName = $this->getRichFieldName(\substr($name, 3));
            if ($this->{$propertyName} instanceof \ArrayAccess || \is_array($this->{$propertyName})) {
                $this->{$propertyName}[] = $arguments[0];

                return $this;
            }

            throw new RichModelCollectionException();
        }

        // Removing element from array, collection in model.
        if (\strncmp($name, 'remove', 6) === 0 && \count($arguments) === 1) {
            $this->throwRichModelReadOnlyException($name);
            $propertyName = $this->getRichFieldName(\substr($name, 6));
            if ($this->{$propertyName} instanceof \ArrayAccess || \is_array($this->{$propertyName})) {
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
        }

        if (\count($arguments) === 0) {
            return null;
        }

        throw new RichModelFieldException(\sprintf('Unrecognized function "%s", arguments count %d.', $name, \count($arguments)));
    }

    /**
     * Removes richModelTrait specific properties from serialization.
     *
     * {@inheritdoc}
     *
     * @return array
     *
     * @throws RichModelFieldException
     */
    public function __sleep()
    {
        $this->initRichModelUtils();
        return (array) \array_reduce(
            $this->richClassReflection->getProperties(),
            function (array $items, \ReflectionProperty $property) {
                if (!$property->isStatic() && !\array_key_exists($property->getName(), RichModelInterface::RICH_FIELD_NAMES)) {
                    $items[] = $property->getName();
                }

                return $items;
            },
            []
        );
    }

    /**
     * Getting field value by it's name.
     *
     * @param string $name
     *
     * @return mixed
     *
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
     *
     * @throws RichModelFieldException
     */
    public function __set($name, $value)
    {
        $name = $this->getRichFieldName($name);

        $this->throwRichModelReadOnlyException($name);
        if (\property_exists($this, $name)) {
            $this->{$name} = $value;
        }
    }

    /**
     * We need update reflection or code will crash.
     */
    public function __wakeup()
    {
        $this->richClassReflection = new \ReflectionClass($this);
    }

    /**
     * Returns true, if field exists in model.
     *
     * @param string $name
     *
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
