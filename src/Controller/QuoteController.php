<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Quote;
use App\Entity\User;
use App\Form\QuoteType;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/quotes')]
#[IsGranted('ROLE_USER')]
final class QuoteController extends AbstractController
{
    // 1. LISTE DES DEVIS
    #[Route('', name: 'app_quote_index', methods: ['GET'])]
    public function index(QuoteRepository $quoteRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('quote/index.html.twig', [
            // SÉCURITÉ : On n'affiche QUE les devis de cet utilisateur
            'quotes' => $quoteRepository->findByUser($user),
        ]);
    }

    // 2. CRÉATION D'UN DEVIS
    #[Route('/new', name: 'app_quote_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, QuoteRepository $quoteRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $quote = new Quote();
        // On lie directement le devis à l'utilisateur connecté en tâche de fond
        $quote->setUser($user);

        // On passe l'utilisateur connecté au formulaire pour filtrer ses clients
        $form = $this->createForm(QuoteType::class, $quote, [
            'current_user' => $user,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // SÉCURITÉ SAAS : On vérifie que le client sélectionné appartient bien à l'utilisateur connecté
            if ($quote->getCustomer()->getUser() !== $user) {
                throw $this->createAccessDeniedException('Ce client ne vous appartient pas.');
            }

            // Génération automatique du numéro unique (ex: DEV-2026-0001)
            $nextNumber = $quoteRepository->generateNextQuoteNumber($user);
            $quote->setNumber($nextNumber);

            $entityManager->persist($quote);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le devis %s a été créé avec succès !', $nextNumber));

            return $this->redirectToRoute('app_quote_index');
        }

        return $this->render('quote/new.html.twig', [
            'quote' => $quote,
            'form' => $form,
        ]);
    }

    // 3. DÉTAIL D'UN DEVIS
    #[Route('/{id}', name: 'app_quote_show', methods: ['GET'])]
    public function show(Quote $quote): Response
    {
        // SÉCURITÉ STRICTE : Si le devis n'appartient pas à l'utilisateur connecté -> 403
        if ($quote->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n’avez pas l’autorisation de voir ce devis.');
        }

        return $this->render('quote/show.html.twig', [
            'quote' => $quote,
        ]);
    }


// add PDF

    #[Route('/{id}/pdf', name: 'app_quote_pdf', methods: ['GET'])]
    public function downloadPdf(Quote $quote): Response
    {
        // SÉCURITÉ : On vérifie que le devis appartient bien à l'utilisateur connecté
        if ($quote->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n’avez pas le droit d’accéder à ce devis.');
        }

        // 1. Configurer les options de Dompdf
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true); // Permet de charger des images ou styles externes si besoin

        // 2. Initialiser Dompdf
        $dompdf = new Dompdf($pdfOptions);

        // 3. Générer le HTML à partir de notre template Twig créé à l'étape précédente
        $html = $this->renderView('quote/pdf.html.twig', [
            'quote' => $quote,
        ]);

        // 4. Charger le HTML dans Dompdf
        $dompdf->loadHtml($html);

        // 5. Configurer le format de la page (A4 en mode Portrait)
        $dompdf->setPaper('A4', 'portrait');

        // 6. Rendre le PDF (calcul des positions, des pages, etc.)
        $dompdf->render();

        // 7. Envoyer le PDF généré au navigateur pour téléchargement automatique
        $fileName = sprintf('devis-%s.pdf', $quote->getNumber());
        $dompdf->stream($fileName, [
            "Attachment" => true // true = force le téléchargement, false = ouvre dans le navigateur
        ]);

        return new Response();
    }

    // 4. MODIFICATION D'UN DEVIS
    #[Route('/{id}/edit', name: 'app_quote_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Quote $quote, EntityManagerInterface $entityManager): Response
    {
        // 1. On sauvegarde l'état des lignes AVANT la soumission du formulaire
        $originalItems = new \Doctrine\Common\Collections\ArrayCollection();
        foreach ($quote->getItems() as $item) {
            $originalItems->add($item);
        }

        $form = $this->createForm(QuoteType::class, $quote, [
        'current_user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 2. On compare : si une ligne originale n'est plus dans le formulaire, on la supprime manuellement
            foreach ($originalItems as $item) {
                if (false === $quote->getItems()->contains($item)) {
                    $entityManager->remove($item);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le devis a bien été modifié.');
            return $this->redirectToRoute('app_quote_show', ['id' => $quote->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('quote/edit.html.twig', [
            'quote' => $quote,
            'form' => $form,
        ]);
    }

    // 5. SUPPRESSION D'UN DEVIS
    #[Route('/{id}/delete', name: 'app_quote_delete', methods: ['POST'])]
    public function delete(Request $request, Quote $quote, EntityManagerInterface $entityManager): Response
    {
        // SÉCURITÉ STRICTE : Interdiction de supprimer le devis d'un autre -> 403
        if ($quote->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n’avez pas l’autorisation de supprimer ce devis.');
        }

        // Protection contre les failles CSRF
        if ($this->isCsrfTokenValid('delete'.$quote->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($quote);
            $entityManager->flush();

            $this->addFlash('danger', sprintf('Le devis %s a été supprimé.', $quote->getNumber()));
        }

        return $this->redirectToRoute('app_quote_index');
    }
}
