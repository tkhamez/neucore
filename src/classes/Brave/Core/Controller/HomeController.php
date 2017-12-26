<?php
namespace Brave\Core\Controller;

use Brave\Core\Resource\UserResource;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use Slim\Views\Twig;

class HomeController
{
	private $view;
	private $logger;
	private $ur;

	public function __construct(Twig $view, LoggerInterface $logger, UserResource $ur) {
		$this->view = $view;
		$this->logger = $logger;
		$this->ur = $ur;
	}

	public function home(ServerRequestInterface $request, ResponseInterface $response, $name = null) {
		// Sample log message
	    if ($name === 'log') {
    		$this->logger->info("logging ...");
	    }

	    // test error log
	    if ($name === 'error') {
	        echo $test;
	    } elseif ($name === 'fatal') {
	        test();
	    }

	    // get a user
	    if ($name === 'user') {
	        if (($user = $this->ur->get(1)) !== null) {
	            echo 'user id 1: ' . $user->name;
	        }
	    }

		// Render index view
		return $this->view->render($response, 'home.html.twig', array(
			'name' => $name
		));
	}
}
