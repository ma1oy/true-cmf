<?php

namespace True\Support\Container;

use Closure;
use Exception;
use ArrayAccess;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use SplFixedArray;
use True\Standards\Container\AbstractContainer;
use True\Standards\Container\AbstractFacade;
use True\Standards\Container\ContainerInterface;
use True\Standards\Container\BootableInterface;
use True\Standards\Container\ContainerAcessibleInterface;
use True\Standards\Container\KeyEnum;
use True\Standards\Container\ScopeEnum;

//use T\Traits\Service;

class Container extends AbstractContainer
{
    const PROXY = 0;
    const SHARE = 1;
    const STACK = 2;

    private $config;

    public $resolved = [];

    public $startTime;

    /**
     * Box constructor.
     *
     * @param array|string|null $config
     * @throws Exception
     */
    public function __construct($config = null)
    {
        $this->startTime = microtime(true);
        AbstractFacade::__registerContainer($this);
        $this->instance(ContainerInterface::class, $this);

        if ($this->isBootable($this)) {
            /**
             * @var BootableInterface $this
             */
            $this->__boot();
        }

        if ($config) {
            if (is_string($config)) {
                if (file_exists($config)) {
                    $config = include $config;
                } else throw new Exception('Services configuration file not found');
            }

            $this->init($config);
        }
    }

    public function isBootable($instance)
    {
        return $instance instanceof BootableInterface;
    }

    public function isContainerAccessible($instance)
    {
        return $instance instanceof ContainerAcessibleInterface;
    }

    protected function error()
    {
        throw new Exception('ContextualBindingException');
    }

