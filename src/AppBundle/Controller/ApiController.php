<?php

namespace AppBundle\Controller;

use AppBundle\Domain\Entity\Fire\Fire;
use AppBundle\Domain\Entity\Position\Direction;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test Api controller -> Makes random moves
 *
 * @package AppBundle\Controller
 * @Route("/api")
 */
class ApiController extends Controller
{
    const NAME = 'Test API';

    /**
     * Return the name of the API
     *
     * @Route("/name", name="api_name")
     * @return JsonResponse
     */
    public function nameAction()
    {
        $this->get('logger')->debug(
            'Random API /name action - Request: {}'
        );

        static $candidates = [
            'Tyrion Lanister'       => 'the.imp@test.com',
            'Jaime Lanister'        => 'kingslayer@test.com',
            'Cersei Lanister'       => 'the.queen@test.com',
            'Ned Stark'             => 'eddard.stark@test.com',
            'Robb Stark'            => 'the-king-in-the-north@test.com',
            'Sansa Stark'           => 'sansa.stark@test.com',
            'Arya Stark'            => 'no-one@test.com',
            'Brandon Stark'         => 'tree-eyes-raven@test.com',
            'Rickon Stark'          => 'rickon.stark@test.com',
            'Jon Snow'              => 'undead@test.com',
            'Daenerys Targarian'    => 'daenerys.targarian@test.com',
            'Robert Baratheon'      => 'robert.the.king@test.com',
            'Stanis Baratheon'      => 'stanis.baratheon@test.com',
            'Joffrey Baratheon'     => 'joffreey.baratheon@test.com',
            'Myrcella Baratheon'    => 'myrcella.baratheon@test.com',
            'Tommem Baratheon'      => 'tommem.baratheon@test.com',
            'Margaery Tyrell'       => 'i-want-to-be-the-queen@test.com',
            'Loras Tyrell'          => 'the-knight-of-the-flowers@test.com',
            'Brienne of Tarth'      => 'big-woman@test.com',
            'Petyr Baelish'         => 'little-finger@test.com',
            'Varys'                 => 'little-birds@test.com',
            'Theon Grayjoy'         => 'thon.greyjoy@test.com',
            'Ramsay Bolton'         => 'ramsay.snow@test.com',
            'Sandor Clegane'        => 'the.hound@test.com',
            'Gregor Clegane'        => 'the.mountain@test.com',
            'Khal Drogo'            => 'khal.drogo@test.com',
            'Hodor'                 => 'hodor@test.com',
            'Bronn'                 => 'mercenary@test.com',
            'Jorah Mormond'         => 'jorah.mormond@test.com',
            'Grey Worm'             => 'grey.work@test.com',
            'Lady Melisandre'       => 'melisandre@test.com',
            'Davos Seaworth'        => 'the-onion-knight@test.com',
            'Ygritte'               => 'red-hars-ygritte@test.com',
            'Mance Raider'          => 'the-king-beyond-the-wall@test.com'
        ];

        $names = array_keys($candidates);
        $index = rand(0, count($candidates) - 1);
        $name = $names[$index];
        $email = $candidates[$name];

        $response = [
            'name'  => $name,
            'email' => $email
        ];

        $this->get('logger')->debug(
            'Random API /name action - Response: ' . json_encode($response)
        );

        return new JsonResponse($response);
    }

    /**
     * Move the player
     *
     * @Route("/move", name="api_move")
     * @Route("/contest/move")
     * @return JsonResponse
     */
    public function moveAction(Request $request)
    {
        $this->get('logger')->debug(
            'Random API /move action - Request: ' . $request->getContent(false)
        );

        static $moves = [
            Direction::UP,
            Direction::DOWN,
            Direction::LEFT,
            Direction::RIGHT,
            Fire::UP,
            Fire::DOWN,
            Fire::LEFT,
            Fire::RIGHT,
        ];

        $firing = rand(0, 8) > 6;
        if ($firing) {
            $index = rand(4, 7);
        } else {
            $index = rand(0, 3);
        }

        $response = [
            'move' => $moves[$index]
        ];

        $this->get('logger')->debug(
            'Random API /move action - Response: ' . json_encode($response)
        );

        return new JsonResponse($response);
    }

    /**
     * Return the name of the API to use in a contest
     *
     * @Route("/contest/name")
     * @return JsonResponse
     */
    public function contestNameAction()
    {
        return new JsonResponse([
            'name'  => 'David Amigo',
            'email' => 'david.amigo@privalia.com'
        ]);
    }
}
