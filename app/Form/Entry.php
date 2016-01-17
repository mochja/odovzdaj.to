<?php

namespace App\Form;

use DateTime;
use App\Repository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class Entry extends AbstractType
{

    /** @var Repository\Classroom */
    protected $triedyRepository;

    /** @var Repository\Subject */
    protected $predmetyRepository;


    public function __construct(Repository\Classroom $triedyRepository, Repository\Subject $predmetyRepository)
    {
        $this->triedyRepository = $triedyRepository;
        $this->predmetyRepository = $predmetyRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('nazov', Type\TextType::class, array(
                'label' => 'Názov termínu',
                'attr' => array('class' => 'form-control', 'placeholder' => 'Zadajte názov termínu')
            ))->add('predmet_id', Type\ChoiceType::class, array(
                'choices' => array_flip($this->predmetyRepository->getList()),
                'label' => 'Predmet',
                'attr' => array('class' => 'form-control')
            ))->add('cas_uzatvorenia', Type\DateTimeType::class, array(
                'label' => 'Trva do',
                'data' => new DateTime,
            ))->add('trieda_id', Type\ChoiceType::class, array(
                'choices' => array_flip($this->triedyRepository->getList()),
                'label' => 'Trieda',
                'attr' => array('class' => 'form-control')
            ))->add('po_uzavierke', Type\CheckboxType::class, array(
                'required' => false,
                'label' => 'Možné odovzdať aj po termíne'
            ))->add('Vytvoriť termín', Type\SubmitType::class, array(
                'attr' => array('class' => 'btn btn-primary form-control')
            ));
    }
    
    public function getName()
    {
        return 'EntryForm';
    }
}
