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

namespace ToolboxBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ToolboxBundle\Manager\AdaptiveConfigManagerInterface;

class AreaConfigurationCommand extends Command
{
    protected static $defaultName = 'toolbox:check-config';
    protected static $defaultDescription = 'Check configuration of a given area element.';

    public function __construct(protected AdaptiveConfigManagerInterface $adaptiveConfigManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'area',
                'a',
                InputOption::VALUE_REQUIRED,
                'Area Brick Id ("image" for example")'
            )
            ->addOption(
                'context',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Context Name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $brickId = $input->getOption('area');
        $contextId = $input->getOption('context');
        $hasContext = true;

        if (empty($contextId)) {
            $hasContext = false;
        }

        if (empty($brickId)) {
            $output->writeln('<error>Please provide a valid Area Brick Id.</error>');

            return Command::SUCCESS;
        }

        $this->adaptiveConfigManager->setContextNameSpace($contextId);

        $brickConfig = $this->adaptiveConfigManager->getAreaConfig($brickId);

        if (empty($brickConfig)) {
            if ($hasContext) {
                $settings = $this->adaptiveConfigManager->getCurrentContextSettings();
                if (in_array($brickId, $settings['disabled_areas'])) {
                    $output->writeln('');
                    $output->writeln(sprintf('<comment>Area Brick with Id "%s" is disabled in "%s" context.</comment>', $brickId, $contextId));
                    $output->writeln('');
                } elseif (!in_array($brickId, $settings['enabled_areas'])) {
                    $output->writeln('');
                    $output->writeln(sprintf('<comment>Area Brick with Id "%s" is not enabled in "%s" context.</comment>', $brickId, $contextId));
                    $output->writeln('');
                }

                return Command::SUCCESS;
            }

            $output->writeln('');
            $output->writeln(sprintf('<error>Area Brick with Id "%s" not found.</error>', $brickId));
            $output->writeln('');

            return Command::SUCCESS;
        }

        $configElements = $brickConfig['config_elements'];
        $configParameter = $brickConfig['config_parameter'] ?? [];

        if (empty($configElements)) {
            $output->writeln('');
            $output->writeln(sprintf('<comment>Area Brick with Id "%s" does not have any configuration elements.</comment>', $brickId));
            $output->writeln('');

            return Command::SUCCESS;
        }

        $contextHeadline = $hasContext ? ('in Context "' . $contextId . '"') : '';
        $headline = sprintf('Configuration for Area Brick "%s" %s', $brickId, $contextHeadline);
        $output->writeln('');
        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', $headline));
        $output->writeln('<info>' . str_repeat('_', strlen($headline)) . '</info>');
        $output->writeln('');
        $output->writeln('');

        $rows = [];
        $c = 0;

        foreach ($configElements as $configName => $configData) {
            $elementConfigData = empty($configData['config']) ? '--' : $this->parseArrayForOutput($configData['config']);

            $rows[] = [
                $configName,
                $configData['type'],
                $configData['title'],
                $configData['description'],
                $elementConfigData,
            ];

            if (!empty($configParameter) || $c < count($configElements) - 1) {
                $rows[] = new TableSeparator();
            }

            $c++;
        }

        if (!empty($configParameter)) {
            $configParameterData = $this->parseArrayForOutput($configParameter);
            $configRow = [new TableCell("\n" . '<fg=magenta>config parameter for element:</>' . "\n\n" . $configParameterData, ['colspan' => 6])];
            $rows[] = $configRow;
        }

        $table = new Table($output);
        $table
            ->setHeaders(['name', 'type', 'title', 'description', 'config_elements'])
            ->setRows($rows);
        $table->render();

        return Command::SUCCESS;
    }

    private function parseArrayForOutput(array $array = [], string $string = '', int $depth = 0): string
    {
        $depthStr = str_repeat(' ', $depth * 3);
        foreach ($array as $key => $value) {
            $dash = $depth === 0 ? '' : '- ';
            $displayValue = !is_array($value) ? ': ' . (is_bool($value) ? ($value ? 'true' : 'false') : (empty($value) ? '--' : $value)) : ':';
            $string .= $depthStr . $dash . $key . $displayValue . "\n";

            if (is_array($value)) {
                $depth++;

                return $this->parseArrayForOutput($value, $string, $depth);
            }
        }

        return $string;
    }
}
