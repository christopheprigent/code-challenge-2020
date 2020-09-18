<?php

namespace AppBundle\Domain\Service\GameEngine;

use AppBundle\Domain\Entity\Contest\Match;
use AppBundle\Domain\Entity\Contest\Round;
use AppBundle\Domain\Entity\Fire\Fire;
use AppBundle\Domain\Entity\Game\Game;
use AppBundle\Domain\Entity\Ghost\Ghost;
use AppBundle\Domain\Entity\Maze\Maze;
use AppBundle\Domain\Entity\Maze\MazeCell;
use AppBundle\Domain\Entity\Player\Player;
use AppBundle\Domain\Entity\Position\Position;
use AppBundle\Domain\Repository\MatchRepositoryInterface;
use AppBundle\Domain\Repository\RoundRepositoryInterface;
use AppBundle\Domain\Service\Contest\ScoreCalculatorException;
use AppBundle\Domain\Service\Contest\ScoreCalculatorInterface;
use AppBundle\Domain\Service\MoveGhost\MoveGhostException;
use AppBundle\Domain\Service\MoveGhost\MoveGhostFactory;
use AppBundle\Domain\Service\MovePlayer\MoveAllPlayersServiceInterface;
use AppBundle\Domain\Service\MovePlayer\MovePlayerException;
use Psr\Log\LoggerInterface;

/**
 * Class GameEngine
 *
 * @package AppBundle\Domain\Service\GameEngine
 */
class GameEngine
{
    /** @var  MoveAllPlayersServiceInterface */
    protected $moveAllPlayersService;

    /** @var  MoveGhostFactory */
    protected $moveGhostFactory;

    /** @var RoundRepositoryInterface */
    protected $roundRepo;

    /** @var MatchRepositoryInterface */
    protected $matchRepo;

    /** @var ScoreCalculatorInterface */
    protected $scoreCalculator;

    /** @var LoggerInterface */
    protected $logger;

    /** @var int Score constants  */
    const SCORE_KILL_PLAYER = 50;
    const SCORE_KILL_GHOST = 25;
    const SCORE_DEAD = -100;

    /**
     * GameEngine constructor.
     *
     * @param MoveAllPlayersServiceInterface $moveAllPlayersService
     * @param MoveGhostFactory $moveGhostFactory
     * @param RoundRepositoryInterface $roundRepo
     * @param MatchRepositoryInterface $matchRepo
     * @param ScoreCalculatorInterface $scoreCalculator
     * @param LoggerInterface $logger
     */
    public function __construct(
        MoveAllPlayersServiceInterface $moveAllPlayersService,
        MoveGhostFactory $moveGhostFactory,
        RoundRepositoryInterface $roundRepo,
        MatchRepositoryInterface $matchRepo,
        ScoreCalculatorInterface $scoreCalculator,
        LoggerInterface $logger
    ) {
        $this->moveAllPlayersService = $moveAllPlayersService;
        $this->moveGhostFactory = $moveGhostFactory;
        $this->roundRepo = $roundRepo;
        $this->matchRepo = $matchRepo;
        $this->scoreCalculator = $scoreCalculator;
        $this->logger = $logger;
    }

    /**
     * Creates a new game
     *
     * @param Maze $maze
     * @param Player[] $players
     * @param int $ghostRate
     * @param int $minGhosts
     * @param int $limitMoves
     * @param string|null $name
     * @param string|null $matchUuid
     * @return Game
     * @throws \Exception
     */
    public function create(
        Maze $maze,
        array $players,
        int $ghostRate,
        int $minGhosts,
        int $limitMoves,
        ?string $name,
        ?string $matchUuid = null
    ) : Game {
        $game = new Game(
            $maze,
            $players,
            array(),
            array(),
            $ghostRate,
            $minGhosts,
            Game::STATUS_NOT_STARTED,
            0,
            $limitMoves,
            null,
            $name,
            $matchUuid
        );

        $this->createGhosts($game);

        return $game;
    }

    /**
     * Resets the game
     *
     * @param Game $game
     * @return $this
     * @throws \Exception
     */
    public function reset(Game &$game) : GameEngine
    {
        $game->resetPlaying();
        $this->createGhosts($game);
        return $this;
    }

