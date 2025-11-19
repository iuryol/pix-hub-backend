<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'pix_key' => $this->pix_key,
            'pix_key_type' => $this->pix_key_type,
            'bank_code' => $this->bank_code,
            'bank_name' => $this->bank_name,
            'agency' => $this->agency,
            'account' => $this->account,
            'account_type' => $this->account_type,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
