<?php

namespace App\Admin\Controller;

use App\Admin\Form\AppSettingsType;
use Craue\ConfigBundle\Util\Config;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute(path: '/settings', name: 'settings')]
final class AppSettingsController extends AbstractController
{
    public function __invoke(Request $request, Config $config): Response
    {
        $current = [];
        foreach (AppSettingsType::SETTINGS as $name) {
            $current[$name] = $config->get($name);
        }

        $form = $this->createForm(AppSettingsType::class, $current);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, int> $submitted */
            $submitted = $form->getData();

            $values = [];
            foreach (AppSettingsType::SETTINGS as $name) {
                $values[$name] = (string) $submitted[$name];
            }

            $config->setMultiple($values);

            $this->addFlash('success', 'Settings updated.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/app_settings.html.twig', ['form' => $form]);
    }
}