    /**
     * Move all the players and ghosts of a game
     *
     * @param Game $game
     * @return bool TRUE if the game is not finished
     * @throws \Exception
     */
    public function move(Game &$game) : bool
    {
        $this->createGhosts($game);
        $game->resetKilledGhosts();
        $this->resetFire($game);
        $this->movePlayers($game);
        $this->checkPlayersFire($game);
        $this->checkPlayersPositions($game);
        $this->moveGhosts($game);

        $game->incMoves();
        if ($game->finished()) {
            $this->updateScores($game);
            return false;
        }

        return true;
    }

    /**
     * Move all the players
     *
     * @param Game $game
     * @return $this
     */
    protected function movePlayers(Game &$game) : GameEngine
    {
        $this->logger->debug(
            'Game engine - Moving all players of game ' . $game->uuid()
        );

        try {
            $this->moveAllPlayersService->move($game);
        } catch (MovePlayerException $exc) {
            $this->logger->error('Error moving players in class: ' . get_class($this->moveAllPlayersService));
            $this->logger->error($exc);
        }
        return $this;
    }

    /**
     * Resets players fire
     *
     * @param Game $game
     * @return $this
     */
    protected function resetFire(Game& $game) : GameEngine
    {
        $this->logger->debug(
            'Game engine - Reset firing direction for all players of game ' . $game->uuid()
        );

        foreach ($game->players() as $player) {
            $player->resetFiringDir();
        }

        return $this;
    }

