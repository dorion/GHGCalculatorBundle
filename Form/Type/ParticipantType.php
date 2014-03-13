<?php
namespace NIIF\GN3\GHGSimulatorBundle\Form\Type;

use Symfony\Component\Form\AbstractType;

use Symfony\Component\Form\FormBuilderInterface;

class ParticipantType extends AbstractType
{
      public function buildForm(FormBuilderInterface $builder, array $options)
      {
        $builder->add(
          'participantLocation',
          'text',
          array(
            'label' => 'Participant Location: ',
            'attr'  => array(
              'size'  => 60,
              'class' => 'participant-location',
            )
          )
        );
        $builder->add(
          'travelingBy',
          'choice',
          array(
            'label' => ' ',
            'choices' => array(
              'car'       => 'by car',
              'train'     => 'by train',
              'plane'     => 'by plane',
              'plane800'  => 'by plane more then 800km'
            ),
          )
        );
      }

      public function getName()
      {
        return 'participant';
      }
      
      public function getDefaultOptions(array $options){
        return array('data_class' => 'NIIF\GN3\GHGSimulatorBundle\Entity\Participant');
      }
}
