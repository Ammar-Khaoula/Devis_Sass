<?php

namespace App\Controller;

use App\Entity\Quote;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/invoice')]
#[IsGranted('ROLE_USER')]
class InvoiceController extends AbstractController
{
    #[Route('/create-from-quote/{id}', name: 'app_invoice_create_from_quote', methods: ['POST'])]
    public function createFromQuote(
        Quote $quote,
        EntityManagerInterface $em,
        InvoiceRepository $invoiceRepository
        ): Response {
        // 1. Sécurité Multi-Tenant : Propriétaire du devis
        if ($quote->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation de facturer ce devis.");
        }

        // 2. Rigueur Métier : Un devis doit obligatoirement être au statut 'accepté' pour être facturé
        if ($quote->getStatus() !== Quote::STATUS_ACCEPTED) {
            $this->addFlash('warning', 'Seuls les devis officiellement marqués comme "Acceptés" par vos clients peuvent être transformés en facture.');
            return $this->redirectToRoute('app_quote_index');
        }

        // 3. Sécurité Anti-Double Facturation
        $existingInvoice = $invoiceRepository->findOneBy(['quote' => $quote]);
        if ($existingInvoice) {
            $this->addFlash('danger', sprintf('Erreur : Une facture (%s) a déjà été émise pour ce devis.', $existingInvoice->getNumber()));
            return $this->redirectToRoute('app_quote_index');
        }

        // 4. Génération du numéro de facture unique (Ex: FACT-2026-0001)
        $year = (new \DateTimeImmutable())->format('Y');
        $lastInvoice = $invoiceRepository->findOneBy(
            ['user' => $this->getUser()],
            ['id' => 'DESC']
        );

        $nextNumber = 1;
        if ($lastInvoice) {
            $lastNumberParts = explode('-', $lastInvoice->getNumber());
            $nextNumber = ((int) end($lastNumberParts)) + 1;
        }
        $invoiceNumber = sprintf('FACT-%s-%04d', $year, $nextNumber);

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $calculatedTotalHt = 0;
        $calculatedTotalVat = 0;
        $calculatedTotalTtc = 0;

        // 5. Initialisation de la facture principale
        $invoice = new Invoice();
        $invoice->setNumber($invoiceNumber)
            ->setTitle('Facture - ' . $quote->getTitle())
            ->setStatus('unpaid')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setDueDate((new \DateTimeImmutable())->modify('+30 days'))
            ->setUser($user)
            ->setCustomer($quote->getCustomer())
            ->setQuote($quote);

        // 6. Clonage strict des lignes
        foreach ($quote->getItems() as $quoteItem) {
            $invoiceItem = new InvoiceItem();
            $invoiceItem->setDescription($quoteItem->getDescription())
                ->setQuantity($quoteItem->getQuantity())
                ->setUnitPrice($quoteItem->getUnitPrice())
                ->setVatRate($quoteItem->getVatRate())
                ->setTotalHt($quoteItem->getTotalHt())
                ->setTotalVat((string) $quoteItem->getVatAmount())
                ->setTotalTtc((string) $quoteItem->getTotalTtc());

            $calculatedTotalHt += (float) $quoteItem->getTotalHt();
            $calculatedTotalVat += (float) $quoteItem->getVatAmount();
            $calculatedTotalTtc += (float) $quoteItem->getTotalTtc();

            $invoice->addInvoiceItem($invoiceItem);
        }

        // Fixation des montants
        $invoice->setTotalHtAmount(number_format($calculatedTotalHt, 2, '.', ''));
        $invoice->setTotalVatAmount(number_format($calculatedTotalVat, 2, '.', ''));
        $invoice->setTotalTtcAmount(number_format($calculatedTotalTtc, 2, '.', ''));

        // 7. Cycle de vie pro : Le devis passe définitivement au statut "Facturé"
        $quote->setStatus(Quote::STATUS_INVOICED);

        $em->persist($invoice);
        $em->flush();

        $this->addFlash('success', sprintf('La facture %s a été générée avec succès !', $invoiceNumber));

        return $this->redirectToRoute('app_quote_index');
    }

    #[Route('/', name: 'app_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoiceRepository): Response
    {
        // On récupère uniquement les factures de l'artisan connecté
        $invoices = $invoiceRepository->findByUser($this->getUser());

        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        // Sécurité Multi-Tenant : Un artisan ne peut pas voir la facture d'un autre
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation de voir cette facture.");
        }

        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_invoice_toggle_status', methods: ['POST'])]
    public function toggleStatus(Invoice $invoice, EntityManagerInterface $em): Response
    {
        // Sécurité Multi-Tenant
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Action non autorisée.");
        }

        // Bascule de statut simple et efficace
        if ($invoice->getStatus() === 'unpaid') {
            $invoice->setStatus('paid');
            $this->addFlash('success', sprintf('La facture %s est désormais marquée comme PAYÉE !', $invoice->getNumber()));
        } else {
            $invoice->setStatus('unpaid');
            $this->addFlash('info', sprintf('La facture %s est repassée en ATTENTE DE PAIEMENT.', $invoice->getNumber()));
        }

        $em->flush();

        return $this->redirectToRoute('app_invoice_index');
    }

    #[Route('/{id}/delete', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(Invoice $invoice, EntityManagerInterface $em): Response
    {
        // Sécurité Multi-Tenant
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Action non autorisée.");
        }

        // Optionnel : On remet le devis associé au statut "Accepté" pour pouvoir le refacturer si besoin
        if ($invoice->getQuote()) {
            $invoice->getQuote()->setStatus(Quote::STATUS_ACCEPTED);
        }

        $em->remove($invoice);
        $em->flush();

        $this->addFlash('danger', 'La facture a bien été supprimée.');

        return $this->redirectToRoute('app_invoice_index');
    }

   #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    public function generatePdf(\App\Entity\Invoice $invoice): Response
    {
        // 1. Sécurité Multi-Tenant
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'avez pas l'autorisation d'accéder à ce document.");
        }

        // 2. Générer le code HTML à partir de ton template Twig
        $html = $this->renderView('invoice/pdf.html.twig', [
            'invoice' => $invoice,
        ]);

        // 3. Configuration et initialisation de Dompdf
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true); // Pratique si tu as des images ou du CSS externe

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Récupération du contenu PDF binaire
        $pdfOutput = $dompdf->output();

        // 5. Création de la réponse HTTP pour forcer le téléchargement automatique
        $response = new Response($pdfOutput);
        $response->headers->set('Content-Type', 'application/pdf');

        // Cette ligne ordonne au navigateur de télécharger le fichier directement sur le bureau
        $filename = sprintf('facture-%s.pdf', $invoice->getNumber());
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/{id}/payments', name: 'app_invoice_payments', methods: ['GET'])]
    public function paymentHistory(Invoice $invoice): Response
    {
        // 1. Sécurité Multi-Tenant
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Action non autorisée.");
        }

        // 2. Étape Historique : On affiche la page récapitulative des encaissements
        return $this->render('invoice/payments.html.twig', [
            'invoice' => $invoice,
        ]);
    }
}
