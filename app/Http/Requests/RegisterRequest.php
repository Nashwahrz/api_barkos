<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'email'             => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'          => ['required', 'confirmed', Password::defaults()],
            'asal_kampus'       => ['required', 'string', 'max:255'],
            'role'              => ['nullable', 'string', 'in:super_admin,pembeli,penjual'],
            'identity_document' => ['required_if:role,penjual', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'recaptcha_token'   => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'recaptcha_token.required' => 'Verifikasi CAPTCHA diperlukan.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->verifyRecaptcha($this->input('recaptcha_token'))) {
                $validator->errors()->add('recaptcha_token', 'Verifikasi CAPTCHA gagal. Silakan coba lagi.');
            }
        });
    }

    private function verifyRecaptcha(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $this->ip(),
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (ConnectionException) {
            return false;
        }
    }
}
