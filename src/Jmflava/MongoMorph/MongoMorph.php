<?php

/**
 * Transforms a Mongo database to files and vice versa.
 *
 * @author Joshua Morse <dashvibe@gmail.com>
 */

namespace Jmflava\MongoMorph;

class MongoMorph
{
    public function __construct(\MongoDB $mongo, $path)
    {
        $this->setMongo($mongo);
        $this->setPath($path);
    }

    /**
     * Creates flat-file data from a MongoDB source.
     *
     * @return void
     */
    public function backup()
    {
        $this->createPath();

        // Fetch collections from Mongo.
        $collections = $this->mongo->listCollections();

        // Manually add system collections.
        $collections[] = $this->mongo->selectCollection('system.indexes');
        $collections[] = $this->mongo->selectCollection('system.users');

        foreach ($collections as $collection) {
            $collectionPath = "{$this->path}/{$collection->getName()}";
            echo "Creating {$collectionPath}/\n";
            $cmd = "mkdir -p {$collectionPath}";
            system($cmd);

            $cursor = $collection->find();
            foreach ($cursor as $record) {
                switch ($collection->getName()) {
                    case 'system.indexes':
                        $recordPath = "{$collectionPath}/{$record['ns']}.json";
                    break;
                    //case 'system.users':
                        //$recordPath = "{$collectionPath}/{$record['_id']}.json";
                    //break;
                    default:
                        if (is_array($record['_id'])) {
                            $recordPath = "{$collectionPath}/{$record['_id']['$id']}.json";
                        } else {
                            $recordPath = "{$collectionPath}/{$record['_id']}.json";
                        }
                }
                $cmd = "touch {$recordPath}";
                $data = json_encode($record, JSON_PRETTY_PRINT);
                file_put_contents($recordPath, $data);
            }
        }
    }

    /**
     * Creates a path for flat-file MongoDB data.
     *
     * @return void
     */
    protected function createPath()
    {
        if (is_dir($this->path)) {
            $cmd = "rm -rf {$this->path}/*";
            system($cmd);
        } else {
            $cmd = "mkdir -p {$this->path}";
            system($cmd);
        }
    }

    /**
     * Restores a MongoDB from files.
     *
     * @return void
     */
    public function restore()
    {
        $collections = scandir($this->path);
        foreach ($collections as $collection) {
            // Ignore "." and "..".
            if ($collection === '.' || $collection === '..') {
                continue;
            }

            // Clear the data out of the collection.
            $this->mongo->selectCollection($collection)->remove();

            $collectionPath = "{$this->path}/{$collection}";
            $records = scandir($collectionPath);

            foreach ($records as $record) {
                // Ignore "." and "..".
                if ($record === '.' || $record === '..') {
                    continue;
                }

                // Set the record's path.
                $recordPath = "{$collectionPath}/{$record}";

                // Get data from the record file.
                $data = json_decode(file_get_contents($recordPath), true);

                if (json_last_error() !== 0) {
                    echo "Invalid JSON detected in {$recordPath}. Skipping...\n";
                } else {
                    // Properly set a MongoId object, where applicable.
                    if (isset($data['_id']['$id'])) {
                        $data['_id'] = new \MongoId($data['_id']['$id']);
                    }

                    // Insert the data into the collection.
                    $this->mongo->selectCollection($collection)->insert($data);
                }
            }
        }
    }

    /**
     * Sets a MongoDB object.
     *
     * @param \MongoDB $mongo
     * @return void
     */
    public function setMongo(\MongoDB $mongo)
    {
        $this->mongo = $mongo;
    }

    /**
     * Sets a path for flat-file MongoDB data.
     *
     * @param mixed $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }
}
