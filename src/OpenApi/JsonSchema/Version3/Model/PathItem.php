<?php

declare(strict_types=1);

/*
 * This file has been auto generated by Jane,
 *
 * Do no edit it directly.
 */

namespace Jane\OpenApi\JsonSchema\Version3\Model;

class PathItem extends \ArrayObject
{
    /**
     * @var string
     */
    protected $dollarRef;
    /**
     * @var string
     */
    protected $summary;
    /**
     * @var string
     */
    protected $description;
    /**
     * @var Operation
     */
    protected $get;
    /**
     * @var Operation
     */
    protected $put;
    /**
     * @var Operation
     */
    protected $post;
    /**
     * @var Operation
     */
    protected $delete;
    /**
     * @var Operation
     */
    protected $options;
    /**
     * @var Operation
     */
    protected $head;
    /**
     * @var Operation
     */
    protected $patch;
    /**
     * @var Operation
     */
    protected $trace;
    /**
     * @var Server[]
     */
    protected $servers;
    /**
     * @var ParameterWithSchemaWithExampleInPath[]|ParameterWithSchemaWithExampleInQuery[]|ParameterWithSchemaWithExampleInHeader[]|ParameterWithSchemaWithExampleInCookie[]|ParameterWithSchemaWithExamplesInPath[]|ParameterWithSchemaWithExamplesInQuery[]|ParameterWithSchemaWithExamplesInHeader[]|ParameterWithSchemaWithExamplesInCookie[]|ParameterWithContentInPath[]|ParameterWithContentNotInPath[]|Reference[]
     */
    protected $parameters;

    /**
     * @return string
     */
    public function getDollarRef(): ?string
    {
        return $this->dollarRef;
    }

    /**
     * @param string $dollarRef
     *
     * @return self
     */
    public function setDollarRef(?string $dollarRef): self
    {
        $this->dollarRef = $dollarRef;

        return $this;
    }

    /**
     * @return string
     */
    public function getSummary(): ?string
    {
        return $this->summary;
    }

    /**
     * @param string $summary
     *
     * @return self
     */
    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getGet(): ?Operation
    {
        return $this->get;
    }

    /**
     * @param Operation $get
     *
     * @return self
     */
    public function setGet(?Operation $get): self
    {
        $this->get = $get;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getPut(): ?Operation
    {
        return $this->put;
    }

    /**
     * @param Operation $put
     *
     * @return self
     */
    public function setPut(?Operation $put): self
    {
        $this->put = $put;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getPost(): ?Operation
    {
        return $this->post;
    }

    /**
     * @param Operation $post
     *
     * @return self
     */
    public function setPost(?Operation $post): self
    {
        $this->post = $post;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getDelete(): ?Operation
    {
        return $this->delete;
    }

    /**
     * @param Operation $delete
     *
     * @return self
     */
    public function setDelete(?Operation $delete): self
    {
        $this->delete = $delete;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getOptions(): ?Operation
    {
        return $this->options;
    }

    /**
     * @param Operation $options
     *
     * @return self
     */
    public function setOptions(?Operation $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getHead(): ?Operation
    {
        return $this->head;
    }

    /**
     * @param Operation $head
     *
     * @return self
     */
    public function setHead(?Operation $head): self
    {
        $this->head = $head;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getPatch(): ?Operation
    {
        return $this->patch;
    }

    /**
     * @param Operation $patch
     *
     * @return self
     */
    public function setPatch(?Operation $patch): self
    {
        $this->patch = $patch;

        return $this;
    }

    /**
     * @return Operation
     */
    public function getTrace(): ?Operation
    {
        return $this->trace;
    }

    /**
     * @param Operation $trace
     *
     * @return self
     */
    public function setTrace(?Operation $trace): self
    {
        $this->trace = $trace;

        return $this;
    }

    /**
     * @return Server[]
     */
    public function getServers(): ?array
    {
        return $this->servers;
    }

    /**
     * @param Server[] $servers
     *
     * @return self
     */
    public function setServers(?array $servers): self
    {
        $this->servers = $servers;

        return $this;
    }

    /**
     * @return ParameterWithSchemaWithExampleInPath[]|ParameterWithSchemaWithExampleInQuery[]|ParameterWithSchemaWithExampleInHeader[]|ParameterWithSchemaWithExampleInCookie[]|ParameterWithSchemaWithExamplesInPath[]|ParameterWithSchemaWithExamplesInQuery[]|ParameterWithSchemaWithExamplesInHeader[]|ParameterWithSchemaWithExamplesInCookie[]|ParameterWithContentInPath[]|ParameterWithContentNotInPath[]|Reference[]
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @param ParameterWithSchemaWithExampleInPath[]|ParameterWithSchemaWithExampleInQuery[]|ParameterWithSchemaWithExampleInHeader[]|ParameterWithSchemaWithExampleInCookie[]|ParameterWithSchemaWithExamplesInPath[]|ParameterWithSchemaWithExamplesInQuery[]|ParameterWithSchemaWithExamplesInHeader[]|ParameterWithSchemaWithExamplesInCookie[]|ParameterWithContentInPath[]|ParameterWithContentNotInPath[]|Reference[] $parameters
     *
     * @return self
     */
    public function setParameters(?array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }
}
