<?php
declare(strict_types=1);
namespace Idk\LegoMediaBundle\Action;

use Doctrine\ORM\EntityManagerInterface;
use Idk\LegoMediaBundle\Service\AttachmentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


final class ShowAction{

    private $manager;
    private $twig;

    public function __construct(AttachmentManager $manager, \Twig_Environment $twig){
        $this->manager = $manager;
        $this->twig = $twig;
    }

    public function __invoke(Request $request, $item): Response{
        return new Response($this->twig->render('@IdkLegoMedia/show.html.twig',
            [
                'docs' => $this->manager->findAll($item),
                'list' => $this->manager->hasList(),
                'entity' => $item
            ]
        ));
    }

}