<?php

namespace App\Controller\Api\Menu;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MenuItemController extends AbstractController
{
    #[Route('/menu/item', name: 'app_menu_item')]
    public function index(): Response
    {
        return $this->render('menu_item/index.html.twig', [
            'controller_name' => 'MenuItemController',
        ]);
    }
}
