<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice;


use Baraja\EcommerceStandard\DTO\InvoiceInterface;
use Baraja\EcommerceStandard\DTO\OrderInterface;
use Baraja\EcommerceStandard\Service\InvoiceManagerInterface;
use Baraja\Shop\Invoice\Entity\Invoice;
use Baraja\Shop\Invoice\Entity\InvoiceRepository;
use Baraja\Shop\Order\Entity\Order;
use Baraja\VariableGenerator\Order\DefaultOrderVariableLoader;
use Baraja\VariableGenerator\VariableGenerator;
use Doctrine\ORM\EntityManagerInterface;
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
	private InvoiceRepository $invoiceRepository;


	public function __construct(
		private string $wwwDir,
		private EntityManagerInterface $entityManager,
	) {
		if (is_dir($wwwDir) === false) {
			throw new \RuntimeException(sprintf('Parameter "wwwDir" with path "%s" does not exist.', $this->wwwDir));
		}
		$invoiceRepository = $this->entityManager->getRepository(Invoice::class);
		assert($invoiceRepository instanceof InvoiceRepository);
		$this->invoiceRepository = $invoiceRepository;
	}


	public function isInvoice(OrderInterface $order): bool
	{
		return $this->invoiceRepository->getByOrder($order) !== [];
	}


	public function createInvoice(OrderInterface $order): Invoice
	{
		$invoiceItem = $this->invoiceRepository->getByOrder($order);
		if ($invoiceItem !== []) {
			/** @var Invoice $invoice */
			$invoice = $invoiceItem[0];
			$invoice->setPrice($order->getPrice());
		} else {
			assert($order instanceof Order);
			$invoice = new Invoice($order, (string) $this->getNextNumber(), $order->getPrice());
		}

		$relativePath = 'invoice/' . date('Y-m') . '/' . $invoice->getNumber() . '_' . Random::generate(6) . '.pdf';
		$absolutePath = $this->wwwDir . '/' . $relativePath;
		$invoice->setPath($relativePath);

		$invoiceGenerator = $this->getInvoiceGenerator($invoice);
		FileSystem::createDir(dirname($absolutePath));
		$invoiceGenerator->exportToPdf($absolutePath, 'F');

		$this->entityManager->persist($invoice);
		$this->entityManager->flush();

		return $invoice;
	}


	public function getInvoicePath(InvoiceInterface $invoice): string
	{
		if ($invoice->getPath() === null) {
			throw new \InvalidArgumentException('File for invoice "' . $invoice->getNumber() . '" does not exist.');
		}

		return $this->wwwDir . '/' . $invoice->getPath();
	}


	public function getByOrder(OrderInterface $order): Invoice
	{
		$invoices = $this->invoiceRepository->getByOrder($order);
		if (isset($invoices[0])) {
			return $invoices[0];
		}

		throw new \InvalidArgumentException(sprintf('Invoice for order "%s" does not exist.', $order->getNumber()));
	}


	public function getInvoiceGenerator(Invoice $invoice): Eciovni
	{
		$order = $invoice->getOrder();
		$locale = $order->getLocale();
		$invoiceAddress = $order->getPaymentAddress();

		$supplier = new ParticipantBuilder(
			'CLEVER MINDS s.r.o.', 'Truhlářská', '1110/4', 'Praha 1 - Nové Město', '110 00',
		);
		$supplier->setIn('01585100');
		$supplier->setTin('CZ01585100');
		$supplier->setAccountNumber('2900428677/2010');

		$customer = new ParticipantBuilder(
			$invoiceAddress->getCompanyName() ?? $order->getCustomer()->getName(),
			$invoiceAddress->getStreet(),
			null,
			$invoiceAddress->getCity(),
			$invoiceAddress->getZip(),
		);
		if ($invoiceAddress->getCin() !== null) {
			$customer->setIn($invoiceAddress->getCin());
		}
		if ($invoiceAddress->getTin() !== null) {
			$customer->setTin($invoiceAddress->getTin());
		}

		$items = [];
		foreach ($order->getItems() as $item) {
			$items[] = new ItemImpl(
				$item->getLabel(),
				$item->getCount(),
				(float) $item->getFinalPrice()->getValue(),
				TaxImpl::fromPercent((float) $item->getVat()->getValue()),
			);
		}
		$delivery = $order->getDelivery();
		if ($delivery !== null) {
			$items[] = new ItemImpl(
				'Doprava - ' . $delivery->getName($locale),
				1,
				(float) $order->getDeliveryPrice()->getValue(),
				TaxImpl::fromPercent(21),
			);
		}
		$payment = $order->getPayment();
		if ($payment !== null) {
			$items[] = new ItemImpl(
				'Platba - ' . $payment->getName(),
				1,
				(float) $order->getPaymentPrice()->getValue(),
				TaxImpl::fromPercent(21),
			);
		}
		if ($order->getSale()->isBiggerThan('0')) {
			$items[] = new ItemImpl(
				'Sleva na celou objednávku',
				1,
				$order->getSale()->getValue() * -1,
				TaxImpl::fromPercent(21),
			);
		}

		$data = new DataImpl(
			$invoice->getNumber(),
			'Faktura',
			new ParticipantImpl($supplier),
			new ParticipantImpl($customer),
			new \DateTimeImmutable($invoice->getInsertedDate()->format('Y-m-d') . ' + 14 days'),
			$invoice->getInsertedDate(),
			$items,
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


	private function getNextNumber(): int
	{
		return (new VariableGenerator(new DefaultOrderVariableLoader($this->entityManager, Invoice::class)))
			->generate();
	}
}
