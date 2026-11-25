<?php

declare(strict_types=1);

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */

namespace Infrangible\CatalogProductOptionProduct\Test\Unit;

use Magento\Framework\Model\AbstractModel;
use ReflectionClass;
use ReflectionException;

/**
 * Creates Magento models without their dependencies, for tests that only need their data.
 */
trait Models
{
    /**
     * Returns a model of the class with the data, without calling its constructor.
     *
     * @template T of AbstractModel
     * @param class-string<T> $className
     * @param string $idFieldName
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     *
     * @return T
     * @throws ReflectionException
     */
    private function createModel(string $className, string $idFieldName, array $data = []): object
    {
        $model = (new ReflectionClass($className))->newInstanceWithoutConstructor();

        $model->setIdFieldName($idFieldName);
        $model->setData($data);

        return $model;
    }
}
