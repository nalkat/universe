<?php declare(strict_types=1);

final class MetadataStore
{
        private const DB_FILENAME = 'metadata.sqlite';
        private const DEFAULT_CHRONICLE_LIMIT = 64;
        private const LEGACY_ENTITY_KEY = '';

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
                if (method_exists($this->db, 'busyTimeout'))
                {
                        $this->db->busyTimeout(5000);
                }
                $this->db->exec('PRAGMA journal_mode=WAL');
                $this->db->exec('PRAGMA synchronous=NORMAL');
                $this->initializeSchema();
        }

        /**
         * @return SQLite3Result|true|null
         */
        private function executeStatement(SQLite3Stmt $statement)
        {
                $attempts = 0;
                $delay = 50000;
                while (true)
                {
                        $result = @$statement->execute();
                        if ($result !== false)
                        {
                                return $result;
                        }
                        $errorCode = $this->db->lastErrorCode();
                        if (($errorCode === SQLITE3_BUSY || $errorCode === SQLITE3_LOCKED) && $attempts < 5)
                        {
                                usleep($delay);
                                $delay *= 2;
                                $attempts++;
                                continue;
                        }
                        $message = $this->db->lastErrorMsg();
                        throw new RuntimeException('Failed to execute metadata statement: ' . $message);
                }
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
                $this->db->exec('CREATE TABLE IF NOT EXISTS metadata_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
                $descriptionSql = <<<SQL
CREATE TABLE IF NOT EXISTS descriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        entity_key TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at REAL NOT NULL
);
SQL;
                $chronicleSql = <<<SQL
CREATE TABLE IF NOT EXISTS chronicles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL,
        entity_key TEXT NOT NULL,
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
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_descriptions_entity ON descriptions(entity_key)');
                $this->db->exec('CREATE INDEX IF NOT EXISTS idx_chronicles_entity ON chronicles(entity_key)');
                $this->ensureColumn('descriptions', 'entity_key', "TEXT NOT NULL DEFAULT ''");
                $this->ensureColumn('chronicles', 'entity_key', "TEXT NOT NULL DEFAULT ''");
                $this->purgeLegacyRows();
        }

        private function ensureColumn(string $table, string $column, string $definition) : void
        {
                $exists = false;
                $result = $this->db->query('PRAGMA table_info(' . $table . ')');
                if ($result instanceof SQLite3Result)
                {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC))
                        {
                                if (isset($row['name']) && strval($row['name']) === $column)
                                {
                                        $exists = true;
                                        break;
                                }
                        }
                }
                if ($exists)
                {
                        return;
                }
                $this->db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }

        private function purgeLegacyRows() : void
        {
                $this->db->exec('DELETE FROM descriptions WHERE entity_key = \'' . self::LEGACY_ENTITY_KEY . '\'');
                $changes = $this->db->changes();
                $this->db->exec('DELETE FROM chronicles WHERE entity_key = \'' . self::LEGACY_ENTITY_KEY . '\'');
                $changes += $this->db->changes();
                if ($changes > 0)
                {
                        $this->db->exec('VACUUM');
                }
        }

        public function storeDescription(string $content, string $category, string $entityKey) : int
        {
                $normalized = trim($content);
                if ($normalized === '')
                {
                        return 0;
                }
                $statement = $this->db->prepare('INSERT INTO descriptions (category, entity_key, content, created_at) VALUES (:category, :entity_key, :content, :created_at)');
                if ($statement === false)
                {
                        throw new RuntimeException('Failed to prepare description insert statement.');
                }
                $statement->bindValue(':category', $category, SQLITE3_TEXT);
                $statement->bindValue(':entity_key', $entityKey, SQLITE3_TEXT);
                $statement->bindValue(':content', $normalized, SQLITE3_TEXT);
                $statement->bindValue(':created_at', microtime(true), SQLITE3_FLOAT);
                $result = $this->executeStatement($statement);
                if ($result instanceof SQLite3Result)
                {
                        $result->finalize();
                }
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
                $result = $this->executeStatement($statement);
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

        public function updateDescription(int $id, string $content) : bool
        {
                $id = intval($id);
                if ($id <= 0)
                {
                        return false;
                }
                $normalized = trim($content);
                if ($normalized === '')
                {
                        return false;
                }
                $statement = $this->db->prepare('UPDATE descriptions SET content = :content, created_at = :created_at WHERE id = :id');
                if ($statement === false)
                {
                        return false;
                }
                $statement->bindValue(':content', $normalized, SQLITE3_TEXT);
                $statement->bindValue(':created_at', microtime(true), SQLITE3_FLOAT);
                $statement->bindValue(':id', $id, SQLITE3_INTEGER);
                $result = $this->executeStatement($statement);
                if ($result instanceof SQLite3Result)
                {
                        $result->finalize();
                }
                if ($this->db->changes() > 0)
                {
                        $this->descriptionCache[$id] = $normalized;
                        return true;
                }
                return false;
        }

        /**
         * @param array<int, string> $participants
         */
        public function storeChronicleEntry(string $category, string $entityKey, string $type, string $text, float $timestamp, array $participants) : int
        {
                $normalized = trim($text);
                if ($normalized === '')
                {
                        return 0;
                }
                $statement = $this->db->prepare('INSERT INTO chronicles (category, entity_key, type, content, timestamp, participants) VALUES (:category, :entity_key, :type, :content, :timestamp, :participants)');
                if ($statement === false)
                {
                        throw new RuntimeException('Failed to prepare chronicle insert statement.');
                }
                $encodedParticipants = json_encode(array_values(array_unique(array_map('strval', $participants))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $statement->bindValue(':category', $category, SQLITE3_TEXT);
                $statement->bindValue(':entity_key', $entityKey, SQLITE3_TEXT);
                $statement->bindValue(':type', $type, SQLITE3_TEXT);
                $statement->bindValue(':content', $normalized, SQLITE3_TEXT);
                $statement->bindValue(':timestamp', $timestamp, SQLITE3_FLOAT);
                $statement->bindValue(':participants', $encodedParticipants ?: '[]', SQLITE3_TEXT);
                $result = $this->executeStatement($statement);
                if ($result instanceof SQLite3Result)
                {
                        $result->finalize();
                }
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
                $result = $this->executeStatement($statement);
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

        /**
         * @param array<int> $ids
         */
        public function deleteChronicleEntries(array $ids) : void
        {
                if (empty($ids))
                {
                        return;
                }
                $placeholders = array();
                foreach ($ids as $index => $id)
                {
                        $placeholders[] = ':id' . $index;
                }
                $sql = 'DELETE FROM chronicles WHERE id IN (' . implode(', ', $placeholders) . ')';
                $statement = $this->db->prepare($sql);
                if ($statement === false)
                {
                        return;
                }
                foreach ($ids as $index => $id)
                {
                        $statement->bindValue(':id' . $index, intval($id), SQLITE3_INTEGER);
                }
                $result = $this->executeStatement($statement);
                if ($result instanceof SQLite3Result)
                {
                        $result->finalize();
                }
                foreach ($ids as $id)
                {
                        $key = intval($id);
                        unset($this->chronicleCache[$key]);
                }
        }
}

trait MetadataBackedNarrative
{
        protected ?int $descriptionId = null;
        protected ?string $narrativeKey = null;

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

        protected function narrativeEntityKey() : string
        {
                if (is_string($this->narrativeKey) && $this->narrativeKey !== '')
                {
                        return $this->narrativeKey;
                }
                $identifier = null;
                if (property_exists($this, 'id') && is_scalar($this->id) && strval($this->id) !== '')
                {
                        $identifier = strval($this->id);
                }
                elseif (method_exists($this, 'getName'))
                {
                        $name = strval($this->getName());
                        if ($name !== '')
                        {
                                $identifier = $name;
                        }
                }
                $parts = array(static::class);
                if ($identifier !== null && $identifier !== '')
                {
                        $parts[] = $identifier;
                }
                $parts[] = spl_object_hash($this);
                $this->narrativeKey = implode(':', $parts);
                return $this->narrativeKey;
        }

        public function setDescription(string $description) : void
        {
                $normalized = trim($description);
                if ($normalized === '')
                {
                        return;
                }
                $store = $this->metadataStore();
                if ($this->descriptionId !== null)
                {
                        $current = $store->fetchDescription($this->descriptionId);
                        if ($current === $normalized)
                        {
                                return;
                        }
                        if ($store->updateDescription($this->descriptionId, $normalized))
                        {
                                return;
                        }
                }
                $newId = $store->storeDescription(
                        $normalized,
                        $this->narrativeCategory(),
                        $this->narrativeEntityKey()
                );
                if ($newId > 0)
                {
                        $this->descriptionId = $newId;
                }
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
                $store = $this->metadataStore();
                $recorded = $store->storeChronicleEntry(
                        $this->narrativeCategory(),
                        $this->narrativeEntityKey(),
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
                                $excess = array_slice($this->chronicleHandles, 0, count($this->chronicleHandles) - $limit);
                                $this->chronicleHandles = array_slice($this->chronicleHandles, -$limit);
                                $store->deleteChronicleEntries($excess);
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
