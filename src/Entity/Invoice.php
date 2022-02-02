<?php

declare(strict_types=1);

namespace Baraja\Shop\Invoice\Entity;


use Baraja\EcommerceStandard\DTO\InvoiceInterface;
use Baraja\EcommerceStandard\DTO\PriceInterface;
use Baraja\Shop\Order\Entity\Order;
use Baraja\Shop\Order\Entity\OrderDocument;
use Baraja\Shop\Price\Price;
use Baraja\Url\Url;
use Baraja\VariableGenerator\Order\OrderEntity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'shop__invoice')]
class Invoice implements OrderEntity, OrderDocument, InvoiceInterface
{
	public const
		TYPE_INVOICE = 'invoice',
		TYPE_PAYMENT_REQUEST = 'payment-request',
		TYPE_PROFORMA = 'proforma',
		TYPE_ORDER = 'order';

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'invoices')]
	private Order $order;

	#[ORM\Column(type: 'string', length: 16)]
	private string $type;

	#[ORM\Column(type: 'integer', unique: true)]
	private int $number;

	/** @var numeric-string */
	#[ORM\Column(type: 'decimal', precision: 15, scale: 4, options: ['unsigned' => true])]
	private string $price;

	#[ORM\Column(type: 'boolean')]
	private bool $paid = false;

	#[ORM\Column(type: 'string', length: 64, nullable: true)]
	private ?string $path = null;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeInterface $insertedDate;


	public function __construct(Order $order, int $number, PriceInterface $price, string $type = self::TYPE_INVOICE)
	{
		$this->order = $order;
		$this->number = $number;
		$this->price = $price->getValue();
		$this->type = $type;
		$this->insertedDate = new \DateTimeImmutable;
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getDownloadLink(): string
	{
		return sprintf('%s/%s', Url::get()->getBaseUrl(), $this->getPath());
	}


	public function getLabel(): string
	{
		return sprintf(
			'Invoice %s%s',
			$this->getNumber(),
			$this->getType() !== '' ? ' (' . $this->getType() . ')' : '',
		);
	}


	public function getOrder(): Order
	{
		return $this->order;
	}


	public function getNumber(): string
	{
		return (string) $this->number;
	}


	public function getPrice(): PriceInterface
	{
		return new Price($this->price, $this->order->getCurrency());
	}


	public function setPrice(PriceInterface $price): void
	{
		if ($price->getCurrency()->getCode() !== $this->order->getCurrencyCode()) {
			throw new \InvalidArgumentException('Given price can not use incompatible currency.');
		}
		$this->price = $price->getValue();
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


	public function getTags(): array
	{
		return ['invoice'];
	}


	public function addTag(string $tag): void
	{
		throw new \LogicException('Not implemented: Can not set tag to invoice.');
	}


	public function hasTag(string $tag): bool
	{
		return $tag === 'invoice';
	}


	public function removeTag(string $tag): void
	{
		// Silence is golden.
	}
}
