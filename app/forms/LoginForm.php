<?php

use Symfony\Component\Form;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Definition of Login Form
 */
class LoginForm extends Form\AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('login', Form\Extension\Core\Type\TextType::class, [
                'label' => 'Meno',
                'attr' => array('class' => 'form-control', 'placeholder' => 'Zadajte meno')
            ])
            ->add('password', Form\Extension\Core\Type\PasswordType::class, [
                'label' => 'Heslo',
                'attr' => array('class' => 'form-control')
            ])
            ->add('Prihlásiť', Form\Extension\Core\Type\SubmitType::class, [
                'attr' => array('class' => 'btn btn-lg btn-primary form-control')
            ]);
    }

    public function getName()
    {
        return 'LoginForm';
    }
}
