<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Invoice\Entity\Invoice;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\InvoiceManagerInterface;
use Baraja\VariableGenerator\Order\DefaultOrderVariableLoader;
use Baraja\VariableGenerator\VariableGenerator;
use CleverMinds\EmailerAccessor;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Random;
use OndrejBrejla\Eciovni\DataImpl;
use OndrejBrejla\Eciovni\Eciovni;
use OndrejBrejla\Eciovni\ItemImpl;
use OndrejBrejla\Eciovni\ParticipantBuilder;
use OndrejBrejla\Eciovni\ParticipantImpl;
use OndrejBrejla\Eciovni\TaxImpl;

final class InvoiceManager implements InvoiceManagerInterface
{
	public function __construct(
		private string $wwwDir,
		private EntityManager $entityManager,
		private EmailerAccessor $emailer
	) {
		if (is_dir($wwwDir) === false) {
			throw new \RuntimeException('Parameter "wwwDir" with path "' . $wwwDir . '" does not exist.');
		}
	}


	public function isInvoice(Order $order): bool
	{
		return $this->getOrderInvoices($order) !== [];
	}


	public function createInvoice(Order $order): Invoice
	{
		$regenerated = false;
		$invoiceItem = $this->getOrderInvoices($order);
		if ($invoiceItem !== []) {
			/** @var Invoice $invoice */
			$invoice = $invoiceItem;
			$invoice->setPrice($order->getPrice());
			$regenerated = true;
		} else {
			$invoice = new Invoice($order, $this->getNextNumber(), $order->getPrice());
			$this->entityManager->persist($invoice);
			$this->entityManager->flush();
		}

		$relativePath = 'invoice/' . date('Y-m') . '/' . $invoice->getNumber() . '_' . Random::generate(6) . '.pdf';
		$absolutePath = $this->wwwDir . '/' . $relativePath;
		$invoice->setPath($relativePath);

		$invoiceGenerator = $this->getInvoiceGenerator($invoice);
		FileSystem::createDir(dirname($absolutePath));
		$invoiceGenerator->exportToPdf($absolutePath, 'F');

		$this->emailer->sendOrderInvoice($invoice, $absolutePath, $regenerated);
		$this->entityManager->flush();

		return $invoice;
	}


	public function getInvoicePath(Invoice $invoice): string
	{
		if ($invoice->getPath() === null) {
			throw new \InvalidArgumentException('File for invoice "' . $invoice->getNumber() . '" does not exist.');
		}

		return $this->wwwDir . '/' . $invoice->getPath();
	}


	public function getInvoiceGenerator(Invoice $invoice): Eciovni
	{
		$order = $invoice->getOrder();
		$locale = $order->getLocale();
		$invoiceAddress = $order->getInvoiceAddress();

		$supplier = new ParticipantBuilder(
			'CLEVER MINDS s.r.o.', 'Truhlářská', '1110/4', 'Praha 1 - Nové Město', '110 00'
		);
		$supplier->setIn('01585100');
		$supplier->setTin('CZ01585100');
		$supplier->setAccountNumber('2900428677/2010');

		$customer = new ParticipantBuilder(
			$invoiceAddress->getCompanyName() ?: $order->getCustomer()->getName(),
			$invoiceAddress->getStreet(),
			null,
			$invoiceAddress->getCity(),
			(string) $invoiceAddress->getZip()
		);
		if ($invoiceAddress->getCin()) {
			$customer->setIn($invoiceAddress->getCin());
		}
		if ($invoiceAddress->getTin()) {
			$customer->setTin($invoiceAddress->getTin());
		}

		$items = [];
		foreach ($order->getItems() as $item) {
			$items[] = new ItemImpl(
				$item->getLabel(),
				$item->getCount(),
				$item->getFinalPrice(),
				TaxImpl::fromPercent($item->getProduct()->getVat()),
			);
		}
		$items[] = new ItemImpl(
			'Doprava - ' . $order->getDelivery()->getName($locale),
			1,
			$order->getDeliveryPrice(),
			TaxImpl::fromPercent(21),
		);
		$items[] = new ItemImpl(
			'Platba - ' . $order->getPayment()->getName(),
			1,
			$order->getPayment()->getPrice(),
			TaxImpl::fromPercent(21),
		);
		if ($order->getSale() > 0) {
			$items[] = new ItemImpl(
				'Sleva na celou objednávku',
				1,
				$order->getSale() * -1,
				TaxImpl::fromPercent(21),
			);
		}

		$data = new DataImpl(
			$invoice->getNumber(),
			'Faktura',
			new ParticipantImpl($supplier),
			new ParticipantImpl($customer),
			DateTime::from($invoice->getInsertedDate()->format('Y-m-d') . ' + 14 days'),
			$invoice->getInsertedDate(),
			$items
		);
		$data->setDateOfVatRevenueRecognition($invoice->getInsertedDate());
		$data->setVariableSymbol($order->getNumber());
		$data->setTextBottom(
			'Společnost je zapsána v OR, vedeném Městským soudem v Praze, spisová značka C, vložka 208645, dne 16.4. 2013'
		);

		$invoiceGenerator = new Eciovni($data);
		$invoiceGenerator->setContactSizeRatio(45);

		return $invoiceGenerator;
	}


	/**
	 * @return Invoice[]
	 */
	private function getOrderInvoices(Order $order): array
	{
		return $this->entityManager->getRepository(Invoice::class)
			->createQueryBuilder('i')
			->where('i.order = :orderId')
			->setParameter('orderId', $order->getId())
			->getQuery()
			->getResult();
	}


	private function getNextNumber(): int
	{
		return (new VariableGenerator(new DefaultOrderVariableLoader($this->entityManager, Invoice::class)))
			->generate();
	}
}
