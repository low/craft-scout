<?php
/**
 * Scout plugin for Craft CMS 3.x.
 *
 * Craft Scout provides a simple solution for adding full-text search to your entries. Scout will automatically keep your search indexes in sync with your entries.
 *
 * @link      https://rias.be
 *
 * @copyright Copyright (c) 2017 Rias
 */

namespace rias\scout\console\controllers;

use Craft;
use rias\scout\jobs\IndexElement;
use rias\scout\Scout;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Default Command.
 *
 * @author    Rias
 *
 * @since     0.1.0
 */
class IndexController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Flush one or all indexes.
     *
     * @param string $index
     *
     * @throws \AlgoliaSearch\AlgoliaException
     *
     * @return mixed
     */
    public function actionFlush($index = '')
    {
        if ($this->confirm(Craft::t('scout', 'Are you sure you want to flush Scout?'))) {
            /* @var \rias\scout\models\IndexModel $mapping */
            foreach ($this->getMappings($index) as $mapping) {
                $index = Scout::$plugin->scoutService->getClient()->initIndex($mapping->indexName);
                $index->clearIndex();
            }

            return ExitCode::OK;
        }

        return ExitCode::OK;
    }

    /**
     * Import one or all indexes.
     *
     * @param string $index
     *
     * @return int
     */
    public function actionImport($index = '')
    {
        /* @var \rias\scout\models\IndexModel $mapping */
        foreach ($this->getMappings($index) as $mapping) {
            // Get all elements to index
            $elements = $mapping->getElementQuery()->all();

            // Create a job to index each element
            $progress = 0;
            $total = count($elements);
            Console::startProgress(
                $progress,
                $total,
                Craft::t('scout', 'Adding elements from index {index}.', ['index' => $index]),
                0.5
            );
            foreach ($elements as $element) {
                Craft::$app->queue->push(new IndexElement([
                    'elements' => $element,
                ]));
                $progress++;
                Console::updateProgress($progress, $total);
            }
            Console::endProgress();
        }

        // Run the queue after adding all elements
        $this->stdout(Craft::t('scout', 'Running queue jobs...'), Console::FG_GREEN);
        Craft::$app->queue->run();

        // Everything went OK
        return ExitCode::OK;
    }

    /**
     * @param string $index
     *
     * @return mixed
     */
    protected function getMappings($index = '')
    {
        $mappings = Scout::$plugin->scoutService->getMappings();

        // If we have an argument, only get indexes that match it
        if (!empty($index)) {
            $mappings = array_filter($mappings, function ($mapping) use ($index) {
                return $mapping->indexName == $index;
            });
        }

        if (!count($mappings)) {
            $this->stderr(Craft::t('scout', 'Index {index} not found.', ['index' => $index]));

            exit(ExitCode::DATAERR);
        }

        return $mappings;
    }
}
