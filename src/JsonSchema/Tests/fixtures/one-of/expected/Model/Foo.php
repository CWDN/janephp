<?php

declare(strict_types=1);

/*
 * This file has been auto generated by Jane,
 *
 * Do no edit it directly.
 */

namespace Jane\JsonSchema\Tests\Expected\Model;

class Foo
{
    /**
     * @var string|object|null[]
     */
    protected $foo;

    /**
     * @return string|object|null[]
     */
    public function getFoo()
    {
        return $this->foo;
    }

    /**
     * @param string|object|null[] $foo
     *
     * @return self
     */
    public function setFoo($foo): self
    {
        $this->foo = $foo;

        return $this;
    }
}
