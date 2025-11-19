<?php

namespace App\Enums;

enum PixStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case CONFIRMED = 'confirmed';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    /**
     * Map SubadqA status to internal status
     */
    public static function fromSubadqA(string $status): self
    {
        return match (strtoupper($status)) {
            'PENDING' => self::PENDING,
            'PROCESSING' => self::PROCESSING,
            'CONFIRMED' => self::PAID,
            'CANCELLED' => self::CANCELLED,
            'FAILED' => self::FAILED,
            default => self::PENDING,
        };
    }

    /**
     * Map SubadqB status to internal status
     */
    public static function fromSubadqB(string $status): self
    {
        return match (strtoupper($status)) {
            'PENDING' => self::PENDING,
            'PROCESSING' => self::PROCESSING,
            'PAID' => self::PAID,
            'CANCELLED' => self::CANCELLED,
            'FAILED' => self::FAILED,
            default => self::PENDING,
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::CONFIRMED, self::PAID]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::CONFIRMED, self::PAID, self::CANCELLED, self::FAILED]);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::CONFIRMED => 'Confirmado',
            self::PAID => 'Pago',
            self::CANCELLED => 'Cancelado',
            self::FAILED => 'Falhou',
        };
    }
}
