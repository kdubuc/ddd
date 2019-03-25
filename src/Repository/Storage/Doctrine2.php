<?php

namespace API\Repository\Storage;

use API\Domain\Collection;
use API\Domain\AggregateRoot;
use Doctrine\ORM\EntityManager;
use Doctrine\Common\Collections\Criteria;

class Doctrine2 implements Storage
{
    /*
     * Constructeur
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Insert model.
     */
    public function insert(AggregateRoot $aggregate_root) : AggregateRoot
    {
        $this->em->persist($aggregate_root);

        $this->em->flush();

        return $aggregate_root;
    }

    /**
     * Get collections.
     */
    public function select($class_name, Criteria $criteria = null) : Collection
    {
        $collection = $this->em->getRepository($class_name)->matching($criteria);

        $collection = new Collection($collection->toArray());

        return $collection;
    }

    /**
     * Update model.
     */
    public function update(AggregateRoot $aggregate_root) : AggregateRoot
    {
        if ($this->em->contains($aggregate_root)) {
            $this->em->flush();

            return $aggregate_root;
        } else {
            return $this->insert($aggregate_root);
        }
    }

    /**
     * Delete values.
     */
    public function delete($class_name, Criteria $criteria = null) : Collection
    {
        $collection = $this->select($class_name, $criteria);

        foreach ($collection as $aggregate_root) {
            $this->em->remove($aggregate_root);
        }

        $this->em->flush();

        return $collection;
    }
}
