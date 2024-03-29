<?php

namespace API\Repository\Storage;

use MongoDB;
use API\Domain\Collection;
use API\Domain\AggregateRoot;
use API\Domain\ValueObject\ID;
use Doctrine\Common\Collections\Criteria;

class KeyValue implements Storage
{
    /*
     * Constructor
     */
    public function __construct(MongoDB\Database $database)
    {
        $this->mongodb = $database;
    }

    /*
     * Get (and create if necessary) the correct MongoDB collection for the
     * provided aggregate root.
     */
    private function getCollectionFor(AggregateRoot $aggregate_root) : MongoDB\Collection
    {
        $collection_name = \get_class($aggregate_root);

        if (\in_array($collection_name, (array) $this->mongodb->listCollectionNames())) {
            $this->mongodb->createCollection($collection_name);
        }

        $collection = $this->mongodb->{$collection_name};

        return $collection;
    }

    /**
     * Insert model.
     */
    public function insert(AggregateRoot $aggregate_root) : void
    {
        $collection = $this->getCollectionFor($aggregate_root);

        $document = $aggregate_root->normalize();

        $collection->replaceOne(
            ['id.uuid' => $aggregate_root->getId()->toString()],
            $document,
            ['upsert' => true]
        );
    }

    /**
     * Get collection.
     */
    public function select($class_name, Criteria $criteria = null) : Collection
    {
        // If no criteria was provided, we create an empty one.
        if (empty($criteria)) {
            $criteria = new Criteria();
        }

        // Prepare the query filter
        $filter = [];
        if ($expression = $criteria->getWhereExpression()) {
            $filter = ExpressionTranslator\MongoDB::translateExpression($expression);
        }

        // Prepare the query options
        $options = [];

        // Slice options
        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();
        if ($offset || $length) {
            $options += ExpressionTranslator\MongoDB::translateSlicing($length, (int) $offset);
        }

        // Ordering options
        if ($orderings = $criteria->getOrderings()) {
            $options += ExpressionTranslator\MongoDB::translateOrderings($orderings);
        }

        // MongoDB natively supports the paging operation using the skip() and limit() commands.
        // The skip(n) directive tells MongoDB that it should skip ‘n’ results and the limit(n) directive instructs
        // MongoDB that it should limit the result length to ‘n’ results. Typically, you will be using the skip()
        // and limit() directives with your cursor. However, as the size of your data increases, this approach has serious
        // performance problems. To prevent this, we are going to leverage the natural order in the stored data with _id.
        /*
        if (array_key_exists('skip', $options)) {
            // unset($options['skip']);
            $cursor = $this->mongodb->$class_name->findOne($filter, array_merge($options, ['projection' => ['_id' => true]]));
            $filter += null !== $cursor ? ['_id' => ['$gte' => $cursor['_id']]] : [];
            unset($options['skip']);
        }
        */

        // Perform the query to obtain cursor
        $cursor = $this->mongodb->$class_name->find($filter, $options);

        // Build all aggregate roots
        $collection = $class_name::collection(array_map(function ($document) use ($class_name) {
            $document = json_decode(json_encode($document), JSON_OBJECT_AS_ARRAY);

            return $class_name::denormalize($document);
        }, $cursor->toArray() ?? []));

        return $collection;
    }

    /**
     * Update model.
     */
    public function update(AggregateRoot $aggregate_root) : void
    {
        $this->insert($aggregate_root);
    }

    /**
     * Delete values.
     */
    public function delete($class_name, Criteria $criteria = null) : void
    {
        if (null === $criteria) {
            $this->mongodb->$class_name->deleteMany([]);

            return;
        }

        $old_collection = $this->select($class_name, $criteria);

        foreach ($old_collection as $aggregate_root) {
            $collection = $this->getCollectionFor($aggregate_root);
            $collection->deleteOne(['id.uuid' => $aggregate_root->getId()->toString()]);
        }
    }

    /**
     * Count results.
     */
    public function count($class_name, Criteria $criteria = null) : int
    {
        // If there is a criteria, we filter the collection, otherwise we
        // count the collection.
        if (!empty($criteria) && $expression = $criteria->getWhereExpression()) {
            $filter = ExpressionTranslator\MongoDB::translateExpression($expression);

            return $this->mongodb->$class_name->count($filter);
        }

        return $this->mongodb->$class_name->count();
    }
}
