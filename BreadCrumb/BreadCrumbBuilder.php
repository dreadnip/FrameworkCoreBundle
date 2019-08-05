<?php

namespace SumoCoders\FrameworkCoreBundle\BreadCrumb;

use JMS\I18nRoutingBundle\Router\DefaultPatternGenerationStrategy;
use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuItem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use SumoCoders\FrameworkCoreBundle\Event\ConfigureMenuEvent;

final class BreadCrumbBuilder
{
    /**
     * @var bool
     */
    private $extractFromRoute = true;

    /**
     * @var string
     */
    private $routingStrategy;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var array
     */
    private $items = [];

    public function __construct(
        string $routingStrategy,
        RequestStack $requestStack,
        FactoryInterface $factory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->routingStrategy = $routingStrategy;
        $this->requestStack = $requestStack;
        $this->factory = $factory;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param RequestStack $requestStack
     * @return \Knp\Menu\ItemInterface
     */
    public function createBreadCrumb(RequestStack $requestStack)
    {
        $this->extractItemsBasedOnTheRequest($requestStack);

        $menu = $this->factory->createItem('root');
        $menu->setChildrenAttribute('class', 'breadcrumb');

        foreach ($this->items as $i => $item) {
            // the last item shouldn't have a link
            if ((count($this->items) - 1) == $i) {
                $item->setUri(null);
            }

            $menu->addChild($item);
        }

        return $menu;
    }

    /**
     * @return BreadCrumbBuilder
     */
    public function dontExtractFromTheRequest()
    {
        $this->extractFromRoute = false;

        return $this;
    }

    /**
     * Extract the items for the breadcrumb based on a uri
     *
     * @param string $uri
     * @param string $locale
     * @return BreadCrumbBuilder
     */
    public function extractItemsBasedOnUri($uri, $locale)
    {
        $this->dontExtractFromTheRequest();

        $item = $this->findItemBasedOnUri(
            $this->getTheMenu(),
            $uri
        );

        if ($item !== null) {
            $this->addItemsBasedOnTheChild($item, $locale);
        }

        return $this;
    }

    /**
     * Add an item into the breadcrumb
     *
     * @param MenuItem $item
     * @return BreadCrumbBuilder
     */
    public function addItem(MenuItem $item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Add a item into the breadcrumb by passing the label and an optional URI.
     *
     * @param string $label
     * @param string $uri
     * @return BreadCrumbBuilder
     */
    public function addSimpleItem($label, $uri = null)
    {
        $item = new MenuItem($label, $this->factory);
        $item->setLabel($label);
        if ($uri !== null) {
            $item->setUri($uri);
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Reset the items and add the given items all at once
     *
     * @param array $items
     * @return BreadCrumbBuilder
     */
    public function overwriteItems(array $items)
    {
        $this->items = $items;

        return $this;
    }

    /**
     * Add items into the breadcrumb based on a given child.
     *
     * @param MenuItem $item
     * @param string   $locale
     */
    private function addItemsBasedOnTheChild(MenuItem $item, $locale)
    {
        if ($item !== null) {
            $items = [];
            $temporaryItem = $item;

            while ($temporaryItem->getParent() !== null) {
                $breadCrumb = new MenuItem($temporaryItem->getName(), $this->factory);
                $breadCrumb->setLabel($temporaryItem->getLabel());

                if ($temporaryItem->getUri() !== '#' && $temporaryItem->getUri() !== null) {
                    $breadCrumb->setUri($temporaryItem->getUri());
                }
                $items[] = $breadCrumb;

                $temporaryItem = $temporaryItem->getParent();
            }

            $home = new MenuItem('core.menu.home', $this->factory);
            $home->setLabel('core.menu.home');
            $homeUri = '/';

            if ($this->localeShouldBeParsed($locale)) {
                $homeUri .= $locale;
            }

            $home->setUri($homeUri);

            $this->items[] = $home;

            $this->items = array_merge($this->items, array_reverse($items));
        }
    }

    private function localeShouldBeParsed(string $locale): bool
    {
        // A custom or prefix routing strategy should always contain the locale
        if ($this->routingStrategy === DefaultPatternGenerationStrategy::STRATEGY_CUSTOM
            || $this->routingStrategy === DefaultPatternGenerationStrategy::STRATEGY_PREFIX) {
            return true;
        }

        // If the current locale is the default locale, don't parse it
        if ($this->requestStack->getCurrentRequest()->getDefaultLocale() === $locale) {
            return false;
        }

        return true;
    }

    /**
     * Grab the menu
     *
     * This method will use the ConfigureMenuEvent to get all the items
     *
     * @return \Knp\Menu\ItemInterface
     */
    private function getTheMenu()
    {
        $menu = $this->factory->createItem('root');

        $this->eventDispatcher->dispatch(
            ConfigureMenuEvent::EVENT_NAME,
            new ConfigureMenuEvent(
                $this->factory,
                $menu
            )
        );

        return $menu;
    }

    /**
     * Find an item in the menu based on its URI
     *
     * @param MenuItem $menuItem
     * @param          $uri
     * @return MenuItem|null
     */
    private function findItemBasedOnUri(MenuItem $menuItem, $uri)
    {
        if ($uri === $menuItem->getUri()) {
            return $menuItem;
        }

        if (!$menuItem->hasChildren()) {
            return null;
        }

        foreach ($menuItem->getChildren() as $child) {
            $item = $this->findItemBasedOnUri(
                $child,
                $uri
            );

            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Extract the items for the breadcrumb based on request
     *
     * @param RequestStack $requestStack
     */
    private function extractItemsBasedOnTheRequest(RequestStack $requestStack)
    {
        if (!$this->extractFromRoute) {
            return;
        }

        $this->extractItemsBasedOnUri(
            $requestStack->getCurrentRequest()->getPathInfo(),
            $requestStack->getCurrentRequest()->getLocale()
        );
    }
}