    /**
     * Register a binding with the container.
     *
     * @param string|array                $abstract // TODO: bind from array
     * @param string|\Closure|Object|null $concrete
     * @param bool                        $shared
     * @param bool                        $mutable
     * @throws \Exception
     */
    public function bind($abstract, $concrete = null, $shared = false, $mutable = false)
    {
        $concrete = $concrete ?: $abstract;
        $placeholder = &$this->bindings[$abstract];
        $make = $this->proxy($concrete, $placeholder);
        $placeholder = [
            self::PROXY => $shared
                ? $mutable
                    ? function (&$params) use (&$make, &$placeholder) {
                        return ($shared = &$placeholder[self::SHARE]) && !$params
                            ? $shared
                            : $shared = $make($params);
                    }
                    : function (&$params) use (&$make, &$placeholder) {
                        return $placeholder[self::SHARE] ?: $placeholder[self::SHARE] = $make($params);
                    }
                : $make,
            self::SHARE => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function singleton($abstract, $concrete)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * {@inheritdoc}
     */
    public function mutable($abstract, $concrete)
    {
        $this->bind($abstract, $concrete, true, true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $abstract, $instance)
    {
        $placeholder = &$this->bindings[$abstract];
        $placeholder = new SplFixedArray(1);
        $placeholder[self::PROXY] = function () use ($instance) {
            return $instance;
        };
    }

    /**
     * {@inheritdoc}
     */
    public function alias(string $alias, $abstract)
    {
        $this->bindings[$alias] = &$this->bindings[$abstract];
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array  $params
     * @return mixed
     */
    protected function bindAndMake(string &$abstract, array &$params = [])
    {
        if (! isset($this->bindings[$abstract])) {
            isset($this->resolved[$abstract])
                ? ++$this->resolved[$abstract]
                : $this->resolved[$abstract] = 1; // statistic
            $this->bind($abstract);
        }

        return $this->bindings[$abstract][self::PROXY]($params);
    }

    protected function proxy(&$concrete, &$placeholder)
    {
        if (is_string($concrete) && class_exists($concrete)) {
            return function(&$params) use (&$concrete, &$placeholder) {
                return $this->create($concrete, $params, $placeholder[self::STACK]);
                // TODO: add is_callable instance check
            };
        }

        if (is_callable($concrete)) {
            return function(&$params) use (&$concrete, &$placeholder) {
                return $this->invoke($concrete, $params, $placeholder[self::STACK]);
            };
        }

        return function() use (&$concrete, &$placeholder) {
            return $placeholder[self::SHARE] = $concrete;
        };
    }

    /**
     * @param string $abstract
     * @param array  $params
     * @return mixed
     */
    public function make(string $abstract, array $params = [])
    {
        return $this->bindAndMake($abstract, $params);
    }

    /**
     * @param string $abstract
     * @return bool
     */
    public function isShared(string $abstract) : bool
    {
        return isset($this->bindings[$abstract]) && ! ! $this->bindings[$abstract][self::SHARE];
    }

    /**
     * {@inheritdoc}
     */
    public function create(
        string &$concrete,
        array &$params,
        &$stack = null
    ) {
        $reflectionClass = new ReflectionClass($concrete);
        $constructor = $reflectionClass->getConstructor();
        $instance = ($constructor && $reflectionParams = $constructor->getParameters())
            ? $reflectionClass->newInstanceArgs($this->build($stack
                ?: $stack = $this->getStack($reflectionParams), $params))
            : new $concrete;

        return $this->isContainerAccessible($instance) ? $instance->__registerContainer($this) : $instance;
    }

    /**
     * {@inheritdoc}
     */
    public function invoke(
        callable $callable,
        array &$params,
        &$stack = null
    ) {
        $reflectionFunction = new ReflectionFunction($callable);
        $instance = ($reflectionParams = $reflectionFunction->getParameters())
            ? $reflectionFunction->invokeArgs($this->build($stack
                ?: $stack = $this->getStack($reflectionParams), $params))
            : call_user_func_array($callable, $params);

        return $this->isContainerAccessible($instance) ? $instance->__registerContainer($this) : $instance;
    }

    /**
     * @param array|callable $callable
     * @param array $params
     */
    public function call($callable, array $params)
    {
        if (is_array($callable)) {
            $instance = $this->bindAndMake($callable[0], $params);
        }
    }

    /**
     * Get stack of classes and parameters for automatic building
     *
     * @param array $params
     * @return SplFixedArray|null $stack
     */
    protected function getStack(array $params)
    {
        $index = -1;
        $length = count($params);
        $stack = new SplFixedArray($length);
        while ($length) {
            $stack[++$index] = $params[--$length]->getClass() ?: $params[$length];
        }

        return $stack;
    }

    /**
     * Build and inject all dependencies with parameters
     *
     * @param SplFixedArray $stack
     * @param array         $params
     * @return array $building
     */
    protected function build(SplFixedArray $stack, array &$params)
    {
        $stackLength = count($stack);
        $building = [];
        while ($stackLength) {
            $item = $stack[--$stackLength];
            $item instanceof ReflectionClass
                ? $building[] = $this->isShared($item->name)
                ? $this->bindings[$item->name][self::SHARE]
                : $this->bindAndMake($item->name, $params)
                : empty($params) ?: $building[] = array_shift($params);
        }

        return $building;

//        $length   = count($params);
//        $index    = count($stack) - 1 - $length;
//        $building = new SplFixedArray($length);
//        while ($length) {
//            $item                = $stack[++$index];
//            $building[--$length] = $item instanceof ReflectionParameter ? array_pop($params)
//                : $this->makeInstance($item->name, $params);
//        }
//        return $building;
    }

    protected function packScope($scope, \Closure $method)
    {
        if (isset($this->config[$scope])) {
            foreach ($this->config[$scope] as $abstract => $paramsOrConcrete) {
                if (is_array($paramsOrConcrete)) {
                    $concrete = $paramsOrConcrete[KeyEnum::Concrete] ?? $abstract;
                    if (isset($paramsOrConcrete[KeyEnum::Alias])) {
                        $this->alias($paramsOrConcrete[KeyEnum::Alias], $abstract);
                    }
                    $method($abstract, $concrete, $paramsOrConcrete[KeyEnum::Arguments] ?? null);
                } else {
                    $method($abstract, $paramsOrConcrete);
                }
            }
        }
    }

    public function init(array $config)
    {
        $this->config = $config;
        $bootServices = [];
        $this->packScope(ScopeEnum::Instantiated, function ($abstract, $concrete) {
            $this->instance($abstract, $concrete);
        });
        $this->packScope(ScopeEnum::Disposable, function ($abstract, $concrete) {
            $this->bind($abstract, $concrete);
        });
        $this->packScope(ScopeEnum::Mutable, function (
            $abstract,
            $concrete,
            $arguments = null
        ) use (&$bootServices) {
            $this->mutable($abstract, $concrete);
            if ($arguments) {
                $bootService = $this->bindAndMake($abstract, $arguments);
                if ($this->isBootable($bootService)) {
                    $bootServices[] = $bootService;
                }
            }
        });
        $this->packScope(ScopeEnum::Single, function (
            $abstract,
            $concrete,
            $arguments = []
        ) use (&$bootServices) {
            $this->singleton($abstract, $concrete);
            $bootService = $this->bindAndMake($abstract, $arguments);
            if ($this->isBootable($bootService)) {
                $bootServices[] = $bootService;
            }
        });
        foreach ($bootServices as $service) {
            $service->__boot();
        }
        unset($bootServices);
    }

//    public function __destruct() {
//        $elapsed = (microtime(true) - $this->startTime) * 1000;
//        echo "<br /><br /><hr />Container execution time : $elapsed ms";
//    }
}