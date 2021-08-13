<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Order\Entity\InvoiceInterface;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderDocument;
use Baraja\Url\Url;
use Baraja\VariableGenerator\Order\OrderEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shop__invoice')]
class Invoice implements OrderEntity, OrderDocument
{
	use IdentifierUnsigned;

	public const
		TYPE_INVOICE = 'invoice',
		TYPE_PAYMENT_REQUEST = 'payment-request',
		TYPE_PROFORMA = 'proforma',
		TYPE_ORDER = 'order';

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'invoices')]
	private Order $order;

	#[ORM\Column(type: 'string', length: 16)]
	private string $type;

	#[ORM\Column(type: 'integer', unique: true)]
	private int $number;

	#[ORM\Column(type: 'float')]
	private float $price;

	#[ORM\Column(type: 'boolean')]
	private bool $paid = false;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $path = null;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;


	public function __construct(Order $order, int $number, float $price, string $type = self::TYPE_INVOICE)
	{
		$this->order = $order;
		$this->number = $number;
		$this->price = $price;
		$this->type = $type;
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getDownloadLink(): string
	{
		return Url::get()->getBaseUrl() . '/' . $this->getPath();
	}


	public function getLabel(): string
	{
		return 'Invoice ' . $this->getNumber()
			. ($this->getType() !== '' ? ' (' . $this->getType() . ')' : '');
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getNumber(): string
	{
		return (string) $this->number;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function setPrice(float $price): void
	{
		$this->price = $price;
	}


	public function isPaid(): bool
	{
		return $this->paid;
	}


	public function setPaid(bool $paid): void
	{
		$this->paid = $paid;
	}


	public function getPath(): ?string
	{
		return $this->path;
	}


	public function setPath(?string $path): void
	{
		$this->path = $path;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function setType(string $type): void
	{
		$this->type = $type;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}
}
