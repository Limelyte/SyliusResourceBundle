<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ResourceBundle\Controller;

use Sylius\Component\Resource\Metadata\ResourceMetadataInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resource controller configuration factory.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@sylius.pl>
 */
class RequestConfigurationFactory implements RequestConfigurationFactoryInterface
{
    const API_VERSION_HEADER = 'Accept';
    const API_GROUPS_HEADER  = 'Accept';

    const API_VERSION_REGEXP = '/(v|version)=(?P<version>[0-9\.]+)/i';
    const API_GROUPS_REGEXP  = '/(g|groups)=(?P<groups>[a-z,_\s]+)/i';

    /**
     * @var ParametersParser
     */
    private $parametersParser;

    /**
     * @var string
     */
    private $configurationClass;

    /**
     * Default parameters.
     *
     * @var array
     */
    private $defaultParameters;
    /**
     * @var Parameters
     */
    private $parameters;

    /**
     * Constructor.
     *
     * @param ParametersParser $parametersParser
     * @param Parameters $parameters
     * @param string $configurationClass
     * @param array $defaultParameters
     */
    public function __construct(ParametersParser $parametersParser, Parameters $parameters, $configurationClass, array $defaultParameters = array())
    {
        $this->parametersParser = $parametersParser;
        $this->configurationClass = $configurationClass;
        $this->defaultParameters = $defaultParameters;
        $this->parameters = $parameters;
    }

    /**
     * Create configuration for given parameters.
     *
     * @return RequestConfiguration
     */
    public function create(ResourceMetadataInterface $metadata, Request $request)
    {
        $parameterNames = null;
        $parameters = $this->parseParametersFromRequest($request);
        $parameters = array_merge($parameters, $this->defaultParameters);
        $parameters = $this->parametersParser->parseRequestValues($parameters, $request, $parameterNames);
        $parameters['parameter_name'] = $parameterNames;

        $routeParams = $request->attributes->get('_route_params', array());
        if (isset($routeParams['_sylius'])) {
            unset($routeParams['_sylius']);

            $request->attributes->set('_route_params', $routeParams);
        }

        $this->parameters->add($parameters);

        return new $this->configurationClass($metadata, $request, $this->parameters);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    private function parseParametersFromRequest(Request $request)
    {
        $parameters = array();

        if ($request->headers->has(self::API_VERSION_HEADER)) {
            if (preg_match(self::API_VERSION_REGEXP, $request->headers->get(self::API_VERSION_HEADER), $matches)) {
                $parameters['serialization_version'] = $matches['version'];
            }
        }

        if ($request->headers->has(self::API_GROUPS_HEADER)) {
            if (preg_match(self::API_GROUPS_REGEXP, $request->headers->get(self::API_GROUPS_HEADER), $matches)) {
                $parameters['serialization_groups'] = array_map('trim', explode(',', $matches['groups']));
            }
        }

        return array_merge($request->attributes->get('_sylius', array()), $parameters);
    }
}
