<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Nette\DI\CompilerExtension;

final class ShopInvoiceExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder, 'Baraja\Shop\Invoice\Entity', __DIR__ . '/Entity',
		);

		$builder->addDefinition($this->prefix('invoiceManager'))
			->setFactory(InvoiceManager::class)
			->setArgument('wwwDir', $builder->parameters['wwwDir'] ?? '');
	}
}
