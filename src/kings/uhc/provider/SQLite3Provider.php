<?php

namespace kings\uhc\provider;

use pocketmine\Player;
use SQLite3;

class SQLite3Provider extends SQLite3
{

    /**
     * BetterSQLite3 constructor.
     * @param string $filename
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
        $this->busyTimeout(5000);
        $this->createTables();
    }

    /**
     * @query create table of Games
     */
    protected function createTables(): void
    {
        $this->exec('CREATE TABLE IF NOT EXISTS players (
            NAME TEXT NOT NULL,
            WINS INT NOT NULL,
            LOSSES INT NOT NULL,
            UNIQUE(NAME)
        )');
    }

    /**
     * @param Player $player
     */
    public function addPlayer(Player $player): void
    {
        $player->sendMessage("§b§l» §r§7Hey, {$player->getName()}, is your first game!");
        $player->sendMessage("§9§l» §r§7We are adding you to the database to follow your progress in your battles...");
        $query = $this->prepare("INSERT OR IGNORE INTO players(NAME,WINS,LOSSES) SELECT :name, :wins, :losses WHERE NOT EXISTS(SELECT * FROM players WHERE NAME = :name);");
        $query->bindValue(":name", $player->getName(), SQLITE3_TEXT);
        $query->bindValue(":wins", 0, SQLITE3_NUM);
        $query->bindValue(":losses", 0, SQLITE3_NUM);
        $query->execute();
    }

    /**
     * Close database
     */
    public function closeDatabase(): void
    {
        $this->close();
    }

    /**
     * @param Player $player
     * @return string
     */
    public function getScore(Player $player): string
    {
        $summary = $this->getSummary($player);
        return "§b§l» §r§bUHC Summary Score" . "\n§r" .
            "§9Player " . "§7: " . "{$player->getName()}\n" .
            "§3UHC Wins " . "§7: " . "§7{$summary['wins']}\n" .
            "§6--------------------" . "\n" .
            "§3UHC Losses " . "§7: " . "§7{$summary['losses']}\n" .
            "§6--------------------";
    }

    /**
     * @param Player $player
     * @return array[]
     */
    public function getSummary(Player $player): array
    {
        return [
            'wins' => $this->getWins($player), 'losses' => $this->getLosses($player)
        ];
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getWins(Player $player): int
    {
        $name = $player->getName();
        if (!$this->verifyPlayerInDB($player)) {
            return 0;
        }
        return $this->querySingle("SELECT WINS FROM players WHERE NAME = '$name'");
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getLosses(Player $player): int
    {
        $name = $player->getName();
        if (!$this->verifyPlayerInDB($player)) {
            return 0;
        }
        return (int)$this->querySingle("SELECT LOSSES FROM players WHERE NAME = '$name'");
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function verifyPlayerInDB(Player $player): bool
    {
        $name = $player->getName();
        $query = $this->querySingle("SELECT NAME FROM players WHERE NAME = '$name'");
        return !($query === null);
    }

    /**
     * Configure leaderboard
     * @return string
     */
    public function getGlobalTops(): string
    {
        $leaderboard = [];
        $result = null;
        $result = $this->query("SELECT NAME, WINS FROM players ORDER BY WINS DESC LIMIT 10");
        if ($result === null) {
            return '';
        }
        $index = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $leaderboard[$index++] = $row;
        }
        $count = count($leaderboard);
        $break = "\n";
        if ($count > 0) {
            $top1 = "§e1. §6Name: §a" . $leaderboard[0]['NAME'] . "  §6Wins: §a" . $leaderboard[0]['WINS'];
        } else {
            $top1 = '';
        }
        if ($count > 1) {
            $top2 = "§e2. §6Name: §e" . $leaderboard[1]['NAME'] . "  §6Wins: §e" . $leaderboard[1]['WINS'];
        } else {
            $top2 = '';
        }
        if ($count > 2) {
            $top3 = "§e3. §6Name: §e" . $leaderboard[2]['NAME'] . "  §6Wins: §e" . $leaderboard[2]['WINS'];
        } else {
            $top3 = '';
        }
        if ($count > 3) {
            $top4 = "§e4. §6Name: §e" . $leaderboard[3]['NAME'] . "  §6Wins: §e" . $leaderboard[3]['WINS'];
        } else {
            $top4 = '';
        }
        if ($count > 4) {
            $top5 = "§e5. §6Name: §e" . $leaderboard[4]['NAME'] . "  §6Wins: §e" . $leaderboard[4]['WINS'];
        } else {
            $top5 = '';
        }
        if ($count > 5) {
            $top6 = "§e6. §6Name: §e" . $leaderboard[5]['NAME'] . "  §6Wins: §e" . $leaderboard[5]['WINS'];
        } else {
            $top6 = '';
        }
        if ($count > 6) {
            $top7 = "§e7. §6Name: §e" . $leaderboard[6]['NAME'] . "  §6Wins: §e" . $leaderboard[6]['WINS'];
        } else {
            $top7 = '';
        }
        if ($count > 7) {
            $top8 = "§e8. §6Name: §e" . $leaderboard[7]['NAME'] . "  §6Wins: §e" . $leaderboard[7]['WINS'];
        } else {
            $top8 = '';
        }
        if ($count > 8) {
            $top9 = "§e9. §6Name: §e" . $leaderboard[8]['NAME'] . "  §6Wins: §e" . $leaderboard[8]['WINS'];
        } else {
            $top9 = '';
        }
        if ($count > 9) {
            $top10 = "§e10. §6Name: §e" . $leaderboard[9]['NAME'] . "  §6Wins: §e" . $leaderboard[9]['WINS'];
        } else {
            $top10 = '';
        }
        return "§bLeaderboard" . "\n" . "§9" . "§aTops UHC Wins" . "\n" . $top1 . $break . $top2 . $break . $top3 . $break . $top4 . $break . $top5 . $break . $top6 . $break . $top7 . $break . $top8 . $break . $top9 . $break . $top10;
    }

    /**
     * @param Player $player
     */
    public function addWin(Player $player): void
    {
        $name = $player->getName();
        $result = $this->getWins($player) + 1;
        $this->exec("UPDATE `players` SET `WINS`='$result' WHERE NAME='$name';");
    }

    /**
     * @param Player $player
     */
    public function addLose(Player $player): void
    {
        $result = $this->getLosses($player) + 1;
        $name = $player->getName();
        $this->exec("UPDATE `players` SET `LOSSES`='$result' WHERE NAME='$name';");
    }
}