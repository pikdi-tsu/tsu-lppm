<?php

namespace App\Exports;

use App\Models\Publication;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PublicationExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Publication::all();
    }

    public function headings(): array
    {
        return [
            '#',
            'Accreditation',
            'Identifier',
            'Quartile',
            'Title',
            'Journal',
            'Publication Name',
            'Creators',
            'Year',
            'Citation',
            'Category'
        ];
    }

    public function map($publication): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $publication->accreditation ?? '-',
            $publication->identifier ?? '-',
            $publication->quartile ?? '-',
            $publication->title ?? '-',
            $publication->journal ?? '-',
            $publication->publication_name ?? '-',
            $publication->creators ?? '-',
            $publication->year ?? '-',
            $publication->citation ?? '-',
            $publication->category ?? '-',
        ];
    }
}
