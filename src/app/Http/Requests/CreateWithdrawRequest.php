<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'pix_key' => ['required_without:bank_code', 'nullable', 'string', 'max:255'],
            'pix_key_type' => ['required_with:pix_key', 'nullable', 'string', 'in:cpf,cnpj,email,phone,random'],
            'bank_code' => ['required_without:pix_key', 'nullable', 'string', 'max:10'],
            'agency' => ['required_with:bank_code', 'nullable', 'string', 'max:10'],
            'account' => ['required_with:bank_code', 'nullable', 'string', 'max:20'],
            'account_type' => ['nullable', 'string', 'in:checking,savings'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'O valor é obrigatório.',
            'amount.numeric' => 'O valor deve ser numérico.',
            'amount.min' => 'O valor mínimo é R$ 0,01.',
            'amount.max' => 'O valor máximo é R$ 999.999,99.',
            'pix_key.required_without' => 'Informe a chave PIX ou os dados bancários.',
            'pix_key_type.required_with' => 'O tipo da chave PIX é obrigatório.',
            'pix_key_type.in' => 'Tipo de chave PIX inválido.',
            'bank_code.required_without' => 'Informe os dados bancários ou a chave PIX.',
            'agency.required_with' => 'A agência é obrigatória.',
            'account.required_with' => 'A conta é obrigatória.',
            'account_type.in' => 'Tipo de conta inválido.',
        ];
    }
}
