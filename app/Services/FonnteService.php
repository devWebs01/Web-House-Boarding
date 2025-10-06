<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FonnteService
{
    protected $token;

    public function __construct()
    {
        $this->token = env('FONNTE_TOKEN'); // Simpan token di config
    }

    public function send($target, $message)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->asForm()->post('https://api.fonnte.com/send', [
            'target' => $this->sanitizePhone($target),
            'message' => $message,
            'countryCode' => '62', // Opsional
        ]);

        $responseData = $response->json();

        // Check if the response indicates success
        if ($response->successful() && isset($responseData['status']) && $responseData['status'] === true) {
            return $responseData;
        } else {
            $errorMessage = $responseData['message'] ?? $response->body();
            throw new \Exception('Fonnte API error: '.$errorMessage);
        }
    }

    protected function sanitizePhone($phone)
    {
        // Hapus leading 0 jika ada, biarkan API menambahkan country code
        return preg_replace('/^0/', '', $phone);
    }
}
