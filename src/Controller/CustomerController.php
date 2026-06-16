<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/customers')]
#[IsGranted('ROLE_USER')]
class CustomerController extends AbstractController
{
    /**
     * Liste des clients de l'utilisateur connecté
     */
    #[Route('', name: 'app_customer_index', methods: ['GET'])]
    public function index(CustomerRepository $customerRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('customer/index.html.twig', [
            // Utilisation de notre méthode personnalisée et sécurisée du Repository
            'customers' => $customerRepository->findByCurrentUser($user),
        ]);
    }

    /**
     * Création d'un nouveau client
     */
    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $customer = new Customer();
        // Sécurité critique : on lie directement le client à l'utilisateur connecté
        $customer->setUser($user);

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($customer);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le client %s a été créé avec succès.', $customer->getFullName()));

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    /**
     * Modification d'un client (avec sécurité anti-intrusion)
     */
    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        // SÉCURITÉ SAAS : Si le client n'appartient pas à l'utilisateur connecté -> Access Denied 403
        if ($customer->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation de modifier ce client.");
        }

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le client %s a été mis à jour.', $customer->getFullName()));

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    /**
     * Suppression sécurisée d'un client (Protection CSRF incluse)
     */
    #[Route('/{id}/delete', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        // SÉCURITÉ SAAS : Vérification de propriété stricte
        if ($customer->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation de supprimer ce client.");
        }

        // Sécurité faille CSRF : on valide le token envoyé par le formulaire Twig
        if ($this->isCsrfTokenValid('delete' . $customer->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($customer);
            $entityManager->flush();

            $this->addFlash('danger', 'Le client a été supprimé définitivement.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
