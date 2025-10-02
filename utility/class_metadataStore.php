<?php declare(strict_types=1);

final class MetadataStore
{
        private const DB_FILENAME = 'metadata.sqlite';
        public const DEFAULT_CHRONICLE_LIMIT = 64;
        private const LEGACY_ENTITY_KEY = '';

        private const DRIVER_SQLITE = 'sqlite';
        private const DRIVER_POSTGRES = 'pgsql';

        private static ?MetadataStore $instance = null;

        private \PDO $connection;
        private string $driver;

        /** @var array<int, string> */
        private array $descriptionCache = array();

        /** @var array<int, array> */
        private array $chronicleCache = array();

        private function __construct()
        {
                $config = $this->loadConfiguration();
                $this->driver = $config['driver'];

                if ($this->driver === self::DRIVER_POSTGRES)
                {
                        try
                        {
                                $this->connection = $this->connectPostgres($config);
                        }
                        catch (\Throwable $exception)
                        {
                                $this->driver = self::DRIVER_SQLITE;
                                $this->connection = $this->connectSqlite($config);
                        }
                }
                else
                {
                        $this->driver = self::DRIVER_SQLITE;
                        $this->connection = $this->connectSqlite($config);
                }

                $this->initializeSchema();
        }

        private function loadConfiguration() : array
        {
                $defaults = array(
                        'driver' => self::DRIVER_SQLITE,
                        'host' => 'localhost',
                        'port' => 5432,
                        'database' => null,
                        'user' => null,
                        'password' => null,
                        'options' => array(),
                        'path' => null,
                );

                $root = defined('PHPROOT') ? PHPROOT : (realpath(__DIR__ . '/..') ?: __DIR__);
                $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'metadata.php';
                $config = array();
                if (is_file($configPath))
                {
                        $loaded = include $configPath;
                        if (is_array($loaded))
                        {
                                $config = $loaded;
                        }
                }

                $merged = array_merge($defaults, $config);
                $driver = strtolower(strval($merged['driver']));
                if ($driver !== self::DRIVER_POSTGRES)
                {
                        $driver = self::DRIVER_SQLITE;
                }
                $merged['driver'] = $driver;

                return $merged;
        }

        private function createOptions(array $config) : array
        {
                $options = array();
                if (isset($config['options']) && is_array($config['options']))
                {
                        $options = $config['options'];
                }

                $options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
                $options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;
                $options[\PDO::ATTR_EMULATE_PREPARES] = false;

                if ($this->driver === self::DRIVER_SQLITE)
                {
                        $options[\PDO::ATTR_TIMEOUT] = 5;
                }

                return $options;
        }

        private function connectSqlite(array $config) : \PDO
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
                $databasePath = $config['path'];
                if (!is_string($databasePath) || $databasePath === '')
                {
                        $databasePath = $metadataRoot . DIRECTORY_SEPARATOR . self::DB_FILENAME;
                }

