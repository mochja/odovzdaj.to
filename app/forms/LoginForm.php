<?php

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class LoginForm extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('login', 'text', array(
                'label' => 'Login'
            ))
            ->add('password', 'password', array(
                'label' => 'Heslo'
            ))
            ->add('save', 'submit');
    }

    public function getName()
    {
        return 'LoginForm';
    }
}