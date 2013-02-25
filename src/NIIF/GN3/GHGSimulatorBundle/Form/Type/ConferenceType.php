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
            'max_length' => 35,
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
            'by_reference'  => false,
            'label'         => 'Participant Location: ',
            'options'       => array(
              'required'  => TRUE,
              'attr'      => array('class' => 'participant-location')
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
