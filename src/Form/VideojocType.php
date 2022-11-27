<?php

namespace App\Form;

use App\Entity\Videojoc;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\FileType;
class VideojocType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titul')
            ->add('descripcio')
            ->add('fechaEstreno')
            ->add('portada', FileType::class, [
                "label"=>"Selecciona una imatge",
                "required"=>false,
                "mapped"=>false,
                'constraints' => [
                    new File([
                        'maxSize' => '500k',
                        'maxSizeMessage'=>'Puja una imatge com a màxim de 10kb',
                        'mimeTypes' => [
                            'image/png',
                            'image/jpg',
                            'image/jpeg'
                        ],

                        'mimeTypesMessage' => 'Per favor puja una imatge tipus png o jpg',
                    ])
                ],
            ])
            ->add('cantitat')
            ->add('preu')
            ->add('videojoc_plataforma')
            ->add('generes')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Videojoc::class,
            'csrf_protection'=>false
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    public function getName(): string
    {
        return '';
    }
}
