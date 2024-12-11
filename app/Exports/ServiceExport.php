<?php

namespace App\Exports;

use App\Models\Service;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ServiceExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Service::all();
    }

    public function headings(): array
    {
        return [
            '#',
            'Nama Ketua',
            'NIDN Ketua',
            'Afiliasi Ketua',
            'KD PT Ketua',
            'Judul',
            'Nama Singkat Skema',
            'Tahun Pertama Usulan',
            'Tahun Usulan Kegiatan',
            'Tahun Pelaksanaan Kegiatan',
            'Lama Kegiatan',
            'Bidang Fokus',
            'Nama Skema',
            'Status Usulan',
            'Dana Disetujui',
            'Afiliasi Sinta ID',
            'Nama Institusi Penerima Dana',
            'Target TKT',
            'Nama Program Hibah',
            'Kategori Sumber Dana',
            'Negara Sumber Dana',
            'Sumber Dana',
            'Authors Member'
        ];
    }

    public function map($service): array
    {
        $authors = [];
        foreach ($service->authors as $author) {
            if ($author->nidn !== $service->nidn_ketua) {
                $authors[] = "(NIDN: $author->nidn) " . $author->name;
            }
        }
        $creators = implode(', ', $authors);

        static $no = 0;
        $no++;

        return [
            $no,
            $service->nama_ketua,
            $service->nidn_ketua,
            $service->afiliasi_ketua,
            $service->kd_pt_ketua,
            $service->judul,
            $service->nama_singkat_skema,
            $service->thn_pertama_usulan,
            $service->thn_usulan_kegiatan,
            $service->thn_pelaksanaan_kegiatan,
            $service->lama_kegiatan,
            $service->bidang_fokus,
            $service->nama_skema,
            $service->status_usulan,
            $service->dana_disetujui,
            $service->afiliasi_sinta_id,
            $service->nama_institusi_penerima_dana,
            $service->target_tkt,
            $service->nama_program_hibah,
            $service->kategori_sumber_dana,
            $service->negara_sumber_dana,
            $service->sumber_dana,
            $creators
        ];
    }
}
