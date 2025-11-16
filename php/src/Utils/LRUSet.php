<?php

namespace Happy\Utils;

/**
 * Least Recently Used (LRU) Set implementation.
 * Maintains a fixed-size set where the least recently used items are evicted.
 */
class LRUSet
{
    private int $maxSize;
    private array $map = [];
    private ?LRUNode $head = null;
    private ?LRUNode $tail = null;

    public function __construct(int $maxSize)
    {
        if ($maxSize <= 0) {
            throw new \InvalidArgumentException('LRUSet maxSize must be greater than 0');
        }
        $this->maxSize = $maxSize;
    }

    /**
     * Move a node to the front (most recently used position).
     */
    private function moveToFront(LRUNode $node): void
    {
        if ($node === $this->head) {
            return;
        }

        // Remove from current position
        if ($node->prev) {
            $node->prev->next = $node->next;
        }
        if ($node->next) {
            $node->next->prev = $node->prev;
        }
        if ($node === $this->tail) {
            $this->tail = $node->prev;
        }

        // Move to front
        $node->prev = null;
        $node->next = $this->head;
        if ($this->head) {
            $this->head->prev = $node;
        }
        $this->head = $node;
        if (!$this->tail) {
            $this->tail = $node;
        }
    }

    /**
     * Add a value to the set.
     */
    public function add(mixed $value): void
    {
        $key = $this->getKey($value);

        if (isset($this->map[$key])) {
            $existingNode = $this->map[$key];
            $this->moveToFront($existingNode);
            return;
        }

        // Create new node
        $newNode = new LRUNode($value);
        $this->map[$key] = $newNode;

        // Add to front
        $newNode->next = $this->head;
        if ($this->head) {
            $this->head->prev = $newNode;
        }
        $this->head = $newNode;
        if (!$this->tail) {
            $this->tail = $newNode;
        }

        // Remove LRU if over capacity
        if (count($this->map) > $this->maxSize) {
            if ($this->tail) {
                $tailKey = $this->getKey($this->tail->value);
                unset($this->map[$tailKey]);
                $this->tail = $this->tail->prev;
                if ($this->tail) {
                    $this->tail->next = null;
                }
            }
        }
    }

    /**
     * Check if a value exists in the set.
     * Accessing a value moves it to the front (most recently used).
     */
    public function has(mixed $value): bool
    {
        $key = $this->getKey($value);

        if (isset($this->map[$key])) {
            $node = $this->map[$key];
            $this->moveToFront($node);
            return true;
        }

        return false;
    }

    /**
     * Delete a value from the set.
     */
    public function delete(mixed $value): bool
    {
        $key = $this->getKey($value);

        if (!isset($this->map[$key])) {
            return false;
        }

        $node = $this->map[$key];

        // Remove from linked list
        if ($node->prev) {
            $node->prev->next = $node->next;
        }
        if ($node->next) {
            $node->next->prev = $node->prev;
        }
        if ($node === $this->head) {
            $this->head = $node->next;
        }
        if ($node === $this->tail) {
            $this->tail = $node->prev;
        }

        unset($this->map[$key]);
        return true;
    }

    /**
     * Clear all values from the set.
     */
    public function clear(): void
    {
        $this->map = [];
        $this->head = null;
        $this->tail = null;
    }

    /**
     * Get the current size of the set.
     */
    public function size(): int
    {
        return count($this->map);
    }

    /**
     * Get all values as an array (ordered from most to least recently used).
     */
    public function toArray(): array
    {
        $result = [];
        $current = $this->head;

        while ($current) {
            $result[] = $current->value;
            $current = $current->next;
        }

        return $result;
    }

    /**
     * Get a unique key for a value (handles objects and primitives).
     */
    private function getKey(mixed $value): string
    {
        if (is_object($value)) {
            return spl_object_hash($value);
        }
        return serialize($value);
    }
}

/**
 * Internal node class for LRU linked list.
 */
class LRUNode
{
    public mixed $value;
    public ?LRUNode $prev = null;
    public ?LRUNode $next = null;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }
}