                $dsn = 'sqlite:' . $databasePath;
                $connection = new \PDO($dsn, null, null, $this->createOptions($config));
                $connection->exec('PRAGMA journal_mode=WAL');
                $connection->exec('PRAGMA synchronous=NORMAL');
                $connection->exec('PRAGMA busy_timeout=5000');
                return $connection;
        }

        private function connectPostgres(array $config) : \PDO
        {
                $database = $config['database'];
                if (!is_string($database) || $database === '')
                {
                        $database = is_string($config['user']) && $config['user'] !== '' ? $config['user'] : 'postgres';
                }
                $host = strval($config['host'] ?? 'localhost');
                $port = intval($config['port'] ?? 5432);
                if ($port <= 0)
                {
                        $port = 5432;
                }
                $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
                $user = is_string($config['user']) ? $config['user'] : '';
                $password = is_string($config['password']) ? $config['password'] : '';

                return new \PDO($dsn, $user, $password, $this->createOptions($config));
        }

        /**
         * @template T
         * @param callable():T $operation
         * @return T
         */
        private function withRetry(callable $operation)
        {
                $attempts = 0;
                $delay = 50000;
                while (true)
                {
                        try
                        {
                                return $operation();
                        }
                        catch (\PDOException $exception)
                        {
                                if ($this->isRetryableException($exception) && $attempts < 5)
                                {
                                        usleep($delay);
                                        $delay *= 2;
                                        $attempts++;
                                        continue;
                                }

                                throw new \RuntimeException('Failed to execute metadata statement: ' . $exception->getMessage(), 0, $exception);
                        }
                }
        }

        private function isRetryableException(\PDOException $exception) : bool
        {
                if ($this->driver === self::DRIVER_SQLITE)
                {
                        $info = $exception->errorInfo;
                        if (is_array($info))
                        {
                                $code = $info[1] ?? null;
                                return $code === 5 || $code === 6;
                        }
                        return false;
                }

                if ($this->driver === self::DRIVER_POSTGRES)
                {
                        $code = $exception->getCode();
                        return $code === '40001' || $code === '40P01';
                }

                return false;
        }

        private function exec(string $sql) : void
        {
                $this->withRetry(function () use ($sql) : int {
                        $result = $this->connection->exec($sql);
                        if ($result === false)
                        {
                                $error = $this->connection->errorInfo();
                                $message = is_array($error) ? strval($error[2] ?? 'Unknown error') : 'Unknown error';
                                throw new \RuntimeException('Failed to execute metadata statement: ' . $message);
                        }
                        return $result;
                });
        }

        private function runStatement(string $sql, array $parameters = array()) : \PDOStatement
        {
                $statement = $this->connection->prepare($sql);
                if ($statement === false)
                {
                        throw new \RuntimeException('Failed to prepare metadata statement.');
                }

                return $this->withRetry(function () use ($statement, $parameters) : \PDOStatement {
                        if (!$statement->execute($parameters))
                        {
                                throw new \RuntimeException('Failed to execute metadata statement.');
                        }
                        return $statement;
                });
        }

        private function query(string $sql) : \PDOStatement
        {
                return $this->withRetry(function () use ($sql) : \PDOStatement {
                        $statement = $this->connection->query($sql);
                        if ($statement === false)
                        {
                                throw new \RuntimeException('Failed to query metadata store.');
                        }
                        return $statement;
                });
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
                if ($this->driver === self::DRIVER_POSTGRES)
                {
                        $this->exec('CREATE TABLE IF NOT EXISTS metadata_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
                        $this->exec('CREATE TABLE IF NOT EXISTS descriptions (
                                id BIGSERIAL PRIMARY KEY,
                                category TEXT NOT NULL,
                                entity_key TEXT NOT NULL,
                                content TEXT NOT NULL,
                                created_at DOUBLE PRECISION NOT NULL
                        )');
                        $this->exec('CREATE TABLE IF NOT EXISTS chronicles (
                                id BIGSERIAL PRIMARY KEY,
                                category TEXT NOT NULL,
                                entity_key TEXT NOT NULL,
                                type TEXT NOT NULL,
                                content TEXT NOT NULL,
                                timestamp DOUBLE PRECISION NOT NULL,
                                participants TEXT NOT NULL
                        )');
                }
                else
                {
                        $this->exec('CREATE TABLE IF NOT EXISTS metadata_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
                        $this->exec('CREATE TABLE IF NOT EXISTS descriptions (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                category TEXT NOT NULL,
                                entity_key TEXT NOT NULL,
                                content TEXT NOT NULL,
                                created_at REAL NOT NULL
                        )');
                        $this->exec('CREATE TABLE IF NOT EXISTS chronicles (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                category TEXT NOT NULL,
                                entity_key TEXT NOT NULL,
                                type TEXT NOT NULL,
                                content TEXT NOT NULL,
                                timestamp REAL NOT NULL,
                                participants TEXT NOT NULL
                        )');
                }

                $this->exec('CREATE INDEX IF NOT EXISTS idx_descriptions_category ON descriptions(category)');
                $this->exec('CREATE INDEX IF NOT EXISTS idx_chronicles_category ON chronicles(category)');
                $this->exec('CREATE INDEX IF NOT EXISTS idx_descriptions_entity ON descriptions(entity_key)');
                $this->exec('CREATE INDEX IF NOT EXISTS idx_chronicles_entity ON chronicles(entity_key)');

                if ($this->driver === self::DRIVER_SQLITE)
                {
                        $this->ensureColumn('descriptions', 'entity_key', "TEXT NOT NULL DEFAULT ''");
                        $this->ensureColumn('chronicles', 'entity_key', "TEXT NOT NULL DEFAULT ''");
                        $this->purgeLegacyRows();
                }
        }

        private function ensureColumn(string $table, string $column, string $definition) : void
        {
                if ($this->driver !== self::DRIVER_SQLITE)
                {
                        return;
                }

                $exists = false;
                $statement = $this->query('PRAGMA table_info(' . $table . ')');
                while ($row = $statement->fetch())
                {
                        if (isset($row['name']) && strval($row['name']) === $column)
                        {
                                $exists = true;
                                break;
                        }
                }
                if ($exists)
                {
                        return;
                }
                $this->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }

        private function purgeLegacyRows() : void
        {
                if ($this->driver !== self::DRIVER_SQLITE)
                {
                        return;
                }

                $statement = $this->runStatement('DELETE FROM descriptions WHERE entity_key = :entity_key', array(':entity_key' => self::LEGACY_ENTITY_KEY));
                $changes = $statement->rowCount();
                $statement = $this->runStatement('DELETE FROM chronicles WHERE entity_key = :entity_key', array(':entity_key' => self::LEGACY_ENTITY_KEY));
                $changes += $statement->rowCount();
                if ($changes > 0)
                {
                        $this->exec('VACUUM');
                }
        }

        public function storeDescription(string $content, string $category, string $entityKey) : int
        {
                $normalized = trim($content);
                if ($normalized === '')
                {
                        return 0;
                }

                $params = array(
                        ':category' => $category,
                        ':entity_key' => $entityKey,
                        ':content' => $normalized,
                        ':created_at' => microtime(true),
                );

                if ($this->driver === self::DRIVER_POSTGRES)
                {
                        $statement = $this->runStatement('INSERT INTO descriptions (category, entity_key, content, created_at) VALUES (:category, :entity_key, :content, :created_at) RETURNING id', $params);
                        $id = (int) $statement->fetchColumn();
                }
                else
                {
                        $this->runStatement('INSERT INTO descriptions (category, entity_key, content, created_at) VALUES (:category, :entity_key, :content, :created_at)', $params);
                        $id = (int) $this->connection->lastInsertId();
                }

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

                $statement = $this->runStatement('SELECT content FROM descriptions WHERE id = :id LIMIT 1', array(':id' => $id));
                $row = $statement->fetch();
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

                $statement = $this->runStatement('UPDATE descriptions SET content = :content, created_at = :created_at WHERE id = :id', array(
                        ':content' => $normalized,
                        ':created_at' => microtime(true),
                        ':id' => $id,
                ));

                if ($statement->rowCount() > 0)
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

                $encodedParticipants = json_encode(array_values(array_unique(array_map('strval', $participants))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encodedParticipants === false)
                {
                        $encodedParticipants = '[]';
                }

                $params = array(
                        ':category' => $category,
                        ':entity_key' => $entityKey,
                        ':type' => $type,
                        ':content' => $normalized,
                        ':timestamp' => $timestamp,
                        ':participants' => $encodedParticipants,
                );

                if ($this->driver === self::DRIVER_POSTGRES)
                {
                        $statement = $this->runStatement('INSERT INTO chronicles (category, entity_key, type, content, timestamp, participants) VALUES (:category, :entity_key, :type, :content, :timestamp, :participants) RETURNING id', $params);
                        $id = (int) $statement->fetchColumn();
                }
                else
                {
                        $this->runStatement('INSERT INTO chronicles (category, entity_key, type, content, timestamp, participants) VALUES (:category, :entity_key, :type, :content, :timestamp, :participants)', $params);
                        $id = (int) $this->connection->lastInsertId();
                }

                if ($id > 0)
                {
                        $this->chronicleCache[$id] = array(
                                'type' => $type,
                                'text' => $normalized,
                                'timestamp' => $timestamp,
                                'participants' => json_decode($encodedParticipants, true) ?: array(),
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

                $statement = $this->runStatement('SELECT type, content, timestamp, participants FROM chronicles WHERE id = :id LIMIT 1', array(':id' => $id));
                $row = $statement->fetch();
                if ($row === false)
                {
                        return null;
                }

                $entry = array(
                        'type' => strval($row['type']),
                        'text' => strval($row['content']),
                        'timestamp' => floatval($row['timestamp']),
                        'participants' => json_decode(strval($row['participants']), true) ?: array(),
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
                $parameters = array();
                foreach ($ids as $index => $id)
                {
                        $placeholder = ':id' . $index;
                        $placeholders[] = $placeholder;
                        $parameters[$placeholder] = (int) $id;
                }
                $sql = 'DELETE FROM chronicles WHERE id IN (' . implode(', ', $placeholders) . ')';
                $this->runStatement($sql, $parameters);
                foreach ($ids as $id)
                {
                        $key = (int) $id;
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

        protected function narrativeKey() : string
        {
                if ($this->narrativeKey !== null)
                {
                        return $this->narrativeKey;
                }
                if (method_exists($this, 'narrativeHandle'))
                {
                        $handle = strval($this->narrativeHandle());
                        if ($handle !== '')
                        {
                                $this->narrativeKey = $handle;
                                return $handle;
                        }
                }
                if (method_exists($this, 'identifier'))
                {
                        $identifier = strval($this->identifier());
                        if ($identifier !== '')
                        {
                                $this->narrativeKey = $identifier;
                                return $identifier;
                        }
                }
                $this->narrativeKey = spl_object_hash($this);
                return $this->narrativeKey;
        }

        protected function narrativeDescription() : ?string
        {
                if ($this->descriptionId === null)
                {
                        return null;
                }
                return $this->metadataStore()->fetchDescription($this->descriptionId);
        }

        protected function ensureNarrativeDescription(string $content) : void
        {
                $store = $this->metadataStore();
                if ($this->descriptionId === null)
                {
                        $this->descriptionId = $store->storeDescription($content, $this->narrativeCategory(), $this->narrativeKey());
                        return;
                }
                if ($content === $this->narrativeDescription())
                {
                        return;
                }
                $store->updateDescription($this->descriptionId, $content);
        }

        /**
         * @return array<int, array>
         */
        protected function narrativeChronicles() : array
        {
                $handles = array();
                foreach ($this->chronicleHandles as $handle)
                {
                        $handles[] = (int) $handle;
                }
                if (empty($handles))
                {
                        return array();
                }
                return $this->metadataStore()->fetchChronicleEntries($handles);
        }

        protected function addNarrativeChronicle(string $type, string $text, float $timestamp, array $participants) : void
        {
                $store = $this->metadataStore();
                $entry = $store->storeChronicleEntry($this->narrativeCategory(), $this->narrativeKey(), $type, $text, $timestamp, $participants);
                if ($entry <= 0)
                {
                        return;
                }
                $this->chronicleHandles[] = $entry;
                $limit = $this->narrativeChronicleLimit();
                if (count($this->chronicleHandles) > $limit)
                {
                        $this->chronicleHandles = array_slice($this->chronicleHandles, -1 * $limit);
                }
        }

        public function setDescription(string $content) : void
        {
                $this->ensureNarrativeDescription($content);
        }

        public function getDescription() : string
        {
                $description = $this->narrativeDescription();
                return ($description === null) ? '' : $description;
        }

        public function getChronicle(?int $limit = null) : array
        {
                $entries = $this->narrativeChronicles();
                if ($limit !== null && $limit > 0 && count($entries) > $limit)
                {
                        return array_slice($entries, -1 * $limit);
                }
                return $entries;
        }

        public function addChronicleEntry(string $type, string $text, ?float $timestamp = null, array $participants = array()) : void
        {
                $normalized = trim(strval($text));
                if ($normalized === '')
                {
                        return;
                }
                $resolvedTimestamp = ($timestamp === null) ? microtime(true) : floatval($timestamp);
                $participantList = array_values(array_unique(array_map('strval', $participants)));
                $this->addNarrativeChronicle($type, $normalized, $resolvedTimestamp, $participantList);
        }

        public function importChronicle(array $entries) : void
        {
                if (empty($entries))
                {
                        return;
                }
                $store = $this->metadataStore();
                $category = $this->narrativeCategory();
                $key = $this->narrativeKey();
                $handles = array();
                foreach ($entries as $entry)
                {
                        if (!is_array($entry))
                        {
                                continue;
                        }
                        $text = trim(strval($entry['text'] ?? ''));
                        if ($text === '')
                        {
                                continue;
                        }
                        $type = strval($entry['type'] ?? 'event');
                        $timestamp = $entry['timestamp'] ?? microtime(true);
                        $participants = array();
                        if (isset($entry['participants']) && is_array($entry['participants']))
                        {
                                $participants = $entry['participants'];
                        }
                        $handle = $store->storeChronicleEntry($category, $key, $type, $text, floatval($timestamp), $participants);
                        if ($handle > 0)
                        {
                                $handles[] = $handle;
                        }
                }
                if (!empty($handles))
                {
                        $this->chronicleHandles = array_merge($this->chronicleHandles, $handles);
                        $limit = $this->narrativeChronicleLimit();
                        if (count($this->chronicleHandles) > $limit)
                        {
                                $this->chronicleHandles = array_slice($this->chronicleHandles, -1 * $limit);
                        }
                }
        }
}
