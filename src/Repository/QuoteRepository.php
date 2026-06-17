<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Quote;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Récupère uniquement les devis de l'utilisateur connecté
     * (Garantit l'isolation stricte Multi-Tenant)
     * * @return Quote[]
     */
    public function findByCurrentUser(User $user): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.user = :user')
            ->setParameter('user', $user)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère automatiquement le prochain numéro unique de devis au format DEV-ANNEE-X
     * Exemple : DEV-2026-0001
     */
    public function generateNextQuoteNumber(User $user): string
    {
        $currentYear = (new \DateTimeImmutable())->format('Y'); // Exemple: "2026"
        $prefix = 'DEV-' . $currentYear . '-';

        // On cherche le dernier numéro généré pour l'année en cours
        $lastQuote = $this->createQueryBuilder('q')
            ->andWhere('q.number LIKE :prefix')
            ->andWhere('q.user = :user')
            ->setParameter('prefix', $prefix . '%')
            ->setParameter('user', $user) // <-- AJOUTE CETTE LIGNE
            ->orderBy('q.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastQuote === null) {
            // Si aucun devis n'existe pour cette année, on commence à 1
            $nextSequence = 1;
        } else {
            // Sinon, on extrait le numéro à la fin (ex: de "DEV-2026-0004", on prend "0004")
            $lastNumber = $lastQuote->getNumber();
            $parts = explode('-', $lastNumber);
            $lastSequence = (int) end($parts);

            // On incrémente de 1
            $nextSequence = $lastSequence + 1;
        }

        // On formate sur 4 chiffres avec des zéros à gauche (ex: 1 devient "0001")
        $formattedSequence = str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);

        return $prefix . $formattedSequence;
    }
}
