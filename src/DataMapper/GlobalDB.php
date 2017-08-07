<?php
namespace Telltale\DataMapper;

use PDO;
use PDOException;

class GlobalDB {

  private $pdo;

  // @TODO: Load this from somewhere besides here! Maybe global/games ??
  private $games = [
    'holdem' => [
      'name' => 'Telltale Texas Hold\'em',
    ],
    'bone' => [
      'name' => 'Bone Download Bundle',
    ],
    'samandmax' => [
      'name' => 'Sam & Max Save the World',
    ],
    'samandmax2' => [
      'name' => 'Sam & Max - Season 2: Beyond Time and Space',
    ],
    'strongbad' => [
      'name' => 'Strong Bad\'s Cool Game for Attractive People',
    ],
    'wallaceandgromit' => [
      'name' => 'Wallace & Gromit\'s Grand Adventures',
    ],
    'monkeyisland' => [
      'name' => 'Tales of Monkey Island',
    ],
    'samandmax3' => [
      'name' => 'Sam & Max - Season 3: The Devil\'s Playhouse',
    ],
    'puzzleagent' => [
      'name' => 'Puzzle Agent',
    ],
    'celebritypoker' => [
      'name' => 'Poker Night at the Inventory',
    ],
    'backtothefuture' => [
      'name' => 'Back to the Future: The Game',
    ],
    'hector' => [
      'name' => 'Hector: Badge of Carnage',
    ],
    'puzzleagent2' => [
      'name' => 'Puzzle Agent 2',
    ],
    'secretofmonkeyisland' => [
      'name' => 'The Secret of Monkey Island: Special Edition',
    ],
    'jurassicpark' => [
      'name' => 'Jurassic Park: The Game',
    ],
    'lawandorder' => [
      'name' => 'Law & Order: Legacies',
    ],
    'walkingdead1' => [
      'name' => 'The Walking Dead: Season One',
    ],
    'fables' => [
      'name' => 'The Wolf Among Us',
    ],
    'walkingdead' => [
      'name' => 'The Walking Dead: Season Two',
    ],
    'borderlands' => [
      'name' => 'Tales from the Borderlands',
    ],
    'gameofthrones' => [
      'name' => 'Game of Thrones',
    ],
    'minecraft' => [
      'name' => 'Minecraft: Story Mode',
    ],
    'walkingdeadm' => [
      'name' => 'The Walking Dead: Michonne',
    ],
    'minecraftap' => [
      'name' => 'Minecraft: Story Mode: Adventure Pass',
    ],
    'batman' => [
      'name' => 'Batman - The Telltale Series',
    ],
    'walkingdead3' => [
      'name' => 'The Walking Dead - A New Frontier',
    ],
    'guardians' => [
      'name' => 'Marvel’s Guardians of the Galaxy: The Telltale Series',
    ],
    'minecraft2' => [
      'name' => 'Minecraft: Story Mode - Season Two',
    ],
    'batman2' => [
      'name' => 'Batman: The Enemy Within',
    ],
  ];

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
   * Return all static game data that is publicly accessible
   * @return array
   */
  public function getAllGames(): Array {
    return $this->games;
  }

  /**
   * Return static game data that is publicly accessible by game key
   * @param string $gameKey
   * @return array
   */
  public function getGame(string $gameKey): Array {
    return $this->games[$gameKey] ?? ['name' => $gameKey];
  }

  /**
   * Get temporary game installer download urls by game key
   * @param string $gameKey
   * @return array
   */
  public function getGameInstallers(string $gameKey): Array {

    $statement = $this->pdo->prepare(
      'SELECT platform, episode, filepath as download
      FROM game_installer
      WHERE game=:game AND enabled = 1
      ORDER BY episode ASC, platform DESC'
    );

    $statement->execute(['game' => $gameKey]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

}
