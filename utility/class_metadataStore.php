<?php declare(strict_types=1);

final class MetadataStore
{
        private const DB_FILENAME = 'metadata.sqlite';
        private const DEFAULT_CHRONICLE_LIMIT = 64;

        private static ?MetadataStore $instance = null;

        /** @var SQLite3 */
        private $db;

        /** @var array<int, string> */
        private array $descriptionCache = array();

        /** @var array<int, array> */
        private array $chronicleCache = array();

        private function __construct()
        {
                $root = defined('PHPROOT') ? PHPROOT : (realpath(__DIR__ . '/..') ?: __DIR__);
                $runtimeRoot = $root . DIRECTORY_SEPARATOR . 'runtime';
                $metadataRoot = $runtimeRoot . DIRECTORY_SEPARATOR . 'meta';
                if (!is_dir($runtimeRoot))
                {
                        mkdir($runtimeRoot, 0777, true);
                }
                if (!is_dir($metadataRoot))
                {
                        mkdir($metadataRoot, 0777, true);
                }
                $databasePath = $metadataRoot . DIRECTORY_SEPARATOR . self::DB_FILENAME;
                $this->db = new SQLite3($databasePath);
                $this->db->exec('PRAGMA journal_mode=WAL');
                $this->db->exec('PRAGMA synchronous=NORMAL');
                $this->initializeSchema();
        }

        public static function instance() : MetadataStore
        {
                if (self::$instance === null)
                {
                        self::$instance = new MetadataStore();
                }
                return self::$instance;
        }

