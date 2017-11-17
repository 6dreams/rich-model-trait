<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Traits;

use SixDreams\RichModel\Exception\RichModelCollectionException;
use SixDreams\RichModel\Exception\RichModelFieldException;
use SixDreams\RichModel\Interfaces\RichModelInterface;

/**
 * Trait RichEntityTrait
 * Трейт который позволяет избавиться от кучи тупых методов в сущностях, поддерживает set, get, is (только для boolean), так же,
 *  если указанный параметр является Collection (Doctrine), то может осуществлять add, remove.
 * @package SixDreams\RichModel\Traits
 */
trait RichModelTrait
{
    /**
     * @var bool
     */
    private $richAccessMapExists;

    /**
     * @var \ReflectionClass
     */
    private $richClassReflection;

    /**
     * Получить название поля в модели.
     *
     * @param string $name
     * @return string
     * @throws RichModelFieldException
     */
    public function getRichFieldName(string $name): string
    {
        $this->richClassReflection = $this->richClassReflection ?? new \ReflectionClass($this);
        $exceptedName = \lcfirst($name);

        // Checking and saving information about richAccessMap.
        if ($this->richAccessMapExists === null) {
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
            }

            if (!\array_key_exists(RichModelInterface::RICH_STRICT, $map)) {
                $field = $exceptedName;
            }
        }

        if ($field === null || !$this->richClassReflection->hasProperty($field)) {
            throw new RichModelFieldException(\sprintf('Field %s not found!', $name));
        }

        return $field;
    }

    /**
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
