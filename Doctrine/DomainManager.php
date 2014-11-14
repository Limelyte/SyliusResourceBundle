<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ResourceBundle\Doctrine;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Resource\Event\ResourceEvent;
use Sylius\Component\Resource\Manager\DomainManagerInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Domain manager.
 *
 * @author Joseph Bielawski <stloyd@gmail.com>
 * @author Paweł Jędrzejewski <pawel@sylius.org>
 */
class DomainManager implements DomainManagerInterface
{
    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var string
     */
    protected $resourceName;

    /**
     * @var string
     */
    protected $bundlePrefix;

    /**
     * @var string
     */
    protected $className;

    public function __construct(ObjectManager $manager, EventDispatcherInterface $eventDispatcher, $bundlePrefix, $resourceName, ClassMetadata $class)
    {
        $this->manager = $manager;
        $this->eventDispatcher = $eventDispatcher;
        $this->bundlePrefix = $bundlePrefix;
        $this->resourceName = $resourceName;
        $this->className = $class->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function createNew()
    {
        return new $this->className();
    }

    /**
     * {@inheritdoc}
     */
    public function create($resource = null, $eventName = 'create')
    {
        if (null === $resource) {
            $resource = $this->createNew();
        }

        return $this->process($resource, 'persist', $eventName);
    }

    /**
     * {@inheritdoc}
     */
    public function update($resource, $eventName = 'update')
    {
        return $this->process($resource, 'persist', $eventName);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($resource, $eventName = 'delete')
    {
        return $this->process($resource, 'remove', $eventName);
    }

    /**
     * @param string $name
     * @param Event  $event
     *
     * @return ResourceEvent
     */
    protected function dispatchEvent($name, Event $event)
    {
        return $this->eventDispatcher->dispatch($this->getEventName($name), $event);
    }

    private function getEventName($eventName)
    {
        return sprintf('%s.%s.%s', $this->bundlePrefix, $this->resourceName, $eventName);
    }

    private function process($resource, $action, $eventName)
    {
        if (!in_array($action, array('persist', 'remove'))) {
            throw new \InvalidArgumentException(sprintf('Unknown object manager action called "%s".', $action));
        }

        $event = $this->dispatchEvent('pre_'.$eventName, new ResourceEvent($resource));

        if ($event->isStopped()) {
            return null;
        }

        $manager = $this->manager;
        if ($manager instanceof EntityManagerInterface) {
            $manager->transactional(function ($manager) use ($resource, $action) {
                $manager->{$action}($resource);
            });
        } else {
            $manager->{$action}($resource);
        }

        $this->dispatchEvent('post_'.$eventName, new ResourceEvent($resource));

        return $resource;
    }
}
