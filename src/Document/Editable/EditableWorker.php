<?php

declare(strict_types=1);

/*
 * This source file is available under two different licenses:
 *   - GNU General Public License version 3 (GPLv3)
 *   - DACHCOM Commercial License (DCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) DACHCOM.DIGITAL AG (https://www.dachcom-digital.com)
 * @license    GPLv3 and DCL
 */

namespace ToolboxBundle\Document\Editable;

use Pimcore\Document\Editable\Block\BlockState;
use Pimcore\Document\Editable\Block\BlockStateStack;
use Pimcore\Extension\Document\Areabrick\AreabrickInterface;
use Pimcore\Model\Document\Editable;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use ToolboxBundle\Document\Response\HeadlessResponse;
use ToolboxBundle\Event\HeadlessElementEvent;
use ToolboxBundle\Manager\ConfigManagerInterface;
use ToolboxBundle\Registry\NormalizerRegistryInterface;
use ToolboxBundle\ToolboxEvents;

class EditableWorker
{
    public function __construct(
        protected ConfigManagerInterface $configManager,
        protected NormalizerRegistryInterface $normalizerRegistry,
        protected EventDispatcherInterface $eventDispatcher,
        protected BlockStateStack $blockStateStack
    ) {
    }

    public function processBrick(HeadlessResponse $data, AreabrickInterface $areabrick): void
    {
        $this->dispatch([
            'elementType'      => $data->getType(),
            'elementSubType'   => $areabrick->getId(),
            'elementHash'      => $this->buildBrickHash(),
            'elementNamespace' => $this->buildBrickNamespace(),
            'data'             => $this->processBrickData($data, $areabrick->getId())
        ]);
    }

    public function processEditable(HeadlessResponse $data, Editable $editable): void
    {
        $this->dispatch([
            'elementType'      => $data->getType(),
            'elementSubType'   => $editable->getType(),
            'elementHash'      => $this->buildEditableHash($editable),
            'elementNamespace' => $this->buildEditableNamespace($editable),
            'data'             => $this->processEditableData($data)
        ]);
    }

    public function processVirtualElement(string $type, string $subType, string $hash, string $namespace): void
    {
        $this->dispatch([
            'elementType'      => $type,
            'elementSubType'   => $subType,
            'elementHash'      => $hash,
            'elementNamespace' => $namespace,
            'data'             => []
        ]);
    }

    public function buildBrickHash(): string
    {
        return hash('xxh3', sprintf('element_hash_%s', str_replace([':', '.'], '_', $this->buildBrickNamespace())));
    }

    public function buildEditableHash(Editable $editable): string
    {
        return hash('xxh3', sprintf('element_hash_%s', str_replace([':', '.'], '_', $editable->getName())));
    }

    public function buildBlockHash(string $blockName, int $blockIndex): string
    {
        return hash('xxh3', sprintf('element_hash_%s_%d', $blockName, $blockIndex));
    }

    private function dispatch(array $arguments): void
    {
        $this->eventDispatcher->dispatch(
            new HeadlessElementEvent(...$arguments),
            ToolboxEvents::HEADLESS_ELEMENT_STACK_ADD
        );
    }

    private function buildEditableNamespace(Editable $editable): string
    {
        return str_replace('.', ':', $editable->getName());
    }

    private function buildBrickNamespace(): string
    {
        $indexes = $this->getBlockState()->getIndexes();
        $blocks = $this->getBlockState()->getBlocks();

        $parts = [];
        for ($i = 0, $iMax = count($blocks); $i < $iMax; $i++) {
            $part = $blocks[$i]->getRealName();
            if (isset($indexes[$i])) {
                $part = sprintf('%s:%d', $part, $indexes[$i]);
            }

            $parts[] = $part;
        }

        return implode(':', $parts);
    }

    private function processBrickData(HeadlessResponse $data, string $areaName): array
    {
        return [
            'configuration' => $data->getBrickConfiguration(),
            'data'          => $this->processElementData($data, $areaName)
        ];
    }

