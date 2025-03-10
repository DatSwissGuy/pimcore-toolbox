<?php

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

namespace ToolboxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use ToolboxBundle\Resolver\ContextResolver;
use ToolboxBundle\ToolboxConfig;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('toolbox');
        $rootNode = $treeBuilder->getRootNode();

        $this->addRootNode($rootNode);
        $this->addContextNode($rootNode);

        $rootNode
            ->children()
                ->scalarNode('context_resolver')->defaultValue(ContextResolver::class)->end()
            ->end();

        return $treeBuilder;
    }

    public function addContextNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->arrayNode('context')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->append($this->buildContextSettingsNode())
                            ->append($this->buildFlagsConfiguration())
                            ->append($this->buildAreasSection())
                            ->append($this->buildWysiwygEditorConfigSection())
                            ->append($this->buildImageThumbnailSection())
                            ->append($this->buildAreaBlockRestrictionConfiguration('areablock_restriction'))
                            ->append($this->buildAreaBlockRestrictionConfiguration('snippet_areablock_restriction'))
                            ->append($this->buildAreaBlockConfiguration())
                            ->append($this->buildThemeConfiguration())
                            ->append($this->buildDataAttributeConfiguration())
                            ->append($this->buildPropertyNormalizerDefaultsConfiguration())
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function addRootNode(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->append($this->buildCoreAreasConfiguration())
                ->append($this->buildFlagsConfiguration())
                ->append($this->buildAreasSection())
                ->append($this->buildWysiwygEditorConfigSection())
                ->append($this->buildImageThumbnailSection())
                ->append($this->buildAreaBlockRestrictionConfiguration('areablock_restriction'))
                ->append($this->buildAreaBlockRestrictionConfiguration('snippet_areablock_restriction'))
                ->append($this->buildAreaBlockConfiguration())
                ->append($this->buildThemeConfiguration())
                ->append($this->buildDataAttributeConfiguration())
                ->append($this->buildPropertyNormalizerDefaultsConfiguration())
            ->end();
    }

    protected function buildCoreAreasConfiguration(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('enabled_core_areas');

        $treeBuilder
            ->prototype('scalar')
                ->defaultValue([])
                ->validate()
                    ->ifTrue(function ($areaName) {
                        return !in_array($areaName, ToolboxConfig::TOOLBOX_AREA_TYPES, true);
                    })
                    ->then(function ($areaName) {
                        throw new InvalidConfigurationException(sprintf(
                            'Invalid core element "%s" in toolbox "enabled_core_areas" configuration". Available types for "enabled_core_areas" are: %s',
                            $areaName,
                            implode(', ', ToolboxConfig::TOOLBOX_AREA_TYPES)
                        ));
                    })
                ->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildContextSettingsNode(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('settings');

        $treeBuilder
            ->beforeNormalization()
                ->ifTrue(function ($v) {
                    return $v['merge_with_root'] === false && (!empty($v['disabled_areas']));
                })
                ->then(function ($v) {
                    @trigger_error('Toolbox context conflict: "merge_with_root" is disabled but there are defined elements in "disabled_areas"', E_USER_ERROR);
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(function ($v) {
                    return $v['merge_with_root'] === false && (!empty($v['enabled_areas']));
                })
                ->then(function ($v) {
                    @trigger_error('Toolbox context conflict: "merge_with_root" is disabled but there are defined elements in "enabled_areas"', E_USER_ERROR);
                })
            ->end()
            ->isRequired()
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('merge_with_root')->defaultValue(true)->end()
                ->variableNode('disabled_areas')->defaultValue([])->end()
                ->variableNode('enabled_areas')->defaultValue([])->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildFlagsConfiguration(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('flags');

        $treeBuilder
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('strict_column_counter')->defaultValue(false)->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildWysiwygEditorConfigSection(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('wysiwyg_editor');

        $treeBuilder
            ->addDefaultsIfNotSet()
            ->children()
                ->variableNode('config')->defaultValue([])->end()
                ->arrayNode('area_editor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('config')
                            ->validate()->ifEmpty()->thenUnset()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('object_editor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('config')
                            ->validate()->ifEmpty()->thenUnset()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildImageThumbnailSection(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('image_thumbnails');

        $treeBuilder
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end();

        return $treeBuilder;
    }

    protected function buildAreaBlockRestrictionConfiguration(string $type): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition($type);

        $treeBuilder
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('disallowed')
                        ->prototype('scalar')->end()
                    ->end()
                    ->arrayNode('allowed')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildAreaBlockConfiguration(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('area_block_configuration');

        $treeBuilder
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('toolbar')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('width')->defaultValue(172)->end()
                        ->integerNode('buttonWidth')->defaultValue(168)->end()
                        ->integerNode('buttonMaxCharacters')->defaultValue(20)->end()
                    ->end()
                ->end()
                ->variableNode('groups')->defaultNull()->end()
                ->enumNode('controlsAlign')->values(['top', 'right', 'left'])->defaultValue('top')->end()
                ->enumNode('controlsTrigger')->values(['hover', 'fixed'])->defaultValue('hover')->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildThemeConfiguration(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('theme');

        $treeBuilder
            ->children()
                ->scalarNode('layout')->cannotBeEmpty()->end()
                ->append($this->buildHeadlessDocumentsSection())
                ->scalarNode('default_layout')
                    ->defaultValue(false)
                ->end()
                ->arrayNode('calculators')
                    ->addDefaultsIfNotSet()
                    ->isRequired()
                    ->children()
                        ->scalarNode('column_calculator')->isRequired()->end()
                        ->scalarNode('slide_calculator')->isRequired()->end()
                    ->end()
                ->end()
                ->arrayNode('grid')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('grid_size')->min(0)->defaultValue(12)->end()
                        ->arrayNode('column_store')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('breakpoints')
                            ->performNoDeepMerging()
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('identifier')->isRequired()->end()
                                    ->scalarNode('name')->defaultValue(null)->end()
                                    ->scalarNode('description')->defaultValue(null)->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('wrapper')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                    ->performNoDeepMerging()
                    ->beforeNormalization()
                        ->ifTrue(function ($v) {
                            return is_array($v) && !isset($v['wrapper_classes']);
                        })
                        ->then(function ($v) {
                            return ['wrapper_classes' => $v];
                        })
                    ->end()
                        ->children()
                            ->arrayNode('wrapper_classes')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('tag')->end()
                                        ->scalarNode('class')->end()
                                        ->scalarNode('attr')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildDataAttributeConfiguration(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('data_attributes');

        $treeBuilder
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->beforeNormalization()
                ->ifTrue(function ($v) {
                    return is_array($v) && !isset($v['values']);
                })
                ->then(function ($v) {
                    return ['values' => $v];
                })
            ->end()
            ->children()
                ->variableNode('values')->end()
            ->end()
        ->end();

        return $treeBuilder;
    }

    protected function buildPropertyNormalizerDefaultsConfiguration(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('property_normalizer');

        $treeBuilder
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('default_type_mapping')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }

    protected function buildAreasSection(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('areas');

        $treeBuilder
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->validate()
                    ->ifTrue(function ($v) {
                        $tabs = $v['tabs'];

                        return count($tabs) > 0 && count(array_filter($v['config_elements'], static function ($configElement) use ($tabs) {
                            return !array_key_exists($configElement['tab'], $tabs);
                        })) > 0;
                    })
                    ->then(function ($v) {
                        @trigger_error(
                            sprintf('Missing or wrong area tab definition in config_elements. Available tabs are: %s', implode(', ', array_keys($v['tabs']))),
                            E_USER_ERROR
                        );
                    })
                ->end()
                ->validate()
                    ->ifTrue(function ($v) {
                        $tabs = $v['tabs'];

                        return count($tabs) === 0 && count(array_filter($v['config_elements'], static function ($configElement) {
                            return $configElement['tab'] !== null;
                        })) > 0;
                    })
                    ->then(function ($v) {
                        @trigger_error('Unknown configured area tabs in config_elements. No tabs have been defined', E_USER_ERROR);
                    })
                ->end()
                ->beforeNormalization()
                    ->ifTrue(function ($v) {
                        foreach ($v['inline_config_elements'] ?? [] as $inlineConfigId => $inlineConfigElement) {
                            if ($inlineConfigElement === '<') {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->then(function ($v) {
                        foreach ($v['inline_config_elements'] ?? [] as $inlineConfigId => $inlineConfigElement) {
                            if ($inlineConfigElement === '<') {
                                $v['inline_config_elements'][$inlineConfigId] = $v['config_elements'][$inlineConfigId];
                                $v['config_elements'][$inlineConfigId]['inline_rendered'] = true;
                            }
                        }

                        return $v;
                    })
                ->end()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->append($this->buildConfigElementsTabSection())
                    ->append($this->buildConfigElementsSection('config_elements'))
                    ->append($this->buildConfigElementsSection('inline_config_elements'))
                    ->append($this->buildConfigPropertyNormalizerSection())
                    ->variableNode('config_parameter')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    protected function buildConfigElementsTabSection(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('tabs');

        $treeBuilder
            ->useAttributeAsKey('name')
            ->prototype('scalar')
            ->validate()
                ->ifNull()->thenEmptyArray()
            ->end()
            ->end();

        return $treeBuilder;
    }

    protected function buildConfigElementsSection(string $configType = 'config_elements', ?string $parent = null): NodeDefinition
    {
        if ($parent === 'config_elements') {
            return (new BooleanNodeDefinition($configType))->defaultFalse()->cannotBeOverwritten();
        }

        $treeBuilder = new ArrayNodeDefinition($configType);

        $typeNode = new ScalarNodeDefinition('type');
        $typeNode->isRequired()->end();

        $treeBuilder
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->validate()
                ->ifTrue(function ($v) {
                    return $v['type'] !== 'block' && is_array($v['children']) && count($v['children']) > 0;
                })
                ->then(function ($v) {
                    @trigger_error(sprintf('Type "%s" cannot have child elements', $v['type']), E_USER_ERROR);
                })
            ->end()
            ->addDefaultsIfNotSet()
                ->children()
                    ->append($typeNode)
                    ->scalarNode('title')->defaultValue(null)->end()
                    ->scalarNode('description')->defaultValue(null)->end()
                    ->scalarNode('property_normalizer')->defaultValue(null)->end()
                    ->scalarNode('tab')->defaultValue(null)->end()
                    ->variableNode('config')->defaultValue([])->end()
                    ->booleanNode('inline_rendered')->cannotBeOverwritten()->defaultFalse()->end()
                    ->append(
                        $parent !== null
                            ? (new BooleanNodeDefinition($configType))->defaultFalse()->cannotBeOverwritten()
                            : $this->buildConfigElementsSection('children', $configType)
                    )
                ->end()
                ->validate()
                    ->ifTrue(function ($v) {
                        return $v['enabled'] === false;
                    })
                    ->thenUnset()
                ->end()
                ->canBeUnset()
                ->canBeDisabled()
                ->treatnullLike(['enabled' => false])
            ->end();

        return $treeBuilder;
    }

    protected function buildConfigPropertyNormalizerSection(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('additional_property_normalizer');

        $treeBuilder
             ->useAttributeAsKey('name')
             ->prototype('scalar')->end();

        return $treeBuilder;
    }

    protected function buildHeadlessDocumentsSection(): ArrayNodeDefinition
    {
        $treeBuilder = new ArrayNodeDefinition('headless_documents');

        $treeBuilder
            ->prototype('array')
            ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('name')->defaultValue(null)->end()
                    ->arrayNode('areas')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->validate()
                                ->ifTrue(function ($v) {
                                    return $v['type'] !== 'block' && is_array($v['children']) && count($v['children']) > 0;
                                })
                                ->then(function ($v) {
                                    @trigger_error(sprintf('Type "%s" cannot have child elements', $v['type']), E_USER_ERROR);
                                })
                            ->end()
                            ->children()
                                ->scalarNode('type')->isRequired()->end()
                                ->variableNode('config')->defaultNull()->end()
                                ->scalarNode('title')->defaultValue(null)->end()
                                ->scalarNode('description')->defaultValue(null)->end()
                                ->scalarNode('property_normalizer')->defaultValue(null)->end()
                                ->append($this->buildConfigElementsSection('children', 'headless_documents'))
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->canBeUnset()
                ->canBeDisabled()
                ->treatnullLike(['enabled' => false])
            ->end();

        return $treeBuilder;
    }
}
