<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\QuoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_QUOTE_USER_NUMBER', columns: ['user_id', 'number'])]
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Isolation SaaS : Liaison directe avec l'utilisateur qui a créé le devis
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    // Liaison avec le client (qui appartient aussi à cet utilisateur)
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] // Empêche de supprimer un client s'il a des devis
    private ?Customer $customer = null;

    #[ORM\Column(length: 50)]
    private ?string $number = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    // Approche pro : Statuts figés par des constantes pour éviter les fautes de frappe
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_INVOICED = 'invoiced';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    // Stockage en type 'decimal' pour éviter les erreurs de virgule flottante sur l'argent
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\OneToMany(targetEntity: QuoteItem::class, mappedBy: 'quote', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Invoice>
     */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'quote')]
    private Collection $invoices;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_DRAFT;
        $this->items = new ArrayCollection();
        $this->invoices = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    // --- GETTERS & SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;
        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

   public function setStatus(string $status): static
{
    if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_INVOICED])) {
        throw new \InvalidArgumentException("Statut de devis invalide");
    }
    $this->status = $status;
    return $this;
}

    public function getTotalAmount(): string
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getTotalHt();
        }

        return number_format($total, 2, '.', '');
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
    /**
     * @return Collection<int, QuoteItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(QuoteItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setQuote($this);
        }
        return $this;
    }

    public function removeItem(QuoteItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getQuote() === $this) {
                $item->setQuote(null);
            }
        }
        return $this;
    }
    /**
     * Calcule la somme de toutes les lignes du devis (Montant total HT)
     */
    public function calculateTotalAmount(): void
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getTotalHt();
        }

        // On sauvegarde le résultat au format chaîne (ex: "300.00") pour le type DECIMAL de la base de données
        $this->totalAmount = number_format($total, 2, '.', '');
    }

    /**
     * Calcule le montant total de la TVA de tout le devis
     */
    public function getTotalVatAmount(): float
    {
        $totalVat = 0.0;
        foreach ($this->items as $item) {
            $totalVat += $item->getVatAmount();
        }
        return $totalVat;
    }

    /**
     * Calcule le montant total TTC du devis
     */
    public function getTotalTtcAmount(): float
    {
        $totalTtc = 0.0;
        foreach ($this->items as $item) {
            $totalTtc += $item->getTotalTtc();
        }
        return $totalTtc;
    }

    // --- ÉVÉNEMENTS DE CYCLE DE VIE ---

    // Cette fonction s'exécute automatiquement JUSTE AVANT d'enregistrer ou modifier en base de données
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTotalsOnSave(): void
    {
        $this->calculateTotalAmount();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }

    public function addInvoice(Invoice $invoice): static
    {
        if (!$this->invoices->contains($invoice)) {
            $this->invoices->add($invoice);
            $invoice->setQuote($this);
        }

        return $this;
    }

    public function removeInvoice(Invoice $invoice): static
    {
        if ($this->invoices->removeElement($invoice)) {
            // set the owning side to null (unless already changed)
            if ($invoice->getQuote() === $this) {
                $invoice->setQuote(null);
            }
        }

        return $this;
    }
}
