<?php
// Lightweight EnVision implementation for local simulations without external dependencies.
// Provides a metrics container compatible with legacy code that expected counters and
// simple serialization helpers without relying on /env mounted paths.

declare(strict_types=1);

#[\AllowDynamicProperties]
class EnVision
{
        /**
         * Directory that holds the serialized metrics file. Defaults to a writable runtime
         * path inside the project unless overridden by environment variables.
         */
        public string $EnVisionDir;

        /**
         * Full path to the metrics file. The file is optional but maintained for
         * compatibility with legacy code that persisted counters for inspection.
         */
        public string $EnVisionFile;

        /**
         * Dynamic metric storage backing the legacy property style counters.
         *
         * @var array<string, int|float|string>
         */
        private array $metrics = array();

        public function __construct(?string $visionFile = null, bool $autoCreate = false)
        {
                $root = getenv('ENV_VISIONROOT') ?: (dirname(__DIR__) . '/runtime/envision');
                $objectsDir = $visionFile ? dirname($visionFile) : ($root . '/objects');

                $this->EnVisionDir = rtrim($objectsDir !== '' ? dirname($objectsDir) : $root, DIRECTORY_SEPARATOR);
                $this->EnVisionFile = $visionFile ?: ($objectsDir . '/cereal');

                if ($autoCreate) {
                        $this->ensureStorage();
                }
        }

        public function __get(string $name)
        {
                if ($name === 'EnVisionDir' || $name === 'EnVisionFile') {
                        return $this->$name;
                }

                if (!array_key_exists($name, $this->metrics)) {
                        $this->metrics[$name] = 0;
                }

                return $this->metrics[$name];
        }

        public function __set(string $name, $value) : void
        {
                if ($name === 'EnVisionDir' || $name === 'EnVisionFile') {
                        $this->$name = (string)$value;
                        return;
                }

                $this->metrics[$name] = $value;
        }

        public function __isset(string $name) : bool
        {
                if ($name === 'EnVisionDir' || $name === 'EnVisionFile') {
                        return isset($this->$name);
                }

                return isset($this->metrics[$name]);
        }

        /**
         * Persist the EnVision metrics to disk when possible.
         */
        public function WriteOut_EnVision() : bool
        {
                if ($this->EnVisionFile === '') {
                        return false;
                }

                if (!$this->ensureStorage()) {
                        return false;
                }

                return @file_put_contents($this->EnVisionFile, serialize($this->metrics), LOCK_EX) !== false;
        }

        /**
         * Legacy compatibility helper for code that expected to serialize the
         * full EnVision object.
         */
        public function serialize($object) : string
        {
                return serialize($object);
        }

        /**
         * Ensure the metrics directory exists and is writable.
         */
        public function validateVisionLocation() : bool
        {
                if ($this->EnVisionFile === '') {
                        return false;
                }

                return $this->ensureStorage();
        }

        private function ensureStorage() : bool
        {
                $directory = dirname($this->EnVisionFile);
                if (!is_dir($directory)) {
                        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                                return false;
                        }
                }

                return is_writable($directory);
        }
}
