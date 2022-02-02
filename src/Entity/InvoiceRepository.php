<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice\Entity;


use Baraja\EcommerceStandard\DTO\OrderInterface;
use Doctrine\ORM\EntityRepository;

final class InvoiceRepository extends EntityRepository
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
}
