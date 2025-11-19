<?php

namespace App\Enums;

enum WithdrawStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case DONE = 'done';
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
            'SUCCESS' => self::SUCCESS,
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
            'DONE' => self::SUCCESS,
            'CANCELLED' => self::CANCELLED,
            'FAILED' => self::FAILED,
            default => self::PENDING,
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::SUCCESS, self::DONE]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESS, self::DONE, self::CANCELLED, self::FAILED]);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::SUCCESS => 'Sucesso',
            self::DONE => 'Concluído',
            self::CANCELLED => 'Cancelado',
            self::FAILED => 'Falhou',
        };
    }
}
