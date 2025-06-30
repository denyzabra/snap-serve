<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicMenuController extends AbstractController
{
    #[Route('/public/public/menu', name: 'app_public_public_menu')]
    public function index(): Response
    {
        return $this->render('public/public_menu/index.html.twig', [
            'controller_name' => 'Public/PublicMenuController',
        ]);
    }
}
