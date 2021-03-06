<?php
/**
 * Created by PhpStorm.
 * User: gseidel
 * Date: 2019-04-11
 * Time: 21:06
 */

namespace Enhavo\Bundle\AppBundle\Viewer;

use Enhavo\Bundle\AppBundle\Template\TemplateTrait;
use FOS\RestBundle\View\View;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractViewer implements ViewerInterface
{
    use ContainerAwareTrait;
    use TemplateTrait;

    abstract public function getType();

    /**
     * {@inheritdoc}
     */
    public function createView($options = []): View
    {
        $view = View::create(null, 200);
        $templateVars = [];

        $templateVars['stylesheets'] = $options['stylesheets'];
        $templateVars['javascripts'] = $options['javascripts'];

        if($options['translations']) {
            $templateVars['translations'] = $this->getTranslations();
        }

        if($options['routes']) {
            $templateVars['routes'] = $this->getRoutes();
        }

        $templateVars['data'] = [
            'view' => [
                'view_id' => $this->getViewId(),
                'label' => $this->container->get('translator')->trans($options['label'], [], $options['translation_domain']),
            ],
            'modals' => [],
        ];

        $view->setTemplateData($templateVars);

        $template = $options['template'];
        $request = $this->container->get('request_stack')->getMasterRequest();
        if($request->attributes->has('_template')) {
            $template = $request->attributes->get('_template');
        }

        $view->setTemplate($this->getTemplate($template));

        return $view;
    }

    private function getRoutes()
    {
        $file = $this->container->getParameter('kernel.project_dir').'/public/js/fos_js_routes.json';
        if(file_exists($file)) {
            return file_get_contents($file);
        }
        return null;
    }

    private function getTranslations()
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $dumper = $this->container->get('enhavo_app.translation.translation_dumper');
        $translations = $dumper->getTranslations('javascript', $request->getLocale());
        return $translations;
    }

    protected function getViewId()
    {
        return $this->container->get('request_stack')->getMasterRequest()->get('view_id');
    }

    public function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setDefaults([
            'javascripts' => [],
            'stylesheets' => [],
            'translations' => false,
            'routes' => false,
            'template' => 'admin/view/base.html.twig',
            'label' => null,
            'translation_domain' => null
        ]);
    }
}
