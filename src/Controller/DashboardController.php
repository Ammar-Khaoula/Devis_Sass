<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\CustomerRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

 #[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(CustomerRepository $customerRepository): Response
    {
        // On récupère l'utilisateur connecté
        $user = $this->getUser();

        // On récupère uniquement ses clients
        $myCustomers = $customerRepository->findByUser($user);

        return $this->render('dashboard/index.html.twig', [
            // On passe le nombre total de ses clients au template Twig
            'total_customers' => count($myCustomers),
        ]);
    }
}
