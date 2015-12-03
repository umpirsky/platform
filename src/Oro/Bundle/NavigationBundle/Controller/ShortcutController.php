<?php

namespace Oro\Bundle\NavigationBundle\Controller;

use Knp\Menu\ItemInterface;
use Knp\Menu\Iterator\RecursiveItemIterator;

use Oro\Bundle\NavigationBundle\Provider\BuilderChainProvider;
use Oro\Bundle\SecurityBundle\Annotation\Acl;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @Route("/shortcut")
 */
class ShortcutController extends Controller
{
    protected $uris = array();

    /**
     * @Route("actionslist", name="oro_shortcut_actionslist")
     * @Template
     */
    public function actionslistAction()
    {
        /** @var $provider BuilderChainProvider */
        $provider = $this->container->get('oro_menu.builder_chain');
        /**
         * merging shortcuts and application menu
         */
        $shortcuts = $provider->get('shortcuts');
        $menuItems = $provider->get('application_menu');
        $result = array_merge($this->getResults($shortcuts), $this->getResults($menuItems));
        ksort($result);

        return array(
            'actionsList'  => $result,
        );
    }

    /**
     * @param ItemInterface $items
     * @return array
     */
    protected function getResults(ItemInterface $items)
    {
        /** @var $translator TranslatorInterface */
        $translator = $this->get('translator');
        $itemIterator = new RecursiveItemIterator($items);
        $iterator = new \RecursiveIteratorIterator($itemIterator, \RecursiveIteratorIterator::SELF_FIRST);
        $result = array();
        /** @var $item ItemInterface */
        foreach ($iterator as $item) {
            if ($item->getExtra('isAllowed') && !in_array($item->getUri(), $this->uris) && $item->getUri() !== '#') {
                $key = $translator->trans($item->getLabel());
                $result[$key] = array('url' => $item->getUri(), 'description' => $item->getExtra('description'));
                $this->uris[] = $item->getUri();
            }
        }

        return $result;
    }
}
