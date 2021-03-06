<?php

namespace SpiffyNavigation\Service;

use InvalidArgumentException;
use RecursiveIteratorIterator;
use RuntimeException;
use SpiffyNavigation\Container;
use SpiffyNavigation\Listener;
use SpiffyNavigation\NavigationEvent;
use SpiffyNavigation\Page\Page;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Router\RouteStackInterface;

class Navigation
{
    const EVENT_GET_HREF  = 'get.href';
    const EVENT_IS_ACTIVE = 'is.active';

    /**
     * Array of containers.
     * @var array
     */
    protected $containers = array();

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * Href cache using object hash.
     * @var array
     */
    protected $hrefCache = array();

    /**
     * Flag to set whether or not recursion should be enabled for isActive() checks.
     * Warning: modifying this value will invalidate the isActive() cache.
     * @var bool
     */
    protected $isActiveRecursion = true;

    /**
     * Active cache using object hash.
     * @var array
     */
    protected $isActiveCache = array();

    /**
     * Router used when assmebling href's from MVC.
     * @var RouteStackInterface
     */
    protected $router;

    /**
     * Route match used for determining active status.
     * @var RouteMatch
     */
    protected $routeMatch;

    /**
     * Register the default events for this controller
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        $events = $this->getEventManager();
        $events->attach(new Listener\HrefListener());
    }

    /**
     * Set the event manager instance used by this context
     *
     * @param  EventManagerInterface $events
     * @return Navigation
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            'Zend\Stdlib\DispatchableInterface',
            __CLASS__,
            get_called_class()
        ));
        $this->events = $events;
        $this->attachDefaultListeners();

        return $this;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }

        return $this->events;
    }

    /**
     * Check if a page is marked active.
     *
     * @param Page $page
     * @return bool
     */
    public function isActive(Page $page)
    {
        $hash = spl_object_hash($page);

        if (isset($this->isActiveCache[$hash])) {
            return $this->isActiveCache[$hash];
        }

        $active = false;
        if ($this->getRouteMatch()) {
            $props = $page->getProperties();
            $name  = $this->getRouteMatch()->getMatchedRouteName();

            if (isset($props['route']) && $props['route'] == $name) {
                $active = true;
            } else if ($this->getIsActiveRecursion()) {
                $iterator = new RecursiveIteratorIterator($page, RecursiveIteratorIterator::CHILD_FIRST);

                /** @var \SpiffyNavigation\Page\Page $page */
                foreach ($iterator as $leaf) {
                    if ($this->isActive($leaf)) {
                        $active = true;
                        break;
                    }
                }
            }
        }

        $this->isActiveCache[$hash] = $active;
        return $active;
    }

    /**
     * Get the href for a page.
     *
     * @param Page $page
     * @return string
     * @throws RuntimeException when an href can not be generated.
     */
    public function getHref(Page $page)
    {
        $hash = spl_object_hash($page);

        if (isset($this->hrefCache[$hash])) {
            return $this->hrefCache[$hash];
        }

        $event = new NavigationEvent();
        $event->setNavigation($this)
              ->setTarget($page);

        $this->getEventManager()->trigger(self::EVENT_GET_HREF, $event);
        $href = $event->getResult();

        if (!$href) {
            throw new RuntimeException('Unable to construct href');
        }

        $this->hrefCache[$hash] = $href;
        return $href;
    }

    /**
     * Add a container to the stack.
     *
     * @param string $name
     * @param Container $container
     * @return Navigation
     * @throws InvalidArgumentException on duplicate container
     */
    public function addContainer($name, Container $container)
    {
        if ($this->hasContainer($name)) {
            throw new InvalidArgumentException(sprintf(
                'A container with name "%s" already exists.',
                $name
            ));
        }
        $this->containers[$name] = $container;
        return $this;
    }

    /**
     * Get a container by name.
     *
     * @param string $name
     * @return Container
     * @throws InvalidArgumentException on missing container
     */
    public function getContainer($name)
    {
        if (!$this->hasContainer($name)) {
            throw new InvalidArgumentException(sprintf(
                'No container with name "%s" could be found.',
                $name
            ));
        }
        return $this->containers[$name];
    }

    /**
     * Check if a container with name exists.
     *
     * @param string $name
     * @return bool
     */
    public function hasContainer($name)
    {
        return isset($this->containers[$name]);
    }

    /**
     * Get containers.
     *
     * @return array
     */
    public function getContainers()
    {
        return $this->containers;
    }

    /**
     * Remove a container from the stack.
     *
     * @param string $name
     * @return Navigation
     * @throws InvalidArgumentException on missing container
     */
    public function removeContainer($name)
    {
        if (!$this->hasContainer($name)) {
            throw new InvalidArgumentException(sprintf(
                'No container with name "%s" could be found.',
                $name
            ));
        }
        unset($this->containers[$name]);
        return $this;
    }

    /**
     * Clear containers.
     *
     * @return Navigation
     */
    public function clearContainers()
    {
        $this->containers = array();
        return $this;
    }

    /**
     * @param RouteMatch $routeMatch
     * @return Navigation
     */
    public function setRouteMatch($routeMatch)
    {
        $this->routeMatch = $routeMatch;
        return $this;
    }

    /**
     * @return RouteMatch
     */
    public function getRouteMatch()
    {
        return $this->routeMatch;
    }

    /**
     * @param \Zend\Mvc\Router\RouteStackInterface $router
     */
    public function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * @return \Zend\Mvc\Router\RouteStackInterface
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @param boolean $isActiveRecursion
     * @return Navigation
     */
    public function setIsActiveRecursion($isActiveRecursion)
    {
        if ($isActiveRecursion != $this->isActiveRecursion) {
            $this->isActiveRecursion = $isActiveRecursion;
            $this->isActiveCache     = array();
        }
        return $this;
    }

    /**
     * @return boolean
     */
    public function getIsActiveRecursion()
    {
        return $this->isActiveRecursion;
    }
}