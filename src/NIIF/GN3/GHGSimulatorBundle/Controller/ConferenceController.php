<?php

namespace NIIF\GN3\GHGSimulatorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;

use NIIF\GN3\GHGSimulatorBundle\Entity\Conference;

class ConferenceController extends Controller
{
    public function indexAction(Request $request)
    {
        $conference = new Conference();

        $form = $this->createFormBuilder($conference)
          ->add(
              'confLocation',
              'text',
              array(
                'label' => 'Conference Location: ',
                'max_length' => 35,
              )
            )
            ->add('confDuration', 'text', array('label' => 'Confernce Duration: '))
            ->add('participantLocations', 'text', array('label' => 'Participant Location: ', ))
            ->getForm();

        if ($request->getMethod() == 'POST') {
            $form->bindRequest($request);

            if ($form->isValid()) {
                // perform some action, such as saving the task to the database

                return $this->redirect($this->generateUrl('ghgsimulator'));
            }
        }

        return $this->render('NIIFGN3GHGSimulatorBundle:Conference:index.html.twig', array(
            'form' => $form->createView(),
        ));
    }
}
