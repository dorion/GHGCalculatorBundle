<?php
namespace NIIF\GN3\GHGSimulatorBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;

class ConferenceType extends AbstractType
{
      public function buildForm(FormBuilderInterface $builder, array $options)
      {
        $builder->add(
          'confLocation',
          'text',
          array(
            'label' => 'Location: ',
            'attr' => array(
              'size' => 80,
              'title'=> 'Location of the conference',
            ),
          )
        );
        $builder->add(
          'confDuration',
          'time',
          array(
            'label' => 'Duration: '
          )
        );
        $builder->add(
          'participants',
          'collection',
          array(
            'type'          => new ParticipantType(),
            'allow_add'     => true,
            'allow_delete'  => true,
            'prototype'     => true,
            'by_reference'  => false,
            'label'         => ' ',
           )
        );
      }

      public function getName()
      {
        return 'conference';
      }

      public function getDefaultOptions(array $options){
        return array('data_class' => 'NIIF\GN3\GHGSimulatorBundle\Entity\Conference');
      }
}
