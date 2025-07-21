<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class ClientController extends AbstractController
{
    #[Route('/clients', name: 'admin_clients')]
    public function index(): Response
    {
        return $this->render('admin/users/clients.html.twig', [
            'page_title' => 'Gestion des Clients',
            'current_menu' => 'clients'
        ]);
    }
} 