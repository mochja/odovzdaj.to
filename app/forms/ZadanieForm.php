<?php

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class ZadanieForm extends AbstractType
{
    /** @var TriedyRepository */
    protected $triedyRepository;
    
    /** @var PredmetyRepository */
    protected $predmetyRepository;
    
    public function __construct(TriedyRepository $triedyRepository, PredmetyRepository $predmetyRepository)
    {
        $this->triedyRepository = $triedyRepository;
        $this->predmetyRepository = $predmetyRepository;
    }
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nazov', 'text', array(
                'label' => 'Nazov terminu'
            ))
            ->add('predmet_id', 'choice', array(
                'choices' => $this->predmetyRepository->getList(),
                'label' => 'Predmet'
            ))
            ->add('cas_uzatvorenia', 'datetime', array(
                'label' => 'Trva do',
                'data' => new DateTime
            ))
            ->add('trieda_id', 'choice', array(
                'choices' => $this->triedyRepository->getList(),
                'label' => 'Trieda'
            ))
            ->add('po_uzavierke', 'checkbox', array(
                'required' => false
            ))
            ->add('save', 'submit');
    }
    
    public function getName()
    {
        return 'ZadanieForm';
    }
}