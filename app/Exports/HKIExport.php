<?php

namespace App\Exports;

use App\Models\HKI;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HKIExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return HKI::all();
    }

    public function headings(): array
    {
        return [
            '#',
            'Tahun Permohonan',
            'Nomor Permohonan',
            'Kategori',
            'TItle',
            'Pemegang Paten',
            'Inventor',
            'Status',
            'Nomor Publikasi',
            'Tanggal Publikasi',
            'Filing Date',
            'Reception Date',
            'Nomor Registrasi',
            'Tanggal Registrasi',
            'Authors'
        ];
    }

    public function map($hki): array
    {
        $authors = [];
        foreach ($hki->authors as $author) {
            $authors[] = "(NIDN: $author->nidn) " . $author->name;
        }

        $creators = implode(', ', $authors);

        static $no = 0;
        $no++;

        return [
            $no,
            $hki->tahun_permohonan ?? '-',
            $hki->nomor_permohonan ?? '-',
            $hki->kategori ?? '-',
            $hki->title ?? '-',
            $hki->pemegang_paten ?? '-',
            $hki->inventor ?? '-',
            $hki->status ?? '-',
            $hki->nomor_publikasi ?? '-',
            $hki->tanggal_publikasi ?? '-',
            $hki->filing_date ?? '-',
            $hki->reception_date ?? '-',
            $hki->nomor_registrasi ?? '-',
            $hki->tanggal_registrasi ?? '-',
            $creators ?? '-'
        ];
    }
}