        private function initializeSchema() : void
        {
                $descriptionSql = <<<SQL
CREATE TABLE IF NOT EXISTS descriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at REAL NOT NULL
);
SQL;
                $chronicleSql = <<<SQL
CREATE TABLE IF NOT EXISTS chronicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        type TEXT NOT NULL,
        content TEXT NOT NULL,
        timestamp REAL NOT NULL,
        participants TEXT NOT NULL
);
SQL;
                $this->db->exec($descriptionSql);
                $this->db->exec($chronicleSql);
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_descriptions_category ON descriptions(category)');
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_chronicles_category ON chronicles(category)');
        }

        public function storeDescription(string $content, string $category) : int
        {
                $normalized = trim($content);
                if ($normalized === '')
                {
                        return 0;
                }
                $statement = $this->db->prepare('INSERT INTO descriptions (category, content, created_at) VALUES (:category, :content, :created_at)');
                if ($statement === false)
                {
                        throw new RuntimeException('Failed to prepare description insert statement.');
                }
                $statement->bindValue(':category', $category, SQLITE3_TEXT);
                $statement->bindValue(':content', $normalized, SQLITE3_TEXT);
                $statement->bindValue(':created_at', microtime(true), SQLITE3_FLOAT);
                $statement->execute();
                $id = (int) $this->db->lastInsertRowID();
                if ($id > 0)
                {
                        $this->descriptionCache[$id] = $normalized;
                }
                return $id;
        }

        public function fetchDescription(int $id) : ?string
        {
                if ($id <= 0)
                {
                        return null;
                }
                if (isset($this->descriptionCache[$id]))
                {
                        return $this->descriptionCache[$id];
                }
                $statement = $this->db->prepare('SELECT content FROM descriptions WHERE id = :id LIMIT 1');
                if ($statement === false)
                {
                        return null;
                }
                $statement->bindValue(':id', $id, SQLITE3_INTEGER);
                $result = $statement->execute();
                if (!($result instanceof SQLite3Result))
                {
                        return null;
                }
                $row = $result->fetchArray(SQLITE3_ASSOC);
                if ($row === false)
                {
                        return null;
                }
                $content = strval($row['content']);
                $this->descriptionCache[$id] = $content;
                return $content;
        }

        /**
         * @param array<int, string> $participants
         */
        public function storeChronicleEntry(string $category, string $type, string $text, float $timestamp, array $participants) : int
        {
                $normalized = trim($text);
                if ($normalized === '')
                {
                        return 0;
                }
                $statement = $this->db->prepare('INSERT INTO chronicles (category, type, content, timestamp, participants) VALUES (:category, :type, :content, :timestamp, :participants)');
                if ($statement === false)
                {
                        throw new RuntimeException('Failed to prepare chronicle insert statement.');
                }
                $encodedParticipants = json_encode(array_values(array_unique(array_map('strval', $participants))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $statement->bindValue(':category', $category, SQLITE3_TEXT);
                $statement->bindValue(':type', $type, SQLITE3_TEXT);
                $statement->bindValue(':content', $normalized, SQLITE3_TEXT);
                $statement->bindValue(':timestamp', $timestamp, SQLITE3_FLOAT);
                $statement->bindValue(':participants', $encodedParticipants ?: '[]', SQLITE3_TEXT);
                $statement->execute();
                $id = (int) $this->db->lastInsertRowID();
                if ($id > 0)
                {
                        $this->chronicleCache[$id] = array(
                                'type' => $type,
                                'text' => $normalized,
                                'timestamp' => $timestamp,
                                'participants' => json_decode($encodedParticipants ?: '[]', true) ?: array()
                        );
                }
                return $id;
        }

        public function fetchChronicleEntry(int $id) : ?array
        {
                if ($id <= 0)
                {
                        return null;
                }
                if (isset($this->chronicleCache[$id]))
                {
                        return $this->chronicleCache[$id];
                }
                $statement = $this->db->prepare('SELECT type, content, timestamp, participants FROM chronicles WHERE id = :id LIMIT 1');
                if ($statement === false)
                {
                        return null;
                }
                $statement->bindValue(':id', $id, SQLITE3_INTEGER);
                $result = $statement->execute();
                if (!($result instanceof SQLite3Result))
                {
                        return null;
                }
                $row = $result->fetchArray(SQLITE3_ASSOC);
                if ($row === false)
                {
                        return null;
                }
                $entry = array(
                        'type' => strval($row['type']),
                        'text' => strval($row['content']),
                        'timestamp' => floatval($row['timestamp']),
                        'participants' => json_decode(strval($row['participants']), true) ?: array()
                );
                $this->chronicleCache[$id] = $entry;
                return $entry;
        }

        /**
         * @param array<int, int> $ids
         * @return array<int, array>
         */
        public function fetchChronicleEntries(array $ids) : array
        {
                $entries = array();
                foreach ($ids as $id)
                {
                        $entry = $this->fetchChronicleEntry((int) $id);
                        if ($entry !== null)
                        {
                                $entries[] = $entry;
                        }
                }
                return $entries;
        }
}

trait MetadataBackedNarrative
{
        protected ?int $descriptionId = null;

        /** @var array<int> */
        protected array $chronicleHandles = array();

        protected function metadataStore() : MetadataStore
        {
                return MetadataStore::instance();
        }

        protected function narrativeCategory() : string
        {
                return static::class;
        }

        protected function narrativeChronicleLimit() : int
        {
                if (property_exists($this, 'chronicleLimit'))
                {
                        $limit = (int) $this->chronicleLimit;
                        if ($limit > 0)
                        {
                                return $limit;
                        }
                }
                return MetadataStore::DEFAULT_CHRONICLE_LIMIT;
        }

        public function setDescription(string $description) : void
        {
                $normalized = trim($description);
                if ($normalized === '')
                {
                        return;
                }
                $this->descriptionId = $this->metadataStore()->storeDescription($normalized, $this->narrativeCategory());
        }

        public function getDescription() : string
        {
                if ($this->descriptionId === null)
                {
                        return '';
                }
                $fetched = $this->metadataStore()->fetchDescription($this->descriptionId);
                return ($fetched === null) ? '' : $fetched;
        }

        public function addChronicleEntry(string $type, string $text, ?float $timestamp = null, array $participants = array()) : void
        {
                $normalized = trim($text);
                if ($normalized === '')
                {
                        return;
                }
                $cleanType = Utility::cleanse_string($type === '' ? 'event' : $type);
                $recorded = $this->metadataStore()->storeChronicleEntry(
                        $this->narrativeCategory(),
                        $cleanType,
                        $normalized,
                        ($timestamp === null) ? microtime(true) : floatval($timestamp),
                        $participants
                );
                if ($recorded > 0)
                {
                        $this->chronicleHandles[] = $recorded;
                        $limit = $this->narrativeChronicleLimit();
                        if ($limit > 0 && count($this->chronicleHandles) > $limit)
                        {
                                $this->chronicleHandles = array_slice($this->chronicleHandles, -$limit);
                        }
                }
        }

        public function importChronicle(array $entries) : void
        {
                $this->chronicleHandles = array();
                foreach ($entries as $entry)
                {
                        if (!is_array($entry))
                        {
                                continue;
                        }
                        $this->addChronicleEntry(
                                strval($entry['type'] ?? 'event'),
                                strval($entry['text'] ?? ''),
                                isset($entry['timestamp']) ? floatval($entry['timestamp']) : null,
                                is_array($entry['participants'] ?? null) ? $entry['participants'] : array()
                        );
                }
        }

        public function getChronicle(?int $limit = null) : array
        {
                if (empty($this->chronicleHandles))
                {
                        return array();
                }
                $handles = $this->chronicleHandles;
                if ($limit !== null)
                {
                        $limit = max(0, (int) $limit);
                        if ($limit === 0)
                        {
                                return array();
                        }
                        $handles = array_slice($handles, -$limit);
                }
                return $this->metadataStore()->fetchChronicleEntries($handles);
        }
}
