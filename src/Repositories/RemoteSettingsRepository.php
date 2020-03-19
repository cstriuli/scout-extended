<?php

declare(strict_types=1);

/**
 * This file is part of Scout Extended.
 *
 * (c) Algolia Team <contact@algolia.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Algolia\ScoutExtended\Repositories;

use Algolia\AlgoliaSearch\Exceptions\NotFoundException;
use Algolia\ScoutExtended\Algolia;
use Algolia\AlgoliaSearch\SearchClient;
use Algolia\AlgoliaSearch\SearchIndex;
use Algolia\ScoutExtended\Settings\Settings;

/**
 * @internal
 */
final class RemoteSettingsRepository
{
    /**
     * Settings that may be know by other names.
     *
     * @var array
     */
    private static $aliases = [
        'attributesToIndex' => 'searchableAttributes',
    ];

    /**
     * @var \Algolia\AlgoliaSearch\SearchClient
     */
    private $client;

    /**
     * @var \Algolia\ScoutExtended\Algolia
     */
    private $algolia;

    /**
     * @var array
     */
    private $defaults;

    /**
     * RemoteRepository constructor.
     *
     * @param \Algolia\AlgoliaSearch\SearchClient $client
     * @param \Algolia\ScoutExtended\Algolia $algolia
     *
     * @return void
     */
    public function __construct(SearchClient $client,  Algolia $algolia)
    {
        $this->client = $client;
        $this->algolia = $algolia;
    }

    /**
     * Get the default settings.
     *
     * @return array
     */
    public function defaults(): array
    {
        if ($this->defaults === null) {
            $indexName = 'temp-laravel-scout-extended';
            $index = $this->client->initIndex($indexName);
            $this->defaults = $this->getSettingsRaw($index);
            $index->delete();
        }

        return $this->defaults;
    }

    /**
     * Find the settings of the given Index.
     *
     * @param  \Algolia\AlgoliaSearch\SearchIndex $index
     *
     * @return \Algolia\ScoutExtended\Settings\Settings
     */
    public function find(SearchIndex $index): Settings
    {
        return new Settings($this->getSettingsRaw($index), $this->defaults());
    }

    /**
     * @param \Algolia\AlgoliaSearch\SearchIndex $index
     * @param \Algolia\ScoutExtended\Settings\Settings $settings
     *
     * @return void
     */
    public function save(SearchIndex $index, Settings $settings): void
    {
        $index->setSettings($settings->compiled())->wait();
    }

    /**
     * @param  \Algolia\AlgoliaSearch\SearchIndex $index
     *
     * @return array
     */
    public function getSettingsRaw(SearchIndex $index): array
    {
        try {
            $settings = $index->getSettings();

            if (isset($settings['replicas'])) {
    
                $settings['replicas_settings'] = array_reduce(
                    $settings['replicas'],
                    function (array $carry, $key) {
                        $carry[$key] = $replicaIndex->getSettings($key);

                        return $carry;
                    },
                    []
                );
            }

        } catch (NotFoundException $e) {
            $index->saveObject(['objectID' => 'temp'])->wait();
            $settings = $index->getSettings();

            $index->clearObjects();
        }

        foreach (self::$aliases as $from => $to) {
            if (array_key_exists($from, $settings)) {
                $settings[$to] = $settings[$from];
                unset($settings[$from]);
            }
        }

        return $settings;
    }
}
