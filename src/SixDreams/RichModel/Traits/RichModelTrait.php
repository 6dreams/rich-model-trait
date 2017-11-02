<?php
declare(strict_types = 1);

namespace SixDreams\RichModel\Traits;

use Doctrine\Common\Collections\Collection;
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
     * Получить название поля в модели.
     *
     * @param string $name
     * @return string
     * @throws RichModelFieldException
     */
    public function getRichFieldName(string $name): string
    {
        $this->richAccessMapExists = $this->richAccessMapExists ?? \property_exists($this, 'richAccessMap');

        if (!$this->richAccessMapExists) {
            return \lcfirst($name);
        }

        $map = self::$richAccessMap;

        if (\array_key_exists($name, $map)) {
            return $map[$name];
        }

        if (!\array_key_exists(RichModelInterface::RICH_STRICT, $map)) {
            return \lcfirst($name);
        }

        throw new RichModelFieldException();
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
        // Если используется геттер
        if (0 === \strpos($name, 'get')) {
            $propertyName = $this->getRichFieldName(\substr($name, 3));
            if (\property_exists($this, $propertyName)) {
                return $this->{$propertyName};
            }
        }

        // Если используется is-геттер
        if (0 === \strpos($name, 'is')) {
            $propertyName = $this->getRichFieldName(\substr($name, 2));
            if (\property_exists($this, $propertyName) && (null === $this->{$propertyName} || \is_bool($this->{$propertyName}))) {
                return $this->{$propertyName} === true;
            }
        }

        // Если используется сеттер
        if (0 === \strpos($name, 'set') && \count($arguments) === 1) {
            $propertyName = $this->getRichFieldName(\substr($name, 3));
            if (\property_exists($this, $propertyName)) {
                $this->{$propertyName} = $arguments[0];

                return $this;
            }
        }

        // Добавление в коллекцию (add)
        if (0 === \strpos($name, 'add') && \count($arguments) === 1) {
            $propertyName = $this->getRichFieldName(\substr($name, 3));
            if (\property_exists($this, $propertyName)) {
                if ($this->{$propertyName} instanceof Collection) {
                    $this->{$propertyName}[] = $arguments[0];

                    return $this;
                }

                throw new RichModelCollectionException();
            }
        }

        // Удаление из коллекции (remove)
        if (0 === \strpos($name, 'remove') && \count($arguments) === 1) {
            $propertyName = $this->getRichFieldName(\substr($name, 6)) . 's';
            if (\property_exists($this, $propertyName)) {
                if ($this->{$propertyName} instanceof Collection) {
                    $this->{$propertyName}->removeElement($arguments[0]);

                    return $this;
                }

                throw new RichModelCollectionException();
            }
        }

        // Sonata fix, за каким-то хреном вызываю __call с названием поля.
        if (\property_exists($this, $name)) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws RichModelFieldException
     */
    public function __get($name)
    {
        $name = $this->getRichFieldName($name);

        return $this->{$name};
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     * @throws RichModelFieldException
     */
    public function __isset($name)
    {
        return \property_exists($this, $this->getRichFieldName($name));
    }
}
