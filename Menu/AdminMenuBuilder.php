<?php
/**
 * This file is part of the Networking package.
 *
 * (c) net working AG <info@networking.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Networking\InitCmsBundle\Menu;

use Sonata\AdminBundle\Admin\AdminInterface;
use Networking\InitCmsBundle\Doctrine\Extensions\Versionable\VersionableInterface;
use Networking\InitCmsBundle\Doctrine\Extensions\Versionable\ResourceVersionInterface;
use Networking\InitCmsBundle\Admin\Pool;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AdminMenuBuilder
 * @package Networking\InitCmsBundle\Component\Menu
 * @author Yorkie Chadwick <y.chadwick@networking.ch>
 */
class AdminMenuBuilder extends MenuBuilder
{
    /**
     * @var Pool
     */
    protected $adminPool;

    /**
     * @param $adminPool
     */
    public function setAdminPool($adminPool)
    {
        $this->adminPool = $adminPool;
    }

    /**
     * @return bool|\Knp\Menu\ItemInterface
     */
    public function createAdminMenu()
    {

        // Default to homepage
        $liveRoute = null;

        $livePath = null;

        $draftRoute = null;

        $draftPath = null;

        $menu = $this->factory->createItem('root');

        $sonataAdmin = null;

        $entity = null;

        $defaultHome = $this->router->generate('networking_init_cms_default');

        $adminLocale = $this->request->getSession()->get('admin/_locale');
        $class = 'nav navbar-nav';
        if ($this->request->isXmlHttpRequest()) {
            $class = 'initnav';
        }

        if ($this->isLoggedIn) {

            $editPath = false;
            $menu->setChildrenAttribute('class', $class . ' pull-right');

            $dashboardUrl = $this->router->generate('sonata_admin_dashboard');

            if ($sonataAdminParam = $this->request->get('_sonata_admin')) {

                $possibleAdmins = explode('|', $sonataAdminParam);

                foreach ($possibleAdmins as $adminCode) {
                    // we are in the admin area
                    $sonataAdmin = $this->adminPool->getAdminByAdminCode($adminCode);
                    if ($id = $this->request->get('id')) {
                        $entity = $sonataAdmin->getObject($id);
                    }
                }

            } else {
                // we are in the frontend
                $entity = $this->request->get('_content');
            }

            if ($entity instanceof VersionableInterface) {
                if ($snapShot = $entity->getSnapshot()) {
                    $liveRoute = $this->router->generate($snapShot->getRoute());
                }
                $draftRoute = $this->router->generate($entity->getRoute());
                $pageAdmin = $this->adminPool->getAdminByAdminCode('networking_init_cms.admin.page');
                $editPath = $pageAdmin->generateObjectUrl('edit', $entity);

                $language = $entity->getRoute()->getLocale();
            } elseif ($entity instanceof ResourceVersionInterface) {
                $liveRoute = $this->router->generate($entity->getRoute());
                $draftRoute = $this->router->generate($entity->getPage()->getRoute());

                $pageAdmin = $this->adminPool->getAdminByAdminCode('networking_init_cms.admin.page');
                $editPath = $pageAdmin->generateObjectUrl('edit', $entity->getPage());

                $language = $entity->getRoute()->getLocale();
            }

            if (!isset($language)) {
                $language = $this->request->getLocale();
            }


            if ($draftRoute) {
                $draftPath = $this->router->generate(
                    'networking_init_view_draft',
                    array('locale' => $language, 'path' => base64_encode($draftRoute))
                );
            } else {

                $draftPath = $this->router->generate(
                    'networking_init_view_draft',
                    array('locale' => $language, 'path' => base64_encode($this->request->getBaseUrl()))
                );
            }
            if ($liveRoute) {
                $livePath = $this->router->generate(
                    'networking_init_view_live',
                    array('locale' => $language, 'path' => base64_encode($liveRoute))
                );
            } else {
                $livePath = $this->router->generate(
                    'networking_init_view_live',
                    array('locale' => $language, 'path' => base64_encode($this->request->getBaseUrl()))
                );

            }


            $session = $this->request->getSession();
            $lastActionUrl = $dashboardUrl;
            $lastActions = $session->get('_networking_initcms_admin_tracker');


            if ($lastActions) {
                $lastActionArray = json_decode($lastActions);
                if (count($lastActionArray)) {
                    if ($this->request->get('_route') == 'sonata_admin_dashboard' || $sonataAdmin) {
                        $lastAction = next($lastActionArray);
                    } else {
                        $lastAction = reset($lastActionArray);
                    }
                    if ($lastAction) {
                        $lastActionUrl = $lastAction->url;
                    }
                }
            }

            if ($editPath && !$sonataAdmin) {

                $menu->addChild(
                    'Edit',
                    array(
                        'label' => $this->translator->trans(
                            'link_action_edit',
                            array(),
                            'SonataAdminBundle',
                            $adminLocale
                        ),
                        'uri' => $editPath
                    )
                );
                $this->addIcon($menu['Edit'], array('icon' => 'pencil', 'append' => false));
            }
            if (!$sonataAdmin && $this->request->get('_route') != 'sonata_admin_dashboard') {
                $menu->addChild('Admin', array('uri' => $lastActionUrl));
            }


            $viewStatus = $this->request->getSession()->get('_viewStatus');
            $translator = $this->translator;
            $webLink = $translator->trans(
                'link.website_' . $viewStatus,
                array(),
                'NetworkingInitCmsBundle',
                $adminLocale
            );

            if ($editPath && !$sonataAdmin) {
                $webLink = $translator->trans(
                    'link.website_' . $viewStatus,
                    array(),
                    'NetworkingInitCmsBundle',
                    $adminLocale
                );

            }

            $dropdown = $menu->addChild(
                $webLink,
                array(
                    'dropdown' => true,
                    'caret' => true,
                )
            );

            if ($draftPath) {
                $dropdown->addChild(
                    $translator->trans('view_website.status_draft', array(), 'NetworkingInitCmsBundle', $adminLocale),
                    array('uri' => $draftPath, 'linkAttributes' => array('class' => 'color-draft'))
                );
            }
            if ($livePath) {
                $dropdown->addChild(
                    $translator->trans(
                        'view_website.status_published',
                        array(),
                        'NetworkingInitCmsBundle',
                        $adminLocale
                    ),
                    array('uri' => $livePath, 'linkAttributes' => array('class' => 'color-published'))
                );
            }

            if (!$draftPath && !$livePath) {
                $dropdown->addChild(
                    $translator->trans('view_website.status_draft', array(), 'NetworkingInitCmsBundle', $adminLocale),
                    array('uri' => $defaultHome, 'linkAttributes' => array('class' => 'color-draft'))
                );
                $dropdown->addChild(
                    $translator->trans(
                        'view_website.status_published',
                        array(),
                        'NetworkingInitCmsBundle',
                        $adminLocale
                    ),
                    array('uri' => $defaultHome)
                );
            }

        }

        return $menu;
    }

