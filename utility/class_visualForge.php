<?php declare(strict_types=1);

final class VisualForge
{
        private const CANVAS = 256;

        private static function createCanvas() : \GdImage
        {
                $image = imagecreatetruecolor(self::CANVAS, self::CANVAS);
                imagesavealpha($image, true);
                $background = imagecolorallocatealpha($image, 11, 15, 26, 0);
                imagefill($image, 0, 0, $background);
                return $image;
        }

        private static function finalizeCanvas(\GdImage $image) : string
        {
                ob_start();
                imagepng($image);
                $data = ob_get_clean();
                if ($data === false)
                {
                        $data = '';
                }
                imagedestroy($image);
                return $data;
        }

        /**
         * @return array{r:int,g:int,b:int}
         */
        private static function colorFromSeed(string $seed, float $lightnessAdjustment = 0.0) : array
        {
                $hash = crc32($seed);
                $hue = ($hash % 360 + 360) % 360;
                $satSeed = ($hash >> 8) & 0xFF;
                $lightSeed = ($hash >> 16) & 0xFF;
                $saturation = 0.55 + ($satSeed / 255.0) * 0.35;
                $lightness = 0.45 + ($lightSeed / 255.0) * 0.25 + $lightnessAdjustment;
                return self::hslToRgb($hue, min(1.0, max(0.2, $saturation)), min(0.95, max(0.2, $lightness)));
        }

