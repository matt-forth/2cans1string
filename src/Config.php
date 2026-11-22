<?php

declare(strict_types=1);

namespace Cans;

use RuntimeException;

/** config.default.php overlaid by the git-ignored config.local.php. */
final class Config
{
    /** @param array<string, mixed> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function load(string $appRoot): self
    {
        $values = require $appRoot . '/config.default.php';
        $local = $appRoot . '/config.local.php';
        if (is_file($local)) {
            $values = array_replace_recursive($values, require $local);
        }
        foreach (['base_url', 'secret', 'probe_key'] as $required) {
            if (($values[$required] ?? '') === '') {
                throw new RuntimeException("Config '$required' is not set — copy config.local.php.example to config.local.php and fill it in.");
            }
        }
        $values['base_url'] = rtrim((string) $values['base_url'], '/');

        return new self($values);
    }

    public function get(string $key): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                throw new RuntimeException("Unknown config key '$key'.");
            }
            $value = $value[$part];
        }

        return $value;
    }

    public function baseUrl(): string
    {
        return $this->values['base_url'];
    }

    /** The path part of base_url ('' when the site is at the domain root), e.g. '/cans'. */
    public function basePath(): string
    {
        return rtrim((string) parse_url($this->baseUrl(), PHP_URL_PATH), '/');
    }
}