    /**
     * Use service to create main admin navigation
     *
     * @param array $menuGroups
     * @return \Knp\Menu\ItemInterface
     */
    public function createAdminSideMenu(array $menuGroups)
    {
        $groups = $this->adminPool->getDashboardGroups();
        $adminMenu = $this->factory->createItem('admin_side_menu');

        if (!$this->authorizationChecker->isGranted('ROLE_SONATA_ADMIN')) {
            return $adminMenu;
        }

        $iconSize = 'large';
        $firstLevelClass = 'first-level';

        $largeMenu = $adminMenu->addChild(
            'large',
            array(
                'attributes' => array('class' => 'nav-custom'))
        );
        $smallMenu = $adminMenu->addChild(
            'small',
            array(
                'attributes' => array('class' => 'nav-custom nav-custom-small', 'style' => 'margin-top: 50px;'))
        );

        $largeMenu->addChild(
            $this->translator->trans('admin.menu.dashboard', array(), 'PageAdmin'),
            array(
                'uri' => $this->router->generate('sonata_admin_dashboard'),
                'current' => $this->request->get('_sonata_admin') ? false : true,
                'attributes' => array('class' => $firstLevelClass, 'icon' => 'glyphicon glyphicon-dashboard', 'icon_size' => 'glyphicon-'.$iconSize),
                'linkAttributes' => array('class' => 'pull-left first-level-text')
            )
        );

        $menu = $largeMenu;
        foreach ($menuGroups as $key => $item) {
            foreach ($item['items'] as $mainMenu) {
                if (array_key_exists($mainMenu, $groups)) {
                    $group = $groups[$mainMenu];
                    if (count($group['items']) > 0) {
                        $first = reset($group['items']);
                        if ($first instanceof AdminInterface && $first->hasRoute('list')) {

                            $current = false;
                            $icon = 'glyphicon-unchecked';
                            $iconType = 'glyphicon';

                            if ($this->request->get('_sonata_admin') == $first->getCode()) {
                                $current = true;
                            }

                            if (method_exists($first, 'getIcon')) {
                                $icon = $first->getIcon();
                            }
                            if(substr($icon, 0, '2') == 'fa'){
                                $iconType = 'fa';
                            }


                            $mainItem = $menu->addChild(
                                $this->translator->trans($group['label'], array(), $group['label_catalogue'] != 'SonataAdminBundle' ? $group['label_catalogue'] : $first->getTranslationDomain()),
                                array(
                                    'uri' => $first->generateUrl('list'),
                                    'current' => $current,
                                    'attributes' => array('class' => $firstLevelClass, 'icon' => sprintf('%s %s', $iconType, $icon), 'icon_size' => sprintf('%s-%s', $iconType, $iconSize)),
                                    'linkAttributes' => array('class' => 'pull-left first-level-text')
                                )
                            );

                            if (count($group['items']) > 1) {
                                foreach ($group['items'] as $subItem) {
                                    $current = false;
                                    if ($this->request->get('_sonata_admin') == $subItem->getCode()) {
                                        $current = true;
                                    }
                                    if ($subItem instanceof AdminInterface && $subItem->hasRoute('list')) {
                                        $mainItem->addChild(
                                            $this->translator->trans($subItem->getLabel(), array(), $subItem->getTranslationDomain()),
                                            array(
                                                'uri' => $subItem->generateUrl('list'),
                                                'current' => $current,
                                                'attributes' => array('class' => 'second-level', 'icon' => $icon, 'icon_size' => $iconSize)
                                            )
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            //
            $iconSize = 'small';
            $firstLevelClass = 'first-level-small';
            $this->showOnlyCurrentChildren($menu);
            $menu = $smallMenu;
        }


        return $adminMenu;
    }

    /**
     * @param $item
     * @param $icon
     * @return mixed
     */
    protected function addIcon($item, $icon)
    {

        $icon = array_merge(array('tag' => (isset($icon['glyphicon'])) ? 'span' : 'i'), $icon);
        $addclass = "";
        if (isset($icon['inverted']) && $icon['inverted'] === true) {
            $addclass = " icon-white";
        }
        $classicon = (isset($icon['glyphicon'])) ? ' class="' . $icon['glyphicon'] : ' class="fa fa-' . $icon['icon'];
        $myicon = ' <' . $icon['tag'] . $classicon . $addclass . '"></' . $icon['tag'] . '>';
        if (!isset($icon['append']) || $icon['append'] === true) {
            $label = $item->getLabel() . " " . $myicon;
        } else {
            $label = $myicon . " " . $item->getLabel();
        }
        $item->setLabel($label)
            ->setExtra('safe_label', true);
        return $item;
    }


}
