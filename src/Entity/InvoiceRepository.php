<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice\Entity;


use Baraja\EcommerceStandard\DTO\InvoiceInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\Shop\Order\Repository\OrderInvoiceRepository;
use Doctrine\ORM\EntityRepository;

final class InvoiceRepository extends EntityRepository implements OrderInvoiceRepository
{
	/**
	 * @return array<int, Invoice>
	 */
	public function getByOrder(OrderInterface $order): array
	{
		/** @var array<int, Invoice> $invoices */
		$invoices = $this->createQueryBuilder('i')
			->where('i.order = :orderId')
			->setParameter('orderId', $order->getId())
			->orderBy('i.insertedDate', 'DESC')
			->getQuery()
			->getResult();

		return $invoices;
	}


	/**
	 * @param array<int, int> $ids
	 * @return array<int, InvoiceInterface>
	 */
	public function getInvoicesByOrderIds(array $ids): array
	{
		/** @var array<int, Invoice> $invoices */
		$invoices = $this->createQueryBuilder('i')
			->where('i.order IN (:ids)')
			->setParameter('ids', $ids)
			->getQuery()
			->getResult();

		$return = [];
		foreach ($invoices as $invoice) {
			$return[$invoice->getOrder()->getId()] = $invoice;
		}

		return $return;
	}
}
