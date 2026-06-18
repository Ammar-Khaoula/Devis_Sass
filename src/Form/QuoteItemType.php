<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\QuoteItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuoteItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => 'Description de la prestation',
                'attr' => [
                    'placeholder' => 'Ex: Création de logo, Développement...',
                    'class' => 'form-control'
                ]
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => [
                    'min' => 1,
                    'class' => 'form-control'
                ]
            ])
            ->add('unitPrice', MoneyType::class, [
                'label' => 'Prix unitaire HT',
                'currency' => 'EUR',
                'scale' => 2,
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control'
                ]
            ])
            ->add('vatRate', ChoiceType::class, [
                'label' => 'Taux TVA',
                'choices' => [
                    '20 %' => '20.00',
                    '10 %' => '10.00',
                    '5.5 %' => '5.50',
                    '0 % (Franchise de TVA)' => '0.00',
                ],
                'attr' => ['class' => 'form-select']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuoteItem::class,
        ]);
    }
}
