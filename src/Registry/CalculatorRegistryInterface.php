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

namespace ToolboxBundle\Registry;

use ToolboxBundle\Calculator\ColumnCalculatorInterface;
use ToolboxBundle\Calculator\SlideColumnCalculatorInterface;

interface CalculatorRegistryInterface
{
    public function register(string $id, mixed $service, string $type): void;

    public function getSlideColumnCalculator(string $alias): SlideColumnCalculatorInterface;

    public function getColumnCalculator(string $alias): ColumnCalculatorInterface;

    public function has(string $alias, string $type): bool;

    /**
     * @throws \Exception
     */
    public function get(string $alias, string $type): SlideColumnCalculatorInterface|ColumnCalculatorInterface;
}
