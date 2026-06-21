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

#[Route('/customer')]
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
            'customers' => $customerRepository->findByUser($user),
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
        $customer->setUser($user);

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($customer);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le client %s %s a été créé avec succès.', $customer->getFirstname(), $customer->getLastname()));

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    /**
     * Modification d'un client
     */
    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if ($customer->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation de modifier ce client.");
        }

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le client %s %s a été mis à jour.', $customer->getFirstname(), $customer->getLastname()));

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    /**
     * Suppression sécurisée d'un client
     */
    #[Route('/{id}/delete', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if ($customer->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation de supprimer ce client.");
        }

        if ($this->isCsrfTokenValid('delete' . $customer->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($customer);
                $entityManager->flush();
                $this->addFlash('danger', 'Le client a été supprimé définitivement.');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Impossible de supprimer ce client car des devis ou factures y sont rattachés.');
            }
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
