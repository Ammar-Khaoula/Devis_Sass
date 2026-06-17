<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Customer;
use App\Entity\Quote;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // On récupère l'utilisateur connecté passé depuis le contrôleur
        /** @var User $user */
        $user = $options['current_user'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Objet du devis',
                'attr' => [
                    'placeholder' => 'Ex: Prestation de développement site web',
                    'class' => 'form-control'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut initial',
                'choices' => [
                    'Brouillon' => Quote::STATUS_DRAFT,
                    'Envoyé' => Quote::STATUS_SENT,
                    'Accepté' => Quote::STATUS_ACCEPTED,
                    'Refusé' => Quote::STATUS_REJECTED,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('customer', EntityType::class, [
                'class' => Customer::class,
                'label' => 'Sélectionner le client',
                'choice_label' => function (Customer $customer) {
                    return $customer->getCompany()
                        ? $customer->getCompany() . ' (' . $customer->getFirstname() . ' ' . $customer->getLastname() . ')'
                        : $customer->getFirstname() . ' ' . $customer->getLastname();
                },
                'attr' => ['class' => 'form-select'],
                // SÉCURITÉ SAAS : On filtre les clients pour n'afficher que ceux de l'utilisateur connecté
                'query_builder' => function (CustomerRepository $customerRepository) use ($user) {
                    return $customerRepository->createQueryBuilder('c')
                        ->andWhere('c.user = :user')
                        ->setParameter('user', $user)
                        ->orderBy('c.lastname', 'ASC');
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
        ]);

        // On oblige le contrôleur à nous fournir l'utilisateur connecté actuel
        $resolver->setRequired('current_user');
        $resolver->setAllowedTypes('current_user', User::class);
    }
}
