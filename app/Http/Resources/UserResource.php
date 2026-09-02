<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nama'              => $this->nama,
            'email'             => $this->email,
            'no_telepon'        => $this->no_telepon,
            'foto_profil'       => $this->foto_profil ? (str_starts_with($this->foto_profil, 'http') ? $this->foto_profil : '/api/storage/' . $this->foto_profil) : null,
            'asal_kampus'       => $this->asal_kampus,
            'role'              => $this->role,
            'aktif'             => (bool) $this->aktif,
            'latitude'          => $this->latitude,
            'longitude'         => $this->longitude,
            'email_verified_at' => $this->email_verified_at,
            'is_online'         => $this->isOnline(),
            'terakhir_aktif_pada' => $this->terakhir_aktif_pada,
            'bank_accounts'     => $this->whenLoaded('bankAccounts'),
            'received_reports_count' => $this->received_reports_count ?? 0,
            'created_at'        => $this->created_at,
        ];
    }
}
