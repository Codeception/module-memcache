<?php

declare(strict_types=1);

namespace Codeception\Module;

use Codeception\Module;
use Codeception\TestInterface;
use Codeception\Exception\ModuleConfigException;
use Memcached;

/**
 * Connects to [memcached](https://www.memcached.org/) using either _Memcache_ or _Memcached_ extension.
 *
 * Performs a cleanup by flushing all values after each test run.
 *
 * ## Status
 *
 * * Maintainer: **davert**
 * * Stability: **beta**
 * * Contact: davert@codeception.com
 *
 * ## Configuration
 *
 * * **`host`** (`string`, default `'localhost'`) - The memcached host
 * * **`port`** (`int`, default `11211`) - The memcached port
 *
 * ### Example (`unit.suite.yml`)
 *
 * ```yaml
 *    modules:
 *        - Memcache:
 *            host: 'localhost'
 *            port: 11211
 * ```
 *
 * Be sure you don't use the production server to connect.
 *
 * ## Public Properties
 *
 * * **memcache** - instance of _Memcache_ or _Memcached_ object
 */
class Memcache extends Module
{
    public \Memcache|Memcached|null $memcache = null;

    /**
     * @var array<string, string|integer>
     */
    protected array $config = [
        'host' => 'localhost',
        'port' => 11211
    ];

    /**
     * Code to run before each test.
     *
     * @throws ModuleConfigException
     */
    public function _before(TestInterface $test): void
    {
        if (class_exists('\Memcache')) {
            $this->memcache = new \Memcache;
            $this->memcache->connect($this->config['host'], $this->config['port']);
        } elseif (class_exists('\Memcached')) {
            $this->memcache = new Memcached;
            $this->memcache->addServer($this->config['host'], $this->config['port']);
        } else {
            throw new ModuleConfigException(__CLASS__, 'Memcache classes not loaded');
        }
    }

    /**
     * Code to run after each test.
     */
    public function _after(TestInterface $test): void
    {
        if (empty($this->memcache)) {
            return;
        }

        $this->memcache->flush();
        if (get_class($this->memcache) == 'Memcache') {
            $this->memcache->close();
        } elseif (get_class($this->memcache) == 'Memcached') {
            $this->memcache->quit();
        }
    }

    /**
     * Grabs value from memcached by key.
     *
     * Example:
     *
     * ``` php
     * <?php
     * $users_count = $I->grabValueFromMemcached('users_count');
     * ```
     */
    public function grabValueFromMemcached(string $key): mixed
    {
        $value = $this->memcache->get($key);
        $this->debugSection("Value", $value);

        return $value;
    }

    /**
     * Checks item in Memcached exists and the same as expected.
     *
     * Examples:
     *
     * ``` php
     * <?php
     * // With only one argument, only checks the key exists
     * $I->seeInMemcached('users_count');
     *
     * // Checks a 'users_count' exists and has the value 200
     * $I->seeInMemcached('users_count', 200);
     * ```
     */
    public function seeInMemcached(string $key, mixed $value = null): void
    {
        $actual = $this->memcache->get($key);
        $this->debugSection("Value", $actual);

        if (null === $value) {
            $this->assertNotFalse($actual, "Cannot find key '{$key}' in Memcached");
        } else {
            $this->assertEquals($value, $actual, "Cannot find key '{$key}' in Memcached with the provided value");
        }
    }

    /**
     * Checks item in Memcached doesn't exist or is the same as expected.
     *
     * Examples:
     *
     * ``` php
     * <?php
     * // With only one argument, only checks the key does not exist
     * $I->dontSeeInMemcached('users_count');
     *
     * // Checks a 'users_count' exists does not exist or its value is not the one provided
     * $I->dontSeeInMemcached('users_count', 200);
     * ```
     */
    public function dontSeeInMemcached(string $key, mixed $value = null): void
    {
        $actual = $this->memcache->get($key);
        $this->debugSection("Value", $actual);

        if (null === $value) {
            $this->assertFalse($actual, "The key '{$key}' exists in Memcached");
        } elseif (false !== $actual) {
            $this->assertEquals($value, $actual, "The key '{$key}' exists in Memcached with the provided value");
        }
    }

    /**
     * Stores an item `$value` with `$key` on the Memcached server.
     */
    public function haveInMemcached(string $key, mixed $value, int $expiration = 0): void
    {
        if (get_class($this->memcache) == 'Memcache') {
            $this->assertTrue($this->memcache->set($key, $value, 0, $expiration));
        } elseif (get_class($this->memcache) == 'Memcached') {
            $this->assertTrue($this->memcache->set($key, $value, $expiration));
        }
    }

    /**
     * Flushes all Memcached data.
     */
    public function clearMemcache(): void
    {
        $this->memcache->flush();
    }
}