    /**
     * Checks the players positions searching overlapping
     *
     * @param Game $game
     * @return $this
     * @throws \Exception
     */
    protected function checkPlayersPositions(Game &$game) : GameEngine
    {
        $players = $game->players();
        $count = count($players);

        for ($i = 0; $i < $count - 1; ++$i) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($players[$i]->position()->equals($players[$j]->position())
                    && !$players[$i]->isKilled() && !$players[$i]->isRespawned()
                    && !$players[$j]->isKilled() && !$players[$j]->isRespawned()) {
                    $players[$i]->killed()->addScore(self::SCORE_DEAD);
                    $players[$j]->killed()->addScore(self::SCORE_DEAD);
                }
            }
        }
        return $this;
    }

    /**
     * Check if a player fired
     *
     * @param Game $game
     * @return GameEngine
     * @throws \Exception
     */
    protected function checkPlayersFire(Game& $game) : GameEngine
    {
        $this->logger->debug(
            'Game engine - Checking if any player fired for game ' . $game->uuid()
        );

        foreach ($game->players() as $player) {
            if ($player->isFiring()) {
                $this->logger->debug(
                    'Game engine - Detected player ' . $player->uuid() . ' firing direction: ' . $player->firingDir()
                );

                // Check if the shot kills a ghost or a player
                $dir = Fire::direction($player->firingDir());
                $pos = clone $player->position();
                for ($i = 0; $i < Fire::DEFAULT_FIRE_RANGE; $i++) {
                    $pos->moveTo($dir);

                    // If wall found, the range is reduced
                    if (!$game->maze()[$pos->y()][$pos->x()]->isEmpty()) {
                        $player->setFireRange(1 + $i);
                        break;
                    }

                    // Check if another player killed
                    $others = $game->playersAtPosition($pos);
                    foreach ($others as $other) {
                        if ($player->uuid() != $other->uuid()
                            && !$other->isKilled()
                            && !$other->isRespawned()) {
                            $player->addScore(self::SCORE_KILL_PLAYER);
                            $other->killed()->addScore(self::SCORE_DEAD);

                            $this->logger->debug(
                                'Game engine - Shot of ' . $player->uuid() .
                                ' killed a player. Score ' . $player->score()
                            );
                            $this->logger->debug(
                                'Game engine - Player ' . $other->uuid() .
                                ' killed by shot. Score ' . $other->score()
                            );
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Move all the ghosts
     *
     * @param Game $game
     * @return $this
     * @throws \Exception
     */
    protected function moveGhosts(Game &$game) : GameEngine
    {
        $this->logger->debug(
            'Game engine - Moving ghosts for game ' . $game->uuid()
        );

        /** @var Ghost[] $ghosts */
        $ghosts = $game->ghosts();
        shuffle($ghosts);

        foreach ($ghosts as $ghost) {
            if (!$this->checkGhostKill($ghost, $game)) {
                try {
                    $moverService = $this->moveGhostFactory->locate($ghost);
                    if ($moverService->move($ghost, $game)) {
                        $this->checkGhostKill($ghost, $game);
                    }
                } catch (MoveGhostException $exc) {
                    $this->logger->error('Error moving ghost.');
                    $this->logger->error($exc);
                }
            }
        }

        return $this;
    }

    /**
     * Checks if a ghost killed a player. If a player is killed the ghost also dies.
     *
     * @param Ghost $ghost
     * @param Game $game
     * @return bool true if the ghost still alive, false in other case
     * @throws \Exception
     */
    protected function checkGhostKill(Ghost $ghost, Game& $game) : bool
    {
        $players = $game->players();
        shuffle($players);

        foreach ($players as $player) {
            if (!$player->isKilled()) {
                if ($player->position()->equals($ghost->position())) {
                    if ($player->isPowered()
                        || $ghost->isNeutral()) {
                        $player->addScore(self::SCORE_KILL_GHOST);

                        $this->logger->debug(
                            'Game engine - Ghost killed by player ' . $player->uuid() .
                            '. Score ' . $player->score()
                        );
                    } elseif (!$player->isRespawned()) {
                        $player->killed()->addScore(self::SCORE_DEAD);

                        $this->logger->debug(
                            'Game engine - Player ' . $player->uuid() .
                            ' killed by ghost. Score ' . $player->score()
                        );
                    }
                    $game->removeGhost($ghost);
                    return true;
                } elseif ($player->fireDirAtPosition($ghost->position())) {
                    $player->addScore(self::SCORE_KILL_GHOST);

                    $this->logger->debug(
                        'Game engine - Shot of ' . $player->uuid() .
                        ' killed a ghost. Score ' . $player->score()
                    );
                    $game->removeGhost($ghost);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Create new ghost if ghost rate reached or not enough ghosts
     *
     * @param Game $game
     * @return $this
     */
    protected function createGhosts(Game &$game) : GameEngine
    {
        $minGhosts = $game->minGhosts();
        $ghostRate = $game->ghostRate();
        if ($ghostRate > 0) {
            $minGhosts += (int)($game->moves() / $ghostRate);
        }

        $ghostCount = count($game->ghosts());
        while ($ghostCount < $minGhosts) {
            $this->createNewGhost($game);
            $ghostCount++;
        }

        return $this;
    }

    /**
     * Create new ghost
     *
     * @param Game $game
     * @param int $type
     * @return $this
     */
    protected function createNewGhost(Game &$game, $type = Ghost::TYPE_RANDOM): GameEngine
    {
        $maze = $game->maze();
        do {
            $y = rand(1, $maze->height() - 2);
            $x = rand(1, $maze->width() - 2);
        } while (!$maze[$y][$x]->isEmpty());
        $game->addGhost(new Ghost(new Position($y, $x), null, $type));
        return $this;
    }

    /**
     * Updates the scores when the game belongs to a contest
     *
     * @param Game $game
     * @return GameEngine
     */
    protected function updateScores(Game& $game): GameEngine
    {
        if (null !== $game->matchUUid()) {
            $math = $this->matchRepo->readMatch($game->matchUUid());
            if (null === $math) {
                $this->logger->error(
                    'Game engine - Error occurred updating math scores. Match not found: ' . $game->matchUUid()
                );
            } else {
                $math->setGame($game);
                try {
                    $this->scoreCalculator->calculateMatchScore($math);
                    $this->matchRepo->persistMatch($math, true);
                    $this->updateClassification($math->roundUuid());
                } catch (ScoreCalculatorException $exc) {
                    $this->logger->error(
                        'Game engine - Exception calculating scores for match ' . $math->uuid()
                    );
                    $this->logger->debug($exc);
                }
            }
        }
        return $this;
    }

    /**
     * Updates the classification when the game belongs to a contest
     *
     * @param string $roundUuid
     * @return GameEngine
     */
    protected function updateClassification(string $roundUuid): GameEngine
    {
        if (null !== $roundUuid) {
            /** @var Round $round */
            $round = $this->roundRepo->readRound($roundUuid);
            if (null === $round) {
                $this->logger->error(
                    'Game engine - Error occurred updating classification. Round not found: ' . $roundUuid
                );
            } else {
                /** @var Match[] $matches */
                $matches = $this->matchRepo->readMatches($roundUuid);

                try {
                    $this->scoreCalculator->calculateRoundScore($round, $matches);
                    $this->matchRepo->persistMatches($matches, false);
                    $this->roundRepo->persistRound($round, true);
                } catch (ScoreCalculatorException $exc) {
                    $this->logger->error(
                        'Game engine - Exception calculating classification for round ' . $roundUuid
                    );
                    $this->logger->debug($exc);
                }
            }
        }
        return $this;
    }
}
