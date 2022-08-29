<?php

namespace Tofex\Foundation\Helper;

use Exception;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Module\FullModuleList;
use Pest;
use Psr\Log\LoggerInterface;
use Tofex\Help\Arrays;
use Tofex\Help\Variables;
use Tofex\Xml\SimpleXml;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data
{
    /** @var Arrays */
    protected $arrayHelper;

    /** @var Variables */
    protected $variableHelper;

    /** @var SimpleXml */
    protected $simpleXml;

    /** @var LoggerInterface */
    protected $logging;

    /** @var Http */
    protected $request;

    /** @var FullModuleList */
    protected $fullModuleList;

    /**
     * @param Arrays          $arrayHelper
     * @param Variables       $variableHelper
     * @param SimpleXml       $simpleXml
     * @param LoggerInterface $logging
     * @param Http            $request
     * @param FullModuleList  $fullModuleList
     */
    public function __construct(
        Arrays $arrayHelper,
        Variables $variableHelper,
        SimpleXml $simpleXml,
        LoggerInterface $logging,
        Http $request,
        FullModuleList $fullModuleList)
    {
        $this->arrayHelper = $arrayHelper;
        $this->variableHelper = $variableHelper;
        $this->simpleXml = $simpleXml;
        $this->logging = $logging;
        $this->request = $request;
        $this->fullModuleList = $fullModuleList;
    }

    /**
     * @return array[]
     */
    public function getItems(): array
    {
        $items = [];

        try {
            $rssClient = new Pest('https://www.tofex.de');

            $url = 'article/feed';

            $tag = $this->request->getParam('tag');

            if ( ! $this->variableHelper->isEmpty($tag)) {
                $url .= sprintf('/tag/%s', $tag);
            }

            $result = $this->simpleXml->simpleXmlLoadString($rssClient->get($url));

            $parsedResult = json_decode(json_encode($result), true);

            $items = $this->arrayHelper->getValue($parsedResult, 'channel:item');

            if ($this->arrayHelper->isAssociative($items)) {
                $items = [$items];
            }

            $items = array_slice($items, 0, 8);
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        return $items;
    }

    /**
     * @return string[]
     */
    public function getLatestTofexPackageVersions(): array
    {
        $composerData = [];

        try {
            $restClient = new Pest('https://composer.tofex.de');

            foreach ($this->getInstalledTofexPackageVersions() as $projectName => $moduleVersion) {
                $url = sprintf('release/versions/repository/%s', base64_encode($projectName));

                $versions = preg_split('/,/', $restClient->get($url));

                natcasesort($versions);

                $latestVersion = end($versions);

                $composerData[ $projectName ] = $latestVersion;
            }
        } catch (Exception $exception) {
            $this->logging->error($exception);
        }

        return $composerData;
    }

    /**
     * @return string[]
     */
    public function getInstalledTofexPackageVersions(): array
    {
        $composerData = [];

        foreach ($this->fullModuleList->getNames() as $moduleName) {
            if (preg_match('/^Tofex_/', $moduleName)) {
                $moduleVersion =
                    $this->arrayHelper->getValue($this->fullModuleList->getOne($moduleName), 'setup_version');

                [, $packageName] = preg_split('/_/', $moduleName, 2);

                if (array_search($packageName, ['BackendWidget', 'Core', 'Foundation', 'Log', 'Run']) === false) {
                    $packageName = lcfirst($packageName);
                    $packageName = strtolower(trim(preg_replace('/([A-Z]|[0-9]+)/', "-$1", $packageName), '-'));

                    $composerData[ sprintf('tofex/m2-%s', $packageName) ] = $moduleVersion;
                }
            }
        }

        return $composerData;
    }
}
