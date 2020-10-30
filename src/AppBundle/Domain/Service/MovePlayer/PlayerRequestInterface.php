<?php

namespace AppBundle\Domain\Service\MovePlayer;

use AppBundle\Domain\Entity\Game\Game;
use AppBundle\Domain\Entity\Player\Player;

/**
 * Class PlayerRequestInterface
 *
 * @package AppBundle\Domain\Service\MovePlayer
 */
interface PlayerRequestInterface
{
    /** @var int constant for the default view range */
    const DEFAULT_VIEW_RANGE = Game::DEFAULT_VIEW_RANGE;

    /**
     * Creates the request data to send to the player. The request data will be a json object.
     *
     * {
     *     "game": {
     *         "id": "uuid"
     *     },
     *     "player": {
     *         "id": "uuid",
     *         "name": "string",
     *         "position": {
     *             "y": "int",
     *             "x": "int"
     *         },
     *         "previous": {
     *             "y": "int",
     *             "x": "int"
     *         },
     *         "area": {
     *             "y1": "int",
     *             "x1": "int",
     *             "y2": "int",
     *             "x2": "int"
     *         },
     *         "fire: "bool"
     *     },
     *     "board": {
     *         "size": {
     *             "height": "int",
     *             "width": "int"
     *         },
     *         "walls": [
     *             {
     *                 "y": "int",
     *                 "x": "int"
     *             }
     *         ]
     *     },
     *     "players": [
     *         {
     *             "y": "int",
     *             "x": "int"
     *         }
     *     ],
     *     "enemies": [
     *         {
     *             "y": "int",
     *             "x": "int",
     *             "neutral": "bool"
     *         }
     *     ]
     * }
     *
     * @param Player $player    The player data.
     * @param Game   $game      The game data.
     * @param int    $viewRange The view distance.
     * @param bool   $asArray   Return as array or string
     * @return string|array Request in json format or array format
     */
    public function create(Player $player, Game $game, $viewRange = self::DEFAULT_VIEW_RANGE, $asArray = false);
}
