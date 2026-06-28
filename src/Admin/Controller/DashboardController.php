<?php

namespace App\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('@EasyAdmin/layout.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Home Decor');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Catalog');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Categories', 'fa fa-folder-tree');
        yield MenuItem::linkTo(ProductCrudController::class, 'Products', 'fa fa-couch');
        yield MenuItem::section('Catalog Scraper');
        yield MenuItem::linkTo(ScrapeSourceCrudController::class, 'Scrape Sources', 'fa fa-spider');
    }
}
