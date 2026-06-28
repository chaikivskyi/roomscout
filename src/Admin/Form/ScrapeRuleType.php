<?php

namespace App\Admin\Form;

use App\CatalogScraper\Enum\ScrapeAction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ScrapeRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', ChoiceType::class, [
                'choices' => ScrapeAction::choices(),
                'placeholder' => '— choose action —',
                'constraints' => [new NotBlank()],
            ])
            ->add('selector', TextType::class, [
                'help' => 'CSS selector the action targets, e.g. ".product-title"',
                'constraints' => [new NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
