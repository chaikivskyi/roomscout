<?php

namespace App\Admin\Form;

use App\CatalogScraper\Enum\ProductField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class ScrapeMappingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('field', ChoiceType::class, [
                'choices' => ProductField::choices(),
                'placeholder' => '— choose product field —',
                'constraints' => [new NotBlank()],
            ])
            ->add('selector', TextType::class, [
                'help' => 'CSS selector locating the value on the product page, e.g. ".product-title"',
                'constraints' => [new NotBlank()],
            ])
            ->add('attribute', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'help' => 'Optional: read this HTML attribute instead of the text, e.g. "src" for images or "href" for links.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
