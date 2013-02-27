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
            'attr' => array('size' => 80),
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
          'participantLocations',
          'collection',
          array(
            'type'          => 'text',
            'allow_add'     => true,
            'allow_delete'  => true,
            'prototype'     => true,
            'label'         => 'Participant Location: ',
            'options'       => array(
              'required'  => TRUE,
              'attr'      => array(
                'class' => 'participant-location',
                'size' => 80
              )
            )
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