        private static function colorToHex(array $rgb) : string
        {
                $r = max(0, min(255, (int) round($rgb['r'] ?? 0)));
                $g = max(0, min(255, (int) round($rgb['g'] ?? 0)));
                $b = max(0, min(255, (int) round($rgb['b'] ?? 0)));
                return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        /**
         * @param array<int, array{r:int,g:int,b:int}> $palette
         * @return array<int, string>
         */
        private static function paletteToHex(array $palette) : array
        {
                $result = array();
                foreach ($palette as $color)
                {
                        $result[] = self::colorToHex($color);
                }
                return $result;
        }

        /**
         * @return array{r:int,g:int,b:int}
         */
        private static function hslToRgb(float $h, float $s, float $l) : array
        {
                $h = fmod(max(0.0, $h), 360.0) / 360.0;
                if ($s <= 0.0)
                {
                        $value = (int) round($l * 255);
                        return array('r' => $value, 'g' => $value, 'b' => $value);
                }
                $q = ($l < 0.5) ? ($l * (1 + $s)) : ($l + $s - $l * $s);
                $p = 2 * $l - $q;
                $r = self::hueToRgb($p, $q, $h + 1.0 / 3.0);
                $g = self::hueToRgb($p, $q, $h);
                $b = self::hueToRgb($p, $q, $h - 1.0 / 3.0);
                return array('r' => (int) round($r * 255), 'g' => (int) round($g * 255), 'b' => (int) round($b * 255));
        }

        private static function hueToRgb(float $p, float $q, float $t) : float
        {
                if ($t < 0)
                {
                        $t += 1;
                }
                if ($t > 1)
                {
                        $t -= 1;
                }
                if ($t < 1 / 6)
                {
                        return $p + ($q - $p) * 6 * $t;
                }
                if ($t < 1 / 2)
                {
                        return $q;
                }
                if ($t < 2 / 3)
                {
                        return $p + ($q - $p) * (2 / 3 - $t) * 6;
                }
                return $p;
        }

        private static function allocate(\GdImage $image, array $rgb, int $alpha = 0) : int
        {
                $a = min(127, max(0, $alpha));
                return imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], $a);
        }

        /**
         * @param array<string,mixed> $attributes
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        private static function package(\GdImage $image, string $generator, array $attributes = array(), string $prompt = '') : array
        {
                $content = self::finalizeCanvas($image);
                $metadata = array(
                        'generator' => $generator,
                        'prompt' => $prompt,
                        'width' => self::CANVAS,
                        'height' => self::CANVAS,
                        'attributes' => $attributes,
                );

                return array(
                        'mime_type' => 'image/png',
                        'content' => $content,
                        'generator' => $generator,
                        'prompt' => $prompt,
                        'width' => self::CANVAS,
                        'height' => self::CANVAS,
                        'attributes' => $attributes,
                        'metadata' => $metadata,
                );
        }

        private static function resolveName(object $entity, string $fallback) : string
        {
                if (method_exists($entity, 'getName'))
                {
                        $name = $entity->getName();
                        if (is_string($name) && $name !== '')
                        {
                                return $name;
                        }
                }
                if (property_exists($entity, 'name'))
                {
                        $candidate = $entity->name;
                        if (is_string($candidate) && $candidate !== '')
                        {
                                return $candidate;
                        }
                }
                return $fallback;
        }

        private static function drawSpiral(\GdImage $image, array $palette, int $arms, int $density) : void
        {
                $center = self::CANVAS / 2;
                $maxRadius = self::CANVAS * 0.45;
                $armColor = self::allocate($image, $palette[0], 0);
                $accentColor = self::allocate($image, $palette[1], 30);
                $stars = max(120, $density * 12);
                for ($arm = 0; $arm < max(2, $arms); $arm++)
                {
                        for ($step = 0; $step < $stars; $step++)
                        {
                                $fraction = $step / $stars;
                                $angle = ($fraction * 5 * M_PI) + ($arm * (M_PI * 2 / max(2, $arms)));
                                $radius = $fraction * $maxRadius;
                                $x = (int) round($center + cos($angle) * $radius + sin($angle * 3) * 4);
                                $y = (int) round($center + sin($angle) * $radius + cos($angle * 2) * 4);
                                imagefilledellipse($image, $x, $y, 2, 2, ($step % 3 === 0) ? $accentColor : $armColor);
                        }
                }
                $coreColor = self::allocate($image, $palette[2], 0);
                imagefilledellipse($image, (int) $center, (int) $center, (int) ($maxRadius * 0.4), (int) ($maxRadius * 0.4), $coreColor);
        }

        private static function drawSystem(\GdImage $image, array $palette, int $planets) : void
        {
                $center = self::CANVAS / 2;
                $maxOrbit = self::CANVAS * 0.42;
                $orbitColor = self::allocate($image, $palette[1], 70);
                $planetCount = max(1, $planets);
                for ($idx = 1; $idx <= $planetCount; $idx++)
                {
                        $orbit = ($idx / ($planetCount + 1)) * $maxOrbit;
                        imagearc($image, (int) $center, (int) $center, (int) ($orbit * 2), (int) ($orbit * 2), 0, 360, $orbitColor);
                        $angle = ($idx * 73) % 360;
                        $x = (int) round($center + cos(deg2rad($angle)) * $orbit);
                        $y = (int) round($center + sin(deg2rad($angle)) * $orbit);
                        $planetColor = self::allocate($image, self::colorFromSeed('planet-' . $idx, 0.1), 10);
                        imagefilledellipse($image, $x, $y, 12, 12, $planetColor);
                }
                $starColor = self::allocate($image, $palette[0], 0);
                imagefilledellipse($image, (int) $center, (int) $center, 40, 40, $starColor);
                $haloColor = self::allocate($image, $palette[2], 60);
                imagefilledellipse($image, (int) $center, (int) $center, 68, 68, $haloColor);
        }

        private static function drawPlanet(\GdImage $image, array $palette, float $habitability, string $classification) : void
        {
                $center = self::CANVAS / 2;
                $radius = self::CANVAS * 0.38;
                $baseColor = self::allocate($image, $palette[0], 0);
                imagefilledellipse($image, (int) $center, (int) $center, (int) ($radius * 2), (int) ($radius * 2), $baseColor);
                $bands = max(3, (int) round(6 + $habitability * 4));
                for ($i = 0; $i < $bands; $i++)
                {
                        $angle = $i * (M_PI / $bands);
                        $bandColor = self::allocate($image, $palette[1], 60);
                        imagefilledarc(
                                $image,
                                (int) $center,
                                (int) $center,
                                (int) ($radius * 2),
                                (int) ($radius * (1.2 + sin($angle) * 0.2)),
                                (int) rad2deg($angle),
                                (int) rad2deg($angle + M_PI / $bands),
                                $bandColor,
                                IMG_ARC_NOFILL
                        );
                }
                if (stripos($classification, 'ring') !== false || stripos($classification, 'gas') !== false)
                {
                        $ringColor = self::allocate($image, $palette[2], 50);
                        imagefilledarc(
                                $image,
                                (int) $center,
                                (int) $center,
                                (int) ($radius * 2.4),
                                (int) ($radius * 0.9),
                                20,
                                200,
                                $ringColor,
                                IMG_ARC_PIE
                        );
                }
        }

        private static function drawCountry(\GdImage $image, array $palette, array $territory) : void
        {
                $border = self::allocate($image, $palette[0], 20);
                $fill = self::allocate($image, $palette[1], 80);
                $center = self::CANVAS / 2;
                $width = self::CANVAS * 0.7;
                $height = self::CANVAS * 0.45;
                imagefilledrectangle(
                        $image,
                        (int) ($center - $width / 2),
                        (int) ($center - $height / 2),
                        (int) ($center + $width / 2),
                        (int) ($center + $height / 2),
                        $fill
                );
                imagerectangle(
                        $image,
                        (int) ($center - $width / 2),
                        (int) ($center - $height / 2),
                        (int) ($center + $width / 2),
                        (int) ($center + $height / 2),
                        $border
                );
                $cities = $territory['cities'] ?? array();
                if (is_array($cities))
                {
                        foreach (array_slice($cities, 0, 18) as $index => $city)
                        {
                                if (!is_array($city))
                                {
                                        continue;
                                }
                                $dotColor = self::allocate($image, self::colorFromSeed('city-' . ($city['name'] ?? $index), 0.2), 30);
                                $x = (int) ($center - $width / 2 + mt_rand(12, (int) $width - 12));
                                $y = (int) ($center - $height / 2 + mt_rand(12, (int) $height - 12));
                                imagefilledellipse($image, $x, $y, 6, 6, $dotColor);
                        }
                }
        }

        private static function drawCity(\GdImage $image, array $palette, array $residents) : void
        {
                $center = self::CANVAS / 2;
                $radius = self::CANVAS * 0.35;
                $outline = self::allocate($image, $palette[0], 0);
                $background = self::allocate($image, $palette[1], 90);
                imagefilledellipse($image, (int) $center, (int) $center, (int) ($radius * 2), (int) ($radius * 2), $background);
                imageellipse($image, (int) $center, (int) $center, (int) ($radius * 2), (int) ($radius * 2), $outline);
                $count = max(1, count($residents));
                $index = 0;
                foreach (array_slice($residents, 0, 200) as $resident)
                {
                        $angle = ($index / $count) * 2 * M_PI;
                        $distance = $radius * (0.2 + ($index % 10) / 10.0);
                        $x = (int) round($center + cos($angle) * $distance);
                        $y = (int) round($center + sin($angle) * $distance);
                        $dot = self::allocate($image, self::colorFromSeed('resident-' . $index, 0.15), 40);
                        imagefilledellipse($image, $x, $y, 4, 4, $dot);
                        $index++;
                }
        }

        private static function drawPerson(\GdImage $image, array $palette, float $wealth) : void
        {
                $body = self::allocate($image, $palette[0], 0);
                $accent = self::allocate($image, $palette[1], 40);
                $center = self::CANVAS / 2;
                $height = self::CANVAS * 0.55;
                $width = $height * 0.35;
                imagefilledellipse($image, (int) $center, (int) ($center - $height * 0.3), (int) $width, (int) ($width * 1.2), $body);
                imagefilledrectangle(
                        $image,
                        (int) ($center - $width / 2),
                        (int) ($center - $height * 0.1),
                        (int) ($center + $width / 2),
                        (int) ($center + $height * 0.45),
                        $body
                );
                imagefilledellipse($image, (int) ($center - $width / 1.5), (int) ($center + $height * 0.35), 26, 26, $accent);
                imagefilledellipse($image, (int) ($center + $width / 1.5), (int) ($center + $height * 0.35), 26, 26, $accent);
                $wealthScale = min(1.0, max(0.0, $wealth));
                $glow = self::allocate($image, $palette[2], (int) (90 - $wealthScale * 70));
                imagefilledellipse($image, (int) $center, (int) ($center + $height * 0.4), (int) ($width * 1.6), 24, $glow);
        }

        private static function drawElement(\GdImage $image, array $palette) : void
        {
                $center = self::CANVAS / 2;
                $color = self::allocate($image, $palette[0], 0);
                $accent = self::allocate($image, $palette[1], 30);
                for ($i = 0; $i < 5; $i++)
                {
                        $angle = deg2rad($i * 72);
                        $x = $center + cos($angle) * self::CANVAS * 0.3;
                        $y = $center + sin($angle) * self::CANVAS * 0.3;
                        imagefilledellipse($image, (int) $x, (int) $y, 36, 36, ($i % 2 === 0) ? $color : $accent);
                        imagearc($image, (int) $center, (int) $center, (int) (abs($x - $center) * 2), (int) (abs($y - $center) * 2), 0, 360, $accent);
                }
                imagefilledellipse($image, (int) $center, (int) $center, 46, 46, $color);
        }

        private static function drawCompound(\GdImage $image, array $palette) : void
        {
                $center = self::CANVAS / 2;
                $primary = self::allocate($image, $palette[0], 0);
                $secondary = self::allocate($image, $palette[1], 30);
                for ($angle = 0; $angle < 360; $angle += 45)
                {
                        $rad = deg2rad($angle);
                        $x = $center + cos($rad) * self::CANVAS * 0.32;
                        $y = $center + sin($rad) * self::CANVAS * 0.32;
                        imageline($image, (int) $center, (int) $center, (int) $x, (int) $y, $secondary);
                        imagefilledellipse($image, (int) $x, (int) $y, 22, 22, ($angle % 90 === 0) ? $primary : $secondary);
                }
                imagefilledellipse($image, (int) $center, (int) $center, 32, 32, $primary);
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function galaxy(Galaxy $galaxy, array $context = array()) : array
        {
                $systems = isset($context['systems']) ? max(1, (int) $context['systems']) : max(1, count($galaxy->getSystems()));
                $name = self::resolveName($galaxy, 'Galaxy');
                $palette = array(
                        self::colorFromSeed($name, 0.25),
                        self::colorFromSeed($name . '-accent', 0.15),
                        self::colorFromSeed($name . '-core', 0.35),
                );
                $canvas = self::createCanvas();
                self::drawSpiral($canvas, $palette, max(2, (int) round(sqrt($systems))), $systems);
                return self::package(
                        $canvas,
                        'visual_forge:galaxy',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'system_count' => $systems,
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function system(System $system, array $context = array()) : array
        {
                $planets = isset($context['planets']) ? (int) $context['planets'] : count($system->getPlanets());
                $name = self::resolveName($system, 'System');
                $palette = array(
                        self::colorFromSeed($name . '-star', 0.3),
                        self::colorFromSeed($name . '-orbit', 0.1),
                        self::colorFromSeed($name . '-halo', 0.2),
                );
                $canvas = self::createCanvas();
                self::drawSystem($canvas, $palette, max(1, $planets));
                return self::package(
                        $canvas,
                        'visual_forge:system',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'planet_count' => max(1, $planets),
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function star(Star $star, array $context = array()) : array
        {
                $name = self::resolveName($star, 'Star');
                $palette = array(
                        self::colorFromSeed($name . '-photosphere', 0.35),
                        self::colorFromSeed($name . '-corona', 0.2),
                        self::colorFromSeed($name . '-flare', 0.4),
                );
                $canvas = self::createCanvas();
                $center = self::CANVAS / 2;
                $core = self::allocate($canvas, $palette[0], 0);
                $halo = self::allocate($canvas, $palette[1], 60);
                imagefilledellipse($canvas, (int) $center, (int) $center, (int) (self::CANVAS * 0.6), (int) (self::CANVAS * 0.6), $halo);
                imagefilledellipse($canvas, (int) $center, (int) $center, (int) (self::CANVAS * 0.48), (int) (self::CANVAS * 0.48), $core);
                $flare = self::allocate($canvas, $palette[2], 80);
                for ($angle = 0; $angle < 360; $angle += 45)
                {
                        imagefilledarc($canvas, (int) $center, (int) $center, self::CANVAS - 20, self::CANVAS - 20, $angle, $angle + 12, $flare, IMG_ARC_PIE);
                }
                return self::package(
                        $canvas,
                        'visual_forge:star',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'spectral' => method_exists($star, 'getSpectralClass') ? $star->getSpectralClass() : null,
                                'stage' => method_exists($star, 'getStage') ? $star->getStage() : null,
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function planet(Planet $planet, array $context = array()) : array
        {
                $habitability = max(0.0, min(1.0, $planet->getHabitabilityScore()));
                $classification = isset($context['classification']) ? strval($context['classification']) : $planet->getHabitabilityClassification();
                $name = self::resolveName($planet, 'Planet');
                $palette = array(
                        self::colorFromSeed($name . '-surface', 0.05 + $habitability * 0.2),
                        self::colorFromSeed($name . '-banding', -0.1 + $habitability * 0.1),
                        self::colorFromSeed($name . '-rings', 0.2),
                );
                $canvas = self::createCanvas();
                self::drawPlanet($canvas, $palette, $habitability, $classification);
                return self::package(
                        $canvas,
                        'visual_forge:planet',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'habitability' => $habitability,
                                'classification' => $classification,
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function country(Country $country, array $context = array()) : array
        {
                $territory = array();
                if (method_exists($country, 'getTerritoryMetadata'))
                {
                        $territory = $country->getTerritoryMetadata();
                }
                elseif (method_exists($country, 'getTerritoryProfile'))
                {
                        $territory = $country->getTerritoryProfile();
                }
                $name = self::resolveName($country, 'Country');
                $palette = array(
                        self::colorFromSeed($name . '-border', 0.2),
                        self::colorFromSeed($name . '-field', 0.05),
                        self::colorFromSeed($name . '-accent', 0.3),
                );
                $canvas = self::createCanvas();
                self::drawCountry($canvas, $palette, is_array($territory) ? $territory : array());
                return self::package(
                        $canvas,
                        'visual_forge:country',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'population' => isset($context['population']) ? (int) $context['population'] : null,
                                'territory' => is_array($territory) ? $territory : null,
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function city(City $city, array $context = array()) : array
        {
                $map = array();
                if (method_exists($city, 'getMapMetadata'))
                {
                        $map = $city->getMapMetadata();
                }
                elseif (method_exists($city, 'getMapOverview'))
                {
                        $map = $city->getMapOverview(0);
                }
                $residents = array();
                if (is_array($map) && isset($map['residents']) && is_array($map['residents']))
                {
                        $residents = $map['residents'];
                }
                elseif (method_exists($city, 'getResidents'))
                {
                        $residents = $city->getResidents();
                }
                $name = self::resolveName($city, 'City');
                $palette = array(
                        self::colorFromSeed($name . '-outline', 0.15),
                        self::colorFromSeed($name . '-fill', 0.1),
                        self::colorFromSeed($name . '-accent', 0.25),
                );
                $canvas = self::createCanvas();
                self::drawCity($canvas, $palette, $residents);
                return self::package(
                        $canvas,
                        'visual_forge:city',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'population' => isset($context['population']) ? (int) $context['population'] : null,
                                'coordinates' => $context['coordinates'] ?? ($map['coordinates'] ?? null),
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function person(Person $person, array $context = array()) : array
        {
                $wealth = 0.0;
                if (method_exists($person, 'getNetWorth'))
                {
                        $worth = $person->getNetWorth();
                        if (is_numeric($worth))
                        {
                                $wealth = min(1.0, max(0.0, floatval($worth) / 1_000_000.0));
                        }
                }
                $name = self::resolveName($person, 'Person');
                $palette = array(
                        self::colorFromSeed($name . '-persona', 0.2),
                        self::colorFromSeed($name . '-orbit', 0.15),
                        self::colorFromSeed($name . '-glow', 0.35),
                );
                $canvas = self::createCanvas();
                self::drawPerson($canvas, $palette, $wealth);
                return self::package(
                        $canvas,
                        'visual_forge:person',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'wealth_index' => $wealth,
                                'profession' => $context['profession'] ?? null,
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function element(Element $element, array $context = array()) : array
        {
                $symbol = method_exists($element, 'getSymbol') ? $element->getSymbol() : '';
                $seed = ($symbol !== '') ? $symbol : $element->getName();
                $palette = array(
                        self::colorFromSeed($seed . '-core', 0.1),
                        self::colorFromSeed($seed . '-shell', 0.2),
                        self::colorFromSeed($seed . '-accent', 0.15),
                );
                $canvas = self::createCanvas();
                self::drawElement($canvas, $palette);
                return self::package(
                        $canvas,
                        'visual_forge:element',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'symbol' => $symbol,
                                'atomic_number' => method_exists($element, 'getAtomicNumber') ? $element->getAtomicNumber() : null,
                        )
                );
        }

        /**
         * @return array{mime_type:string,content:string,generator:string,prompt:string,width:int,height:int,attributes:array<string,mixed>,metadata:array<string,mixed>}
         */
        public static function compound(Compound $compound, array $context = array()) : array
        {
                $name = $compound->getName();
                $palette = array(
                        self::colorFromSeed($name . '-bond', 0.2),
                        self::colorFromSeed($name . '-atom', 0.1),
                        self::colorFromSeed($name . '-accent', 0.25),
                );
                $canvas = self::createCanvas();
                self::drawCompound($canvas, $palette);
                return self::package(
                        $canvas,
                        'visual_forge:compound',
                        array(
                                'palette' => self::paletteToHex($palette),
                                'formula' => method_exists($compound, 'getFormula') ? $compound->getFormula() : '',
                        )
                );
        }
}
