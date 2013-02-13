<?php

namespace NIIF\GN3\GHGSimulatorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('NIIFGN3GHGSimulatorBundle:Default:index.html.twig', array('name' => $name));
    }
}
