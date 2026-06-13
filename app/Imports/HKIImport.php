<?php

namespace App\Imports;

use App\Models\HKI;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class HKIImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $row = array_map(function ($value) {
            return $value === '-' ? null : $value;
        }, $row);

        return new HKI([
            'tahun_permohonan' => $row['tahun_permohonan'],
            'nomor_permohonan' => $row['nomor_permohonan'],
            'kategori' => $row['kategori'],
            'title' => $row['title'],
            'pemegang_paten' => $row['pemegang_paten'],
            'inventor' => $row['inventor'],
            'status' => $row['status'],
            'nomor_publikasi' => $row['no_publikasi'],
            'tanggal_publikasi' => $row['tgl_publikasi'],
            'filing_date' => $row['filing_date'],
            'reception_date' => $row['reception_date'],
            'nomor_registrasi' => $row['no_registrasi'],
            'tanggal_registrasi' => $row['tgl_registrasi'],
        ]);
    }

    // public function headingRow(): int
    // {
    //     return 5;
    // }
}
