<?php

namespace App\Exports;

use App\Models\Research;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ResearchExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Research::all();
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

    public function map($research): array
    {
        $authors = [];
        foreach ($research->authors as $author) {
            if ($author->nidn !== $research->nidn_ketua) {
                $authors[] = "(NIDN: $author->nidn) " . $author->name;
            }
        }
        $creators = implode(', ', $authors);

        static $no = 0;
        $no++;

        return [
            $no,
            $research->nama_ketua,
            $research->nidn_ketua,
            $research->afiliasi_ketua,
            $research->kd_pt_ketua,
            $research->judul,
            $research->nama_singkat_skema,
            $research->thn_pertama_usulan,
            $research->thn_usulan_kegiatan,
            $research->thn_pelaksanaan_kegiatan,
            $research->lama_kegiatan,
            $research->bidang_fokus,
            $research->nama_skema,
            $research->status_usulan,
            $research->dana_disetujui,
            $research->afiliasi_sinta_id,
            $research->nama_institusi_penerima_dana,
            $research->target_tkt,
            $research->nama_program_hibah,
            $research->kategori_sumber_dana,
            $research->negara_sumber_dana,
            $research->sumber_dana,
            $creators
        ];
    }
}
