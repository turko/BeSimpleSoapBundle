<?php

/*
 * This file is part of the BeSimpleSoapBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 * (c) Francis Besset <francis.besset@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BeSimple\SoapBundle\DependencyInjection;

use BeSimple\SoapCommon\Cache;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Kernel;

/**
 * BeSimpleSoapExtension.
 *
 * @author Christian Kerl <christian-kerl@web.de>
 * @author Francis Besset <francis.besset@gmail.com>
 */
class BeSimpleSoapExtension extends Extension
{
    // maps config options to service suffix
    private $bindingConfigToServiceSuffixMap = [
        'rpc-literal' => 'rpcliteral',
        'document-wrapped' => 'documentwrapped',
    ];

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('request.xml');

        $loader->load('loaders.xml');
        $loader->load('converters.xml');
        $loader->load('webservice.xml');

        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->process($configuration->getConfigTree(), $configs);

        $this->registerCacheConfiguration($config['cache'], $container, $loader);

        if (!empty($config['clients'])) {
            $this->registerClientConfiguration($config['clients'], $container, $loader);
        }

        $container->setParameter(
            'besimple.soap.definition.dumper.options.stylesheet',
            $config['wsdl_dumper']['stylesheet']
        );

        foreach ($config['services'] as $name => $serviceConfig) {
            $serviceConfig['name'] = $name;
            $this->createWebServiceContext($serviceConfig, $container);
        }

        $container->setParameter('besimple.soap.exception_listener.controller', $config['exception_controller']);
    }

    private function registerCacheConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        $loader->load('soap.xml');

        $config['type'] = $this->getCacheType($config['type']);

        foreach (['type', 'lifetime', 'limit'] as $key) {
            $container->setParameter('besimple.soap.cache.' . $key, $config[$key]);
        }
    }

    private function getCacheType($type)
    {
        switch ($type) {
            case 'none':
                return Cache::TYPE_NONE;

            case 'disk':
                return Cache::TYPE_DISK;

            case 'memory':
                return Cache::TYPE_MEMORY;

            case 'disk_memory':
                return Cache::TYPE_DISK_MEMORY;
        }
    }

    private function registerClientConfiguration(array $config, ContainerBuilder $container, XmlFileLoader $loader)
    {
        if (3 === Kernel::MAJOR_VERSION) {
            $loader->load('client3.xml');
        } else {
            $loader->load('client.xml');
        }

        foreach ($config as $client => $options) {
            $definition = $this->createChildDefinition('besimple.soap.client.builder');
            $container->setDefinition(sprintf('besimple.soap.client.builder.%s', $client), $definition);

            $definition->replaceArgument(0, $options['wsdl']);

            $defOptions = $container
                ->getDefinition('besimple.soap.client.builder')
                ->getArgument(1);

            foreach (['cache_type', 'user_agent', 'options'] as $key) {
                if (isset($options[$key])) {
                    $defOptions[$key] = $options[$key];
                }
            }

            $proxy = $options['proxy'];
            if (false !== $proxy['host']) {
                if (null !== $proxy['auth']) {
                    if ('basic' === $proxy['auth']) {
                        $proxy['auth'] = \CURLAUTH_BASIC;
                    } elseif ('ntlm' === $proxy['auth']) {
                        $proxy['auth'] = \CURLAUTH_NTLM;
                    }
                }

                $definition->addMethodCall(
                    'withProxy',
                    [
                        $proxy['host'],
                        $proxy['port'],
                        $proxy['login'],
                        $proxy['password'],
                        $proxy['auth']
                    ]
                );
            }

            $authArray = $options['authentication'];
            if (null !== $authArray) {
                $type = $authArray['type'];
                if ((null === $type || 'digest' == $type)
                    && $authArray['local_cert']
                ) {
                    $definition->addMethodCall(
                        'withBasicAuthentication',
                        [
                            $authArray['local_cert'],
                            $authArray['password']
                        ]
                    );
                } elseif ((null === $type || 'basic' == $type)
                    && $authArray['login']
                ) {
                    $definition->addMethodCall(
                        'withBasicAuthentication',
                        [
                            $authArray['login'],
                            $authArray['password']
                        ]
                    );
                }
            }

            if (isset($defOptions['cache_type'])) {
                $defOptions['cache_type'] = $this->getCacheType($defOptions['cache_type']);
            }

            if (isset($defOptions['options']['soap_version'])) {
                $defOptions['options']['soap_version'] = $this->getSoapVersion($defOptions['options']['soap_version']);
            }

            $definition->replaceArgument(1, $defOptions);

            $classmap = $this->createClientClassmap($client, $options['classmap'], $container);
            $definition->replaceArgument(2, new Reference($classmap));

            $this->createClient($client, $container);
        }
    }

    private function createChildDefinition($id)
    {
        if (class_exists('Symfony\Component\DependencyInjection\ChildDefinition')) {
            return new ChildDefinition($id);
        }

        return new DefinitionDecorator($id);
    }

    private function getSoapVersion($version)
    {
        switch ($version) {

            case 'soap_1_1':
                return \SOAP_1_1;
                break;
            default:
                return \SOAP_1_2;
        }
    }

    private function createClientClassmap($client, array $classmap, ContainerBuilder $container)
    {
        $definition = $this->createChildDefinition('besimple.soap.classmap');
        $container->setDefinition(sprintf('besimple.soap.classmap.%s', $client), $definition);

        if (!empty($classmap)) {
            $definition->setMethodCalls(
                [
                    ['set', [$classmap]],
                ]
            );
        }

        return sprintf('besimple.soap.classmap.%s', $client);
    }

    private function createClient($client, ContainerBuilder $container)
    {
        $definition = $this->createChildDefinition('besimple.soap.client');
        $container->setDefinition(sprintf('besimple.soap.client.%s', $client), $definition);

        if (3 === Kernel::MAJOR_VERSION) {
            $definition->setFactory(
                [
                    new Reference(sprintf('besimple.soap.client.builder.%s', $client)),
                    'build'
                ]
            );
        } else {
            $definition->setFactoryService(sprintf('besimple.soap.client.builder.%s', $client));
        }
    }

    private function createWebServiceContext(array $config, ContainerBuilder $container)
    {
        $bindingSuffix = $this->bindingConfigToServiceSuffixMap[$config['binding']];
        unset($config['binding']);

        $contextId = 'besimple.soap.context.' . $config['name'];
        $definition = $this->createChildDefinition('besimple.soap.context.' . $bindingSuffix);
        $container->setDefinition($contextId, $definition);

        if (isset($config['cache_type'])) {
            $config['cache_type'] = $this->getCacheType($config['cache_type']);
        }

        $options = $container
            ->getDefinition('besimple.soap.context.' . $bindingSuffix)
            ->getArgument(2);

        $definition->replaceArgument(2, array_merge($options, $config));
    }
}
