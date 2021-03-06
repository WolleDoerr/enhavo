<?php
/**
 * Created by PhpStorm.
 * User: gseidel
 * Date: 2019-01-17
 * Time: 11:46
 */

namespace Enhavo\Bundle\UserBundle\Controller;

use Enhavo\Bundle\AppBundle\Template\TemplateTrait;
use Enhavo\Bundle\AppBundle\Viewer\ViewFactory;
use Enhavo\Bundle\UserBundle\Form\Type\ResetPasswordType;
use Enhavo\Bundle\UserBundle\User\UserManager;
use FOS\RestBundle\View\ViewHandler;
use FOS\UserBundle\Form\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResetPasswordController extends AbstractController
{
    use TemplateTrait;
    use UserConfigurationTrait;

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var FactoryInterface
     */
    private $formFactory;

    /**
     * @var ViewHandler
     */
    private $viewHandler;

    /**
     * @var ViewFactory
     */
    private $viewFactory;

    /**
     * ResetPasswordController constructor.
     * @param ViewHandler $viewHandler
     * @param ViewFactory $viewFactory
     * @param UserManager $userManager
     * @param FactoryInterface $formFactory
     */
    public function __construct(ViewHandler $viewHandler, ViewFactory $viewFactory, UserManager $userManager, FactoryInterface $formFactory)
    {
        $this->userManager = $userManager;
        $this->formFactory = $formFactory;
        $this->viewHandler = $viewHandler;
        $this->viewFactory = $viewFactory;
    }

    public function resetAction(Request $request)
    {
        $configuration = $this->createConfiguration($request);

        if($request->getMethod() == 'POST') {
            $user = $this->userManager->findUserByUsernameOrEmail($request->get('username'));
            if($user === null) {
                $this->addFlash('error', $this->get('translator')->trans('reset.form.error.invalid-user', [], 'EnhavoUserBundle'));
            } else {
                $this->addFlash('success', $this->get('translator')->trans('reset.message.success', [], 'EnhavoUserBundle'));
                $this->userManager->sendResetEmail($user, $configuration->getMailTemplate(), $configuration->getConfirmRoute());
                return $this->redirectToRoute($configuration->getRedirectRoute('enhavo_user_theme_reset_password_check'));
            }
        }

        $view = $this->viewFactory->create('reset', [
            'template' => $configuration->getTemplate()
        ]);

        return $this->viewHandler->handle($view);
    }

    public function checkAction(Request $request)
    {
        return $this->render($this->getTemplate('theme/security/registration/check.html.twig'));
    }

    public function confirmAction(Request $request)
    {
        $configuration = $this->createConfiguration($request);

        $token = $request->get('token');
        $user = $this->userManager->findUserByConfirmationToken($token);

        if($user === null) {
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(ResetPasswordType::class, $user, [
            'validation_groups' => 'reset'
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted()) {
            if ($form->isValid()) {
                $user->setConfirmationToken(null);
                $user->setPasswordRequestedAt(null);
                $this->userManager->updateUser($user);

                $url = $this->generateUrl($configuration->getRedirectRoute('enhavo_app_index'));
                $response = new RedirectResponse($url);
                $this->userManager->authenticateUser($user, $response);
                return $response;
            }
        }

        $form = $form->createView();
        $view = $this->viewFactory->create('reset', [
            'form' => $form,
            'template' => $configuration->getTemplate('theme/security/registration/confirm.html.twig'),
            'parameters' => [
                'token' => $token
            ]
        ]);

        return $this->viewHandler->handle($view);
    }

    public function finishAction(Request $request)
    {
        return $this->render($this->getTemplate('theme/security/registration/check.html.twig'));
    }
}
