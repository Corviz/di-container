<?php

namespace Corviz\DI;

use Closure;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionMethod;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    private array $map = [];

    /**
     * @var array
     */
    private array $singletonObjects = [];

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return mixed Entry.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     */
    public function get(string $id)
    {
        if ($this->isSingleton($id)) {
            /*
             * Object is instantiated as
             * singleton already. Just fetch it.
             */
            return $this->singletonObjects[$id];
        } elseif ($this->has($id)) {
            /*
             * Creates a new instance
             * using map information.
             */
            return $this->build($id);
        } elseif (class_exists($id)) {
            /*
             * Class exists but it is
             * not mapped yet.
             */
            $this->set(
                $id,
                method_exists($id, '__construct') ?
                    $this->generateArgumentsMap($id) : []
            );

            return $this->get($id);
        }

        throw new ContainerException("Couldn't create '$id'");
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->map[$id]);
    }

    /**
     * Calls an object method,
     * injecting its dependencies.
     *
     * @param mixed $obj
     * @param string $method
     * @param array $params
     *
     * @return mixed
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    public function invoke(
        $obj,
        string $method,
        array $params = []
    ) {
        if (is_string($obj)) {
            //Attempt to retrieve object from
            //the container.
            $obj = $this->get($obj);
        } elseif (!is_object($obj)) {
            //Not a valid argument.
            throw new ContainerException('Invalid object');
        }

        $map = $this->generateArgumentsMap(
            $obj,
            $method,
            $params
        );
        $mapParams = $this->getParamsFromMap($map);

        return $obj->$method(...$mapParams);
    }

    /**
     * @param string $name
     * @param mixed  $definition
     *
     * @throws ContainerException
     */
    public function set(string $name, $definition)
    {
        if ($this->isSingleton($name)) {
            throw new ContainerException('Can\'t set a singleton twice.');
        }

        $this->map[$name] = $definition;
    }

    /**
     * @param string $name
     * @param mixed  $definition
     */
    public function setSingleton(string $name, $definition)
    {
        $this->set($name, $definition);
        $this->singletonObjects[$name] = $this->get($name);
    }

    /**
     * Build an object according to the map information.
     *
     * @param string $name
     *
     * @return mixed
     *
     * @throws NotFoundExceptionInterface
     * @throws ContainerException
     */
    private function build(string $name)
    {
        $instance = null;
        $map = $this->map[$name];

        if (is_array($map)) {
            $params = $this->getParamsFromMap($map);
            $instance = new $name(...$params);
        } elseif ($map instanceof Closure) {
            $instance = $map($this);
        } elseif (is_object($map)) {
            $instance = $map;
        } elseif (is_string($map)) {
            $instance = $this->get($map);
        } else {
            throw new ContainerException('Invalid map');
        }

        return $instance;
    }

    /**
     * Generates a map that will be used by 'build()' method
     * to generate the args.
     *
     * @param mixed  $class
     * @param string $method
     * @param array  $predefined
     *
     * @throws ContainerException
     *
     * @return array
     */
    private function generateArgumentsMap(
        $class,
        string $method = '__construct',
        array $predefined = []
    ): array {
        $arguments = [];

        try {
            $refMethod = new ReflectionMethod($class, $method);

            foreach ($refMethod->getParameters() as $parameter) {
                $arg = [
                    'value'   => null,
                    'isClass' => false,
                ];

                if (isset($predefined[$parameter->getName()])) {
                    //Get a predefined parameter
                    $arg['value'] = $predefined[$parameter->getName()];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    //Parameter has a default value, just pass it
                    $arg['value'] = $parameter->getDefaultValue();
                } elseif ($parameter->hasType()) {
                    /* @var $pClass ReflectionClass */
                    $pClass = $parameter->getType() && !$parameter->getType()->isBuiltin()
                        ? new ReflectionClass($parameter->getType()->getName())
                        : null;

                    //Only possible to pass get classes
                    if (is_null($pClass)) {
                        $pName = $parameter->getName();

                        throw new Exception("Parameter '$pName' is not a class");
                    }

                    $arg['value'] = $pClass->getName();
                    $arg['isClass'] = true;
                } else {
                    throw new Exception('Could not define a value');
                }

                $arguments[] = $arg;
            }
        } catch (Exception $exception) {
            throw new ContainerException("Could not read method $method from $class", 0, $exception);
        }

        return $arguments;
    }

    /**
     * @param array $mapArray
     *
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getParamsFromMap(array &$mapArray): array
    {
        $params = [];

        foreach ($mapArray as $item) {
            if (!isset($item['isClass']) || !isset($item['value'])) {
                continue;
            }

            $params[] = $item['isClass'] ?
                $this->get((string) $item['value']) : $item['value'];
        }

        return $params;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function isSingleton(string $name): bool
    {
        return isset($this->singletonObjects[$name]);
    }
}