    private function processEditableData(HeadlessResponse $data): array
    {
        if ($data->hasBrickParent() === true) {
            // it's a nested simple editable
            // (e.g. a editable within a block element which can could be the "accordion" element)
            $parsedData = $this->processElementData($data, $data->getBrickParent());
            // it's a simple editable without any brick relation
        } elseif ($data->hasEditableConfiguration() === true) {
            $parsedData = $this->processSimpleEditableData($data);
        } else {
            // unknown, just return the given data
            $parsedData = $data->getInlineConfigElementData();
        }

        return ['data' => $parsedData];
    }

    private function processSimpleEditableData(HeadlessResponse $data): array
    {
        $normalizedData = [];

        $config = $data->getEditableConfiguration();
        $editableType = $data->getEditableType();
        $elementData = $data->getInlineConfigElementData();

        foreach ($elementData as $configName => $configData) {
            if (array_key_exists('property_normalizer', $config) && $config['property_normalizer'] !== null) {
                $configData = $this->applyNormalizer($config['property_normalizer'], $configData);
            } elseif (null !== $defaultNormalizer = $this->getDefaultNormalizer($editableType)) {
                $configData = $this->applyNormalizer($defaultNormalizer, $configData);
            } elseif ($configData instanceof Editable) {
                $configData = $configData->render();
            } else {
                $configData = null;
            }

            $normalizedData[$configName] = $configData;
        }

        return $normalizedData;
    }

    private function processElementData(HeadlessResponse $data, string $areaName): array
    {
        $normalizedData = [];
        $brickConfig = $this->configManager->getAreaConfig($areaName);

        $configBlocks = [
            'config_elements'                => $data->getConfigElementData(),
            'inline_config_elements'         => $data->getInlineConfigElementData(),
            'additional_property_normalizer' => $data->getAdditionalConfigData(),
        ];

        foreach ($configBlocks as $configBlockName => $configBlockData) {
            $configElements = $brickConfig[$configBlockName] ?? [];
            foreach ($configBlockData as $configName => $configData) {
                if ($configBlockName === 'additional_property_normalizer' && array_key_exists($configName, $configElements)) {
                    $configData = $this->applyNormalizer($configElements[$configName], $configData);
                } elseif ($configBlockName !== 'additional_property_normalizer') {
                    $configNode = $this->findBrickConfigNode($configName, $configElements);
                    if ($configNode !== null) {
                        if ($configNode['property_normalizer'] !== null) {
                            $configData = $this->applyNormalizer($configNode['property_normalizer'], $configData);
                        } elseif (null !== $defaultNormalizer = $this->getDefaultNormalizer($configNode['type'])) {
                            $configData = $this->applyNormalizer($defaultNormalizer, $configData);
                        }
                    }
                }

                // not normalized, use default editable data
                if ($configData instanceof Editable\EditableInterface) {
                    $configData = $configData->getData();
                }

                $normalizedData[$configName] = $configData;
            }
        }

        return $normalizedData;
    }

    private function findBrickConfigNode(string $configName, array $configElements)
    {
        if (array_key_exists($configName, $configElements)) {
            return $configElements[$configName];
        }

        foreach ($configElements as $configElement) {
            if (array_key_exists('children', $configElement) && null !== $childNode = $this->findBrickConfigNode($configName, $configElement['children'])) {
                return $childNode;
            }
        }

        return null;
    }

    private function applyNormalizer(string $normalizerName, mixed $value)
    {
        return $this->normalizerRegistry->get($normalizerName)->normalize($value, $this->configManager->getContextIdentifier());
    }

    private function getDefaultNormalizer(string $type): ?string
    {
        $propertyNormalizerConfig = $this->configManager->getConfig('property_normalizer');
        $defaultMapping = $propertyNormalizerConfig['default_type_mapping'];

        return $defaultMapping[$type] ?? null;
    }

    private function getBlockState(): BlockState
    {
        return $this->blockStateStack->getCurrentState();
    }
}
