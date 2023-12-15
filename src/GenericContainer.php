<?php

declare(strict_types=1);

namespace Testcontainers;

use Docker\API\Model\ContainerConfigExposedPortsItem;
use Docker\API\Model\ContainerCreateResponse;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\ContainersIdExecPostBody;
use Docker\API\Model\ContainersIdJsonGetResponse200;
use Docker\API\Model\ExecIdStartPostBody;
use Docker\API\Model\HostConfig;
use Docker\API\Model\PortBinding;

class GenericContainer implements TestContainer
{
    private ContainersCreatePostBody $containerDefinition;

    private ContainerCreateResponse $container;


    public function __construct(
        string $image,
    ) {
        $this->containerDefinition = new ContainersCreatePostBody();
        $this->containerDefinition->setImage($image);
    }

    public function start(): self
    {
        $this->container = Testcontainers::getRuntime()
            ->containerCreate($this->containerDefinition);

        Testcontainers::getRuntime()
            ->containerStart($this->container->getId());

        return $this;
    }

    public function stop(): self
    {
        Testcontainers::getRuntime()
            ->containerStop($this->container->getId());

        Testcontainers::getRuntime()
            ->containerDelete($this->container->getId());

        unset($this->container);

        return $this;
    }

    public function inspect(): ContainersIdJsonGetResponse200
    {
        return Testcontainers::getRuntime()
            ->containerInspect($this->container->getId());
    }

    public function withExposedPorts(string $port): self
    {
        $this->containerDefinition
            ->setExposedPorts([$port => new ContainerConfigExposedPortsItem()]);

        $hostConfig = (new HostConfig())
            ->setPortBindings([$port => [new PortBinding()]]);

        $this->containerDefinition
            ->setHostConfig($hostConfig);

        return $this;
    }

    public function exec(array $command): string
    {
        $request = (new ContainersIdExecPostBody())
            ->setCmd($command)
            ->setAttachStdout(true)
            ->setAttachStderr(true);

        $response = Testcontainers::getRuntime()
            ->containerExec($this->container->getId(), $request);

        $rr = new ExecIdStartPostBody();
        $rr->setDetach(false);
        $rr->setTty(true);




        $r = Testcontainers::getRuntime()
            ->execStart($response->getId(), $rr);

        $foo = Testcontainers::getRuntime()
            ->execInspect($response->getId());


        $a = Testcontainers::getRuntime()
            ->containerAttach($this->container->getId());

        var_dump($a->getBody()->getContents());
        var_dump($foo);
    }

    public function withCommand(array $command): self
    {
        $this->containerDefinition->setCmd($command);
        return $this;
    }

    public function getHost(): string
    {
        return $this->inspect()->getNetworkSettings()->getGateway();
    }

    public function getMappedPort(string $port): int
    {
        return (int) $this->inspect()->getNetworkSettings()->getPorts()[$port][0]->getHostPort();
    }

    public function getFirstMappedPort(): int
    {
        $port = array_key_first($this->containerDefinition->getExposedPorts());
        return $this->getMappedPort($port);
    }
}
