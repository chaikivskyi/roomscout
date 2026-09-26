<?php

namespace App\Admin\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, int|string|null>>
 */
final class AppSettingsType extends AbstractType
{
    /**
     * @var list<string>
     */
    public const array SETTINGS = ['free_search_count', 'free_max_image_size_mb'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('free_search_count', IntegerType::class, [
            'label' => 'Free searches per user',
            'help' => 'How many catalog searches a user may run for free.',
            'constraints' => [
                new Assert\NotNull(),
                new Assert\PositiveOrZero(),
            ],
        ]);

        $builder->add('free_max_image_size_mb', IntegerType::class, [
            'label' => 'Free image upload size (MB)',
            'help' => 'Largest photo, in megabytes, a user may upload when creating a project.',
            'constraints' => [
                new Assert\NotNull(),
                new Assert\Positive(),
            ],
        ]);
    }
}
