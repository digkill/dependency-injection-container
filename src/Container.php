<?php

namespace App\Container;

use App\Container\Exception\ContainerException;
use App\Container\Exception\ParameterNotFoundException;
use App\Container\Exception\ServiceNotFoundException;
use App\Container\Reference\ParameterReference;
use App\Container\Reference\ServiceReference;
use Interop\Container\ContainerInterface as InteropContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements InteropContainerInterface
{
    private $services;
    private $parameters;
    private $serviceStore;

    /**
     * Container constructor.
     * @param array $services
     * @param array $parameters
     */
    public function __construct(array $services = [], array $parameters = [])
    {
        $this->services = $services;
        $this->parameters = $parameters;
        $this->serviceStore = [];
    }

    /**
     * @param string $id
     * @return mixed
     * @throws ContainerException
     * @throws ServiceNotFoundException
     */
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException('Service not found: ' . $id);
        }

        if (!isset($this->serviceStore[$id])) {
            $this->serviceStore[$id] = $this->createService($id);
        }

        return $this->serviceStore[$id];
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has($id)
    {
        return isset($this->services[$id]);
    }

    /**
     * @param $id
     * @return array|mixed
     * @throws ParameterNotFoundException
     */
    public function getParameter($id)
    {
        $tokens = explode('.', $id);
        $context = $this->parameters;

        while (null !== ($token = array_shift($tokens))) {
            if (!isset($context[$token])) {
                throw new ParameterNotFoundException('Parameter not found: ' . $id);
            }

            $context = $context[$token];
        }

        return $context;
    }

    /**
     * @param $id
     * @return object
     * @throws ContainerException
     * @throws \ReflectionException
     */
    private function createService($id)
    {
        $entry = &$this->services[$id];

        if (!is_array($entry) || !isset($entry['class'])) {
            throw new ContainerException($id . ' service entry must be an array containing a \'class\' key');
        } elseif (!class_exists($entry['class'])) {
            throw new ContainerException($id . ' service class does not exist: ' . $entry['class']);
        } elseif (isset($entry['lock'])) {
            throw new ContainerException($id . ' service contains a circular reference');
        }

        $entry['lock'] = true;

        $arguments = isset($entry['arguments']) ? $this->resolveArguments($id, $entry['arguments']) : [];

        $reflector = new \ReflectionClass($entry['class']);
        $service = $reflector->newInstanceArgs($arguments);

        if (isset($entry['calls'])) {
            $this->initializeService($service, $id, $entry['calls']);
        }

        return $service;
    }

    /**
     * @param $id
     * @param $argumentDefinitions
     * @return array
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ServiceNotFoundException
     */
    private function resolveArguments($id, $argumentDefinitions)
    {
        $arguments = [];

        foreach ($argumentDefinitions as $argumentDefinition) {
            if ($argumentDefinition instanceof ServiceReference) {
                $argumentServiceId = $argumentDefinition->getId();

                $arguments[] = $this->get($argumentServiceId);
            } elseif ($argumentDefinition instanceof ParameterReference) {
                $argumentParameterId = $argumentDefinition->getId();

                $arguments[] = $this->getParameter($argumentParameterId);
            } else {
                $arguments[] = $argumentDefinition;
            }
        }

        return $arguments;
    }

    /**
     * @param $service
     * @param $name
     * @param array $callDefinitions
     * @throws ContainerException
     * @throws ParameterNotFoundException
     * @throws ServiceNotFoundException
     */
    private function initializeService($service, $name, array $callDefinitions)
    {
        foreach ($callDefinitions as $callDefinition) {
            if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                throw new ContainerException($name . ' service calls must be arrays containing a \'method\' key');
            } elseif (!is_callable([$service, $callDefinition['method']])) {
                throw new ContainerException($name . ' service asks for call to uncallable method: ' . $callDefinition['method']);
            }

            $arguments = isset($callDefinition['arguments']) ? $this->resolveArguments($name, $callDefinition['arguments']) : [];

            call_user_func_array([$service, $callDefinition['method']], $arguments);
        }
    }

}